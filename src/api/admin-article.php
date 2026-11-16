<?php
require __DIR__ . '/../includes/owner.php';
require __DIR__ . '/../includes/artikel.php';

function admin_article_guard(PDO $pdo): array {
    if (empty($_SESSION['user_id'])) json_out(401, ['message' => 'Sesi berakhir.']);
    $me = db_one($pdo, 'SELECT id, role, is_master FROM users WHERE id = ? AND is_active = 1 LIMIT 1', [(int) $_SESSION['user_id']]);
    if (!$me || $me['role'] !== 'admin') json_out(403, ['message' => 'Akses ditolak.']);
    return ['id' => (int) $me['id'], 'master' => (int) $me['is_master'] === 1];
}

function article_list(PDO $pdo): void {
    $q = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
    $status = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
    $cat = (int) ($_GET['category'] ?? 0);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per = 20;

    $where = '1=1';
    $params = [];
    if ($q !== '') { $where .= ' AND (a.title LIKE ? OR a.excerpt LIKE ?)'; $like = '%' . addcslashes($q, '%_\\') . '%'; $params[] = $like; $params[] = $like; }
    if (in_array($status, ['draft','published','archived'], true)) { $where .= ' AND a.status = ?'; $params[] = $status; }
    if ($cat > 0) { $where .= ' AND a.category_id = ?'; $params[] = $cat; }

    $total = (int) db_one($pdo, "SELECT COUNT(*) AS n FROM articles a WHERE $where", $params)['n'];
    $pages = max(1, (int) ceil($total / $per));
    $page = min($page, $pages);
    $offset = ($page - 1) * $per;

    $rows = db_all($pdo, "SELECT a.id, a.slug, a.title, a.excerpt, a.cover_image, a.status, a.views, a.published_at, a.created_at, a.updated_at,
                                 c.name AS category_name, c.slug AS category_slug, u.full_name AS author_name
                          FROM articles a
                          LEFT JOIN article_categories c ON c.id = a.category_id
                          JOIN users u ON u.id = a.author_id
                          WHERE $where ORDER BY a.created_at DESC LIMIT $per OFFSET $offset", $params);

    $cats = db_all($pdo, 'SELECT id, slug, name FROM article_categories ORDER BY sort_order ASC, name ASC');

    json_out(200, [
        'items' => array_map(fn($r) => [
            'id' => (int) $r['id'], 'slug' => $r['slug'], 'title' => $r['title'], 'excerpt' => $r['excerpt'],
            'cover_image' => $r['cover_image'] ? 'uploads/artikel/' . $r['cover_image'] : null,
            'status' => $r['status'], 'views' => (int) $r['views'],
            'category' => $r['category_name'] ? ['name' => $r['category_name'], 'slug' => $r['category_slug']] : null,
            'author' => $r['author_name'], 'published_at' => $r['published_at'], 'created_at' => $r['created_at'], 'updated_at' => $r['updated_at'],
        ], $rows),
        'total' => $total, 'page' => $page, 'pages' => $pages, 'per' => $per,
        'categories' => array_map(fn($c) => ['id' => (int) $c['id'], 'slug' => $c['slug'], 'name' => $c['name']], $cats),
    ]);
}

function article_get(PDO $pdo, int $id): void {
    $a = db_one($pdo, 'SELECT * FROM articles WHERE id = ? LIMIT 1', [$id]);
    if (!$a) json_out(404, ['message' => 'Artikel tidak ditemukan.']);
    $tags = db_all($pdo, 'SELECT t.name FROM article_tags t JOIN article_tag_map m ON m.tag_id = t.id WHERE m.article_id = ?', [$id]);
    json_out(200, [
        'id' => (int) $a['id'], 'slug' => $a['slug'], 'title' => $a['title'], 'excerpt' => $a['excerpt'],
        'content' => $a['content'], 'cover_image' => $a['cover_image'] ? 'uploads/artikel/' . $a['cover_image'] : null,
        'category_id' => $a['category_id'] ? (int) $a['category_id'] : null,
        'status' => $a['status'], 'views' => (int) $a['views'],
        'meta_title' => $a['meta_title'], 'meta_description' => $a['meta_description'],
        'published_at' => $a['published_at'], 'created_at' => $a['created_at'], 'updated_at' => $a['updated_at'],
        'tags' => array_map(fn($t) => $t['name'], $tags),
    ]);
}

function article_save(PDO $pdo, array $admin, array $b): void {
    $id = (int) ($b['id'] ?? 0);
    $title = clean($b['title'] ?? '');
    $excerpt = clean($b['excerpt'] ?? '');
    $content = is_string($b['content'] ?? null) ? $b['content'] : '';
    $categoryId = (int) ($b['category_id'] ?? 0);
    $status = in_array($b['status'] ?? '', ['draft','published','archived'], true) ? $b['status'] : 'draft';
    $metaTitle = clean($b['meta_title'] ?? '');
    $metaDesc = clean($b['meta_description'] ?? '');
    $tags = is_array($b['tags'] ?? null) ? $b['tags'] : [];

    $errors = [];
    if (mb_strlen($title) < 5 || mb_strlen($title) > 200) $errors['title'] = 'Judul harus 5–200 karakter.';
    if ($excerpt === '') $errors['excerpt'] = 'Ringkasan wajib diisi.';
    elseif (mb_strlen($excerpt) > 300) $errors['excerpt'] = 'Ringkasan maksimal 300 karakter.';
    if (trim(strip_tags($content)) === '') $errors['content'] = 'Isi artikel wajib diisi.';
    if ($categoryId > 0 && !db_one($pdo, 'SELECT id FROM article_categories WHERE id = ?', [$categoryId])) $errors['category_id'] = 'Kategori tidak ditemukan.';
    if ($errors) json_out(400, ['message' => 'Periksa kembali data artikel.', 'errors' => $errors]);

    $content = artikel_sanitize($content);
    $slugBase = artikel_slug($b['slug'] ?? '') ?: artikel_slug($title);
    $slug = artikel_unique_slug($pdo, $slugBase, $id ?: null);

    $publishedAt = null;
    if ($status === 'published') {
        $existing = $id ? db_one($pdo, 'SELECT published_at FROM articles WHERE id = ?', [$id]) : null;
        $publishedAt = ($existing && $existing['published_at']) ? $existing['published_at'] : date('Y-m-d H:i:s');
    }

    $pdo->beginTransaction();
    try {
        if ($id > 0) {
            $pdo->prepare('UPDATE articles SET slug = ?, title = ?, excerpt = ?, content = ?, category_id = ?, status = ?, meta_title = ?, meta_description = ?, published_at = ? WHERE id = ?')
                ->execute([$slug, $title, $excerpt, $content, $categoryId ?: null, $status, $metaTitle ?: null, $metaDesc ?: null, $publishedAt, $id]);
        } else {
            $pdo->prepare('INSERT INTO articles (slug, title, excerpt, content, category_id, author_id, status, meta_title, meta_description, published_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$slug, $title, $excerpt, $content, $categoryId ?: null, $admin['id'], $status, $metaTitle ?: null, $metaDesc ?: null, $publishedAt]);
            $id = (int) $pdo->lastInsertId();
        }

        $pdo->prepare('DELETE FROM article_tag_map WHERE article_id = ?')->execute([$id]);
        foreach ($tags as $tagName) {
            $tagName = clean($tagName);
            if ($tagName === '') continue;
            $tagSlug = artikel_slug($tagName);
            if ($tagSlug === '') continue;
            $pdo->prepare('INSERT IGNORE INTO article_tags (slug, name) VALUES (?, ?)')->execute([$tagSlug, $tagName]);
            $tag = db_one($pdo, 'SELECT id FROM article_tags WHERE slug = ?', [$tagSlug]);
            if ($tag) $pdo->prepare('INSERT IGNORE INTO article_tag_map (article_id, tag_id) VALUES (?, ?)')->execute([$id, (int) $tag['id']]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    json_out(200, ['message' => 'Artikel disimpan.', 'id' => $id, 'slug' => $slug]);
}

function article_delete(PDO $pdo, int $id): void {
    $a = db_one($pdo, 'SELECT cover_image FROM articles WHERE id = ?', [$id]);
    if (!$a) json_out(404, ['message' => 'Artikel tidak ditemukan.']);
    $pdo->prepare('DELETE FROM articles WHERE id = ?')->execute([$id]);
    if ($a['cover_image']) { $f = __DIR__ . '/../uploads/artikel/' . basename($a['cover_image']); if (is_file($f)) @unlink($f); }
    json_out(200, ['message' => 'Artikel dihapus.']);
}

function article_set_cover(PDO $pdo, int $id, array $f): void {
    if (!isset($f['cover'])) json_out(400, ['message' => 'Pilih gambar cover.']);
    if ($f['cover']['error'] !== UPLOAD_ERR_OK) json_out(400, ['message' => 'Upload gagal.']);
    if ($f['cover']['size'] > 3 * 1024 * 1024) json_out(413, ['message' => 'Maksimal 3 MB.']);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['cover']['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if (!$ext) json_out(415, ['message' => 'Format harus JPG, PNG, atau WebP.']);

    $dir = __DIR__ . '/../uploads/artikel';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) json_out(500, ['message' => 'Folder upload gagal dibuat.']);
    $name = $id . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($f['cover']['tmp_name'], $dir . '/' . $name)) json_out(500, ['message' => 'Gagal menyimpan file.']);

    $old = db_one($pdo, 'SELECT cover_image FROM articles WHERE id = ?', [$id]);
    $pdo->prepare('UPDATE articles SET cover_image = ? WHERE id = ?')->execute([$name, $id]);
    if ($old && $old['cover_image'] && is_file($dir . '/' . basename($old['cover_image']))) @unlink($dir . '/' . basename($old['cover_image']));

    json_out(200, ['message' => 'Cover tersimpan.', 'url' => 'uploads/artikel/' . $name]);
}

try {
    $admin = admin_article_guard($pdo);
    $method = $_SERVER['REQUEST_METHOD'] ?? '';

    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'list';
        if ($action === 'get') article_get($pdo, (int) ($_GET['id'] ?? 0));
        article_list($pdo);
    }
    if ($method !== 'POST') json_out(405, ['message' => 'Metode tidak diizinkan.']);

    $isMultipart = stripos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') === 0;
    $b = $isMultipart ? $_POST : read_post_json();
    $action = is_string($b['action'] ?? null) ? $b['action'] : '';

    if ($action === 'save') article_save($pdo, $admin, $b);
    if ($action === 'delete') article_delete($pdo, (int) ($b['id'] ?? 0));
    if ($action === 'upload_cover') article_set_cover($pdo, (int) ($b['id'] ?? 0), $_FILES);

    json_out(400, ['message' => 'Aksi tidak dikenal.']);
} catch (Throwable $e) {
    error_log('[admin-article] ' . $e->getMessage());
    json_out(500, ['message' => 'Terjadi kesalahan pada server.']);
}