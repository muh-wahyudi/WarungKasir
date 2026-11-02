<?php
require __DIR__ . '/includes/owner_layout.php';
$u = owner_page_guard($pdo);
owner_layout_start($u, '', 'Pemindai Barcode', 'Gunakan kamera HP untuk memindai barcode dari desktop.');
?>
<style>
  .scanner-wrap { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 60vh; text-align: center; }
  .scanner-icon { width: 80px; height: 80px; border-radius: 50%; background: var(--gl); color: var(--gd); display: flex; align-items: center; justify-content: center; margin-bottom: 20px; animation: pulse-slow 2s infinite; }
  .scanner-icon svg { width: 40px; height: 40px; }
  @keyframes pulse-slow { 0% { box-shadow: 0 0 0 0 rgba(22,163,74,.4); } 70% { box-shadow: 0 0 0 20px rgba(22,163,74,0); } 100% { box-shadow: 0 0 0 0 rgba(22,163,74,0); } }
  .scanner-status { font-size: 1.1rem; font-weight: 600; margin-bottom: 8px; }
  .scanner-desc { color: var(--mu); font-size: .95rem; max-width: 300px; margin: 0 auto 24px; }
  
  /* Area kamera */
  #reader { width: 100%; max-width: 500px; border-radius: 16px; overflow: hidden; display: none; }
  #reader video { border-radius: 16px; object-fit: cover; }
  .cam-overlay { display: none; width: 100%; max-width: 500px; margin-top: 16px; }
  .cam-overlay.show { display: block; }
  .cam-btn-row { display: flex; gap: 12px; justify-content: center; margin-top: 20px; }
  .cam-btn-row .btn { flex: 1; max-width: 200px; }
</style>

<div class="scanner-wrap">
  <div class="scanner-icon" id="icon-idle">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><line x1="7" y1="12" x2="17" y2="12"/></svg>
  </div>
  <div class="scanner-status" id="status-title">Menunggu permintaan desktop…</div>
  <div class="scanner-desc" id="status-desc">Buka halaman ini di HP. Saat Anda menekan tombol kamera di desktop, permintaan akan muncul di sini.</div>
  
  <!-- Area Kamera (tersembunyi awalnya) -->
  <div id="reader"></div>
  <div class="cam-overlay" id="cam-controls">
    <div class="cam-btn-row">
      <button class="btn line" type="button" id="btn-cancel-scan">Batal</button>
    </div>
  </div>
</div>

<!-- Library html5-qrcode untuk pemindaian barcode -->
<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<?php owner_layout_end(['js/scan.js']); ?>