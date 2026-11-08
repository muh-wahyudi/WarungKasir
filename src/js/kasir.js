(() => {
const state = { q: '', products: [], cart: new Map() };
let busy = false;
let scanInterval = null;
let currentScanId = null;
const extraProducts = new Map();   // produk hasil scan yang tidak ada di daftar 20 produk yang tampil
const scanDone = new Map();   // barcode -> jumlah scan yang sudah dimasukkan ke keranjang

function stopScanSession() {
  if (scanInterval) clearInterval(scanInterval);
  scanInterval = null;
  currentScanId = null;
  scanDone.clear();
  extraProducts.clear();
}

const initials = (n) => esc(n.split(/\s+/).slice(0, 2).map((w) => w[0] || '').join('').toUpperCase());
const thumb = (x) => x.image
  ? `<img class="pimg" src="${esc(x.image)}" alt="" width="40" height="40" loading="lazy">`
  : `<span class="pimg ph" aria-hidden="true">${initials(x.name)}</span>`;

const formatNumber = (val) => {
  if (!val) return '';
  return val.toString().replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
};

async function loadProducts() {
  if (busy) return;
  busy = true;
  $('#err').hidden = true;
  try {
    const r = await fetch('api/kasir-data.php?' + new URLSearchParams({ q: state.q }), {
      credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' }
    });
    if (r.status === 401) { location.href = 'login.html'; return; }
    if (r.status === 403) { location.href = 'admin.php'; return; }
    if (!r.ok) throw new Error('HTTP ' + r.status);
    const j = await r.json();
    state.products = j.items || [];
    renderProducts();
  } catch (e) {
    $('#err').hidden = false;
    $('#err').textContent = 'Produk belum dapat dimuat. Periksa koneksi lalu klik "Muat ulang".';
  } finally { busy = false; }
}

function renderProducts() {
  const list = $('#prod-list');
  if (!state.products.length) {
    list.innerHTML = state.q
      ? '<p class="empty">Tidak ada produk yang cocok. Coba kata kunci lain.</p>'
      : '<p class="empty">Belum ada produk dengan stok tersedia. Tambahkan produk di menu Produk.</p>';
    return;
  }
  list.innerHTML = state.products.map((p) => {
    const inCart = state.cart.get(p.id)?.qty || 0;
    const full = (p.stock - inCart) <= 0;
    return `<button class="prod" type="button" data-add="${p.id}" ${full ? 'disabled' : ''}>
      ${thumb(p)}
      <div class="grow"><b>${esc(p.name)}</b>
        <small>${p.sku ? 'Kode: ' + esc(p.sku) + ' · ' : ''}Stok ${num(p.stock)}</small>
      </div>
      <div class="val">${rp(p.price)}</div>
    </button>`;
  }).join('');
}

const cartQty = () => { let n = 0; for (const it of state.cart.values()) n += it.qty; return n; };
const cartTotal = () => { let t = 0; for (const it of state.cart.values()) t += it.qty * it.price; return t; };

function cartRows() {
  return [...state.cart.values()].map((it) => `
    <div class="cart-item">
      <div class="grow"><b>${esc(it.name)}</b><small>${rp(it.price)} × ${num(it.qty)} = ${rp(it.price * it.qty)}</small></div>
      <div class="qty" role="group" aria-label="Jumlah ${esc(it.name)}">
        <button type="button" data-dec="${it.id}" aria-label="Kurangi">−</button>
        <input type="number" min="1" max="${it.stock}" step="1" value="${it.qty}" data-qty="${it.id}" aria-label="Jumlah">
        <button type="button" data-inc="${it.id}" aria-label="Tambah">+</button>
      </div>
      <button class="link danger" type="button" data-del="${it.id}" aria-label="Hapus">Hapus</button>
    </div>`).join('');
}

function renderCart() {
  const empty = '<p class="empty">Keranjang kosong. Pilih produk.</p>';
  const rows = state.cart.size ? cartRows() : empty;
  $('#cart-items').innerHTML = rows;
  $('#cart-items-sheet').innerHTML = state.cart.size ? cartRows() : '<p class="empty">Keranjang kosong.</p>';
  const t = cartTotal(), q = cartQty();
  $('#cart-count').textContent = `${num(q)} item`;
  $('#total').textContent = rp(t);
  $('#total-sheet').textContent = rp(t);
  $('#fab-count').textContent = num(q);
  $('#fab-total').textContent = rp(t);
  updateChange();
  renderProducts();
}

function addToCart(id) {
  const p = state.products.find((x) => x.id === id) || extraProducts.get(id);
  if (!p) return;
  const cur = state.cart.get(id) || { id: p.id, name: p.name, price: p.price, stock: p.stock, qty: 0 };
  if (cur.qty >= p.stock) { toast('Stok tidak mencukupi untuk produk ini.'); return; }
  cur.qty += 1; state.cart.set(id, cur); renderCart();
}

// Cari produk berdasarkan kode: dari daftar yang tampil, lalu ke server (daftar hanya memuat 20 produk).
async function findProductBySku(code) {
  let p = state.products.find((x) => x.sku === code) || [...extraProducts.values()].find((x) => x.sku === code);
  if (p) return p;
  try {
    const r = await fetch('api/kasir-data.php?' + new URLSearchParams({ q: code }), {
      credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' }
    });
    if (!r.ok) return null;
    const j = await r.json();
    p = (j.items || []).find((x) => x.sku === code);
    if (p) extraProducts.set(p.id, p);
    return p || null;
  } catch (e) { return null; }
}

function setQty(id, qty) {
  const it = state.cart.get(id); if (!it) return;
  if (!Number.isInteger(qty) || qty < 1) { state.cart.delete(id); renderCart(); return; }
  if (qty > it.stock) { toast(`Stok ${it.name} hanya ${num(it.stock)}.`); qty = it.stock; }
  it.qty = qty; state.cart.set(id, it); renderCart();
}

function methodOf(scope) { return scope.querySelector('input[type=radio]:checked')?.value || 'Tunai'; }
function updateChange() {
  const total = cartTotal();
  const panels = [
    { scope: document.querySelector('.kasir-cart'), cash: '#cash', box: '#cash-box', change: '#change', label: '#change-label', btn: '#checkout' },
    { scope: $('#cart-sheet'), cash: '#cash-sheet', box: '#cash-box-sheet', change: '#change-sheet', label: '#change-label-sheet', btn: '#checkout-sheet' },
  ];
  for (const p of panels) {
    const tunai = methodOf(p.scope) === 'Tunai';
    const cash = Number(String($(p.cash).value).replace(/\D/g, '') || 0);
    const kurang = tunai && cash > 0 && cash < total;
    $(p.box).hidden = !tunai;
    $(p.label).textContent = kurang ? 'Kurang' : 'Kembalian';
    $(p.change).textContent = rp(kurang ? total - cash : (tunai && cash >= total && cash > 0 ? cash - total : 0));
    // Tombol Bayar aktif hanya jika ada barang dan, untuk Tunai, uang pelanggan sudah diisi dan tidak kurang dari total
    const ready = total > 0 && (!tunai || cash >= total);
    const btn = $(p.btn);
    if (!btn.dataset.busy) btn.disabled = !ready;
  }
}

const sheetEl = $('#cart-sheet'), sheetScrim = $('#sheet-scrim');
function openSheet() { sheetEl.classList.add('open'); sheetScrim.classList.add('on'); }
function closeSheet() { sheetEl.classList.remove('open'); sheetScrim.classList.remove('on'); }
$('#cart-fab').addEventListener('click', openSheet);
$('#sheet-close').addEventListener('click', closeSheet);
sheetScrim.addEventListener('click', closeSheet);
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeSheet(); });

