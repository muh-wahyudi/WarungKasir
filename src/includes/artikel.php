<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/owner.php';

function artikel_slug(string $text): string {
    $text = mb_strtolower(trim($text));
    $text = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}

function artikel_unique_slug(PDO $pdo, string $slug, ?int $excludeId = null): string {
    $base = $slug;
    $i = 1;
    while (true) {
        $sql = 'SELECT id FROM articles WHERE slug = ?' . ($excludeId ? ' AND id <> ?' : '') . ' LIMIT 1';
        $params = $excludeId ? [$slug, $excludeId] : [$slug];
        $exists = db_one($pdo, $sql, $params);
        if (!$exists) return $slug;
        $slug = $base . '-' . $i++;
        if ($i > 100) return $base . '-' . bin2hex(random_bytes(4));
    }
}

function artikel_excerpt(string $content, int $max = 200): string {
    $text = strip_tags($content);
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);
    if (mb_strlen($text) <= $max) return $text;
    return mb_substr($text, 0, $max) . '…';
}

function artikel_sanitize(string $html): string {
    $allowed = '<p><br><strong><b><em><i><u><s><ul><ol><li><h2><h3><h4><blockquote><a><img><code><pre><hr><table><thead><tbody><tr><th><td>';
    $html = strip_tags($html, $allowed);
    $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
    $html = preg_replace('/\son\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);
    $html = preg_replace('/javascript:/i', '', $html);
    return $html;
}

function artikel_slugify_or_fail(string $slug): string {
    $slug = artikel_slug($slug);
    if ($slug === '') json_out(400, ['message' => 'Slug tidak valid.', 'errors' => ['slug' => 'Slug tidak valid.']]);
    if (strlen($slug) > 200) json_out(400, ['message' => 'Slug maksimal 200 karakter.', 'errors' => ['slug' => 'Slug maksimal 200 karakter.']]);
    return $slug;
}

function artikel_read_minutes(string $content): int {
    $words = str_word_count(strip_tags($content));
    return max(1, (int) ceil($words / 200));
}

function artikel_seo_meta(string $title, ?string $metaTitle, ?string $metaDesc, string $excerpt): array {
    return [
        'title' => $metaTitle ?: $title,
        'description' => $metaDesc ?: mb_substr($excerpt, 0, 160),
    ];
}