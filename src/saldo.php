<?php
require __DIR__ . '/includes/owner_layout.php';
$u = owner_page_guard($pdo);
owner_layout_start($u, 'saldo', 'Saldo & Withdrawal', 'Kelola saldo dan pencairan dana · ' . e($u['business_name']));
?>
      <style>
        .st-bar { margin-bottom: 16px; }
        .st-bar .seg { max-width: 100%; overflow-x: auto; }
        .st-bar .seg button { white-space: nowrap; }
        .note-w { margin-bottom: 16px; padding: 12px 14px; border-radius: 12px; background: var(--warnbg); color: #92400E; font-size: .92rem; }
        .fld select { width: 100%; padding: 10px 12px; border: 1.5px solid #CBD5E1; border-radius: 10px; background: #fff; font: inherit; }
        .fld select:focus { outline: none; border-color: var(--g); box-shadow: 0 0 0 4px rgba(22,163,74,.15); }
        .fld select[aria-invalid=true] { border-color: var(--err); }
        .wd-box .btn { width: 100%; margin-top: 14px; }
        .wd-box .hint { margin: 10px 0 0; }
        .wd-sum { margin: 12px 0 0; padding: 12px 14px; border-radius: 12px; background: var(--bg); }
        .wd-sum .dl:last-child { border-bottom: 0; }
        .acct { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .acct .grow { min-width: 160px; }
        .chips { display: flex; gap: 6px; flex-wrap: wrap; }
        .chip-d { padding: 2px 8px; border-radius: 999px; background: #F1F5F9; font-size: .8rem; font-weight: 600; white-space: nowrap; }
        .chip-d.up { background: var(--gl); color: var(--gd); }
        .chip-d.dn { background: var(--errbg); color: #B91C1C; }
        .more { display: flex; justify-content: center; margin-top: 14px; }
        .tbl-wrap { overflow-x: auto; }
        .tbl tbody tr.nolink { cursor: default; }
        .mut { color: var(--mu); font-size: .85rem; }
        @media (max-width: 720px) { .fld select, .fld input { font-size: 16px; } .tbl-wrap { overflow: visible; } }
      </style>

      <div class="note-e" id="err" role="alert" hidden></div>
      <div class="note-w" id="inactive" hidden><b>Wallet belum diaktifkan.</b> Pembayaran QRIS baru akan masuk ke saldo setelah admin mengaktifkan wallet.</div>

      <div class="sec-h st-bar">
        <div class="seg" id="tabs" role="group" aria-label="Bagian saldo">
          <button type="button" data-tab="saldo" class="on" aria-pressed="true">Saldo &amp; Withdrawal</button>
          <button type="button" data-tab="riwayat" aria-pressed="false">Riwayat Saldo</button>
          <button type="button" data-tab="rekening" aria-pressed="false">Rekening Bank</button>
        </div>
      </div>

      <!-- Saldo & Withdrawal -->
      <section id="v-saldo" aria-label="Saldo dan withdrawal">
        <div class="stats" id="stats"><div class="card stat sk"></div><div class="card stat sk"></div><div class="card stat sk"></div><div class="card stat sk"></div></div>
        <div class="row2">
          <section class="card" aria-labelledby="h-wdh">
            <div class="card-h"><h2 id="h-wdh">Riwayat Withdrawal</h2></div>
            <div class="tbl-wrap">
              <table class="tbl">
                <thead><tr><th>ID</th><th>Diajukan</th><th>Nominal</th><th>Biaya</th><th>Diterima</th><th>Rekening</th><th>Status</th></tr></thead>
                <tbody id="wd-tbl"></tbody>
              </table>
            </div>
          </section>
          <div class="stack">
            <section class="card wd-box" id="wd-box" aria-labelledby="h-wd"></section>
          </div>
        </div>
      </section>

      <!-- Riwayat saldo -->
      <section id="v-riwayat" aria-label="Riwayat saldo" hidden>
        <div class="sec-h">
          <div class="seg sm" id="subtabs" role="group" aria-label="Jenis riwayat">
            <button type="button" data-sub="pay" class="on">Pembayaran QRIS</button>
            <button type="button" data-sub="ledger">Mutasi Saldo</button>
          </div>
        </div>
        <section class="card" id="s-pay">
          <div class="tbl-wrap">
            <table class="tbl">
              <thead><tr><th>Transaksi</th><th>Dibayar</th><th>Pembayaran</th><th>MDR</th><th>Pajak</th><th>Total Biaya</th><th>Saldo Bersih</th><th>Status</th></tr></thead>
              <tbody id="pay-tbl"></tbody>
            </table>
          </div>
          <p class="sub-n" id="pay-note"></p>
          <div class="more"><button class="btn line" type="button" id="pay-more" hidden>Muat lebih banyak</button></div>
        </section>
        <section class="card" id="s-ledger" hidden>
          <div class="tbl-wrap">
            <table class="tbl">
              <thead><tr><th>Waktu</th><th>Jenis</th><th>Keterangan</th><th>Perubahan Saldo</th></tr></thead>
              <tbody id="led-tbl"></tbody>
            </table>
          </div>
          <div class="more"><button class="btn line" type="button" id="led-more" hidden>Muat lebih banyak</button></div>
        </section>
      </section>

      <!-- Rekening bank -->
      <section id="v-rekening" aria-label="Rekening bank" hidden>
        <section class="card">
          <div class="card-h"><h2>Rekening Pencairan</h2><button class="btn" type="button" id="add-bank">Tambah Rekening</button></div>
          <ul class="list" id="bank-list"></ul>
          <p class="sub-n">Rekening baru harus diverifikasi admin sebelum bisa dipakai. Untuk mengganti rekening, tambahkan rekening baru lalu nonaktifkan yang lama. Nomor rekening disimpan terenkripsi dan hanya 4 digit terakhir yang ditampilkan.</p>
        </section>
      </section>
<?php owner_layout_end(['js/saldo.js']); ?>