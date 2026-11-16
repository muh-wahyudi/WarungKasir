<?php
require __DIR__ . '/includes/artikel.php';
header('Cache-Control: public, max-age=300');
$h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Artikel & Tips Warung - WarungKasir</title>
  <meta name="description" content="Tutorial, tips bisnis, dan cerita sukses untuk pemilik warung dan UMKM Indonesia.">
  <meta property="og:title" content="Artikel & Tips Warung - WarungKasir">
  <meta property="og:description" content="Tutorial, tips bisnis, dan cerita sukses untuk pemilik warung dan UMKM Indonesia.">
  <meta property="og:type" content="website">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --g: #16A34A;
      --gd: #15803D;
      --gl: #DCFCE7;
      --bg: #F8FAFC;
      --tx: #0F172A;
      --mu: #64748B;
      --bd: #E2E8F0;
    }
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

    .section { padding: 80px 0; }
    .section-head { text-align: center; margin-bottom: 48px; }
    .section-head h2 { font-size: clamp(1.6rem, 3.5vw, 2.2rem); font-weight: 800; letter-spacing: -0.02em; margin-bottom: 12px; }
    .section-head p { color: var(--mu); max-width: 540px; margin: 0 auto; }

    .artikel-search { max-width: 480px; margin: 0 auto 24px; }
    .artikel-search input { width: 100%; padding: 14px 18px; border: 1.5px solid var(--bd); border-radius: 12px; font: inherit; font-size: 1rem; background: #fff; }
    .artikel-search input:focus { outline: none; border-color: var(--g); box-shadow: 0 0 0 4px rgba(22,163,74,.15); }
    .artikel-cats { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; margin-bottom: 40px; }
    .cat-chip { padding: 8px 18px; border-radius: 999px; border: 1.5px solid var(--bd); background: #fff; color: var(--tx); font-size: .9rem; font-weight: 600; cursor: pointer; transition: all .15s; font-family: inherit; }
    .cat-chip:hover { background: var(--bg); }
    .cat-chip.on { background: var(--g); color: #fff; border-color: var(--g); }

    .artikel-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 24px; }
    .artikel-card { display: flex; flex-direction: column; background: #fff; border: 1px solid var(--bd); border-radius: 18px; overflow: hidden; text-decoration: none; color: inherit; transition: transform .15s, box-shadow .15s; }
    .artikel-card:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(15,23,42,.08); }
    .artikel-cover { aspect-ratio: 16/9; overflow: hidden; background: var(--gl); }
    .artikel-cover img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .artikel-body { padding: 22px; display: flex; flex-direction: column; gap: 10px; flex: 1; }
    .artikel-cat { display: inline-block; padding: 3px 10px; background: var(--gl); color: var(--gd); font-size: .75rem; font-weight: 700; border-radius: 999px; align-self: flex-start; }
    .artikel-body h3 { font-size: 1.15rem; font-weight: 700; margin: 0; line-height: 1.35; color: var(--tx); }
    .artikel-body p { color: var(--mu); font-size: .92rem; margin: 0; flex: 1; line-height: 1.55; }
    .artikel-body small { color: var(--mu); font-size: .82rem; }
    .artikel-empty { grid-column: 1/-1; text-align: center; color: var(--mu); padding: 60px 20px; font-size: 1rem; }
    .artikel-pager { display: flex; justify-content: space-between; align-items: center; margin-top: 48px; gap: 12px; flex-wrap: wrap; }
    .artikel-pager #info { color: var(--mu); font-size: .9rem; }
    .artikel-pager .btn-ghost { cursor: pointer; background: #fff; font-family: inherit; font-size: .95rem; }
    .artikel-pager .btn-ghost:disabled { opacity: .4; cursor: not-allowed; }

    .cta { padding: 72px 0; background: linear-gradient(135deg, var(--gd) 0%, var(--g) 100%); text-align: center; color: #fff; }
    .cta h2 { font-size: clamp(1.6rem, 3.5vw, 2.2rem); font-weight: 800; margin-bottom: 16px; }
    .cta p { color: #DCFCE7; max-width: 540px; margin: 0 auto 32px; }
    .cta a { display: inline-block; padding: 14px 32px; background: #fff; color: var(--gd); border-radius: 12px; font-weight: 700; transition: transform 0.15s; }
    .cta a:hover { transform: translateY(-2px); }

    .footer { padding: 40px 0; text-align: center; color: var(--mu); font-size: 0.9rem; border-top: 1px solid var(--bd); }
    .footer b { color: var(--tx); }

    @media (max-width: 640px) {
      .nav-links a:not(.btn-primary):not(.btn-ghost) { display: none; }
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

<section class="section" style="padding-top: 60px;">
  <div class="container">
    <div class="section-head">
      <h2>Artikel &amp; Tips Warung</h2>
      <p>Tutorial, tips bisnis, dan cerita sukses untuk pemilik warung dan UMKM Indonesia.</p>
    </div>

    <div class="artikel-search">
      <input id="q" type="search" placeholder="Cari artikel…" aria-label="Cari artikel">
    </div>
    <div class="artikel-cats" id="cats"></div>

    <div class="artikel-grid" id="list"><p class="artikel-empty">Memuat artikel…</p></div>

    <div class="artikel-pager">
      <button class="btn-ghost" id="prev" disabled>← Sebelumnya</button>
      <span id="info"></span>
      <button class="btn-ghost" id="next" disabled>Berikutnya →</button>
    </div>
  </div>
</section>

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
  const state = { q: '', category: '', page: 1 };
  const CATS = [];

  async function load() {
    try {
      const r = await fetch('api/artikel-publik.php?' + new URLSearchParams({ action: 'list', ...state }));
      const j = await r.json();
      if (!r.ok) throw new Error();

      if (j.categories && !CATS.length) {
        CATS.push(...j.categories);
        $('#cats').innerHTML = '<button class="cat-chip on" data-cat="">Semua</button>' + CATS.map((c) => `<button class="cat-chip" data-cat="${esc(c.slug)}">${esc(c.name)}</button>`).join('');
      }

      $('#list').innerHTML = j.items.length ? j.items.map((a) => `
        <a class="artikel-card" href="artikel-detail.php?slug=${encodeURIComponent(a.slug)}">
          <div class="artikel-cover">${a.cover_image ? `<img src="${esc(a.cover_image)}" alt="" loading="lazy">` : ''}</div>
          <div class="artikel-body">
            ${a.category ? `<span class="artikel-cat">${esc(a.category.name)}</span>` : ''}
            <h3>${esc(a.title)}</h3>
            <p>${esc(a.excerpt)}</p>
            <small>${esc(a.published_at || '')} · ${a.views} views</small>
          </div>
        </a>`).join('') : '<p class="artikel-empty">Belum ada artikel.</p>';

      const from = j.total ? (j.page - 1) * j.per + 1 : 0;
      const to = from ? from + j.items.length - 1 : 0;
      $('#info').textContent = j.total ? `${from}–${to} dari ${j.total}` : '';
      $('#prev').disabled = j.page <= 1;
      $('#next').disabled = j.page >= j.pages;
    } catch {
      $('#list').innerHTML = '<p class="artikel-empty">Gagal memuat artikel.</p>';
    }
  }

  $('#q').addEventListener('input', (e) => {
    clearTimeout(window._t);
    window._t = setTimeout(() => { state.q = e.target.value.trim(); state.page = 1; load(); }, 300);
  });
  $('#cats').addEventListener('click', (e) => {
    const b = e.target.closest('[data-cat]'); if (!b) return;
    document.querySelectorAll('#cats .cat-chip').forEach((x) => x.classList.remove('on'));
    b.classList.add('on');
    state.category = b.dataset.cat; state.page = 1; load();
  });
  $('#prev').addEventListener('click', () => { if (state.page > 1) { state.page--; load(); } });
  $('#next').addEventListener('click', () => { state.page++; load(); });

  load();
})();
</script>
</body>
</html>