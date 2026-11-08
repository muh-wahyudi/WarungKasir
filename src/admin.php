<?php
require __DIR__ . '/includes/helpers.php';
header('Cache-Control: no-store');

if (empty($_SESSION['user_id'])) { header('Location: login.html'); exit; }

$st = $pdo->prepare('SELECT full_name, email, role, is_master FROM users WHERE id = ? AND is_active = 1 LIMIT 1');
$st->execute([$_SESSION['user_id']]);
$u = $st->fetch();
if (!$u) { session_destroy(); header('Location: login.html'); exit; }
if ($u['role'] !== 'admin') { header('Location: owner-dashboard.php'); exit; }

$isMaster = (int) $u['is_master'] === 1;
$h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$wk = json_encode(['master' => $isMaster, 'name' => $u['full_name'], 'email' => $u['email']], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Panel - WarungKasir</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/owner-dashboard.css">
  <link rel="stylesheet" href="css/admin.css">
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
  <symbol id="i-money" viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6.5 12h.01M17.5 12h.01"/></symbol>
  <symbol id="i-activity" viewBox="0 0 24 24"><path d="M3 12h4l3-8 4 16 3-8h4"/></symbol>
  <symbol id="i-alert" viewBox="0 0 24 24"><path d="M12 3l10 18H2z"/><path d="M12 10v5M12 18h.01"/></symbol>
  <symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></symbol>
  <symbol id="i-x" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></symbol>
  <symbol id="i-chev" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></symbol>
</defs></svg>

<div class="shell">
  <div class="scrim" id="scrim"></div>
  <aside class="side" id="side" aria-label="Menu admin">
    <div class="side-head">
      <a class="brand" href="admin.php"><span class="logo"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9l1.5-5h15L21 9"/><path d="M3 9a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0"/><path d="M5 12v8h14v-8M10 20v-4h4v4"/></svg></span><span class="bt">WarungKasir<small>ADMIN PANEL</small></span></a>
      <button class="icon-btn close-side" id="close-side" type="button" aria-label="Tutup menu"><svg class="i"><use href="#i-x"/></svg></button>
    </div>
    <nav class="nav">
      <a href="#dash" data-go="dash"><svg class="i" aria-hidden="true"><use href="#i-home"/></svg>Dashboard Admin</a>
      <a href="#users" data-go="users"><svg class="i" aria-hidden="true"><use href="#i-users"/></svg>Pengguna</a>
      <a href="#subs" data-go="subs"><svg class="i" aria-hidden="true"><use href="#i-repeat"/></svg>Langganan</a>
      <a href="#pay" data-go="pay"><svg class="i" aria-hidden="true"><use href="#i-card"/></svg>Pembayaran</a>
      <a href="admin-wallet.php"><svg class="i" aria-hidden="true"><use href="#i-card"/></svg>Wallet &amp; Withdrawal</a>
      <a href="#plans" data-go="plans"><svg class="i" aria-hidden="true"><use href="#i-box"/></svg>Paket</a>
      <a href="#support" data-go="support"><svg class="i" aria-hidden="true"><use href="#i-chat"/></svg>Dukungan Pelanggan</a>
      <a href="#settings" data-go="settings"><svg class="i" aria-hidden="true"><use href="#i-sliders"/></svg>Pengaturan Platform</a>
    </nav>
    <div class="profile"><span class="av">A</span><div><b><?= $h($u['full_name']) ?></b><small><?= $isMaster ? 'Master Admin' : 'Admin' ?></small></div></div>
  </aside>

  <div class="content">
    <header class="head">
      <button class="icon-btn menu-btn" id="open-side" type="button" aria-label="Buka menu" aria-expanded="false"><svg class="i"><use href="#i-menu"/></svg></button>
      <div class="head-t"><p id="crumb">Admin / Dashboard</p><h1 id="title">Dashboard Admin</h1></div>
      <div class="search"><svg class="i" aria-hidden="true"><use href="#i-search"/></svg><input type="search" placeholder="Cari pengguna…" aria-label="Cari pengguna, tekan Enter"></div>
      <div class="head-r">
        <button class="icon-btn" id="bell" type="button" aria-label="Notifikasi"><svg class="i"><use href="#i-bell"/></svg><span class="dot"></span></button>
        <div class="dd">
          <button class="who" id="who" type="button" aria-haspopup="menu" aria-expanded="false"><span class="av">A</span><span class="who-n">Admin</span><svg class="i"><use href="#i-chev"/></svg></button>
          <div class="menu-dd" id="dd" role="menu" hidden>
            <a href="#settings" role="menuitem" data-go="settings" data-tab="1">Profil Administrator</a>
            <a href="#settings" role="menuitem" data-go="settings" data-tab="0">Pengaturan Platform</a>
            <a href="login.html" role="menuitem" id="logout">Keluar</a>
          </div>
        </div>
      </div>
    </header>

    <main class="main">
      <div class="search search-m"><svg class="i" aria-hidden="true"><use href="#i-search"/></svg><input type="search" placeholder="Cari pengguna…" aria-label="Cari pengguna, tekan Enter"></div>
      <section class="view" id="v-dash">
        <div class="ph"><h2>Dashboard Admin </h2><p>Pantau aktivitas, pertumbuhan pengguna, dan kinerja layanan WarungKasir.</p></div>
        <div class="stats" id="dash-stats"></div>
        <div class="sec-h"><h2>Tren Platform</h2>
          <div class="seg" role="group" aria-label="Periode grafik"><button type="button" data-r="7">7 Hari</button><button type="button" data-r="30">30 Hari</button><button type="button" data-r="6m" class="on">6 Bulan</button></div></div>
        <div class="row-even">
          <section class="card" aria-labelledby="h-gu"><div class="card-h"><h2 id="h-gu">Pertumbuhan Pengguna</h2></div><div class="lc" id="lc-users"></div></section>
          <section class="card" aria-labelledby="h-gr"><div class="card-h"><h2 id="h-gr">Pendapatan Berlangganan</h2></div>
            <div class="lgd"><span style="--c:#86EFAC">Pro</span><span style="--c:#15803D">Pro Max</span></div>
            <div class="plot"><div class="y" id="rev-y" aria-hidden="true"></div><div class="cols" id="rev-cols"></div></div>
            <p class="sub" id="rev-note"></p></section>
        </div>
        <div class="row-13">
          <section class="card" aria-labelledby="h-dp"><div class="card-h"><h2 id="h-dp">Distribusi Paket</h2></div>
            <div class="dist"><div class="donut" id="donut"><div><b>1.250</b><small>pengguna</small></div></div><div class="lg" id="lg"></div></div>
            <p class="sub"><b>Paling banyak digunakan: Free</b> (74,4% dari semua pengguna). Di antara pelanggan berbayar, Pro mencakup 90,6%.</p></section>
          <section class="card" aria-labelledby="h-ds"><div class="card-h"><h2 id="h-ds">Dukungan Pelanggan</h2><button class="link" data-go="support" type="button">Lihat Semua</button></div>
            <div class="tk" id="tk-sum"></div>
            <div class="wrap"><table class="tbl"><thead><tr><th>ID Tiket</th><th>Pelanggan</th><th>Subjek</th><th>Tanggal</th><th>Prioritas</th><th>Status</th><th>Aksi</th></tr></thead><tbody id="d-tk"></tbody></table></div></section>
        </div>
        <section class="card mb" aria-labelledby="h-du"><div class="card-h"><h2 id="h-du">Pengguna Terbaru</h2><button class="link" data-go="users" type="button">Lihat Semua</button></div>
          <div class="wrap"><table class="tbl"><thead><tr><th>ID Pengguna</th><th>Nama</th><th>Nama Usaha</th><th>Email</th><th>Paket</th><th>Bergabung</th><th>Status</th><th>Aksi</th></tr></thead><tbody id="d-users"></tbody></table></div></section>
        <section class="card" aria-labelledby="h-dy"><div class="card-h"><h2 id="h-dy">Pembayaran Terbaru</h2><button class="link" data-go="pay" type="button">Lihat Semua Pembayaran</button></div>
          <div class="wrap"><table class="tbl"><thead><tr><th>ID Transaksi</th><th>Pelanggan</th><th>Paket</th><th>Tanggal</th><th>Nominal</th><th>Metode</th><th>Status</th><th>Aksi</th></tr></thead><tbody id="d-pays"></tbody></table></div></section>
      </section>

      <section class="view" id="v-users">
        <div class="ph"><h2>Manajemen Pengguna </h2><p>Lihat akun pemilik usaha yang terdaftar di WarungKasir.</p></div>
        <div class="stats" id="user-stats"></div>
        <section class="card">
          <div class="filters"><input id="u-q" type="search" placeholder="Cari ID, nama, usaha, atau email" aria-label="Cari pengguna">
            <select id="u-plan" aria-label="Filter paket"><option value="">Semua paket</option><option>Free</option><option>Pro</option><option>Pro Max</option></select>
            <select id="u-st" aria-label="Filter status akun"><option value="">Semua status</option><option>Aktif</option><option>Menunggu</option><option>Nonaktif</option></select></div>
          <div class="wrap"><table class="tbl"><thead><tr><th>ID Pengguna</th><th>Nama</th><th>Nama Usaha</th><th>Email</th><th>Paket</th><th>Bergabung</th><th>Status</th><th>Aksi</th></tr></thead><tbody id="u-tbl"></tbody></table></div>
          <p class="sub" id="u-count"></p></section>
      </section>

      <section class="view" id="v-subs">
        <div class="ph"><h2>Manajemen Langganan </h2><p>Pantau subscription aktif, berakhir, dan menunggu pembayaran.</p></div>
        <div class="stats" id="sub-stats"></div>
        <section class="card mb"><div class="card-h"><h2>Akan Berakhir dalam 7 Hari</h2><span class="badge w">18 langganan</span></div><ul class="list" id="exp"></ul><p class="sub">Menampilkan 3 yang paling dekat.</p></section>
        <section class="card">
          <div class="filters">
            <select id="s-plan" aria-label="Filter paket"><option value="">Semua paket</option><option>Pro</option><option>Pro Max</option></select>
            <select id="s-st" aria-label="Filter status"><option value="">Semua status</option><option>Aktif</option><option>Berakhir</option><option>Menunggu pembayaran</option></select></div>
          <div class="wrap"><table class="tbl"><thead><tr><th>ID Langganan</th><th>Pelanggan</th><th>Paket</th><th>Mulai</th><th>Berakhir</th><th>Status</th><th>Aksi</th></tr></thead><tbody id="s-tbl"></tbody></table></div></section>
      </section>

      <section class="view" id="v-pay">
        <div class="ph"><h2>Manajemen Pembayaran </h2><p>Riwayat pembayaran paket langganan.</p></div>
        <div class="stats" id="pay-stats"></div>
        <section class="card">
          <div class="filters"><input id="p-q" type="search" placeholder="Cari ID transaksi" aria-label="Cari ID transaksi">
            <select id="p-date" aria-label="Filter tanggal"><option value="">Semua tanggal</option><option value="7">7 hari terakhir</option></select>
            <select id="p-plan" aria-label="Filter paket"><option value="">Semua paket</option><option>Pro</option><option>Pro Max</option><option>Pro 3 Bulan</option></select>
            <select id="p-method" aria-label="Filter metode"><option value="">Semua metode</option><option>QRIS</option><option>Transfer Bank</option><option>Virtual Account</option><option>E-Wallet</option></select>
            <select id="p-st" aria-label="Filter status"><option value="">Semua status</option><option>Berhasil</option><option>Menunggu</option><option>Gagal</option><option>Dikembalikan</option></select></div>
          <div class="wrap"><table class="tbl"><thead><tr><th>ID Transaksi</th><th>Pelanggan</th><th>Paket</th><th>Tanggal</th><th>Nominal</th><th>Metode</th><th>Status</th><th>Aksi</th></tr></thead><tbody id="p-tbl"></tbody></table></div></section>
      </section>

      <section class="view" id="v-plans">
        <div class="ph"><h2>Manajemen Paket </h2><p>Katalog paket langganan WarungKasir.</p></div>
        <div class="plans" id="plan-cards"></div>
      </section>

      <section class="view" id="v-support">
        <div class="ph"><h2>Dukungan Pelanggan </h2><p>Pantau keluhan dan pertanyaan dari pemilik usaha.</p></div>
        <div class="stats" id="tk-stats"></div>
        <section class="card">
          <div class="tabs" id="t-tabs"><button class="on" data-f="">Semua</button><button data-f="Baru">Baru</button><button data-f="Sedang ditangani">Sedang ditangani</button><button data-f="Menunggu balasan">Menunggu balasan</button><button data-f="Selesai">Selesai</button></div>
          <div class="wrap"><table class="tbl"><thead><tr><th>ID Tiket</th><th>Pelanggan</th><th>Subjek</th><th>Tanggal</th><th>Prioritas</th><th>Status</th><th>Aksi</th></tr></thead><tbody id="t-tbl"></tbody></table></div></section>
      </section>

      <section class="view" id="v-settings">
        <div class="ph"><h2>Pengaturan Platform </h2><p>Formulir ini hanya tampilan desain.</p></div>
        <section class="card">
          <div class="tabs" data-for="set"><button class="on" data-t="0">Platform</button><button data-t="1">Profil Admin</button><button data-t="2">Notifikasi</button><button data-t="3">Tambah Admin</button><button data-t="4">Tampilan</button><button data-t="5">Bantuan</button></div>
          <div id="set-0"></div><div id="set-1" hidden></div><div id="set-2" hidden></div><div id="set-3" hidden></div><div id="set-4" hidden></div><div id="set-5" hidden></div>
        </section>
      </section>
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
<script>window.WK = <?= $wk ?>;</script>
<script src="js/admin.js"></script>
</body>
</html>