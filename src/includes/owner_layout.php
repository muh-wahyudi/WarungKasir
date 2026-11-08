<?php
require_once __DIR__ . '/owner.php';

function e($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
// URL aset dengan nomor versi otomatis (waktu ubah file), agar browser tidak memakai JS lama dari cache.
function asset_url(string $path): string {
    $f = __DIR__ . '/../' . $path;
    return e($path) . '?v=' . (is_file($f) ? filemtime($f) : '0');
}
function owner_initials(string $name): string {
    $w = preg_split('/\s+/', trim($name)) ?: [''];
    return mb_strtoupper(mb_substr($w[0], 0, 1) . (isset($w[1]) ? mb_substr($w[1], 0, 1) : ''));
}
function owner_first_name(array $u): string { return preg_split('/\s+/', trim($u['full_name']))[0] ?? ''; }

function owner_layout_start(array $u, string $active, string $title, string $subHtml): void {
    $ini = e(owner_initials($u['full_name']));
    $items = [
        ['dashboard', 'Dashboard', 'home', 'owner-dashboard.php'],
        ['kasir', 'Kasir', 'cart', 'kasir.php'],
        ['produk', 'Produk & Stok', 'box', 'produk.php'],
        ['transaksi', 'Transaksi & Laporan', 'receipt', 'transaksi.php'],
        ['saldo', 'Saldo & Withdrawal', 'wallet', 'saldo.php'],
        ['pengaturan', 'Pengaturan', 'sliders', 'pengaturan.php'],
    ];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#16A34A">
  <title><?= e($title) ?> - WarungKasir</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/owner-dashboard.css?v=2">
</head>
<body>
<svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs>
  <symbol id="i-home" viewBox="0 0 24 24"><path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/><path d="M10 20v-6h4v6"/></symbol>
  <symbol id="i-cart" viewBox="0 0 24 24"><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.6 11.5a1 1 0 0 0 1 .8h8.8a1 1 0 0 0 1-.8L20 7H6"/></symbol>
  <symbol id="i-box" viewBox="0 0 24 24"><path d="M21 8l-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/></symbol>
  <symbol id="i-layers" viewBox="0 0 24 24"><path d="M12 3l9 5-9 5-9-5 9-5z"/><path d="M3 12.5l9 5 9-5"/><path d="M3 17l9 5 9-5"/></symbol>
  <symbol id="i-receipt" viewBox="0 0 24 24"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/></symbol>
  <symbol id="i-chart" viewBox="0 0 24 24"><path d="M4 20V4"/><path d="M4 20h16"/><path d="M8 16v-5M12 16V8M16 16v-3"/></symbol>
  <symbol id="i-star" viewBox="0 0 24 24"><path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/></symbol>
  <symbol id="i-sliders" viewBox="0 0 24 24"><path d="M4 6h8M18 6h2M4 12h2M12 12h8M4 18h10M20 18h0"/><circle cx="15" cy="6" r="2"/><circle cx="9" cy="12" r="2"/><circle cx="17" cy="18" r="2"/></symbol>
  <symbol id="i-bell" viewBox="0 0 24 24"><path d="M6 9a6 6 0 0 1 12 0c0 6 2 7 2 7H4s2-1 2-7"/><path d="M10 20a2 2 0 0 0 4 0"/></symbol>
  <symbol id="i-money" viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6.5 12h.01M17.5 12h.01"/></symbol>
  <symbol id="i-alert" viewBox="0 0 24 24"><path d="M12 3l10 18H2z"/><path d="M12 10v5M12 18h.01"/></symbol>
  <symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></symbol>
  <symbol id="i-x" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></symbol>
  <symbol id="i-chev" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></symbol>
  <symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></symbol>
  <symbol id="i-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></symbol>
  <symbol id="i-check" viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></symbol>
  <symbol id="i-wallet" viewBox="0 0 24 24"><path d="M3 7a2 2 0 0 1 2-2h12v4"/><path d="M3 7v11a2 2 0 0 0 2 2h14a1 1 0 0 0 1-1v-3"/><path d="M21 9h-4a2 2 0 0 0 0 4h4z"/></symbol>
  <symbol id="i-camera" viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></symbol>
</defs></svg>

<div class="shell">
  <div class="scrim" id="scrim"></div>
<?php if ($active === 'dashboard'): ?>
  <nav class="tabbar" aria-label="Navigasi cepat">
<?php foreach ($items as [$key, $label, $icon, $href]): if (!in_array($key, ['dashboard', 'kasir', 'produk'], true)) continue; ?>
    <a <?= $key === $active ? 'class="on" aria-current="page" ' : '' ?>href="<?= e($href) ?>"><svg class="i" aria-hidden="true"><use href="#i-<?= $icon ?>"/></svg><span><?= e($key === 'produk' ? 'Produk' : $label) ?></span></a>
<?php endforeach; ?>
    <button type="button" id="tab-more" aria-label="Buka menu lengkap"><svg class="i" aria-hidden="true"><use href="#i-menu"/></svg><span>Menu</span></button>
  </nav>
<?php endif; ?>
  <aside class="side" id="side" aria-label="Menu utama">
    <div class="side-head">
      <a class="brand" href="owner-dashboard.php"><span class="logo"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9l1.5-5h15L21 9"/><path d="M3 9a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0"/><path d="M5 12v8h14v-8M10 20v-4h4v4"/></svg></span>WarungKasir</a>
      <button class="icon-btn close-side" id="close-side" type="button" aria-label="Tutup menu"><svg class="i"><use href="#i-x"/></svg></button>
    </div>
    <nav class="nav">
<?php foreach ($items as [$key, $label, $icon, $href]): ?>
      <a <?= $key === $active ? 'class="on" aria-current="page" ' : '' ?>href="<?= $href ? e($href) : '#' ?>"<?= $href ? '' : ' data-soon="' . e($label) . '"' ?>><svg class="i" aria-hidden="true"><use href="#i-<?= $icon ?>"/></svg><?= e($label) ?></a>
<?php endforeach; ?>
    </nav>
    <div class="profile"><span class="av"><?= $ini ?></span><div><b><?= e($u['full_name']) ?></b><small>Pemilik · Paket <span id="side-plan"><?= e($u['plan']) ?></span></small></div></div>
  </aside>

  <div class="content">
    <header class="head">
      <button class="icon-btn menu-btn" id="open-side" type="button" aria-label="Buka menu" aria-expanded="false"><svg class="i"><use href="#i-menu"/></svg></button>
      <div class="head-t">
        <h1><?= e($title) ?></h1>
        <p><?= $subHtml ?></p>
      </div>
      <div class="head-r">
        <button class="icon-btn" id="bell" type="button" aria-label="Notifikasi"><svg class="i"><use href="#i-bell"/></svg><span class="dot" id="bell-dot" hidden></span></button>
        <div class="dd">
          <button class="who" id="who" type="button" aria-haspopup="menu" aria-expanded="false"><span class="av"><?= $ini ?></span><span class="who-n"><?= e(owner_first_name($u)) ?></span><svg class="i"><use href="#i-chev"/></svg></button>
          <div class="menu-dd" id="dd" role="menu" hidden>
            <a href="pengaturan.php#profil" role="menuitem">Profil Saya</a>
            <a href="pengaturan.php#usaha" role="menuitem">Pengaturan Usaha</a>
            <a href="login.html" role="menuitem" id="logout">Keluar</a>
          </div>
        </div>
      </div>
    </header>

    <main class="main<?= $active === 'dashboard' ? ' has-tabbar' : '' ?>">
<?php
}

function owner_layout_end(array $scripts = []): void {
?>
    </main>
  </div>
</div>

<dialog id="dlg" aria-labelledby="dlg-t">
  <div class="dlg">
    <div class="dlg-h"><h2 id="dlg-t"></h2><button class="icon-btn" id="dlg-x" type="button" aria-label="Tutup"><svg class="i"><use href="#i-x"/></svg></button></div>
    <div id="dlg-b"></div>
  </div>
</dialog>
<div class="toast" id="toast" role="status"></div>
<script src="<?= asset_url('js/owner-common.js') ?>"></script>
<script>var tm=document.getElementById("tab-more");if(tm)tm.addEventListener("click",function(){var b=document.getElementById("open-side");if(b)b.click();});</script>
<?php foreach ($scripts as $s): ?>
<script src="<?= asset_url($s) ?>"></script>
<?php endforeach; ?>
</body>
</html>
<?php
}