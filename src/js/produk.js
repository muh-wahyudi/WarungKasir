const POLL_MS = 30000;
const state = { q: '', stok: '', sort: 'terbaru', page: 1 };
const STATUS = { aman: ['Aman', ''], menipis: ['Menipis', 'w'], habis: ['Habis', 'e'] };
let data = null, busy = false, again = false;
const initials = (n) => esc(n.split(/\s+/).slice(0, 2).map((w) => w[0] || '').join('').toUpperCase());
const thumb = (x) => x.image ? `<img class="pimg" src="${esc(x.image)}" alt="" width="44" height="44" loading="lazy">` : `<span class="pimg ph" aria-hidden="true">${initials(x.name)}</span>`;

async function load() {
  if (busy) { again = true; return; }
  busy = true;
  try {
    const r = await fetch('api/produk-data.php?' + new URLSearchParams(state), { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });
    if (r.status === 401) { location.href = 'login.html'; return; }
    if (r.status === 403) { location.href = 'admin.php'; return; }
    if (!r.ok) throw new Error('HTTP ' + r.status);
    data = await r.json();
    state.page = data.page;
    $('#err').hidden = true;
    render();
  } catch (e) {
    if (!data) { $('#err').hidden = false; $('#err').textContent = 'Produk belum dapat dimuat. Periksa koneksi Anda lalu muat ulang halaman.'; }
  } finally {
    busy = false;
    if (again) { again = false; load(); }
  }
}

const atLimit = () => data.plan.max_products !== null && data.summary.total >= data.plan.max_products;

function render() {
  const s = data.summary, p = data.plan, mx = p.max_products;
  $('#stats').innerHTML = [
    ['Total Produk', num(s.total) + ' <small>produk</small>', mx === null ? 'Tanpa batas paket' : `Batas paket ${num(mx)}`, 'box', 'g'],
    ['Stok Aman', num(s.aman) + ' <small>produk</small>', 'Di atas batas stok', 'check', ''],
    ['Perlu Restok', num(s.menipis + s.habis) + ' <small>produk</small>', 'Menipis atau habis', 'alert', s.menipis + s.habis ? 'w' : ''],
    ['Stok Habis', num(s.habis) + ' <small>produk</small>', s.habis ? 'Segera diisi ulang' : 'Tidak ada yang habis', 'alert', s.habis ? 'e' : '']
  ].map(([l, v, n, i, t]) => `<article class="card stat"><div class="ic ${t}">${ic(i)}</div><div><p class="l">${l}</p><p class="v">${v}</p><p class="n">${n}</p></div></article>`).join('');

  const ratio = mx ? s.total / mx : 0;
  $('#cap-t').textContent = mx === null ? `Paket ${p.name} · ${num(s.total)} produk (tanpa batas)` : `Paket ${p.name} · ${num(s.total)} dari ${num(mx)} produk`;
  $('#cap-bar').style.width = Math.min(100, ratio * 100) + '%';
  $('#cap-bar').parentElement.classList.toggle('warn', ratio >= .9 && ratio < 1);
  $('#cap-bar').parentElement.classList.toggle('full', ratio >= 1);
  const note = $('#cap-note');
  note.hidden = ratio < .9;
  note.textContent = ratio >= 1 ? 'Batas produk paket Anda sudah tercapai. Hapus produk yang tidak dipakai atau upgrade paket untuk menambah produk.' : 'Kapasitas produk hampir penuh. Pertimbangkan upgrade paket sebelum batasnya tercapai.';

  const filtered = state.q || state.stok;
  $('#pt').innerHTML = data.items.length ? data.items.map((x, i) => `<tr><td data-label="Produk"><div class="pcell">${thumb(x)}<div><b>${esc(x.name)}</b>${x.sku ? `<br><small class="muted">Kode: ${esc(x.sku)}</small>` : ''}</div></div></td><td data-label="Harga">${rp(x.price)}</td><td data-label="Stok"><b>${num(x.stock)}</b></td><td data-label="Status"><span class="badge ${STATUS[x.status][1]}">${STATUS[x.status][0]}</span></td><td data-label="Batas Stok">${num(x.min_stock)}</td><td data-label="Aksi"><button class="link" type="button" data-act="edit" data-i="${i}">Ubah</button> <button class="link danger" type="button" data-act="del" data-i="${i}">Hapus</button></td></tr>`).join('')
    : s.total === 0
      ? '<tr><td colspan="6" class="empty">Belum ada produk. Tambahkan produk pertama Anda untuk mulai berjualan.<br><br><button class="btn" type="button" data-act="add">Tambah Produk</button></td></tr>'
      : `<tr><td colspan="6" class="empty">Tidak ada produk yang cocok dengan ${filtered ? 'pencarian atau filter ini' : 'tampilan ini'}.<br><br><button class="link" type="button" data-act="reset">Hapus pencarian dan filter</button></td></tr>`;

  const from = data.total ? (data.page - 1) * data.per + 1 : 0, to = from ? from + data.items.length - 1 : 0;
  $('#pg-info').textContent = data.total ? `Menampilkan ${from}–${to} dari ${num(data.total)} produk` : '';
  $('#pg-prev').disabled = data.page <= 1;
  $('#pg-next').disabled = data.page >= data.pages;

  const out = [];
  if (s.menipis + s.habis) out.push(`${num(s.menipis + s.habis)} produk perlu direstok${s.habis ? ` (${num(s.habis)} sudah habis)` : ''}`);
  if (mx && ratio >= .9) out.push(`Penggunaan produk ${num(s.total)} dari ${num(mx)} pada paket ${p.name}`);
  setAlerts(out);
}

