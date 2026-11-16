<?php
require __DIR__ . '/../includes/owner.php';
require __DIR__ . '/../includes/upload.php';

$saved = null;
try {
    $me = owner_api_guard($pdo);
    owner_require($me, 'produk');
    $uid = (int) $me['owner_id'];
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_out(405, ['message' => 'Metode tidak diizinkan.']);
    require_same_origin();

    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        if (empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) json_out(413, ['message' => 'Ukuran foto terlalu besar. Maksimal 2 MB.']);
        json_out(400, ['message' => 'Produk tidak valid.']);
    }
    $own = db_one($pdo, 'SELECT id, image FROM products WHERE id = ? AND owner_id = ? AND is_active = 1', [$id, $uid]);
    if (!$own) json_out(404, ['message' => 'Produk tidak ditemukan. Mungkin sudah dihapus.']);
    if (!isset($_FILES['foto'])) json_out(400, ['message' => 'Pilih foto terlebih dahulu.']);

    $saved = save_product_image($_FILES['foto'], $uid);
    $pdo->prepare('UPDATE products SET image = ? WHERE id = ? AND owner_id = ?')->execute([$saved, $id, $uid]);
    delete_image_file($own['image']);
    json_out(200, ['message' => 'Foto disimpan.', 'image' => 'uploads/' . $saved]);
} catch (UploadError $ex) {
    json_out($ex->status, ['message' => $ex->getMessage()]);
} catch (Throwable $ex) {
    if ($saved) delete_image_file($saved);
    error_log('[produk-foto] ' . $ex->getMessage());
    json_out(500, ['message' => 'Terjadi kesalahan pada server.']);
}