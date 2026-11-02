<?php
require __DIR__ . '/includes/owner_layout.php';
$u = owner_page_guard($pdo);
owner_layout_start($u, 'dashboard', 'Dashboard', 'Selamat datang kembali, <b>' . e(owner_first_name($u)) . '</b>! · ' . e($u['business_name']));
?>
      <div class="note-e" id="err" role="alert" hidden></div>
      <div class="sec-h">
        <div class="sec-t"><h2>Ringkasan Penjualan</h2><span class="live" id="live" role="status"><i></i><span id="live-t">Memuat data…</span></span></div>
        <div class="seg" role="group" aria-label="Periode ringkasan">
          <button type="button" data-p="today" class="on" aria-pressed="true">Hari Ini</button>
          <button type="button" data-p="7" aria-pressed="false">7 Hari Terakhir</button>
          <button type="button" data-p="30" aria-pressed="false">30 Hari Terakhir</button>
        </div>
      </div>
      <div class="stats" id="stats"></div>

      <div class="row2">
        <section class="card" aria-labelledby="h-chart">
          <div class="card-h">
            <div><h2 id="h-chart">Grafik Penjualan</h2><small id="chart-sub" style="color:var(--mu)"></small></div>
            <div class="seg sm" role="group" aria-label="Periode grafik">
              <button type="button" data-p="today" class="on">Hari Ini</button>
              <button type="button" data-p="7">7 Hari</button>
              <button type="button" data-p="30">30 Hari</button>
            </div>
          </div>
          <div class="plot"><div class="y" id="y" aria-hidden="true"></div><div class="cols" id="cols"></div></div>
          <p class="sub-n" id="chart-note" hidden>Belum ada penjualan pada periode ini.</p>
        </section>
        <section class="card" aria-labelledby="h-top">
          <div class="card-h"><div><h2 id="h-top">Produk Terlaris</h2><small id="top-sub" class="muted"></small></div></div>
          <ul class="list" id="top"></ul>
        </section>
      </div>

      <div class="row2">
        <section class="card" aria-labelledby="h-tx">
          <div class="card-h"><h2 id="h-tx">Transaksi Terbaru</h2><button class="link" id="all-tx" type="button">Lihat Semua</button></div>
          <table class="tbl">
            <thead><tr><th>ID Transaksi</th><th>Tanggal &amp; Waktu</th><th>Pelanggan</th><th>Total</th><th>Metode</th><th>Status</th></tr></thead>
            <tbody id="tx"></tbody>
          </table>
        </section>
        <div class="stack">
          <section class="card" aria-labelledby="h-rs">
            <div class="card-h"><h2 id="h-rs">Perlu Restok</h2></div>
            <ul class="list" id="rs"></ul>
          </section>
          <section class="card plan" aria-labelledby="h-plan">
            <div class="plan-h"><h2 id="h-plan">Paket Langganan Anda</h2><span class="badge" id="plan-badge">…</span></div>
            <div class="use"><span>Penggunaan produk</span><span id="use"></span></div>
            <div class="meter g"><i id="use-bar"></i></div>
            <ul id="plan-feat"></ul>
            <button class="link" type="button" data-soon="Paket Langganan">Lihat Paket</button>
            <div class="other" id="plan-other" hidden></div>
          </section>
        </div>
      </div>
<?php owner_layout_end(['js/owner-dashboard.js']); ?>