const fld = (name, label, input, hint = '') => `<div class="fld"><label for="pf-${name}">${label}</label>${input}${hint ? `<small class="hint">${hint}</small>` : ''}<small class="ferr" id="e-${name}" role="alert"></small></div>`;

const emptyPhoto = (existing = null) => ({ blob: null, url: null, remove: false, existing });
let photo = emptyPhoto();
const MAX_BYTES = 2 * 1024 * 1024;

function loadImage(file) {
  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file), im = new Image();
    im.onload = () => { URL.revokeObjectURL(url); resolve(im); };
    im.onerror = () => { URL.revokeObjectURL(url); reject(new Error('Foto tidak dapat dibaca. Coba foto lain.')); };
    im.src = url;
  });
}

async function prepareImage(file) {
  if (!/^image\/(jpeg|png|webp)$/.test(file.type)) throw new Error('Format foto harus JPG, PNG, atau WebP.');
  if (file.size > 15 * 1024 * 1024) throw new Error('Foto terlalu besar. Maksimal 15 MB.');
  let src;
  try { src = await createImageBitmap(file, { imageOrientation: 'from-image' }); } catch { src = await loadImage(file); }
  const w = src.width || src.naturalWidth, h = src.height || src.naturalHeight;
  for (const [side, q] of [[800, .85], [800, .7], [600, .7], [480, .6]]) {
    const k = Math.min(1, side / Math.max(w, h)), c = document.createElement('canvas');
    c.width = Math.max(1, Math.round(w * k)); c.height = Math.max(1, Math.round(h * k));
    const g = c.getContext('2d');
    g.fillStyle = '#fff'; g.fillRect(0, 0, c.width, c.height);
    g.drawImage(src, 0, 0, c.width, c.height);
    const blob = await new Promise((r) => c.toBlob(r, 'image/jpeg', q));
    if (blob && blob.size <= MAX_BYTES) return blob;
  }
  throw new Error('Foto tidak dapat diperkecil. Coba foto lain.');
}

function renderPreview() {
  const src = photo.blob ? photo.url : (!photo.remove && photo.existing) ? photo.existing : null;
  $('#pv').innerHTML = src ? `<img src="${esc(src)}" alt="Pratinjau foto produk">` : '<span>Belum ada foto</span>';
  $('[data-act=unpick]').hidden = !src;
  $('[data-act=pick]').textContent = src ? 'Ganti Foto' : 'Pilih Foto';
}

async function uploadPhoto(id, blob) {
  const fd = new FormData();
  fd.append('id', String(id));
  fd.append('foto', blob, 'foto.jpg');
  const r = await fetch('api/produk-foto.php', { method: 'POST', body: fd, credentials: 'same-origin' });
  let j = {};
  try { j = await r.json(); } catch { }
  if (r.status === 401) location.href = 'login.html';
  return { ok: r.ok, message: j.message || 'Foto gagal diunggah.' };
}

