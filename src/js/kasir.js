// Halaman Kasir: pilih produk, keranjang, metode bayar, simpan transaksi.
// Di HP, keranjang tampil sebagai bottom sheet. Data dari api/kasir-data.php.
// Seluruh berkas dibungkus IIFE agar variabel lokal TIDAK bocor ke global
// dan tidak bentrok dengan global dari owner-common.js (mis. `scrim`).
(() => {
const state = { q: '', products: [], cart: new Map() };
let busy = false;

const initials = (n) => esc(n.split(/\s+/).slice(0, 2).map((w) => w[0] || '').join('').toUpperCase());
const thumb = (x) => x.image
  ? `<img class="pimg" src="${esc(x.image)}" alt="" width="40" height="40" loading="lazy">`
  : `<span class="pimg ph" aria-hidden="true">${initials(x.name)}</span>`;

/* ---------- Produk ---------- */
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

/* ---------- Keranjang (desktop + sheet) ---------- */
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
  const p = state.products.find((x) => x.id === id);
  if (!p) return;
  const cur = state.cart.get(id) || { id: p.id, name: p.name, price: p.price, stock: p.stock, qty: 0 };
  if (cur.qty >= p.stock) { toast('Stok tidak mencukupi untuk produk ini.'); return; }
  cur.qty += 1; state.cart.set(id, cur); renderCart();
}

function setQty(id, qty) {
  const it = state.cart.get(id); if (!it) return;
  if (!Number.isInteger(qty) || qty < 1) { state.cart.delete(id); renderCart(); return; }
  if (qty > it.stock) { toast(`Stok ${it.name} hanya ${num(it.stock)}.`); qty = it.stock; }
  it.qty = qty; state.cart.set(id, it); renderCart();
}

/* ---------- Metode bayar (dua panel) ---------- */
function methodOf(scope) { return scope.querySelector('input[type=radio]:checked')?.value || 'Tunai'; }
function updateChange() {
  const total = cartTotal();
  const m1 = methodOf(document.querySelector('.kasir-cart'));
  const c1 = Number($('#cash').value || 0);
  $('#cash-box').hidden = m1 !== 'Tunai';
  $('#change').textContent = m1 === 'Tunai' && c1 >= total && c1 > 0 ? rp(c1 - total) : rp(0);
  const m2 = methodOf($('#cart-sheet'));
  const c2 = Number($('#cash-sheet').value || 0);
  $('#cash-box-sheet').hidden = m2 !== 'Tunai';
  $('#change-sheet').textContent = m2 === 'Tunai' && c2 >= total && c2 > 0 ? rp(c2 - total) : rp(0);
}

/* ---------- Bottom sheet ---------- */
const sheetEl = $('#cart-sheet'), sheetScrim = $('#sheet-scrim');
function openSheet() { sheetEl.classList.add('open'); sheetScrim.classList.add('on'); }
function closeSheet() { sheetEl.classList.remove('open'); sheetScrim.classList.remove('on'); }
$('#cart-fab').addEventListener('click', openSheet);
$('#sheet-close').addEventListener('click', closeSheet);
sheetScrim.addEventListener('click', closeSheet);
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeSheet(); });

/* ---------- Simpan ---------- */
async function doCheckout(scope) {
  const btn = scope.querySelector('#checkout, #checkout-sheet');
  if (btn.disabled) return;
  const errBox = scope.querySelector('#e-form, #e-form-sheet');
  errBox.textContent = '';
  if (!state.cart.size) { errBox.textContent = 'Keranjang masih kosong.'; return; }
  const method = methodOf(scope);
  const cash = method === 'Tunai' ? Number(scope.querySelector('#cash, #cash-sheet').value || 0) : 0;
  const total = cartTotal();
  if (method === 'Tunai' && cash > 0 && cash < total) { errBox.textContent = 'Uang pelanggan kurang dari total.'; return; }
  btn.disabled = true; const label = btn.innerHTML; btn.textContent = 'Menyimpan…';
  try {
    const items = [...state.cart.values()].map((it) => ({ id: it.id, qty: it.qty }));
    const cust = scope.querySelector('#cust, #cust-sheet').value.trim();
    const { ok, status, body } = await postJSON('api/kasir-data.php', { method, customer: cust, items, cash });
    if (ok) { showReceipt(body); resetCart(); loadProducts(); closeSheet(); return; }
    if (status === 409 && Array.isArray(body.kurang)) {
      errBox.textContent = body.message || 'Stok tidak mencukupi.';
      body.kurang.forEach((k) => { const it = state.cart.get(k.id); if (it && k.alasan === 'stok kurang') it.qty = Math.max(1, it.qty - k.kurang); });
      renderCart(); return;
    }
    errBox.textContent = body.message || 'Gagal menyimpan transaksi. Coba lagi.';
  } catch { errBox.textContent = 'Tidak dapat terhubung ke server.'; }
  finally { btn.disabled = false; btn.innerHTML = label; }
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

/* ---------- Event ---------- */
let timer;
$('#q').addEventListener('input', (e) => { clearTimeout(timer); timer = setTimeout(() => { state.q = e.target.value.trim(); loadProducts(); }, 250); });
$('#reload').addEventListener('click', loadProducts);
$('#clear').addEventListener('click', () => doCheckoutClear(document.querySelector('.kasir-cart')));
$('#clear-sheet').addEventListener('click', () => doCheckoutClear($('#cart-sheet')));
$('#checkout').addEventListener('click', () => doCheckout(document.querySelector('.kasir-cart')));
$('#checkout-sheet').addEventListener('click', () => doCheckout($('#cart-sheet')));

document.querySelectorAll('input[name="pay"], input[name="pay-sheet"]').forEach((r) => r.addEventListener('change', updateChange));
['#cash','#cash-sheet'].forEach((s) => { const el = $(s); if (el) el.addEventListener('input', updateChange); });

document.addEventListener('click', (e) => {
  const add = e.target.closest('[data-add]'); if (add) { addToCart(Number(add.dataset.add)); return; }
  const inc = e.target.closest('[data-inc]'); if (inc) { const id = Number(inc.dataset.inc); setQty(id, (state.cart.get(id)?.qty || 0) + 1); return; }
  const dec = e.target.closest('[data-dec]'); if (dec) { const id = Number(dec.dataset.dec); setQty(id, (state.cart.get(id)?.qty || 0) - 1); return; }
  const del = e.target.closest('[data-del]'); if (del) { state.cart.delete(Number(del.dataset.del)); renderCart(); return; }
  const nw = e.target.closest('[data-act="new"]'); if (nw) closeModal();
});
document.addEventListener('change', (e) => {
  const q = e.target.closest('[data-qty]'); if (!q) return;
  setQty(Number(q.dataset.qty), parseInt(q.value, 10));
});

function doCheckoutClear(scope) {
  const err = scope.querySelector('#e-form, #e-form-sheet');
  if (state.cart.size) { state.cart.clear(); renderCart(); if (err) err.textContent = ''; }
}

/* ---------- Mulai ---------- */
renderCart();
loadProducts();
})();