let pollingInterval = null;
let payOrderId = null;   // pesanan QRIS yang sedang menunggu pembayaran

async function doCheckout(scope) {
  const btn = scope.querySelector('#checkout, #checkout-sheet');
  if (btn.disabled) return;
  const errBox = scope.querySelector('#e-form, #e-form-sheet');
  errBox.textContent = '';
  if (!state.cart.size) { errBox.textContent = 'Keranjang masih kosong.'; return; }
  
  const method = methodOf(scope);
  const cashRaw = scope.querySelector('#cash, #cash-sheet').value || '0';
  const cash = method === 'Tunai' ? Number(String(cashRaw).replace(/\D/g, '')) : 0;
  const total = cartTotal();
  
  if (method === 'Tunai' && cash < total) { errBox.textContent = 'Uang pelanggan wajib diisi dan tidak boleh kurang dari total.'; return; }
  
  btn.disabled = true; btn.dataset.busy = '1'; const label = btn.innerHTML; btn.textContent = 'Menyimpan…';
  try {
    const items = [...state.cart.values()].map((it) => ({ id: it.id, qty: it.qty }));
    const cust = scope.querySelector('#cust, #cust-sheet').value.trim();
    const { ok, status, body } = await postJSON('api/kasir-data.php', { action: 'checkout', method, customer: cust, items, cash });
    
    if (ok) { 
      // Transaksi tersimpan: tandai sesi scan 'completed' -> kamera HP tertutup otomatis
      if (currentScanId) {
        try {
          await postJSON('api/scan-request.php', { action: 'complete', id: currentScanId });
        } catch (e) {}
        stopScanSession();
      }

      if (method === 'QRIS') {
        showPaymentModal(body.midtrans_order_id, total);
      } else {
        showReceipt(body); 
        resetCart(); 
        loadProducts(); 
        closeSheet(); 
      }
      return; 
    }
    
    if (status === 409 && Array.isArray(body.kurang)) {
      errBox.textContent = body.message || 'Stok tidak mencukupi.';
      body.kurang.forEach((k) => { const it = state.cart.get(k.id); if (it && k.alasan === 'stok kurang') it.qty = Math.max(1, it.qty - k.kurang); });
      renderCart(); return;
    }
    errBox.textContent = body.message || 'Gagal menyimpan transaksi. Coba lagi.';
  } catch { errBox.textContent = 'Tidak dapat terhubung ke server.'; }
  finally { delete btn.dataset.busy; btn.innerHTML = label; updateChange(); }
}