dlg.addEventListener('close', () => { if (photo.url) URL.revokeObjectURL(photo.url); photo = emptyPhoto(); removeScanOverlay(); });
document.addEventListener('change', async (ev) => {
  if (ev.target.id !== 'pf-file') return;
  const f = ev.target.files[0];
  ev.target.value = '';
  if (!f) return;
  $('#e-foto').textContent = '';
  try {
    const blob = await prepareImage(f);
    if (photo.url) URL.revokeObjectURL(photo.url);
    photo.blob = blob; photo.url = URL.createObjectURL(blob); photo.remove = false;
    renderPreview();
  } catch (err) { $('#e-foto').textContent = err.message; }
});

let scanPollInterval = null;
let scanOverlay = null;

function removeScanOverlay() {
  if (scanPollInterval) clearInterval(scanPollInterval);
  scanPollInterval = null;
  if (scanOverlay) {
    scanOverlay.remove();
    scanOverlay = null;
  }
}

async function pollScanStatus(scanId) {
  if (!scanId) return;
  try {
    const r = await fetch('api/scan-request.php?id=' + scanId, { credentials: 'same-origin', cache: 'no-store' });
    if (!r.ok) return;
    const j = await r.json();

    if (j.status === 'completed') {
      removeScanOverlay();
      const inp = $('#pf-sku');
      if (inp) {
        inp.value = j.barcode;
        inp.dispatchEvent(new Event('input', { bubbles: true }));
      }
      toast('Barcode berhasil dipindai: ' + j.barcode);
    } else if (['rejected', 'expired', 'canceled'].includes(j.status)) {
      removeScanOverlay();
      toast('Pemindaian dibatalkan atau gagal.');
    }
  } catch (e) { }
}

async function startScan() {
  try {
    const { ok, body } = await postJSON('api/scan-request.php', { action: 'create' });

    if (!ok) {
      toast(body.message);
      return;
    }

    const scanId = body.id;

    scanOverlay = document.createElement('div');
    scanOverlay.id = 'scan-overlay';
    scanOverlay.style.cssText = 'position:fixed;inset:0;z-index:100;display:flex;align-items:center;justify-content:center;background:rgba(15,23,42,.5);';
    scanOverlay.innerHTML = `
      <div style="background:#fff;padding:24px;border-radius:18px;max-width:320px;text-align:center;box-shadow:0 20px 50px rgba(15,23,42,.25);">
        <div style="width:48px;height:48px;margin:0 auto 16px;border:4px solid var(--gl);border-top-color:var(--g);border-radius:50%;animation:spin 1s linear infinite;"></div>
        <p style="font-weight:600;margin-bottom:8px;">Permintaan terkirim!</p>
        <p style="color:var(--mu);font-size:.9rem;margin-bottom:16px;">Silakan buka aplikasi di HP Anda, setujui permintaan kamera, dan arahkan ke barcode.</p>
        <button class="btn line" type="button" style="width:100%;" id="btn-cancel-scan-overlay">Batalkan</button>
      </div>
      <style>@keyframes spin { to { transform: rotate(360deg); } }</style>
    `;
    document.body.appendChild(scanOverlay);

    scanOverlay.querySelector('#btn-cancel-scan-overlay').addEventListener('click', async () => {
      try { await postJSON('api/scan-request.php', { action: 'cancel', id: scanId }); } catch (e) { }
      removeScanOverlay();
      toast('Pemindaian dibatalkan.');
    });

    scanPollInterval = setInterval(() => pollScanStatus(scanId), 2000);
  } catch (e) {
    toast('Gagal meminta scan. Periksa koneksi.');
  }
}

