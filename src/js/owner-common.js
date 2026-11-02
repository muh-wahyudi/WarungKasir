const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];
const num = (n) => Number(n).toLocaleString('id-ID');
const rp = (n) => 'Rp' + num(Math.round(n));
const short = (v) => (v >= 1e6 ? (v / 1e6).toFixed(1).replace('.', ',').replace(',0', '') + ' jt' : v >= 1000 ? (v / 1000).toFixed(1).replace('.', ',').replace(',0', '') + ' rb' : String(v));
const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const ic = (n) => `<svg class="i" aria-hidden="true"><use href="#i-${n}"/></svg>`;

const dlg = $('#dlg');
const modal = (title, html) => { $('#dlg-t').textContent = title; $('#dlg-b').innerHTML = html; if (!dlg.open) dlg.showModal(); };
const closeModal = () => { if (dlg.open) dlg.close(); };
dlg.addEventListener('click', (e) => { if (e.target === dlg) dlg.close(); });
$('#dlg-x').addEventListener('click', closeModal);
const soon = (name) => modal('Segera hadir', `<p>Halaman <b>${esc(name)}</b> belum dibuat. Saat ini halaman Dashboard dan Produk yang sudah berjalan.</p>`);

function toast(msg) {
  const t = $('#toast');
  t.textContent = msg;
  t.classList.add('show');
  clearTimeout(toast.id);
  toast.id = setTimeout(() => t.classList.remove('show'), 3200);
}

let alerts = [];
function setAlerts(list) { alerts = list; $('#bell-dot').hidden = !list.length; }
$('#bell').addEventListener('click', () => modal('Notifikasi', alerts.length ? alerts.map((a) => `<div class="dl"><span>${esc(a)}</span></div>`).join('') : '<p>Tidak ada notifikasi baru.</p>'));

async function postJSON(url, body) {
  const r = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'ngrok-skip-browser-warning': '1'
    },
    credentials: 'same-origin',
    body: JSON.stringify(body)
  });
  let j = {};
  try { j = await r.json(); } catch { }
  if (r.status === 401) location.href = 'login.html';
  return { ok: r.ok, status: r.status, body: j };
}