const paymentModalOpen = () => !!$('#payment-container');

function stopPayment() {
  if (pollingInterval) clearInterval(pollingInterval);
  pollingInterval = null;
  payOrderId = null;
}

function finishQris(code, total) {
  stopPayment();
  closeModal();
  showReceipt({ code, total, method: 'QRIS', status: 'Selesai', customer: 'Pelanggan Umum', items: [...state.cart.values()] });
  resetCart();
  loadProducts();
}

// Batal: transaksi QRIS yang menunggu dibatalkan di server (stok dikembalikan); keranjang tetap ada untuk dicoba lagi
async function cancelPayment(btn) {
  const orderId = payOrderId;
  if (!orderId) { closeModal(); return; }
  btn.disabled = true;
  btn.textContent = 'Membatalkan…';
  try {
    const { body } = await postJSON('api/kasir-data.php', { action: 'cancel_payment', order_id: orderId });
    if (body.status === 'Selesai') { finishQris(body.code, cartTotal()); toast('Pembayaran ternyata sudah diterima. Transaksi selesai.'); return; }
    if (body.status === 'Dibatalkan') {
      stopPayment();
      closeModal();
      toast('Pembayaran QRIS dibatalkan. Keranjang dipertahankan.');
      loadProducts();
      return;
    }
    toast(body.message || 'Gagal membatalkan. Coba lagi.');
  } catch (e) {
    toast('Tidak dapat terhubung ke server.');
  }
  btn.disabled = false;
  btn.textContent = 'Batal';
}

