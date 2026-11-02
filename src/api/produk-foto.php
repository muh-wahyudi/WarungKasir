<?php
// Unggah foto produk (multipart: id + foto). Menimpa foto lama bila ada.
require __DIR__ . '/../includes/owner.php';
require __DIR__ . '/../includes/upload.php';

$saved = null;
try {
    $uid = owner_api_guard($pdo);
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_out(405, ['message' => 'Metode tidak diizinkan.']);
    require_same_origin();

    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        // Isi permintaan melebihi post_max_size: PHP mengosongkan $_POST dan $_FILES.
        if (empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) json_out(413, ['message' => 'Ukuran foto terlalu besar. Maksimal 2 MB.']);
        json_out(400, ['message' => 'Produk tidak valid.']);
    }
    $own = db_one($pdo, 'SELECT id, image FROM products WHERE id = ? AND owner_id = ? AND is_active = 1', [$id, $uid]);
    if (!$own) json_out(404, ['message' => 'Produk tidak ditemukan. Mungkin sudah dihapus.']);
    if (!isset($_FILES['foto'])) json_out(400, ['message' => 'Pilih foto terlebih dahulu.']);

    $saved = save_product_image($_FILES['foto'], $uid);
    $pdo->prepare('UPDATE products SET image = ? WHERE id = ? AND owner_id = ?')->execute([$saved, $id, $uid]);
    delete_image_file($own['image']);   // foto lama dibuang setelah yang baru tersimpan
    json_out(200, ['message' => 'Foto disimpan.', 'image' => 'uploads/' . $saved]);
} catch (UploadError $ex) {
    json_out($ex->status, ['message' => $ex->getMessage()]);
} catch (Throwable $ex) {
    if ($saved) delete_image_file($saved);
    error_log('[produk-foto] ' . $ex->getMessage());
    json_out(500, ['message' => 'Terjadi kesalahan pada server.']);
}