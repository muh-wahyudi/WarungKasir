<?php
require __DIR__ . '/../includes/owner.php';
require __DIR__ . '/../includes/upload.php';

const PER_PAGE = 10;

function parse_product(array $b): array {
    $e = [];
    $name = clean($b['name'] ?? '');
    $sku = clean($b['sku'] ?? '');
    if (mb_strlen($name) < 2 || mb_strlen($name) > 120) $e['name'] = 'Nama produk harus 2–120 karakter.';
    if ($sku === '') $e['sku'] = 'Kode produk wajib diisi.';
    elseif (!preg_match('/^[A-Za-z0-9._\-\/ ]{1,40}$/', $sku)) $e['sku'] = 'Kode hanya boleh huruf, angka, spasi, titik, garis, dan garis miring (maksimal 40 karakter).';
    $int = fn($v, $min, $max) => filter_var($v, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
    $price = $int($b['price'] ?? null, 1, 100000000);
    $stock = $int($b['stock'] ?? null, 0, 1000000);
    $min = $int($b['min_stock'] ?? null, 0, 100000);
    if ($price === false) $e['price'] = 'Harga harus berupa angka bulat antara Rp1 dan Rp100.000.000.';
    if ($stock === false) $e['stock'] = 'Stok harus berupa angka bulat 0 atau lebih.';
    if ($min === false) $e['min_stock'] = 'Batas stok harus berupa angka bulat 0 atau lebih.';
    return [$e, ['name' => $name, 'sku' => $sku, 'price' => $price, 'stock' => $stock, 'min_stock' => $min]];
}

function dup_error(PDOException $ex): void {
    if (($ex->errorInfo[1] ?? 0) !== 1062) return;
    $isSku = str_contains($ex->getMessage(), 'uq_products_owner_sku');
    $field = $isSku ? 'sku' : 'name';
    $msg = $isSku ? 'Kode produk ini sudah dipakai produk lain.' : 'Produk dengan nama ini sudah ada.';
    json_out(409, ['message' => $msg, 'errors' => [$field => $msg]]);
}

function list_products(PDO $pdo, int $uid): void {
    $q = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 60) : '';
    $stok = is_string($_GET['stok'] ?? null) ? $_GET['stok'] : '';
    $sort = is_string($_GET['sort'] ?? null) ? $_GET['sort'] : 'terbaru';
    $page = max(1, (int) ($_GET['page'] ?? 1));

    $stokSql = ['aman' => ' AND stock > min_stock', 'menipis' => ' AND stock > 0 AND stock <= min_stock', 'habis' => ' AND stock <= 0'];
    $sorts = ['terbaru' => 'id DESC', 'nama' => 'name ASC, id DESC', 'harga_naik' => 'price ASC, id DESC', 'harga_turun' => 'price DESC, id DESC',
              'stok_naik' => 'stock ASC, id DESC', 'stok_turun' => 'stock DESC, id DESC'];
    $order = $sorts[$sort] ?? $sorts['terbaru'];

    $where = 'owner_id = ? AND is_active = 1' . ($stokSql[$stok] ?? '');
    $params = [$uid];
    if ($q !== '') {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $where .= ' AND (name LIKE ? OR sku LIKE ?)';
        array_push($params, $like, $like);
    }

    $total = (int) db_one($pdo, "SELECT COUNT(*) AS n FROM products WHERE $where", $params)['n'];
    $pages = max(1, (int) ceil($total / PER_PAGE));
    $page = min($page, $pages);
    $offset = ($page - 1) * PER_PAGE;
    $rows = db_all($pdo, "SELECT id, name, sku, image, price, stock, min_stock FROM products WHERE $where ORDER BY $order LIMIT " . PER_PAGE . " OFFSET $offset", $params);

    $sum = db_one($pdo, 'SELECT COUNT(*) AS total, COALESCE(SUM(stock > min_stock), 0) AS aman,
                         COALESCE(SUM(stock > 0 AND stock <= min_stock), 0) AS menipis, COALESCE(SUM(stock <= 0), 0) AS habis
                         FROM products WHERE owner_id = ? AND is_active = 1', [$uid]);
    $plan = owner_plan($pdo, $uid);

    json_out(200, [
        'items' => array_map(fn($r) => [
            'id' => (int) $r['id'], 'name' => $r['name'], 'sku' => $r['sku'], 'image' => $r['image'] ? 'uploads/' . $r['image'] : null, 'price' => (int) $r['price'],
            'stock' => (int) $r['stock'], 'min_stock' => (int) $r['min_stock'],
            'status' => $r['stock'] <= 0 ? 'habis' : ($r['stock'] <= $r['min_stock'] ? 'menipis' : 'aman'),
        ], $rows),
        'total' => $total, 'page' => $page, 'pages' => $pages, 'per' => PER_PAGE,
        'summary' => ['total' => (int) $sum['total'], 'aman' => (int) $sum['aman'], 'menipis' => (int) $sum['menipis'], 'habis' => (int) $sum['habis']],
        'plan' => ['name' => $plan['name'], 'max_products' => $plan['max_products']],
    ]);
}

function handle_post(PDO $pdo, int $uid): void {
    $b = read_post_json();
    $action = is_string($b['action'] ?? null) ? $b['action'] : '';

    if ($action === 'create') {
        [$e, $d] = parse_product($b);
        if ($e) json_out(400, ['message' => 'Periksa kembali data produk.', 'errors' => $e]);
        $plan = owner_plan($pdo, $uid);
        try {
            $pdo->beginTransaction();
            $pdo->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE')->execute([$uid]);
            $count = (int) db_one($pdo, 'SELECT COUNT(*) AS n FROM products WHERE owner_id = ? AND is_active = 1', [$uid])['n'];
            if ($plan['max_products'] !== null && $count >= $plan['max_products']) {
                $pdo->rollBack();
                json_out(403, ['code' => 'limit', 'message' => "Paket {$plan['name']} maksimal {$plan['max_products']} produk. Upgrade paket untuk menambah produk lagi."]);
            }
            $pdo->prepare('INSERT INTO products (owner_id, name, sku, price, stock, min_stock) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$uid, $d['name'], $d['sku'], $d['price'], $d['stock'], $d['min_stock']]);
            $id = (int) $pdo->lastInsertId();
            $pdo->commit();
            json_out(201, ['message' => 'Produk ditambahkan.', 'id' => $id]);
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            dup_error($ex);
            throw $ex;
        }
    }

    $id = filter_var($b['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) json_out(400, ['message' => 'Produk tidak valid.']);
    $own = db_one($pdo, 'SELECT id, image FROM products WHERE id = ? AND owner_id = ? AND is_active = 1', [$id, $uid]);
    if (!$own) json_out(404, ['message' => 'Produk tidak ditemukan. Mungkin sudah dihapus.']);

    if ($action === 'update') {
        [$e, $d] = parse_product($b);
        if ($e) json_out(400, ['message' => 'Periksa kembali data produk.', 'errors' => $e]);
        try {
            $pdo->prepare('UPDATE products SET name = ?, sku = ?, price = ?, stock = ?, min_stock = ? WHERE id = ? AND owner_id = ?')
                ->execute([$d['name'], $d['sku'], $d['price'], $d['stock'], $d['min_stock'], $id, $uid]);
        } catch (PDOException $ex) {
            dup_error($ex);
            throw $ex;
        }
        json_out(200, ['message' => 'Perubahan disimpan.']);
    }

    if ($action === 'remove_image') {
        $pdo->prepare('UPDATE products SET image = NULL WHERE id = ? AND owner_id = ?')->execute([$id, $uid]);
        delete_image_file($own['image']);
        json_out(200, ['message' => 'Foto dihapus.']);
    }

    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM products WHERE id = ? AND owner_id = ?')->execute([$id, $uid]);
        delete_image_file($own['image']);
        json_out(200, ['message' => 'Produk dihapus.']);
    }

    json_out(400, ['message' => 'Aksi tidak dikenal.']);
}

try {
    $uid = owner_api_guard($pdo);
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if ($method === 'GET') list_products($pdo, $uid);
    elseif ($method === 'POST') handle_post($pdo, $uid);
    else json_out(405, ['message' => 'Metode tidak diizinkan.']);
} catch (Throwable $ex) {
    error_log('[produk] ' . $ex->getMessage());
    json_out(500, ['message' => 'Terjadi kesalahan pada server.']);
}