function showPaymentModal(orderId, total) {
  payOrderId = orderId;
  modal('Menunggu Pembayaran QRIS', `
    <div style="text-align:center; padding:20px;">
      <p style="margin-bottom:16px;">Silakan pelanggan scan QR code di bawah ini:</p>
      <div id="payment-container" style="min-height: 200px; display:flex; align-items:center; justify-content:center; background:var(--bg); border-radius:12px;">
        <span style="color:var(--mu);">Memuat QR...</span>
      </div>
      <p style="margin-top:16px; font-size:0.9rem; color:var(--mu);">Total: <b>${rp(total)}</b></p>
      <p id="payment-status" style="margin-top:8px; font-weight:600; color:var(--warn);">Menunggu pembayaran...</p>
    </div>
    <div class="modal-f">
      <button class="btn line" type="button" data-act="cancel-pay">Batal</button>
    </div>
  `);

  fetch('api/kasir-data.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',
    body: JSON.stringify({ action: 'create_payment', order_id: orderId, total: total })
  }).then(r => r.json()).then(j => {
    const box = $('#payment-container');
    if (!box) return;
    if (j.qr_url) {
      box.innerHTML = `<img src="${esc(j.qr_url)}" style="max-width:100%; border-radius:8px;" alt="QRIS">`;
    } else {
      box.innerHTML = `<p style="color:var(--err);">Gagal memuat QR. Tekan Batal lalu coba lagi.</p>`;
    }
  }).catch(() => {
    const box = $('#payment-container');
    if (box) box.innerHTML = `<p style="color:var(--err);">Gagal memuat QR. Tekan Batal lalu coba lagi.</p>`;
  });

  if (pollingInterval) clearInterval(pollingInterval);
  pollingInterval = setInterval(async () => {
    try {
      const r = await fetch('api/kasir-data.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ action: 'check_status', order_id: orderId })
      });
      const j = await r.json();
      if (orderId !== payOrderId) return;   // pembayaran ini sudah dibatalkan atau diganti

      if (j.status === 'Selesai') {
        finishQris(j.code, total);
      } else if (j.status === 'Dibatalkan') {   // QR kedaluwarsa atau dibatalkan di Midtrans; stok sudah dikembalikan
        const open = paymentModalOpen();
        stopPayment();
        if (open) closeModal();
        toast('Pembayaran QRIS kedaluwarsa atau dibatalkan. Keranjang dipertahankan, silakan coba lagi.');
        loadProducts();
      }
    } catch (e) { /* gangguan sesaat: coba lagi pada putaran berikutnya */ }
  }, 2000);
}

function resetCart() {
  state.cart.clear();
  ['#cust','#cust-sheet','#cash','#cash-sheet'].forEach((s) => { const el = $(s); if (el) el.value = ''; });
  ['#e-form','#e-form-sheet'].forEach((s) => { const el = $(s); if (el) el.textContent = ''; });
  renderCart();
}

function showReceipt(r) {
  const rows = r.items.map((it) => `<div class="dl"><span>${esc(it.name)} × ${num(it.qty)}</span><span>${rp(it.price * it.qty)}</span></div>`).join('');
  const cashRow = r.method === 'Tunai' && r.cash !== null
    ? `<div class="dl"><span>Uang pelanggan</span><span>${rp(r.cash)}</span></div><div class="dl"><span>Kembalian</span><b>${rp(r.change)}</b></div>` : '';
  modal('Transaksi Tersimpan', `
    <div class="dl"><span>Kode</span><b>${esc(r.code)}</b></div>
    <div class="dl"><span>Pelanggan</span><span>${esc(r.customer)}</span></div>
    ${rows}<div class="dl"><span>Total</span><b>${rp(r.total)}</b></div>
    <div class="dl"><span>Metode</span><span>${esc(r.method)}</span></div>
    <div class="dl"><span>Status</span><span class="badge ${r.status === 'Selesai' ? '' : 'w'}">${esc(r.status)}</span></div>${cashRow}
    <div class="modal-f"><button class="btn" type="button" data-act="new">Transaksi Baru</button></div>`);
}

let timer;
$('#q').addEventListener('input', (e) => { clearTimeout(timer); timer = setTimeout(() => { state.q = e.target.value.trim(); loadProducts(); }, 250); });
$('#reload').addEventListener('click', loadProducts);
$('#clear').addEventListener('click', () => doCheckoutClear(document.querySelector('.kasir-cart')));
$('#clear-sheet').addEventListener('click', () => doCheckoutClear($('#cart-sheet')));
$('#checkout').addEventListener('click', () => doCheckout(document.querySelector('.kasir-cart')));
$('#checkout-sheet').addEventListener('click', () => doCheckout($('#cart-sheet')));

