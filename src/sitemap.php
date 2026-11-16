<?php
require __DIR__ . '/includes/artikel.php';
header('Content-Type: application/xml; charset=utf-8');
$base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$rows = db_all($pdo, "SELECT slug, updated_at FROM articles WHERE status = 'published' AND published_at <= NOW() ORDER BY published_at DESC LIMIT 1000");
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url><loc><?= $base ?>/index.html</loc><priority>1.0</priority></url>
  <url><loc><?= $base ?>/artikel.php</loc><priority>0.8</priority></url>
<?php foreach ($rows as $r): ?>
  <url>
    <loc><?= $base ?>/artikel-detail.php?slug=<?= urlencode($r['slug']) ?></loc>
    <lastmod><?= date('Y-m-d', strtotime($r['updated_at'])) ?></lastmod>
    <priority>0.7</priority>
  </url>
<?php endforeach; ?>
</urlset>