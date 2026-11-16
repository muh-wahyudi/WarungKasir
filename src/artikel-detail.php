<?php
require __DIR__ . '/includes/artikel.php';
$slug = is_string($_GET['slug'] ?? null) ? $_GET['slug'] : '';
$h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Artikel - WarungKasir</title>
  <meta name="description" content="Baca artikel tips bisnis, tutorial, dan cerita sukses untuk pemilik warung.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root { --g: #16A34A; --gd: #15803D; --gl: #DCFCE7; --bg: #F8FAFC; --tx: #0F172A; --mu: #64748B; --bd: #E2E8F0; }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif; background: #fff; color: var(--tx); line-height: 1.6; }
    a { text-decoration: none; color: inherit; }
    .container { max-width: 1140px; margin: 0 auto; padding: 0 24px; }

    .nav { position: sticky; top: 0; z-index: 50; background: rgba(255,255,255,0.9); backdrop-filter: blur(12px); border-bottom: 1px solid var(--bd); }
    .nav-inner { display: flex; align-items: center; justify-content: space-between; height: 72px; }
    .brand { display: flex; align-items: center; gap: 10px; font-size: 1.25rem; font-weight: 800; letter-spacing: -0.02em; }
    .logo { width: 38px; height: 38px; border-radius: 11px; background: var(--g); display: grid; place-items: center; }
    .nav-links { display: flex; align-items: center; gap: 8px; }
    .nav-links a { padding: 10px 18px; border-radius: 10px; font-weight: 600; font-size: 0.95rem; }
    .nav-links a:hover { background: var(--bg); }
    .btn-primary { background: var(--g); color: #fff !important; padding: 10px 20px; border-radius: 10px; font-weight: 700; transition: background 0.15s; }
    .btn-primary:hover { background: var(--gd); }
    .btn-ghost { border: 1.5px solid var(--g); color: var(--gd) !important; padding: 10px 20px; border-radius: 10px; font-weight: 700; transition: background 0.15s; }
    .btn-ghost:hover { background: var(--gl); }

    .detail-page { padding: 48px 0 80px; }
    .detail-wrap { max-width: 760px; margin: 0 auto; }
    .detail-breadcrumb { margin-bottom: 24px; }
    .detail-breadcrumb a { color: var(--gd); font-weight: 600; font-size: .95rem; display: inline-flex; align-items: center; gap: 6px; }
    .detail-breadcrumb a:hover { text-decoration: underline; }
    .detail-cat { display: inline-block; padding: 3px 10px; background: var(--gl); color: var(--gd); font-size: .78rem; font-weight: 700; border-radius: 999px; margin-bottom: 12px; }
    .detail-title { font-size: clamp(1.8rem, 4vw, 2.4rem); font-weight: 800; letter-spacing: -.02em; line-height: 1.25; color: var(--tx); margin: 0 0 12px; }
    .detail-meta { color: var(--mu); font-size: .92rem; margin-bottom: 28px; }
    .detail-cover { margin: 0 0 32px; border-radius: 18px; overflow: hidden; }
    .detail-cover img { width: 100%; display: block; }
    .detail-content { font-size: 1.05rem; line-height: 1.8; color: #1e293b; }
    .detail-content h2 { font-size: 1.5rem; font-weight: 800; margin: 36px 0 14px; color: var(--tx); line-height: 1.3; }
    .detail-content h3 { font-size: 1.2rem; font-weight: 700; margin: 28px 0 10px; color: var(--tx); line-height: 1.35; }
    .detail-content p { margin: 0 0 18px; }
    .detail-content ul, .detail-content ol { margin: 0 0 18px; padding-left: 26px; }
    .detail-content li { margin-bottom: 8px; }
    .detail-content blockquote { border-left: 4px solid var(--g); background: var(--bg); padding: 16px 22px; margin: 20px 0; border-radius: 8px; font-style: italic; color: var(--tx); }
    .detail-content img { max-width: 100%; border-radius: 12px; margin: 20px 0; display: block; }
    .detail-content a { color: var(--gd); font-weight: 600; text-decoration: underline; }
    .detail-content code { background: var(--bg); padding: 2px 8px; border-radius: 6px; font-size: .9em; color: #b91c1c; font-family: monospace; }
    .detail-content pre { background: #1e293b; color: #f1f5f9; padding: 16px 20px; border-radius: 12px; overflow-x: auto; margin: 20px 0; }
    .detail-content pre code { background: none; color: inherit; padding: 0; }
    .detail-content strong, .detail-content b { font-weight: 700; color: var(--tx); }
    .detail-tags { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 40px; padding-top: 32px; border-top: 1px solid var(--bd); }
    .detail-tags .tag { padding: 6px 14px; background: var(--bg); color: var(--tx); font-size: .85rem; font-weight: 600; border-radius: 999px; }
    .detail-related { margin-top: 60px; padding-top: 40px; border-top: 1px solid var(--bd); }
    .detail-related h2 { font-size: 1.4rem; font-weight: 800; margin: 0 0 24px; color: var(--tx); }
    .related-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; }
    .related-card { background: #fff; border: 1px solid var(--bd); border-radius: 14px; padding: 18px; text-decoration: none; color: inherit; transition: transform .15s, box-shadow .15s; display: block; }
    .related-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(15,23,42,.08); }
    .related-card h3 { font-size: 1rem; font-weight: 700; margin: 0 0 6px; line-height: 1.4; color: var(--tx); }
    .related-card small { color: var(--mu); font-size: .82rem; }
    .detail-loading { text-align: center; padding: 80px 20px; color: var(--mu); }
    .detail-loading a { color: var(--gd); font-weight: 600; }

    .cta { padding: 72px 0; background: linear-gradient(135deg, var(--gd) 0%, var(--g) 100%); text-align: center; color: #fff; }
    .cta h2 { font-size: clamp(1.6rem, 3.5vw, 2.2rem); font-weight: 800; margin-bottom: 16px; }
    .cta p { color: #DCFCE7; max-width: 540px; margin: 0 auto 32px; }
    .cta a { display: inline-block; padding: 14px 32px; background: #fff; color: var(--gd); border-radius: 12px; font-weight: 700; transition: transform 0.15s; }
    .cta a:hover { transform: translateY(-2px); }

    .footer { padding: 40px 0; text-align: center; color: var(--mu); font-size: 0.9rem; border-top: 1px solid var(--bd); }
    .footer b { color: var(--tx); }

    @media (max-width: 640px) {
      .nav-links a:not(.btn-primary):not(.btn-ghost) { display: none; }
      .detail-content { font-size: 1rem; line-height: 1.75; }
      .detail-content h2 { font-size: 1.3rem; }
      .detail-content h3 { font-size: 1.1rem; }
    }
  </style>
</head>
<body>

<nav class="nav">
  <div class="container nav-inner">
    <a href="index.html" class="brand">
      <span class="logo">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l1.5-5h15L21 9"/><path d="M3 9a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0"/><path d="M5 12v8h14v-8M10 20v-4h4v4"/></svg>
      </span>
      WarungKasir
    </a>
    <div class="nav-links">
      <a href="index.html#fitur">Fitur</a>
      <a href="index.html#harga">Harga</a>
      <a href="artikel.php">Artikel</a>
      <a href="login.html" class="btn-ghost">Masuk</a>
      <a href="register.html" class="btn-primary">Daftar Gratis</a>
    </div>
  </div>
</nav>

<main class="detail-page">
  <div class="container">
    <div class="detail-wrap" id="root">
      <p class="detail-loading">Memuat artikel…</p>
    </div>
  </div>
</main>

<section class="cta">
  <div class="container">
    <h2>Siap Memodernisasi Warung Anda?</h2>
    <p>Bergabung dengan para pemilik warung yang sudah beralih ke kasir digital.</p>
    <a href="register.html">Daftar Gratis Sekarang</a>
  </div>
</section>

<footer class="footer">
  <div class="container">
    <p>&copy; 2026 <b>WarungKasir</b>. Dibuat untuk UMKM Indonesia.</p>
  </div>
</footer>

<script>
(() => {
  const $ = (s) => document.querySelector(s);
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const slug = <?= json_encode($slug) ?>;

  async function load() {
    if (!slug) {
      $('#root').innerHTML = '<p class="detail-loading">Artikel tidak ditemukan. <a href="artikel.php">Kembali ke daftar artikel</a></p>';
      return;
    }
    try {
      const r = await fetch('api/artikel-publik.php?action=detail&slug=' + encodeURIComponent(slug));
      if (!r.ok) throw new Error();
      const a = await r.json();

      document.title = (a.meta_title || a.title) + ' - WarungKasir';
      const metaDesc = document.querySelector('meta[name="description"]');
      if (metaDesc) metaDesc.setAttribute('content', a.meta_description || a.excerpt);

      $('#root').innerHTML = `
        <nav class="detail-breadcrumb"><a href="artikel.php">← Semua Artikel</a></nav>
        ${a.category ? `<span class="detail-cat">${esc(a.category.name)}</span>` : ''}
        <h1 class="detail-title">${esc(a.title)}</h1>
        <p class="detail-meta">${esc(a.author)} · ${esc(a.published_at || '')} · ${a.read_minutes} menit baca · ${a.views} views</p>
        ${a.cover_image ? `<div class="detail-cover"><img src="${esc(a.cover_image)}" alt="${esc(a.title)}"></div>` : ''}
        <div class="detail-content">${a.content}</div>
        ${a.tags && a.tags.length ? `<div class="detail-tags">${a.tags.map((t) => `<span class="tag">#${esc(t)}</span>`).join('')}</div>` : ''}
        ${a.related && a.related.length ? `<div class="detail-related"><h2>Artikel Terkait</h2><div class="related-grid">${a.related.map((r) => `<a class="related-card" href="artikel-detail.php?slug=${encodeURIComponent(r.slug)}"><h3>${esc(r.title)}</h3></a>`).join('')}</div></div>` : ''}
      `;
    } catch {
      $('#root').innerHTML = '<p class="detail-loading">Artikel tidak ditemukan. <a href="artikel.php">Kembali ke daftar artikel</a></p>';
    }
  }

  load();
})();
</script>
</body>
</html>