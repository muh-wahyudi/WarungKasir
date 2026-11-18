<?php
require_once __DIR__ . '/helpers.php';

function admin_layout_guard(PDO $pdo): array {
    header('Cache-Control: no-store');
    if (empty($_SESSION['user_id'])) { header('Location: login.html'); exit; }
    $st = $pdo->prepare('SELECT id, full_name, email, role, is_master FROM users WHERE id = ? AND is_active = 1 LIMIT 1');
    $st->execute([$_SESSION['user_id']]);
    $u = $st->fetch();
    if (!$u) { session_destroy(); header('Location: login.html'); exit; }
    if ($u['role'] !== 'admin') { header('Location: owner-dashboard.php'); exit; }
    $u['is_master'] = (int) $u['is_master'] === 1;
    return $u;
}

function admin_layout_start(array $u, string $active, string $title, string $crumb, string $headExtra = ''): void {
    $h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $isMaster = $u['is_master'];
    $ini = $h(mb_strtoupper(mb_substr($u['full_name'], 0, 1)));
    $wk = json_encode(['master' => $isMaster, 'name' => $u['full_name'], 'email' => $u['email']], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

    $navItems = [
        ['dash', 'Dashboard Admin', 'home', 'admin-dashboard.php'],
        ['users', 'Pengguna', 'users', 'admin-users.php'],
        ['subs', 'Langganan', 'repeat', 'admin.php#subs'],
        ['pay', 'Pembayaran', 'card', 'admin.php#pay'],
        ['wallet', 'Wallet & Withdrawal', 'wallet', 'admin-wallet.php'],
        ['artikel', 'Artikel', 'box', 'admin-artikel.php'],
        ['plans', 'Paket', 'box', 'admin.php#plans'],
        ['support', 'Dukungan Pelanggan', 'chat', 'admin.php#support'],
        ['settings', 'Pengaturan Platform', 'sliders', 'admin.php#settings'],
    ];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $h($title) ?> - Admin WarungKasir</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/owner-dashboard.css">
  <link rel="stylesheet" href="css/admin.css">
  <?= $headExtra ?>
  <script>window.WK = <?= $wk ?>;</script>
</head>
<body>
<svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs>
  <symbol id="i-home" viewBox="0 0 24 24"><path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/><path d="M10 20v-6h4v6"/></symbol>
  <symbol id="i-users" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"/><path d="M2 20a7 7 0 0 1 14 0"/><circle cx="17" cy="9" r="2.5"/><path d="M17 14a5 5 0 0 1 5 5"/></symbol>
  <symbol id="i-repeat" viewBox="0 0 24 24"><path d="M17 2l4 4-4 4"/><path d="M3 11V9a3 3 0 0 1 3-3h15"/><path d="M7 22l-4-4 4-4"/><path d="M21 13v2a3 3 0 0 1-3 3H3"/></symbol>
  <symbol id="i-card" viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M6 15h4"/></symbol>
  <symbol id="i-box" viewBox="0 0 24 24"><path d="M21 8l-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/></symbol>
  <symbol id="i-chat" viewBox="0 0 24 24"><path d="M21 12a8 8 0 0 1-11.6 7.1L3 21l1.9-5.4A8 8 0 1 1 21 12z"/></symbol>
  <symbol id="i-sliders" viewBox="0 0 24 24"><path d="M4 6h8M18 6h2M4 12h2M12 12h8M4 18h10M20 18h0"/><circle cx="15" cy="6" r="2"/><circle cx="9" cy="12" r="2"/><circle cx="17" cy="18" r="2"/></symbol>
  <symbol id="i-bell" viewBox="0 0 24 24"><path d="M6 9a6 6 0 0 1 12 0c0 6 2 7 2 7H4s2-1 2-7"/><path d="M10 20a2 2 0 0 0 4 0"/></symbol>
  <symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></symbol>
  <symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></symbol>
  <symbol id="i-x" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></symbol>
  <symbol id="i-chev" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></symbol>
  <symbol id="i-layers" viewBox="0 0 24 24"><path d="M12 3l9 5-9 5-9-5 9-5z"/><path d="M3 12.5l9 5 9-5"/><path d="M3 17l9 5 9-5"/></symbol>
  <symbol id="i-wallet" viewBox="0 0 24 24"><path d="M3 7a2 2 0 0 1 2-2h12v4"/><path d="M3 7v11a2 2 0 0 0 2 2h14a1 1 0 0 0 1-1v-3"/><path d="M21 9h-4a2 2 0 0 0 0 4h4z"/></symbol>
  <symbol id="i-alert" viewBox="0 0 24 24"><path d="M12 3l10 18H2z"/><path d="M12 10v5M12 18h.01"/></symbol>
  <symbol id="i-money" viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6.5 12h.01M17.5 12h.01"/></symbol>
  <symbol id="i-check" viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></symbol>
</defs></svg>

<div class="shell">
  <div class="scrim" id="scrim"></div>
  <aside class="side" id="side" aria-label="Menu admin">
    <div class="side-head">
      <a class="brand" href="admin.php"><span class="logo"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9l1.5-5h15L21 9"/><path d="M3 9a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0"/><path d="M5 12v8h14v-8M10 20v-4h4v4"/></svg></span><span class="bt">WarungKasir<small>ADMIN PANEL</small></span></a>
      <button class="icon-btn close-side" id="close-side" type="button" aria-label="Tutup menu"><svg class="i"><use href="#i-x"/></svg></button>
    </div>
    <nav class="nav">
<?php foreach ($navItems as [$key, $label, $icon, $href]): ?>
      <a href="<?= $h($href) ?>"<?= $key === $active ? ' class="on" aria-current="page"' : '' ?>><svg class="i" aria-hidden="true"><use href="#i-<?= $icon ?>"/></svg><?= $h($label) ?></a>
<?php endforeach; ?>
    </nav>
    <div class="profile"><span class="av"><?= $ini ?></span><div><b><?= $h($u['full_name']) ?></b><small><?= $isMaster ? 'Master Admin' : 'Admin' ?></small></div></div>
  </aside>

  <div class="content">
    <header class="head">
      <button class="icon-btn menu-btn" id="open-side" type="button" aria-label="Buka menu" aria-expanded="false"><svg class="i"><use href="#i-menu"/></svg></button>
      <div class="head-t"><p><?= $h($crumb) ?></p><h1><?= $h($title) ?></h1></div>
      <div class="head-r">
        <button class="icon-btn" id="bell" type="button" aria-label="Notifikasi"><svg class="i"><use href="#i-bell"/></svg><span class="dot"></span></button>
        <div class="dd">
          <button class="who" id="who" type="button" aria-haspopup="menu" aria-expanded="false"><span class="av"><?= $ini ?></span><span class="who-n">Admin</span><svg class="i"><use href="#i-chev"/></svg></button>
          <div class="menu-dd" id="dd" role="menu" hidden>
            <a href="admin.php#settings" role="menuitem" data-tab="1">Profil Administrator</a>
            <a href="admin.php#settings" role="menuitem" data-tab="0">Pengaturan Platform</a>
            <a href="login.html" role="menuitem" id="logout">Keluar</a>
          </div>
        </div>
      </div>
    </header>

    <main class="main">
<?php
}

function admin_layout_end(array $scripts = []): void {
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
<script src="js/admin-layout.js"></script>
<?php foreach ($scripts as $s): ?>
<script src="<?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endforeach; ?>
</body>
</html>
<?php
}