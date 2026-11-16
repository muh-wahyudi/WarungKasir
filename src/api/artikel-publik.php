<?php
require __DIR__ . '/../includes/artikel.php';

try {
    $action = $_GET['action'] ?? 'list';

    if ($action === 'list') {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $per = 12;
        $catSlug = is_string($_GET['category'] ?? null) ? $_GET['category'] : '';
        $q = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';

        $where = "a.status = 'published' AND a.published_at <= NOW()";
        $params = [];
        if ($catSlug !== '') { $where .= ' AND c.slug = ?'; $params[] = $catSlug; }
        if ($q !== '') { $where .= ' AND (a.title LIKE ? OR a.excerpt LIKE ?)'; $like = '%' . addcslashes($q, '%_\\') . '%'; $params[] = $like; $params[] = $like; }

        $total = (int) db_one($pdo, "SELECT COUNT(*) AS n FROM articles a LEFT JOIN article_categories c ON c.id = a.category_id WHERE $where", $params)['n'];
        $pages = max(1, (int) ceil($total / $per));
        $page = min($page, $pages);
        $offset = ($page - 1) * $per;

        $rows = db_all($pdo, "SELECT a.slug, a.title, a.excerpt, a.cover_image, a.views, a.published_at,
                                     c.name AS cat_name, c.slug AS cat_slug
                              FROM articles a LEFT JOIN article_categories c ON c.id = a.category_id
                              WHERE $where ORDER BY a.published_at DESC LIMIT $per OFFSET $offset", $params);

        json_out(200, [
            'items' => array_map(fn($r) => [
                'slug' => $r['slug'], 'title' => $r['title'], 'excerpt' => $r['excerpt'],
                'cover_image' => $r['cover_image'] ? 'uploads/artikel/' . $r['cover_image'] : null,
                'views' => (int) $r['views'], 'published_at' => $r['published_at'],
                'category' => $r['cat_name'] ? ['name' => $r['cat_name'], 'slug' => $r['cat_slug']] : null,
            ], $rows),
            'total' => $total, 'page' => $page, 'pages' => $pages, 'per' => $per,
            'categories' => db_all($pdo, 'SELECT slug, name FROM article_categories ORDER BY sort_order ASC, name ASC'),
        ]);
    }

    if ($action === 'detail') {
        $slug = is_string($_GET['slug'] ?? null) ? $_GET['slug'] : '';
        $a = db_one($pdo, "SELECT a.*, c.name AS cat_name, c.slug AS cat_slug, u.full_name AS author_name
                           FROM articles a LEFT JOIN article_categories c ON c.id = a.category_id
                           JOIN users u ON u.id = a.author_id
                           WHERE a.slug = ? AND a.status = 'published' AND a.published_at <= NOW() LIMIT 1", [$slug]);
        if (!$a) json_out(404, ['message' => 'Artikel tidak ditemukan.']);
        $pdo->prepare('UPDATE articles SET views = views + 1 WHERE id = ?')->execute([(int) $a['id']]);
        $tags = db_all($pdo, 'SELECT t.name FROM article_tags t JOIN article_tag_map m ON m.tag_id = t.id WHERE m.article_id = ?', [(int) $a['id']]);
        $related = db_all($pdo, "SELECT slug, title, cover_image FROM articles WHERE category_id = ? AND id <> ? AND status = 'published' ORDER BY published_at DESC LIMIT 3",
            [(int) $a['category_id'], (int) $a['id']]);
        json_out(200, [
            'slug' => $a['slug'], 'title' => $a['title'], 'excerpt' => $a['excerpt'], 'content' => $a['content'],
            'cover_image' => $a['cover_image'] ? 'uploads/artikel/' . $a['cover_image'] : null,
            'views' => (int) $a['views'] + 1, 'published_at' => $a['published_at'],
            'author' => $a['author_name'], 'read_minutes' => artikel_read_minutes($a['content']),
            'meta_title' => $a['meta_title'], 'meta_description' => $a['meta_description'],
            'category' => $a['cat_name'] ? ['name' => $a['cat_name'], 'slug' => $a['cat_slug']] : null,
            'tags' => array_map(fn($t) => $t['name'], $tags),
            'related' => array_map(fn($r) => ['slug' => $r['slug'], 'title' => $r['title'], 'cover_image' => $r['cover_image'] ? 'uploads/artikel/' . $r['cover_image'] : null], $related),
        ]);
    }

    json_out(400, ['message' => 'Aksi tidak dikenal.']);
} catch (Throwable $e) {
    error_log('[artikel-publik] ' . $e->getMessage());
    json_out(500, ['message' => 'Terjadi kesalahan pada server.']);
}