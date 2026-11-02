<?php
// API kasir (api/kasir-data.php): daftar produk untuk dipilih (GET), simpan transaksi (POST).
// Pemilik data selalu dari sesi. Total dihitung ulang di server. Stok dijaga agar tidak oversell.
require __DIR__ . '/../includes/owner.php';

function list_products(PDO $pdo, int $uid): void {
    $q = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 60) : '';
    $params = [$uid];
    $where = 'owner_id = ? AND is_active = 1 AND stock > 0';
    if ($q !== '') {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $where .= ' AND (name LIKE ? OR sku LIKE ?)';
        array_push($params, $like, $like);
    }
    $rows = db_all($pdo, "SELECT id, name, sku, image, price, stock FROM products WHERE $where ORDER BY name ASC LIMIT 20", $params);
    json_out(200, ['items' => array_map(fn($r) => [
        'id' => (int) $r['id'], 'name' => $r['name'], 'sku' => $r['sku'],
        'image' => $r['image'] ? 'uploads/' . $r['image'] : null,
        'price' => (int) $r['price'], 'stock' => (int) $r['stock'],
    ], $rows)]);
}

function checkout(PDO $pdo, int $uid): void {
    $b = read_post_json();
    $method = is_string($b['method'] ?? null) ? $b['method'] : '';
    if (!in_array($method, ['Tunai', 'QRIS', 'Transfer'], true)) json_out(400, ['message' => 'Metode bayar tidak valid.']);

    $cust = clean($b['customer'] ?? '');
    if ($cust === '') $cust = 'Pelanggan Umum';
    if (mb_strlen($cust) > 100) $cust = mb_substr($cust, 0, 100);

    $raw = $b['items'] ?? null;
    if (!is_array($raw) || !$raw) json_out(400, ['message' => 'Keranjang masih kosong.']);
    if (count($raw) > 50) json_out(400, ['message' => 'Maksimal 50 jenis produk per transaksi.']);

    // Kumpulkan qty per produk (jika produk sama dikirim dua kali, gabungkan)
    $want = [];
    foreach ($raw as $it) {
        $id = filter_var($it['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $qty = filter_var($it['qty'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 999]]);
        if ($id === false || $qty === false) json_out(400, ['message' => 'Ada item yang tidak valid di keranjang.']);
        $want[$id] = ($want[$id] ?? 0) + $qty;
    }
    if (count($want) > 50) json_out(400, ['message' => 'Maksimal 50 jenis produk per transaksi.']);

    $cash = filter_var($b['cash'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000000000]]);
    if ($cash === false) $cash = 0;

    try {
        $pdo->beginTransaction();

        // Kunci baris owner: menjamin kode TRX berurut dan mencegah dua checkout bersamaan menghasilkan kode sama.
        $pdo->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE')->execute([$uid]);

        // Kunci baris produk yang akan dijual, sekaligus validasi milik owner + aktif + stok cukup.
        $ids = array_keys($want);
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $rows = db_all($pdo, "SELECT id, name, price, stock FROM products
                              WHERE owner_id = ? AND is_active = 1 AND id IN ($ph) FOR UPDATE",
                       array_merge([$uid], $ids));
        $map = [];
        foreach ($rows as $r) $map[(int) $r['id']] = $r;

        $kurang = [];
        $total = 0;
        foreach ($want as $id => $qty) {
            if (!isset($map[$id])) { $kurang[] = ['id' => $id, 'name' => null, 'kurang' => $qty, 'alasan' => 'tidak ditemukan']; continue; }
            $p = $map[$id];
            if ((int) $p['stock'] < $qty) { $kurang[] = ['id' => $id, 'name' => $p['name'], 'kurang' => $qty - (int) $p['stock'], 'alasan' => 'stok kurang']; continue; }
            $total += $qty * (int) $p['price'];
        }
        if ($kurang) { $pdo->rollBack(); json_out(409, ['message' => 'Stok beberapa produk tidak mencukupi. Periksa keranjang.', 'kurang' => $kurang]); }

        if ($method === 'Tunai' && $cash > 0 && $cash < $total) {
            $pdo->rollBack();
            json_out(400, ['message' => 'Uang pelanggan kurang dari total.']);
        }

        // Kode TRX berurut per owner: TRX-00001, TRX-00002, ...
        $next = (int) db_one($pdo, "SELECT COALESCE(MAX(CAST(SUBSTRING(code,5) AS UNSIGNED)),0)+1 AS n
                                    FROM transactions WHERE owner_id = ? AND code LIKE 'TRX-%'", [$uid])['n'];
        $code = 'TRX-' . str_pad((string) $next, 5, '0', STR_PAD_LEFT);

        $status = $method === 'Tunai' ? 'Selesai' : 'Menunggu';
        $pdo->prepare('INSERT INTO transactions (owner_id, code, customer_name, total, payment_method, status)
                       VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$uid, $code, $cust, $total, $method, $status]);
        $trxId = (int) $pdo->lastInsertId();

        $insItem = $pdo->prepare('INSERT INTO transaction_items (transaction_id, product_id, product_name, qty, unit_price)
                                  VALUES (?, ?, ?, ?, ?)');
        $decStock = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND owner_id = ? AND stock >= ?');
        foreach ($want as $id => $qty) {
            $p = $map[$id];
            $insItem->execute([$trxId, $id, $p['name'], $qty, (int) $p['price']]);
            $decStock->execute([$qty, $id, $uid, $qty]);
            if ($decStock->rowCount() !== 1) { $pdo->rollBack(); json_out(409, ['message' => 'Stok berubah saat menyimpan. Silakan ulangi.']); }
        }

        $kembalian = $method === 'Tunai' ? max(0, $cash - $total) : 0;
        $pdo->commit();

        json_out(200, [
            'message' => 'Transaksi tersimpan.',
            'code' => $code, 'status' => $status, 'method' => $method,
            'customer' => $cust, 'total' => $total, 'cash' => $method === 'Tunai' ? $cash : null, 'change' => $kembalian,
            'items' => array_map(fn($id) => ['name' => $map[$id]['name'], 'qty' => $want[$id], 'price' => (int) $map[$id]['price']], array_keys($want)),
        ]);
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if (($ex->errorInfo[1] ?? 0) === 1062) json_out(409, ['message' => 'Terjadi bentrok nomor transaksi. Silakan ulangi.']);
        throw $ex;
    }
}

try {
    $uid = owner_api_guard($pdo);
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if ($method === 'GET') list_products($pdo, $uid);
    elseif ($method === 'POST') checkout($pdo, $uid);
    else json_out(405, ['message' => 'Metode tidak diizinkan.']);
} catch (Throwable $ex) {
    error_log('[kasir] ' . $ex->getMessage());
    json_out(500, ['message' => 'Terjadi kesalahan pada server.']);
}