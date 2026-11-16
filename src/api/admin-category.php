<?php
require __DIR__ . '/../includes/artikel.php';

function admin_cat_guard(PDO $pdo): array {
    if (empty($_SESSION['user_id'])) json_out(401, ['message' => 'Sesi berakhir.']);
    $me = db_one($pdo, 'SELECT id, role FROM users WHERE id = ? AND is_active = 1 LIMIT 1', [(int) $_SESSION['user_id']]);
    if (!$me || $me['role'] !== 'admin') json_out(403, ['message' => 'Akses ditolak.']);
    return $me;
}

function cat_list(PDO $pdo): void {
    $rows = db_all($pdo, 'SELECT id, slug, name, description, sort_order FROM article_categories ORDER BY sort_order ASC, name ASC');
    json_out(200, ['items' => array_map(fn($r) => [
        'id' => (int) $r['id'], 'slug' => $r['slug'], 'name' => $r['name'],
        'description' => $r['description'], 'sort_order' => (int) $r['sort_order'],
    ], $rows)]);
}

function cat_save(PDO $pdo, array $b): void {
    $id = (int) ($b['id'] ?? 0);
    $name = clean($b['name'] ?? '');
    $slug = clean($b['slug'] ?? '');
    $description = clean($b['description'] ?? '');
    $sort = (int) ($b['sort_order'] ?? 0);

    $errors = [];
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) $errors['name'] = 'Nama harus 2–100 karakter.';
    if ($slug === '') $slug = artikel_slug($name);
    elseif (!preg_match('/^[a-z0-9-]+$/', $slug)) $errors['slug'] = 'Slug hanya huruf kecil, angka, dan strip.';
    if (mb_strlen($description) > 255) $errors['description'] = 'Deskripsi maksimal 255 karakter.';
    if ($sort < 0 || $sort > 999) $errors['sort_order'] = 'Urutan 0–999.';

    if ($errors) json_out(400, ['message' => 'Periksa kembali data kategori.', 'errors' => $errors]);

    $dup = db_one($pdo, 'SELECT id FROM article_categories WHERE slug = ? AND id <> ? LIMIT 1', [$slug, $id]);
    if ($dup) json_out(409, ['message' => 'Slug sudah dipakai kategori lain.', 'errors' => ['slug' => 'Slug sudah dipakai.']]);

    if ($id > 0) {
        if (!db_one($pdo, 'SELECT id FROM article_categories WHERE id = ?', [$id])) json_out(404, ['message' => 'Kategori tidak ditemukan.']);
        $pdo->prepare('UPDATE article_categories SET slug = ?, name = ?, description = ?, sort_order = ? WHERE id = ?')
            ->execute([$slug, $name, $description ?: null, $sort, $id]);
    } else {
        $pdo->prepare('INSERT INTO article_categories (slug, name, description, sort_order) VALUES (?, ?, ?, ?)')
            ->execute([$slug, $name, $description ?: null, $sort]);
        $id = (int) $pdo->lastInsertId();
    }

    json_out(200, ['message' => 'Kategori disimpan.', 'id' => $id]);
}

function cat_delete(PDO $pdo, int $id): void {
    if (!db_one($pdo, 'SELECT id FROM article_categories WHERE id = ?', [$id])) json_out(404, ['message' => 'Kategori tidak ditemukan.']);
    $pdo->prepare('DELETE FROM article_categories WHERE id = ?')->execute([$id]);
    json_out(200, ['message' => 'Kategori dihapus.']);
}

try {
    admin_cat_guard($pdo);
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if ($method === 'GET') cat_list($pdo);
    if ($method !== 'POST') json_out(405, ['message' => 'Metode tidak diizinkan.']);

    $b = read_post_json();
    $action = is_string($b['action'] ?? null) ? $b['action'] : '';

    if ($action === 'create' || $action === 'update') cat_save($pdo, $b);
    if ($action === 'delete') cat_delete($pdo, (int) ($b['id'] ?? 0));

    json_out(400, ['message' => 'Aksi tidak dikenal.']);
} catch (Throwable $e) {
    error_log('[admin-category] ' . $e->getMessage());
    json_out(500, ['message' => 'Terjadi kesalahan pada server.']);
}