const side = $('#side'), scrim = $('#scrim'), dd = $('#dd'), who = $('#who');
const openSide = (on) => { side.classList.toggle('open', on); scrim.classList.toggle('on', on); $('#open-side').setAttribute('aria-expanded', on); };
const openDd = (on) => { dd.hidden = !on; who.setAttribute('aria-expanded', on); };
$('#open-side').addEventListener('click', () => openSide(true));
$('#close-side').addEventListener('click', () => openSide(false));
scrim.addEventListener('click', () => openSide(false));
who.addEventListener('click', (e) => { e.stopPropagation(); openDd(dd.hidden); });
document.addEventListener('click', (e) => {
  if (!dd.contains(e.target)) openDd(false);
  const a = e.target.closest('[data-soon]');
  if (a) { e.preventDefault(); openSide(false); openDd(false); soon(a.dataset.soon); }
});
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { openSide(false); openDd(false); } });
$('#logout').addEventListener('click', (e) => {
  e.preventDefault();
  fetch('api/logout.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'ngrok-skip-browser-warning': '1' }, body: '{}', credentials: 'same-origin' })
    .catch(() => {}).finally(() => { location.href = 'login.html'; });
});

(() => {
  const SCAN_POLL_MS = 2000;
  let scanPollInterval = null;
  let isScanning = false;
  let html5QrScanner = null;
  let activeScanId = null;
  let scannerContainer = null;

  async function checkPendingScan() {
    if (isScanning || document.visibilityState !== 'visible') return;
    const isEditingProduct = dlg.open && dlg.querySelector('#pf');
    if (isEditingProduct) return;
    try {
      const r = await fetch('api/scan-request.php?pending=1', {
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { 'ngrok-skip-browser-warning': '1' }
      });
      if (!r.ok) return;
      const j = await r.json();
      if (j.pending && j.pending.id !== activeScanId) {
        activeScanId = j.pending.id;
        showScanConfirm(activeScanId);
      }
    } catch (e) {}
  }

  function showScanConfirm(id) {
    if (dlg.open) {
      const oldHtml = $('#dlg-b').innerHTML;
      const oldTitle = $('#dlg-t').textContent;
      $('#dlg-b').innerHTML = `
        <p style="margin-bottom:20px">Desktop meminta akses kamera HP Anda untuk memindai barcode produk.</p>
        <div class="modal-f">
          <button class="btn line" type="button" id="btn-reject-scan">Tolak</button>
          <button class="btn" type="button" id="btn-approve-scan">Setujui & Buka Kamera</button>
        </div>
      `;
      $('#dlg-t').textContent = 'Permintaan Scan Barcode';
      
      $('#btn-approve-scan').addEventListener('click', () => { 
        $('#dlg-b').innerHTML = oldHtml; $('#dlg-t').textContent = oldTitle;
        closeModal(); 
        approveScan(id); 
      });
      $('#btn-reject-scan').addEventListener('click', () => { 
        $('#dlg-b').innerHTML = oldHtml; $('#dlg-t').textContent = oldTitle;
        closeModal(); 
        rejectScan(id); 
      });
    } else {
      modal('Permintaan Scan Barcode', `
        <p style="margin-bottom:20px">Desktop meminta akses kamera HP Anda untuk memindai barcode produk.</p>
        <div class="modal-f">
          <button class="btn line" type="button" id="btn-reject-scan">Tolak</button>
          <button class="btn" type="button" id="btn-approve-scan">Setujui & Buka Kamera</button>
        </div>
      `);
      $('#btn-approve-scan').addEventListener('click', () => { closeModal(); approveScan(id); });
      $('#btn-reject-scan').addEventListener('click', () => { closeModal(); rejectScan(id); });
    }
  }

  async function rejectScan(id) {
    try { await postJSON('api/scan-request.php', { action: 'reject', id }); } catch (e) {}
    activeScanId = null;
    toast('Permintaan scan ditolak.');
  }

  async function approveScan(id) {
    try {
      const { ok } = await postJSON('api/scan-request.php', { action: 'approve', id });
      if (!ok) return;
      isScanning = true;
      openScanner(id);
    } catch (e) {}
  }

  async function openScanner(id) {
    if (!window.Html5Qrcode) {
      const script = document.createElement('script');
      script.src = 'https://unpkg.com/html5-qrcode';
      script.onload = () => openScanner(id);
      script.onerror = () => { toast('Gagal memuat pemindai.'); isScanning = false; };
      document.head.appendChild(script);
      return;
    }

    const overlay = document.createElement('div');
    overlay.style.cssText = 'position:fixed;inset:0;z-index:200;background:rgba(15,23,42,.8);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:20px;';
    overlay.innerHTML = `
      <div style="background:#fff;padding:16px;border-radius:16px;width:100%;max-width:400px;text-align:center;">
        <div id="scan-reader" style="width:100%;border-radius:12px;overflow:hidden;margin-bottom:16px;"></div>
        <button class="btn line" type="button" style="width:100%;" id="btn-cancel-scan">Batal</button>
      </div>
    `;
    document.body.appendChild(overlay);
    scannerContainer = overlay;

    html5QrScanner = new Html5Qrcode("scan-reader");
    
    const scanConfig = {
      fps: 10,
      qrbox: { width: 280, height: 160 }
    };

    html5QrScanner.start(
      { facingMode: "environment" },
      scanConfig,
      async (decodedText) => {
        if (!isScanning) return;
        isScanning = false;
        
        if (html5QrScanner) {
          try { await html5QrScanner.stop(); } catch (e) {}
          html5QrScanner.clear();
          html5QrScanner = null;
        }
        if (scannerContainer) { scannerContainer.remove(); scannerContainer = null; }
        toast('Barcode terdeteksi: ' + decodedText);
        
        try {
          await postJSON('api/scan-request.php', { action: 'complete', id: id, barcode: decodedText });
        } catch (e) {}
        activeScanId = null;
      },
      () => {}
    ).catch((err) => {
      console.error('Camera error:', err);
      toast('Kamera tidak dapat dibuka.');
      isScanning = false;
      if (scannerContainer) { scannerContainer.remove(); scannerContainer = null; }
      activeScanId = null;
    });

    overlay.querySelector('#btn-cancel-scan').addEventListener('click', async () => {
      if (html5QrScanner) {
        try { await html5QrScanner.stop(); } catch (e) {}
        html5QrScanner.clear();
        html5QrScanner = null;
      }
      if (scannerContainer) { scannerContainer.remove(); scannerContainer = null; }
      isScanning = false;
      try { await postJSON('api/scan-request.php', { action: 'cancel', id: id }); } catch (e) {}
      activeScanId = null;
    });
  }

  checkPendingScan();
  scanPollInterval = setInterval(checkPendingScan, SCAN_POLL_MS);
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') checkPendingScan();
  });
})();