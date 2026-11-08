<?php
// API Kasir: daftar produk, checkout, dan QRIS Midtrans.
require __DIR__ . '/../includes/owner.php';
require __DIR__ . '/../includes/payments.php';

const KASIR_PRODUK_LIMIT = 20;
const KASIR_MAX_ITEMS = 100;

/* ---------- Daftar produk untuk kasir ---------- */
function kasir_produk(PDO $pdo, int $uid): void {
    $q = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 60) : '';
    $where = 'owner_id = ? AND is_active = 1 AND stock > 0';
    $params = [$uid];
    if ($q !== '') {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $where .= ' AND (name LIKE ? OR sku LIKE ?)';
        array_push($params, $like, $like);
    }
    $rows = db_all($pdo, "SELECT id, name, sku, image, price, stock FROM products
                          WHERE $where ORDER BY name ASC LIMIT " . KASIR_PRODUK_LIMIT, $params);
    json_out(200, [
        'items' => array_map(fn($r) => [
            'id' => (int) $r['id'],
            'name' => $r['name'],
            'sku' => $r['sku'],
            'image' => $r['image'] ? 'uploads/' . $r['image'] : null,
            'price' => (int) $r['price'],
            'stock' => (int) $r['stock'],
        ], $rows),
    ]);
}

/* ---------- Checkout transaksi ---------- */
function kasir_checkout(PDO $pdo, int $uid, array $b): void {
    $method = in_array($b['method'] ?? '', ['Tunai', 'QRIS'], true) ? $b['method'] : null;
    if (!$method) json_out(400, ['message' => 'Metode bayar tidak valid.']);

    $items = is_array($b['items'] ?? null) ? $b['items'] : [];
    if (!$items || count($items) > KASIR_MAX_ITEMS) json_out(400, ['message' => 'Keranjang tidak valid.']);

    $cust = clean($b['customer'] ?? '');
    $cust = $cust === '' ? 'Pelanggan Umum' : mb_substr($cust, 0, 100);
    $cash = (int) ($b['cash'] ?? 0);

    $pdo->beginTransaction();
    try {
        $ids = array_map(fn($x) => (int) $x['id'], $items);
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $prod = [];
        foreach (db_all($pdo, "SELECT id, name, price, stock FROM products
                               WHERE owner_id = ? AND is_active = 1 AND id IN ($ph) FOR UPDATE",
                               array_merge([$uid], $ids)) as $r) {
            $prod[(int) $r['id']] = $r;
        }

        $kurang = [];
        $total = 0;
        $lines = [];
        foreach ($items as $it) {
            $id = (int) $it['id'];
            $qty = (int) ($it['qty'] ?? 0);
            if ($qty < 1 || !isset($prod[$id])) {
                $kurang[] = ['id' => $id, 'kurang' => 0, 'alasan' => 'produk tidak ditemukan'];
                continue;
            }
            $p = $prod[$id];
            if ($qty > (int) $p['stock']) {
                $kurang[] = ['id' => $id, 'kurang' => $qty - (int) $p['stock'], 'alasan' => 'stok kurang'];
                continue;
            }
            $lines[] = ['id' => $id, 'name' => $p['name'], 'qty' => $qty, 'price' => (int) $p['price']];
            $total += $qty * (int) $p['price'];
        }
        if ($kurang) { $pdo->rollBack(); json_out(409, ['message' => 'Stok tidak mencukupi.', 'kurang' => $kurang]); }
        if ($total <= 0) { $pdo->rollBack(); json_out(400, ['message' => 'Total transaksi kosong.']); }
        if ($method === 'Tunai' && $cash < $total) { $pdo->rollBack(); json_out(400, ['message' => 'Uang pelanggan kurang dari total.']); }

        // Kode transaksi berurutan per pemilik
        $last = db_one($pdo, "SELECT code FROM transactions WHERE owner_id = ? ORDER BY id DESC LIMIT 1", [$uid]);
        $n = $last ? ((int) substr($last['code'], 4)) + 1 : 1;
        $code = 'TRX-' . str_pad((string) $n, 5, '0', STR_PAD_LEFT);

        $pdo->prepare('INSERT INTO transactions (owner_id, code, customer_name, total, payment_method, status) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$uid, $code, $cust, $total, $method, $method === 'QRIS' ? 'Menunggu' : 'Selesai']);
        $trxId = (int) $pdo->lastInsertId();

        $ins = $pdo->prepare('INSERT INTO transaction_items (transaction_id, product_id, product_name, qty, unit_price) VALUES (?, ?, ?, ?, ?)');
        $upd = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND owner_id = ?');
        foreach ($lines as $l) {
            $ins->execute([$trxId, $l['id'], $l['name'], $l['qty'], $l['price']]);
            $upd->execute([$l['qty'], $l['id'], $uid]);
        }

        // QRIS: buat pesanan Midtrans
        $orderId = null;
        if ($method === 'QRIS') {
            $orderId = 'WK-' . $uid . '-' . $trxId . '-' . time();
            try {
                $charge = midtrans_charge_qris($orderId, $total);
                if (!in_array((string) ($charge['status_code'] ?? ''), ['200', '201'], true)) {
                    throw new Exception('Midtrans menolak charge: ' . json_encode($charge));
                }
                $pdo->prepare('UPDATE transactions SET midtrans_order_id = ? WHERE id = ?')->execute([$orderId, $trxId]);
            } catch (Throwable $e) {
                error_log('[kasir] midtrans: ' . $e->getMessage());
                $pdo->rollBack();
                json_out(502, ['message' => 'Gagal membuat pembayaran QRIS. Coba lagi.']);
            }
        }

        $pdo->commit();
        json_out(200, [
            'code' => $code,
            'total' => $total,
            'method' => $method,
            'status' => $method === 'QRIS' ? 'Menunggu' : 'Selesai',
            'customer' => $cust,
            'cash' => $method === 'Tunai' ? $cash : null,
            'change' => $method === 'Tunai' ? $cash - $total : null,
            'items' => $lines,
            'midtrans_order_id' => $orderId,
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/* ---------- QRIS: buat QR ---------- */
function kasir_create_payment(PDO $pdo, int $uid, array $b): void {
    $orderId = clean($b['order_id'] ?? '');
    $trx = $orderId === '' ? null : pay_find_by_order($pdo, $uid, $orderId);
    if (!$trx) json_out(404, ['message' => 'Transaksi tidak ditemukan.']);
    if ($trx['status'] !== 'Menunggu') json_out(409, ['message' => 'Transaksi tidak menunggu pembayaran.']);
    try {
        $r = midtrans_get_status($orderId);
        if (!$r) throw new Exception('Tidak dapat menghubungi Midtrans.');

        // Charge ulang hanya jika Midtrans memastikan order belum ada.
        if ((string) ($r['status_code'] ?? '') === '404') {
            $r = midtrans_charge_qris($orderId, (int) $trx['total']);
        }

        // Respons status Midtrans tidak memuat "actions"; URL QR dibentuk dari transaction_id.
        $qr = $r['actions'][0]['url'] ?? null;
        if (!$qr && !empty($r['transaction_id'])) {
            $qr = MIDTRANS_STATUS_URL . 'qris/' . rawurlencode($r['transaction_id']) . '/qr-code';
        }
        if (!$qr) json_out(502, ['message' => 'QR tidak tersedia. Coba lagi.']);
        json_out(200, ['qr_url' => $qr]);
    } catch (Throwable $e) {
        error_log('[kasir] payment: ' . $e->getMessage());
        json_out(502, ['message' => 'Gagal memuat QR. Coba lagi.']);
    }
}

/* ---------- QRIS: cek status ---------- */
function kasir_check_status(PDO $pdo, int $uid, array $b): void {
    $orderId = clean($b['order_id'] ?? '');
    $trx = $orderId === '' ? null : pay_find_by_order($pdo, $uid, $orderId);
    if (!$trx) json_out(404, ['message' => 'Transaksi tidak ditemukan.']);
    $st = pay_reconcile($pdo, $uid, $trx);
    $code = db_one($pdo, 'SELECT code FROM transactions WHERE id = ?', [(int) $trx['id']])['code'] ?? '';
    json_out(200, ['status' => $st, 'code' => $code]);
}

/* ---------- QRIS: batalkan ---------- */
function kasir_cancel_payment(PDO $pdo, int $uid, array $b): void {
    $orderId = clean($b['order_id'] ?? '');
    $trx = $orderId === '' ? null : pay_find_by_order($pdo, $uid, $orderId);
    if (!$trx) json_out(404, ['message' => 'Transaksi tidak ditemukan.']);
    $r = pay_cancel($pdo, $uid, $trx);
    json_out(200, ['status' => $r['status'], 'message' => $r['message'], 'code' => $trx['code']]);
}

/* ---------- Router ---------- */
try {
    $uid = owner_api_guard($pdo);
    $m = $_SERVER['REQUEST_METHOD'] ?? '';

    if ($m === 'GET') kasir_produk($pdo, $uid);
    if ($m !== 'POST') json_out(405, ['message' => 'Metode tidak diizinkan.']);

    $b = read_post_json();
    $action = is_string($b['action'] ?? null) ? $b['action'] : '';
    if ($action === 'checkout')       kasir_checkout($pdo, $uid, $b);
    if ($action === 'create_payment') kasir_create_payment($pdo, $uid, $b);
    if ($action === 'check_status')   kasir_check_status($pdo, $uid, $b);
    if ($action === 'cancel_payment') kasir_cancel_payment($pdo, $uid, $b);
    json_out(400, ['message' => 'Aksi tidak dikenal.']);
} catch (Throwable $e) {
    error_log('[kasir] ' . $e->getMessage());
    json_out(500, ['message' => 'Terjadi kesalahan pada server.']);
}