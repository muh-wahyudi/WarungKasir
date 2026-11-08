// Halaman Pengaturan. Data dan penyimpanan lewat api/pengaturan-data.php.
// Fungsi bersama ($, rp, esc, postJSON, toast, dll.) ada di owner-common.js.
(() => {
const TABS = ['profil', 'usaha', 'keamanan', 'paket'];
const BOX = { profil: '#v-profil', usaha: '#v-usaha', keamanan: '#v-keamanan', paket: '#v-paket' };
let data = null;
let tab = 'profil';
let payInterval = null;
let payOrderId = null;

const tabFromHash = () => { const t = location.hash.replace('#', ''); return TABS.includes(t) ? t : 'profil'; };
const initials = (n) => n.trim().split(/\s+/).slice(0, 2).map((w) => (w[0] || '').toUpperCase()).join('');

/* ---------- Pengambilan data ---------- */
async function load() {
  try {
    const r = await fetch('api/pengaturan-data.php', { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });
    if (r.status === 401) { location.href = 'login.html'; return; }
    if (r.status === 403) { location.href = 'admin.php'; return; }
    if (!r.ok) throw new Error('HTTP ' + r.status);
    data = await r.json();
    $('#err').hidden = true;
    fill();
  } catch (e) {
    $('#err').hidden = false;
    $('#err').textContent = 'Data belum dapat dimuat. Periksa koneksi Anda lalu muat ulang halaman.';
  }
}

function fill() {
  const p = data.profile, b = data.business;
  $('#p-name').value = p.full_name;
  $('#p-email').value = p.email;
  $('#p-phone').value = p.phone;
  $('#p-since').textContent = p.member_since ? 'Bergabung sejak ' + p.member_since : '';
  $('#b-name').value = b.business_name;
  $('#b-address').value = b.alamat || '';
  renderPlan();
}

/* ---------- Paket ---------- */
function features(p) {
  const hist = p.history_days == null ? 'Riwayat transaksi tidak terbatas' : p.history_days === 365 ? 'Riwayat transaksi satu tahun' : `Riwayat transaksi ${num(p.history_days)} hari`;
  const f = [p.max_products == null ? 'Produk tidak terbatas' : `Maksimal ${num(p.max_products)} produk`, hist, `${p.max_accounts ?? '-'} akun pengguna`];
  if (p.price > 0) f.push('Ekspor laporan ke Excel');
  return f;
}

function renderPlan() {
  const p = data.plan, mx = p.max_products, used = p.used_products, ratio = mx ? used / mx : 0;

  $('#pk-cur').innerHTML = `
    <div class="plan-h"><h2>Paket Anda</h2><span class="badge">${esc(p.name)}</span></div>
    <div class="dl"><span>Harga</span><b>${p.price ? rp(p.price) + '/bulan' : 'Gratis'}</b></div>
    ${p.started_at ? `<div class="dl"><span>Aktif sejak</span><span>${esc(p.started_at)}</span></div>` : ''}
    ${p.ends_at ? `<div class="dl"><span>Berlaku sampai</span><span>${esc(p.ends_at)}</span></div>` : ''}
    <div class="use"><span>Penggunaan produk</span><span>${mx == null ? `${num(used)} produk (tanpa batas)` : `${num(used)} dari ${num(mx)} produk`}</span></div>
    <div class="meter g${ratio >= 1 ? ' full' : ratio >= .9 ? ' warn' : ''}"><i style="width:${Math.min(100, ratio * 100)}%"></i></div>
    <ul>${features(p).map((x) => `<li>${esc(x)}</li>`).join('')}</ul>`;

  $('#pk-plans').innerHTML = data.plans.map((x) => {
    const cur = x.code === p.code;
    let btn = '';
    if (cur) {
      btn = '<span class="badge">Paket Anda</span>';
    } else if (x.price === 0) {
      btn = '<button class="btn line" type="button" disabled>Gratis</button>';
    } else {
      btn = `<button class="btn" type="button" data-pay="${esc(x.code)}" data-name="${esc(x.name)}" data-price="${x.price}">Upgrade ke ${esc(x.name)}</button>`;
    }
    return `<article class="card pl${cur ? ' cur' : ''}">
      <h3>${esc(x.name)}</h3>
      <div class="price">${x.price ? rp(x.price) + '<small>/bulan</small>' : 'Rp0'}</div>
      <ul>${features(x).map((f) => `<li>${esc(f)}</li>`).join('')}</ul>
      ${btn}
    </article>`;
  }).join('');

  const sp = $('#side-plan'); if (sp) sp.textContent = p.name;
}

/* ---------- Checkout Langganan ---------- */
function stopPay() {
  if (payInterval) clearInterval(payInterval);
  payInterval = null;
  payOrderId = null;
}

function startSubscription(planCode, planName, price) {
  modal('Konfirmasi Langganan', `
    <div class="dl"><span>Paket</span><b>${esc(planName)}</b></div>
    <div class="dl"><span>Durasi</span><span>30 Hari</span></div>
    <div class="dl"><span>Total</span><b style="color:var(--gd)">${rp(price)}</b></div>
    <p class="sub-n" style="margin-top:16px">Pilih metode pembayaran QRIS. Setelah pembayaran berhasil, paket akan aktif secara otomatis.</p>
    <p class="ferr" id="e-pay" style="display:none"></p>
    <div class="modal-f">
      <button class="btn line" type="button" data-act="close">Batal</button>
      <button class="btn" type="button" id="btn-do-pay" data-code="${planCode}" data-price="${price}">Bayar dengan QRIS</button>
    </div>
  `);
  $('#btn-do-pay').focus();
}

async function processPayment(planCode, price, btn) {
  const err = $('#e-pay');
  err.style.display = 'none';
  const lbl = btn.textContent;
  btn.disabled = true; btn.textContent = 'Memproses…';

  try {
    const { ok, body } = await postJSON('api/pengaturan-data.php', { action: 'checkout', plan_code: planCode });
    if (!ok) {
      err.textContent = body.message || 'Gagal membuat tagihan.';
      err.style.display = 'block';
      btn.disabled = false; btn.textContent = lbl;
      return;
    }

    // PERBAIKAN: simpan order ID di variabel lokal dan hentikan polling lama
    // SEBELUM mengisi payOrderId. Sebelumnya stopPay() dipanggil setelah
    // payOrderId diisi sehingga nilainya kembali menjadi null dan polling
    // mengirim order_id kosong ("Transaksi langganan tidak ditemukan").
    const orderId = body.order_id;
    stopPay();
    payOrderId = orderId;

    modal('Pembayaran QRIS', `
      <div style="text-align:center; padding:10px;">
        <p style="margin-bottom:16px;">Scan QR code di bawah ini untuk membayar:</p>
        <div style="background:#fff; padding:10px; border-radius:12px; border:1px solid var(--bd); display:inline-block;">
          <img src="${esc(body.qr_url)}" style="width:200px; height:200px;" alt="QRIS">
        </div>
        <p style="margin-top:16px; font-weight:700;">Total: ${rp(body.total)}</p>
        <p id="pay-status" style="color:var(--warn); font-weight:600; margin-top:8px;">Menunggu pembayaran...</p>
        <div id="pay-debug" style="margin-top:8px; font-size:0.75rem; color:var(--mu); text-align:left;"></div>
        <button class="btn line" type="button" id="btn-cek-status" style="margin-top:12px; width:100%;">Cek Status Manual</button>
      </div>
      <div class="modal-f">
        <button class="btn line" type="button" data-act="close-pay" id="btn-cancel-pay">Batal</button>
      </div>
    `);

    let checking = false;
    const checkStatus = async () => {
      if (checking) return;               // hindari request bertumpuk
      const statusEl = document.getElementById('pay-status');
      if (!statusEl) { stopPay(); return; } // modal sudah ditutup: hentikan polling
      checking = true;
      try {
        const r = await postJSON('api/pengaturan-data.php', { action: 'check_status', order_id: orderId });
        if (!r.ok) {
          statusEl.textContent = 'Gagal cek status: ' + ((r.body && r.body.message) || 'error server');
          statusEl.style.color = 'var(--err)';
          return;
        }

        if (r.body.status === 'active') {
          stopPay();
          closeModal();
          toast(r.body.message || 'Pembayaran berhasil. Paket diaktifkan.');
          load();
        } else {
          const dbg = r.body.debug || {};
          const st = r.body.midtrans_status || 'unknown';
          const sc = dbg.status_code || '-';
          const sm = dbg.status_message || '-';

          statusEl.textContent = `Status: ${st.toUpperCase()}`;
          statusEl.style.color = st === 'settlement' ? 'var(--gd)' : 'var(--warn)';

          const dbgEl = document.getElementById('pay-debug');
          if (dbgEl) dbgEl.innerHTML = `
            <p>Order ID: <b>${esc(dbg.order_id_sent || orderId)}</b></p>
            <p>Status Code: <b>${esc(sc)}</b></p>
            <p>Message: <b>${esc(sm)}</b></p>
            <p>Transaction Status: <b style="color:var(--err)">${esc(st)}</b></p>
          `;
        }
      } catch (e) {
        statusEl.textContent = 'Tidak dapat terhubung ke server.';
        statusEl.style.color = 'var(--err)';
      } finally {
        checking = false;
      }
    };

    document.getElementById('btn-cek-status').onclick = checkStatus;

    // Auto polling tiap 3 detik
    payInterval = setInterval(checkStatus, 3000);

  } catch (e) {
    err.textContent = 'Tidak dapat terhubung ke server.';
    err.style.display = 'block';
    btn.disabled = false; btn.textContent = lbl;
  }
}

/* ---------- Nama di menu dan header ---------- */
function syncIdentity() {
  const name = data.profile.full_name, biz = data.business.business_name;
  $$('.av').forEach((a) => { a.textContent = initials(name); });
  const pb = $('.profile b'); if (pb) pb.textContent = name;
  const wn = $('.who-n'); if (wn) wn.textContent = name.trim().split(/\s+/)[0] || name;
  const sub = $('.head-t p'); if (sub) sub.textContent = 'Kelola akun, usaha, dan paket · ' + biz;
}

/* ---------- Formulir ---------- */
function clearErrors(form) {
  $$('.ferr', form).forEach((e) => { e.textContent = ''; });
  $$('[aria-invalid]', form).forEach((el) => el.removeAttribute('aria-invalid'));
}

async function submitForm(form, formKey, action, values, onOk) {
  clearErrors(form);
  const btn = form.querySelector('button[type=submit]');
  if (btn.disabled) return;
  const label = btn.textContent;
  btn.disabled = true; btn.textContent = 'Menyimpan…';
  try {
    const { ok, body } = await postJSON('api/pengaturan-data.php', { action, ...values });
    if (ok) { onOk(body); toast(body.message || 'Tersimpan.'); return; }
    const errs = body.errors || {};
    Object.keys(errs).forEach((k) => {
      const box = $('#e-' + k), input = form.elements[k];
      if (box) box.textContent = errs[k];
      if (input) input.setAttribute('aria-invalid', 'true');
    });
    if (!Object.keys(errs).length) $('#e-form-' + formKey).textContent = body.message || 'Gagal menyimpan. Coba lagi.';
    else { const first = form.querySelector('[aria-invalid]'); if (first) first.focus(); }
  } catch (e) {
    $('#e-form-' + formKey).textContent = 'Tidak dapat terhubung ke server.';
  } finally {
    btn.disabled = false; btn.textContent = label;
  }
}

$('#f-profil').addEventListener('submit', (e) => {
  e.preventDefault();
  submitForm(e.target, 'profil', 'save_profile', { full_name: $('#p-name').value, phone: $('#p-phone').value }, (b) => {
    data.profile.full_name = b.profile.full_name;
    data.profile.phone = b.profile.phone;
    $('#p-name').value = b.profile.full_name;
    $('#p-phone').value = b.profile.phone;
    syncIdentity();
  });
});

$('#f-usaha').addEventListener('submit', (e) => {
  e.preventDefault();
  submitForm(e.target, 'usaha', 'save_business', { business_name: $('#b-name').value, alamat: $('#b-address').value }, (b) => {
    data.business.business_name = b.business.business_name;
    data.business.alamat = b.business.alamat;
    $('#b-name').value = b.business.business_name;
    $('#b-address').value = b.business.alamat;
    syncIdentity();
  });
});

$('#f-keamanan').addEventListener('submit', (e) => {
  e.preventDefault();
  submitForm(e.target, 'keamanan', 'change_password', { current: $('#s-current').value, password: $('#s-new').value, confirm: $('#s-confirm').value }, () => {
    e.target.reset();
  });
});

/* ---------- Event Listeners ---------- */
document.addEventListener('click', (e) => {
  const payBtn = e.target.closest('[data-pay]');
  if (payBtn) {
    startSubscription(payBtn.dataset.pay, payBtn.dataset.name, payBtn.dataset.price);
    return;
  }

  const doPay = e.target.closest('#btn-do-pay');
  if (doPay) {
    processPayment(doPay.dataset.code, doPay.dataset.price, doPay);
    return;
  }

  const cancelPay = e.target.closest('#btn-cancel-pay');
  if (cancelPay) {
    stopPay();
    closeModal();
  }
});

/* ---------- Tab ---------- */
function setTab(t, writeHash) {
  tab = t;
  $$('[data-tab]').forEach((b) => { const on = b.dataset.tab === t; b.classList.toggle('on', on); b.setAttribute('aria-pressed', on); });
  TABS.forEach((k) => { $(BOX[k]).hidden = k !== t; });
  if (writeHash) { try { history.replaceState(null, '', '#' + t); } catch (e) { /* alamat tidak bisa diubah: abaikan */ } }
}

$('#tabs').addEventListener('click', (e) => { const b = e.target.closest('[data-tab]'); if (b && b.dataset.tab !== tab) setTab(b.dataset.tab, true); });
window.addEventListener('hashchange', () => { const t = tabFromHash(); if (t !== tab) setTab(t, false); });

setTab(tabFromHash(), false);
load();
})();