function productForm(x) {
  const v = (k, d = '') => esc(x ? (x[k] ?? d) : d);
  photo = emptyPhoto(x ? x.image : null);
  modal(x ? 'Ubah Produk' : 'Tambah Produk', `<form id="pf" novalidate data-id="${x ? x.id : ''}">
    <div class="fld"><label>Foto produk <span class="opt">(opsional)</span></label>
      <div class="photo-row"><div class="pv" id="pv"></div>
        <div class="photo-btns"><button class="btn line" type="button" data-act="pick">Pilih Foto</button><button class="link danger" type="button" data-act="unpick" hidden>Hapus Foto</button><small class="hint">JPG, PNG, atau WebP. Diperkecil otomatis.</small></div>
        <input type="file" id="pf-file" accept="image/jpeg,image/png,image/webp" hidden></div>
      <small class="ferr" id="e-foto" role="alert"></small></div>
    ${fld('name', 'Nama produk <span class="req">*</span>', `<input id="pf-name" name="name" maxlength="120" autocomplete="off" required value="${v('name')}">`)}
    
    <div class="fld">
      <label for="pf-sku">Kode produk atau barcode <span class="req">*</span></label>
      <div style="display:flex;gap:8px;">
        <input id="pf-sku" name="sku" maxlength="40" autocomplete="off" required value="${v('sku')}" style="flex:1;">
        <button class="btn line" type="button" data-act="scan" title="Scan dengan kamera HP" style="padding:0 12px;"><svg class="i" aria-hidden="true"><use href="#i-camera"/></svg></button>
      </div>
      <small class="ferr" id="e-sku" role="alert"></small>
    </div>

    <div class="fg">
      ${fld('price', 'Harga jual (Rp) <span class="req">*</span>', `<input id="pf-price" name="price" type="text" inputmode="numeric" required value="${v('price') ? num(v('price')) : ''}">`)}
      ${fld('stock', 'Stok <span class="req">*</span>', `<input id="pf-stock" name="stock" type="number" inputmode="numeric" min="0" step="1" required value="${x ? v('stock') : '0'}">`)}
    </div>
    ${fld('min_stock', 'Batas stok menipis <span class="req">*</span>', `<input id="pf-min_stock" name="min_stock" type="number" inputmode="numeric" min="0" step="1" required value="${x ? v('min_stock') : '5'}">`, 'Produk ditandai "Menipis" saat stok sama dengan atau di bawah angka ini.')}
    <p class="ferr" id="e-form" role="alert"></p>
    <div class="modal-f"><button class="btn line" type="button" data-act="close">Batal</button><button class="btn" type="submit">${x ? 'Simpan Perubahan' : 'Tambah Produk'}</button></div>
  </form>`);
  renderPreview();
  $('#pf-name').focus();

  const priceInput = $('#pf-price');
  priceInput.addEventListener('input', function(e) {
    let value = this.value.replace(/\D/g, '');
    if (value) {
      this.value = Number(value).toLocaleString('id-ID');
    } else {
      this.value = '';
    }
  });
}

function showErrors(errs) {
  $$('#pf [aria-invalid]').forEach((el) => el.removeAttribute('aria-invalid'));
  $$('#pf .ferr').forEach((el) => { el.textContent = ''; });
  Object.entries(errs).forEach(([k, m]) => {
    const t = $('#e-' + k), i = $('#pf-' + k);
    if (t) t.textContent = m;
    if (i) i.setAttribute('aria-invalid', 'true');
  });
  const first = $('#pf [aria-invalid]'); if (first) first.focus();
}

function validate(d) {
  const e = {}, isInt = (s, min) => /^\d+$/.test(s) && Number(s) >= min;
  if (d.name.length < 2) e.name = 'Nama produk minimal 2 karakter.';
  if (d.sku.length === 0) e.sku = 'Kode produk wajib diisi.';
  if (!isInt(d.price, 1)) e.price = 'Isi harga dengan angka bulat, minimal Rp1.';
  if (!isInt(d.stock, 0)) e.stock = 'Isi stok dengan angka bulat, 0 atau lebih.';
  if (!isInt(d.min_stock, 0)) e.min_stock = 'Isi batas stok dengan angka bulat, 0 atau lebih.';
  return e;
}

function limitModal(msg) {
  modal('Batas paket tercapai', `<p>${esc(msg)}</p><div class="modal-f"><button class="btn line" type="button" data-act="close">Tutup</button><button class="btn" type="button" data-soon="Paket Langganan">Lihat Paket</button></div>`);
}

