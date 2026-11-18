<?php
require __DIR__ . '/includes/admin_layout.php';
$u = admin_layout_guard($pdo);
admin_layout_start($u, 'users', 'Pengguna', 'Admin / Pengguna');
?>
      <div class="ph"><h2>Manajemen Pengguna</h2><p>Lihat akun pemilik usaha yang terdaftar di WarungKasir.</p></div>

      <div class="stats" id="user-stats"></div>

      <section class="card">
        <div class="filters">
          <input id="u-q" type="search" placeholder="Cari nama, usaha, atau email" aria-label="Cari pengguna">
          <select id="u-plan" aria-label="Filter paket">
            <option value="">Semua paket</option>
            <option value="free">Free</option>
            <option value="pro">Pro</option>
            <option value="promax">Pro Max</option>
          </select>
          <select id="u-st" aria-label="Filter status">
            <option value="">Semua status</option>
            <option value="active">Aktif</option>
            <option value="inactive">Nonaktif</option>
          </select>
        </div>
        <div class="wrap">
          <table class="tbl">
            <thead><tr><th>ID</th><th>Nama</th><th>Usaha</th><th>Email</th><th>Paket</th><th>Bergabung</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody id="u-tbl"><tr><td colspan="8" class="empty">Memuat…</td></tr></tbody>
          </table>
        </div>
        <div class="pager"><span id="pg-info"></span><div><button class="link" type="button" id="pg-prev">Sebelumnya</button><button class="link" type="button" id="pg-next">Berikutnya</button></div></div>
      </section>
<?php admin_layout_end(['js/admin-users.js']); ?>