<?php
require __DIR__ . '/includes/admin_layout.php';
$u = admin_layout_guard($pdo);
admin_layout_start($u, 'dash', 'Dashboard Admin', 'Admin / Dashboard');
?>
      <div class="ph"><h2>Dashboard Admin</h2><p>Pantau aktivitas, pertumbuhan pengguna, dan kinerja layanan WarungKasir.</p></div>

      <div class="sec-h">
        <h2>Ringkasan</h2>
        <div class="seg" role="group" aria-label="Periode">
          <button type="button" data-r="7">7 Hari</button>
          <button type="button" data-r="30">30 Hari</button>
          <button type="button" data-r="6m" class="on">6 Bulan</button>
        </div>
      </div>
      <div class="stats" id="dash-stats"></div>

      <div class="row-even">
        <section class="card"><div class="card-h"><h2>Pertumbuhan Pengguna</h2></div><div class="lc" id="lc-users"></div></section>
        <section class="card">
          <div class="card-h"><h2>Distribusi Paket</h2></div>
          <div class="dist">
            <div class="donut" id="donut"></div>
            <div class="lg" id="lg"></div>
          </div>
        </section>
      </div>

      <div class="row-13">
        <section class="card">
          <div class="card-h"><h2>Langganan Akan Berakhir</h2></div>
          <ul class="list" id="exp"></ul>
        </section>
        <section class="card">
          <div class="card-h"><h2>Pengguna Terbaru</h2><a href="admin-users.php" class="link">Lihat Semua</a></div>
          <div class="wrap">
            <table class="tbl">
              <thead><tr><th>ID</th><th>Nama</th><th>Usaha</th><th>Email</th><th>Paket</th><th>Bergabung</th><th>Status</th></tr></thead>
              <tbody id="d-users"></tbody>
            </table>
          </div>
        </section>
      </div>

      <section class="card">
        <div class="card-h"><h2>Pembayaran Terbaru</h2><a href="admin-pay.php" class="link">Lihat Semua</a></div>
        <div class="wrap">
          <table class="tbl">
            <thead><tr><th>ID</th><th>Pelanggan</th><th>Paket</th><th>Tanggal</th><th>Nominal</th><th>Metode</th><th>Status</th></tr></thead>
            <tbody id="d-pays"></tbody>
          </table>
        </div>
      </section>
<?php admin_layout_end(['js/admin-dashboard.js']); ?>