document.addEventListener('submit', async (ev) => {
  const form = ev.target.closest('#pf'); if (!form) return;
  ev.preventDefault();
  const btn = $('button[type=submit]', form);
  if (btn.disabled) return;
  const f = form.elements, d = { name: f.name.value.trim(), sku: f.sku.value.trim(), price: f.price.value.replace(/\D/g, ''), stock: f.stock.value.trim(), min_stock: f.min_stock.value.trim() };
  const errs = validate(d);
  if (Object.keys(errs).length) { showErrors(errs); return; }
  showErrors({});
  const id = form.dataset.id, label = btn.textContent;
  btn.disabled = true; btn.textContent = 'Menyimpan…';
  try {
    const { ok, status, body } = await postJSON('api/produk-data.php', { action: id ? 'update' : 'create', id: id ? Number(id) : undefined, name: d.name, sku: d.sku, price: Number(d.price), stock: Number(d.stock), min_stock: Number(d.min_stock) });
    if (ok) {
      const pid = id ? Number(id) : body.id;
      let warn = '';
      if (photo.blob) {
        btn.textContent = 'Mengunggah foto…';
        const up = await uploadPhoto(pid, photo.blob);
        if (!up.ok) warn = up.message;
      } else if (photo.remove && photo.existing) {
        const rm = await postJSON('api/produk-data.php', { action: 'remove_image', id: pid });
        if (!rm.ok) warn = rm.body.message || 'Foto gagal dihapus.';
      }
      closeModal();
      toast(warn ? `${body.message || 'Tersimpan.'} Foto belum tersimpan: ${warn}` : (body.message || 'Tersimpan.'));
      load();
      return;
    }
    if (status === 403 && body.code === 'limit') { limitModal(body.message); load(); return; }
    if (status === 404) { closeModal(); toast(body.message); load(); return; }
    if (body.errors) showErrors(body.errors); else $('#e-form').textContent = body.message || 'Gagal menyimpan. Coba lagi.';
  } catch {
    $('#e-form').textContent = 'Tidak dapat terhubung ke server. Periksa koneksi Anda.';
  }
  btn.disabled = false; btn.textContent = label;
});

function confirmDelete(x) {
  modal('Hapus produk?', `<p>Produk <b>${esc(x.name)}</b> akan dihapus permanen. Riwayat transaksi yang sudah tercatat tetap tersimpan.</p><p class="ferr" id="e-form" role="alert"></p>
    <div class="modal-f"><button class="btn line" type="button" data-act="close">Batal</button><button class="btn danger" type="button" data-act="confirm-del" data-id="${x.id}">Hapus</button></div>`);
}

document.addEventListener('click', async (e) => {
  const b = e.target.closest('[data-act]'); if (!b || !data) return;
  const act = b.dataset.act, x = data.items[+b.dataset.i];
  if (act === 'pick') { $('#pf-file').click(); return; }
  if (act === 'unpick') { if (photo.url) URL.revokeObjectURL(photo.url); photo.blob = null; photo.url = null; photo.remove = !!photo.existing; renderPreview(); return; }
  if (act === 'scan') { startScan(); return; }
  if (act === 'close') closeModal();
  else if (act === 'add') atLimit() ? limitModal(`Paket ${data.plan.name} maksimal ${data.plan.max_products} produk. Hapus produk yang tidak dipakai atau upgrade paket untuk menambah.`) : productForm();
  else if (act === 'edit' && x) productForm(x);
  else if (act === 'del' && x) confirmDelete(x);
  else if (act === 'reset') { state.q = ''; state.stok = ''; state.page = 1; $('#q').value = ''; $('#f-stok').value = ''; load(); }
  else if (act === 'confirm-del') {
    if (b.disabled) return;
    b.disabled = true; b.textContent = 'Menghapus…';
    try {
      const { ok, body } = await postJSON('api/produk-data.php', { action: 'delete', id: Number(b.dataset.id) });
      if (ok || (body.message || '').includes('tidak ditemukan')) { closeModal(); toast(ok ? 'Produk dihapus.' : body.message); load(); return; }
      $('#e-form').textContent = body.message || 'Gagal menghapus. Coba lagi.';
    } catch { $('#e-form').textContent = 'Tidak dapat terhubung ke server. Periksa koneksi Anda.'; }
    b.disabled = false; b.textContent = 'Hapus';
  }
});

let timer;
$('#q').addEventListener('input', (e) => { clearTimeout(timer); timer = setTimeout(() => { state.q = e.target.value.trim(); state.page = 1; load(); }, 300); });
$('#f-stok').addEventListener('change', (e) => { state.stok = e.target.value; state.page = 1; load(); });
$('#f-sort').addEventListener('change', (e) => { state.sort = e.target.value; state.page = 1; load(); });
$('#pg-prev').addEventListener('click', () => { if (state.page > 1) { state.page--; load(); } });
$('#pg-next').addEventListener('click', () => { if (data && state.page < data.pages) { state.page++; load(); } });

load();
setInterval(() => { if (document.visibilityState === 'visible' && !dlg.open) load(); }, POLL_MS);
document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible' && !dlg.open) load(); });