document.querySelectorAll('input[name="pay"], input[name="pay-sheet"]').forEach((r) => r.addEventListener('change', updateChange));

const cashEl = $('#cash');
if (cashEl) {
  cashEl.addEventListener('input', function() {
    const raw = this.value.replace(/\D/g, '');
    this.value = formatNumber(raw);
    updateChange();
  });
}

const cashSheetEl = $('#cash-sheet');
if (cashSheetEl) {
  cashSheetEl.addEventListener('input', function() {
    const raw = this.value.replace(/\D/g, '');
    this.value = formatNumber(raw);
    updateChange();
  });
}

document.addEventListener('click', (e) => {
  const add = e.target.closest('[data-add]'); if (add) { addToCart(Number(add.dataset.add)); return; }
  const inc = e.target.closest('[data-inc]'); if (inc) { const id = Number(inc.dataset.inc); setQty(id, (state.cart.get(id)?.qty || 0) + 1); return; }
  const dec = e.target.closest('[data-dec]'); if (dec) { const id = Number(dec.dataset.dec); setQty(id, (state.cart.get(id)?.qty || 0) - 1); return; }
  const del = e.target.closest('[data-del]'); if (del) { state.cart.delete(Number(del.dataset.del)); renderCart(); return; }
  const nw = e.target.closest('[data-act="new"]'); if (nw) { closeModal(); }
  const cp = e.target.closest('[data-act="cancel-pay"]'); if (cp) { cancelPayment(cp); return; }
  const closeBtn = e.target.closest('[data-act="close"]'); if (closeBtn) { closeModal(); }
});
document.addEventListener('change', (e) => {
  const q = e.target.closest('[data-qty]'); if (!q) return;
  setQty(Number(q.dataset.qty), parseInt(q.value, 10));
});

function doCheckoutClear(scope) {
  const err = scope.querySelector('#e-form, #e-form-sheet');
  if (state.cart.size) { state.cart.clear(); renderCart(); if (err) err.textContent = ''; }
}

$('#btn-scan-kasir').addEventListener('click', async () => {
  if (currentScanId) {
    toast('Sesi scan sudah berjalan.');
    return;
  }
  
  try {
    const { ok, body } = await postJSON('api/scan-request.php', { action: 'create', mode: 'kasir' });
    if (!ok) { toast(body.message); return; }
    
    currentScanId = body.id;
    scanDone.clear();
    
    scanInterval = setInterval(async () => {
      try {
        const r = await fetch('api/scan-request.php?check_barcodes=1&id=' + currentScanId, { credentials: 'same-origin', cache: 'no-store' });
        const j = await r.json();

        // Sesi dibatalkan/habis dari sisi HP atau server -> hentikan polling di desktop
        if (['canceled', 'expired', 'completed'].includes(j.status)) {
          stopScanSession();
          toast('Sesi scan berakhir.');
          return;
        }

        // Tiap produk punya jumlah scan; tambahkan ke keranjang sebanyak selisih yang belum diproses
        const fresh = [];
        for (const { code, n } of (j.scans || [])) {
          const done = scanDone.get(code) || 0;
          if (n <= done) continue;
          scanDone.set(code, n);   // tandai dulu agar polling berikutnya tidak memproses dua kali
          fresh.push({ code, add: n - done });
        }
        for (const { code, add } of fresh) {
          const p = await findProductBySku(code);
          if (!p) { toast('Produk dengan barcode ' + code + ' tidak ditemukan.'); continue; }
          for (let i = 0; i < add; i++) addToCart(p.id);
          toast('Produk ditambahkan: ' + p.name + (add > 1 ? ' ×' + add : ''));
        }
      } catch (e) {}
    }, 2000);
    
    toast('Menunggu scan dari HP...');
    
  } catch (e) { toast('Gagal meminta scan.'); }
});

renderCart();
loadProducts();
})();