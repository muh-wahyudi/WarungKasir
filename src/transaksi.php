<?php
require __DIR__ . '/includes/owner_layout.php';
$u = owner_page_guard($pdo);
owner_layout_start($u, 'transaksi', 'Transaksi & Laporan', 'Riwayat penjualan dan laporan usaha · ' . e($u['business_name']));
?>
      <style>
        .tx-bar { margin-bottom: 10px; }
        .tx-bar .seg { max-width: 100%; overflow-x: auto; }
        .tx-bar .seg button { white-space: nowrap; }
        .tx-custom { display: flex; align-items: flex-end; gap: 12px; flex-wrap: wrap; margin-bottom: 14px; padding: 14px 16px; }
        .tx-custom .fld { margin: 0; }
        .tx-meta { display: flex; align-items: center; justify-content: space-between; gap: 8px 12px; flex-wrap: wrap; margin: 0 0 14px; }
        .tx-range { margin: 0; color: var(--mu); font-size: .9rem; }
        .tx-sum { margin: 0 0 10px; color: var(--mu); font-size: .9rem; }
        .tx-note { margin: 0 0 14px; }
        .tx-cmp { margin: -4px 0 16px; }
        .tx-pend { display: flex; align-items: center; justify-content: space-between; gap: 8px 14px; flex-wrap: wrap; margin: 0 0 14px; }
        .tx-two { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; margin-bottom: 16px; }
        .tbl.static tbody tr { cursor: default; }
        .tbl tr.zero { opacity: .55; }
        .tbl tfoot td { font-weight: 700; border-top: 2px solid var(--bd); border-bottom: 0; }
        .dl-up { color: var(--gd); font-weight: 600; }
        .dl-dn { color: #B91C1C; font-weight: 600; }
        #rp-status .val small { display: block; font-weight: 500; }
        @media (min-width: 721px) { .tbl .num { text-align: right; } }
        @media (max-width: 720px) {
          .tx-bar { flex-direction: column; align-items: stretch; }
          .tx-bar .seg { display: flex; }
          .tx-bar .seg button { flex: 1 1 auto; }
          .tx-custom .fld { flex: 1 1 140px; }
          .tx-custom .btn { flex: 1 1 100%; }
          .tx-meta .btn { width: 100%; }
          .tx-two { grid-template-columns: 1fr; }
        }
      </style>

      <div class="note-e" id="err" role="alert" hidden></div>

      <div class="sec-h tx-bar">
        <div class="seg" id="tabs" role="group" aria-label="Bagian halaman">
          <button type="button" data-tab="riwayat" class="on" aria-pressed="true">Riwayat Transaksi</button>
          <button type="button" data-tab="laporan" aria-pressed="false">Laporan Penjualan</button>
        </div>
        <div class="seg" id="periods" role="group" aria-label="Periode">
          <button type="button" data-p="today" aria-pressed="false">Hari Ini</button>
          <button type="button" data-p="7" class="on" aria-pressed="true">7 Hari</button>
          <button type="button" data-p="30" aria-pressed="false">30 Hari</button>
          <button type="button" data-p="month" aria-pressed="false">Bulan Ini</button>
          <button type="button" data-p="all" aria-pressed="false">Semua</button>
          <button type="button" data-p="custom" id="btn-custom" aria-pressed="false" aria-expanded="false" aria-controls="custom">Pilih Tanggal</button>
        </div>
      </div>

      <div class="card tx-custom" id="custom" hidden>
        <div class="fld"><label for="d-from">Dari tanggal</label><input id="d-from" type="date"></div>
        <div class="fld"><label for="d-to">Sampai tanggal</label><input id="d-to" type="date"></div>
        <button class="btn" type="button" id="d-apply">Terapkan</button>
      </div>

      <div class="tx-meta">
        <p class="tx-range" id="range"></p>
        <button class="btn line" type="button" id="export">Unduh Excel</button>
      </div>
      <p class="note-w tx-note" id="plan-note" hidden></p>
      <div class="note-w tx-pend" id="pend-note" role="status" hidden>
        <span id="pend-text"></span>
        <button class="link" type="button" id="pend-check">Periksa status</button>
      </div>

      <!-- Riwayat transaksi -->
      <section id="v-riwayat" aria-label="Riwayat transaksi">
        <section class="card">
          <div class="filters">
            <div class="sbox"><svg class="i" aria-hidden="true"><use href="#i-search"/></svg><input id="q" type="search" placeholder="Cari kode transaksi atau nama pelanggan" aria-label="Cari transaksi" maxlength="60" autocomplete="off"></div>
            <select id="f-status" aria-label="Filter status">
              <option value="">Semua status</option><option value="Selesai">Selesai</option><option value="Menunggu">Menunggu</option><option value="Dibatalkan">Dibatalkan</option>
            </select>
            <select id="f-method" aria-label="Filter metode bayar">
              <option value="">Semua metode</option><option value="Tunai">Tunai</option><option value="QRIS">QRIS</option>
            </select>
          </div>
          <p class="tx-sum" id="r-sum"></p>
          <div class="wrap">
            <table class="tbl">
              <thead><tr><th>ID Transaksi</th><th>Tanggal &amp; Waktu</th><th>Pelanggan</th><th>Total</th><th>Metode</th><th>Status</th></tr></thead>
              <tbody id="tx-body"><tr><td colspan="6" class="empty">Memuat transaksi…</td></tr></tbody>
            </table>
          </div>
          <div class="pager"><span id="pg-info"></span><div><button class="link" type="button" id="pg-prev">Sebelumnya</button><button class="link" type="button" id="pg-next">Berikutnya</button></div></div>
        </section>
      </section>

      <!-- Laporan penjualan -->
      <section id="v-laporan" aria-label="Laporan penjualan" hidden>
        <div class="stats" id="rp-stats"></div>
        <p class="sub-n tx-cmp" id="rp-cmp"></p>

        <section class="card mb" aria-labelledby="h-rp-recap">
          <div class="card-h"><h2 id="h-rp-recap">Rekap Penjualan</h2></div>
          <div class="wrap">
            <table class="tbl static">
              <thead><tr><th id="rp-col1">Tanggal</th><th class="num">Transaksi</th><th class="num">Barang Terjual</th><th class="num">Penjualan</th><th class="num">Rata-rata</th></tr></thead>
              <tbody id="rp-recap"></tbody>
              <tfoot id="rp-recap-foot"></tfoot>
            </table>
          </div>
        </section>

        <div class="tx-two">
          <section class="card" aria-labelledby="h-rp-method">
            <div class="card-h"><h2 id="h-rp-method">Metode Pembayaran</h2></div>
            <ul class="list" id="rp-methods"></ul>
          </section>
          <section class="card" aria-labelledby="h-rp-status">
            <div class="card-h"><h2 id="h-rp-status">Status Transaksi</h2></div>
            <ul class="list" id="rp-status"></ul>
            <p class="sub-n">Hanya transaksi berstatus Selesai yang dihitung sebagai penjualan.</p>
          </section>
        </div>

        <section class="card" aria-labelledby="h-rp-prod">
          <div class="card-h"><div><h2 id="h-rp-prod">Penjualan per Produk</h2><small class="muted" id="rp-prod-sub"></small></div></div>
          <div class="wrap">
            <table class="tbl static">
              <thead><tr><th>Produk</th><th class="num">Terjual</th><th class="num">Penjualan</th><th class="num">Rata-rata Harga</th><th class="num">Porsi</th></tr></thead>
              <tbody id="rp-prods"></tbody>
            </table>
          </div>
        </section>
      </section>
<?php owner_layout_end(['js/transaksi.js']); ?>