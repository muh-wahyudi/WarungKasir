// Logika pemindai barcode di HP. Berjalan bersama owner-common.js.
// Mendengarkan permintaan scan dari desktop, membuka kamera, lalu mengirim hasilnya.
(() => {
  const POLL_MS = 2000; // cek setiap 2 detik
  let currentRequestId = null;
  let scanner = null;
  let isScanning = false;
  let pollInterval = null;

  const $idle = $('#icon-idle');
  const $title = $('#status-title');
  const $desc = $('#status-desc');
  const $reader = $('#reader');
  const $controls = $('#cam-controls');

  // ----- Fungsi UI -----
  function setIdle(message = 'Menunggu permintaan desktop…', desc = 'Buka halaman ini di HP. Saat Anda menekan tombol kamera di desktop, permintaan akan muncul di sini.') {
    $title.textContent = message;
    $desc.textContent = desc;
    $idle.style.display = 'flex';
    $reader.style.display = 'none';
    $controls.classList.remove('show');
  }

  function setScanning(show) {
    if (show) {
      $idle.style.display = 'none';
      $reader.style.display = 'block';
      $controls.classList.add('show');
      $title.textContent = 'Arahkan kamera ke barcode';
      $desc.textContent = 'Posisikan barcode di dalam kotak.';
    } else {
      setIdle('Kamera ditutup.', 'Menunggu permintaan desktop…');
    }
  }

  function showError(msg) {
    setIdle('Terjadi kesalahan', msg);
  }

  // ----- Polling API -----
  async function poll() {
    if (isScanning) return; // jangan poll saat kamera sedang aktif
    try {
      const r = await fetch('api/scan-request.php?pending=1', { credentials: 'same-origin', cache: 'no-store' });
      if (r.status === 401) { location.href = 'login.html'; return; }
      if (r.status === 403) { location.href = 'admin.php'; return; }
      if (!r.ok) return;

      const j = await r.json();
      if (j.pending && j.pending.id !== currentRequestId) {
        currentRequestId = j.pending.id;
        // Hentikan polling sementara untuk konfirmasi
        if (pollInterval) clearInterval(pollInterval);
        showConfirmDialog(currentRequestId);
      }
    } catch (e) {
      // silent error, coba lagi di interval berikutnya
    }
  }

  // ----- Dialog Konfirmasi -----
  function showConfirmDialog(id) {
    modal('Permintaan Scan Barcode', `
      <p style="margin-bottom:20px">Desktop meminta akses kamera HP Anda untuk memindai barcode produk.</p>
      <p class="sample" style="margin-top:0;margin-bottom:20px">Setujui untuk membuka kamera, atau tolak jika Anda tidak merasa meminta.</p>
      <div class="modal-f">
        <button class="btn line" type="button" id="btn-reject">Tolak</button>
        <button class="btn" type="button" id="btn-approve">Setujui & Buka Kamera</button>
      </div>
    `);

    $('#btn-approve').addEventListener('click', () => { closeModal(); approveScan(id); });
    $('#btn-reject').addEventListener('click', () => { closeModal(); rejectScan(id); });
  }

  async function rejectScan(id) {
    setIdle('Permintaan ditolak.', 'Menunggu permintaan desktop…');
    try {
      await postJSON('api/scan-request.php', { action: 'reject', id });
    } catch (e) {}
    currentRequestId = null;
    startPolling();
  }

  async function approveScan(id) {
    try {
      const { ok, body } = await postJSON('api/scan-request.php', { action: 'approve', id });
      if (!ok) { showError(body.message || 'Gagal menyetujui.'); startPolling(); return; }
      
      isScanning = true;
      setScanning(true);
      startScanner(id);
    } catch (e) {
      showError('Tidak dapat terhubung ke server.');
      startPolling();
    }
  }

  // ----- Scanner Kamera -----
  function startScanner(id) {
    if (!window.Html5Qrcode) {
      showError('Pustaka pemindai tidak termuat. Periksa koneksi internet.');
      return;
    }

    scanner = new Html5Qrcode("reader");
    const config = {
      fps: 10,
      qrbox: { width: 280, height: 160 }, // kotak persegi panjang untuk barcode
      supportedScanTypes: [Html5QrcodeScanType.SCAN_TYPE_CAMERA]
    };

    scanner.start(
      { facingMode: "environment" },
      config,
      onScanSuccess,
      (errorMessage) => { /* abaikan error per frame (biasanya karena belum ada barcode) */ }
    ).catch((err) => {
      showError('Kamera tidak dapat dibuka. Pastikan izin kamera diberikan.');
      isScanning = false;
      startPolling();
    });
  }

  async function onScanSuccess(decodedText) {
    if (!isScanning) return; // cegah duplikat
    isScanning = false;

    // Hentikan scanner & matikan kamera segera
    if (scanner) {
      try { await scanner.stop(); } catch (e) {}
      try { scanner.clear(); } catch (e) {}
      scanner = null;
    }

    setScanning(false);
    setIdle('Mengirim barcode…', 'Mohon tunggu.');

    try {
      const { ok, body } = await postJSON('api/scan-request.php', { action: 'complete', id: currentRequestId, barcode: decodedText });
      if (ok) {
        setIdle('Barcode berhasil dikirim!', `Kode: ${decodedText}. Desktop akan menerima data ini.`);
        // Reset setelah 3 detik
        setTimeout(() => { currentRequestId = null; startPolling(); }, 3000);
      } else {
        showError(body.message || 'Gagal mengirim barcode.');
        startPolling();
      }
    } catch (e) {
      showError('Tidak dapat terhubung ke server.');
      startPolling();
    }
  }

  // ----- Batal Scan dari HP -----
  $('#btn-cancel-scan').addEventListener('click', async () => {
    if (isScanning) {
      isScanning = false;
      if (scanner) {
        try { await scanner.stop(); } catch (e) {}
        try { scanner.clear(); } catch (e) {}
        scanner = null;
      }
      setScanning(false);
    }
    if (currentRequestId) {
      try { await postJSON('api/scan-request.php', { action: 'cancel', id: currentRequestId }); } catch (e) {}
    }
    currentRequestId = null;
    setIdle('Pemindaian dibatalkan.', 'Menunggu permintaan desktop…');
    startPolling();
  });

  // ----- Lifecycle -----
  function startPolling() {
    if (pollInterval) clearInterval(pollInterval);
    poll(); // langsung sekali
    pollInterval = setInterval(() => {
      if (document.visibilityState === 'visible' && !isScanning) poll();
    }, POLL_MS);
  }

  // Jalankan saat halaman siap
  startPolling();

  // Tangani jika halaman ditinggalkan saat scan
  window.addEventListener('beforeunload', () => {
    if (scanner) { try { scanner.stop(); } catch (e) {} }
  });
})();