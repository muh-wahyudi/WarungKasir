<?php
require __DIR__ . '/includes/admin_layout.php';
$u = admin_layout_guard($pdo);
$v = fn($f) => $f . '?v=' . (is_file(__DIR__ . '/' . $f) ? filemtime(__DIR__ . '/' . $f) : '0');
$headExtra = '<style>
  .fld select, .fld textarea { width: 100%; }
  .fld textarea { min-height: 80px; resize: vertical; }
  .fld textarea[aria-invalid=true], .fld select[aria-invalid=true] { border-color: var(--err); }
  .note-w { margin-bottom: 16px; padding: 12px 14px; border-radius: 12px; background: var(--warnbg); color: #92400E; font-size: .92rem; }
  .tbl tbody tr.nolink { cursor: default; }
  .acts { display: flex; gap: 8px; flex-wrap: wrap; }
  .tabs { flex-wrap: nowrap; }
  .set-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 20px; max-width: 760px; }
  .mut { color: var(--mu); font-size: .85rem; }
  .pre { white-space: pre-wrap; word-break: break-word; font-size: .8rem; color: var(--mu); max-width: 360px; }
  .proof-box { margin-top: 12px; padding: 12px; border-radius: 12px; background: var(--bg); }
  .num { font-variant-numeric: tabular-nums; }
  @media (max-width: 720px) { .set-grid { grid-template-columns: 1fr; } .fld select, .fld input, .fld textarea { font-size: 16px; } }
</style>';
admin_layout_start($u, 'wallet', 'Wallet & Withdrawal', 'Admin / Wallet & Withdrawal', $headExtra);
?>
      <div class="note-e" id="err" role="alert" hidden></div>
      <div class="note-w" id="devkey" hidden><b>Kunci enkripsi mode uji.</b> Nomor rekening dienkripsi dengan kunci yang disimpan di database. Untuk produksi, atur environment variable <code>WALLET_ENC_KEY</code> di server.</div>
      <div class="stats" id="stats"><div class="card stat sk"></div><div class="card stat sk"></div><div class="card stat sk"></div><div class="card stat sk"></div></div>

      <section class="card">
        <div class="tabs" id="tabs" role="tablist">
          <button type="button" class="on" data-tab="wd">Withdrawal</button>
          <button type="button" data-tab="bank">Rekening Bank</button>
          <button type="button" data-tab="wallet">Saldo Warung</button>
          <button type="button" data-tab="set">Pengaturan Wallet</button>
          <button type="button" data-tab="audit">Audit Log</button>
        </div>

        <div id="t-wd">
          <div class="filters"><select id="f-wd" aria-label="Filter status withdrawal"><option value="">Semua status</option><option>Pending</option><option>Approved</option><option>Processing</option><option>Completed</option><option>Rejected</option><option>Failed</option></select></div>
          <div class="wrap"><table class="tbl"><thead><tr><th>ID</th><th>Warung</th><th>Nominal</th><th>Biaya</th><th>Diterima</th><th>Rekening Tujuan</th><th>Diajukan</th><th>Status</th><th>Aksi</th></tr></thead><tbody id="wd-tbl"></tbody></table></div>
        </div>

        <div id="t-bank" hidden>
          <div class="filters"><select id="f-bank" aria-label="Filter status rekening"><option value="pending">Menunggu verifikasi</option><option value="">Semua status</option><option value="verified">Terverifikasi</option><option value="rejected">Ditolak</option><option value="disabled">Dinonaktifkan</option></select></div>
          <p class="sub" style="margin-top:0">Sebelum memverifikasi, pastikan nama pemilik rekening sesuai dengan pemilik warung.</p>
          <div class="wrap"><table class="tbl"><thead><tr><th>Warung</th><th>Bank</th><th>Atas Nama</th><th>No. Rekening</th><th>Diajukan</th><th>Status</th><th>Aksi</th></tr></thead><tbody id="bank-tbl"></tbody></table></div>
        </div>

        <div id="t-wallet" hidden>
          <div id="liab"></div>
          <div class="wrap"><table class="tbl"><thead><tr><th>Warung</th><th>Pending</th><th>Tersedia</th><th>Diproses</th><th>Total Dicairkan</th><th>Biaya QRIS</th><th>Aksi</th></tr></thead><tbody id="wal-tbl"></tbody></table></div>
        </div>

        <div id="t-set" hidden>
          <form id="set-form" novalidate></form>
        </div>

        <div id="t-audit" hidden>
          <div class="wrap"><table class="tbl"><thead><tr><th>Waktu</th><th>Admin</th><th>Aksi</th><th>Objek</th><th>Alasan</th></tr></thead><tbody id="aud-tbl"></tbody></table></div>
        </div>
      </section>
<?php admin_layout_end([$v('js/admin-wallet.js')]); ?>