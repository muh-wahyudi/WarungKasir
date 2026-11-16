(() => {
const TABS = ['profil', 'usaha', 'keamanan', 'paket', 'staf'];
const BOX = { profil: '#v-profil', usaha: '#v-usaha', keamanan: '#v-keamanan', paket: '#v-paket', staf: '#v-staf' };
const HAS_STAFF = !!document.querySelector('[data-tab="staf"]');
const HAS_PAKET = !!document.querySelector('[data-tab="paket"]');
const CAN_CHANGE_EMAIL = !!document.querySelector('#btn-change-email');
const STAFF_STATUS = {
  active: ['Aktif', ''],
  invited: ['Menunggu', 'w'],
  disabled: ['Nonaktif', 'n'],
};
const STAFF_ROLE = { admin: 'Admin', kasir: 'Kasir' };
let data = null;
let tab = 'profil';
let payInterval = null;
let payOrderId = null;
let emailChangeTarget = '';

const validTabs = () => TABS.filter((t) => {
  if (t === 'staf') return HAS_STAFF;
  if (t === 'paket') return HAS_PAKET;
  return true;
});
const tabFromHash = () => { const t = location.hash.replace('#', ''); return validTabs().includes(t) ? t : 'profil'; };
const initials = (n) => n.trim().split(/\s+/).slice(0, 2).map((w) => (w[0] || '').toUpperCase()).join('');

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
  if (HAS_PAKET) renderPlan();
}

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
        <button class="btn line" type="button" id="btn-cek-status" style="margin-top:12px; width:100%;">Cek Status Manual</button>
      </div>
      <div class="modal-f">
        <button class="btn line" type="button" data-act="close-pay" id="btn-cancel-pay">Batal</button>
      </div>
    `);

    let checking = false;
    const checkStatus = async () => {
      if (checking) return;
      const statusEl = document.getElementById('pay-status');
      if (!statusEl) { stopPay(); return; }
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
          const st = r.body.midtrans_status || 'unknown';
          statusEl.textContent = `Status: ${st.toUpperCase()}`;
          statusEl.style.color = st === 'settlement' ? 'var(--gd)' : 'var(--warn)';
        }
      } catch (e) {
        statusEl.textContent = 'Tidak dapat terhubung ke server.';
        statusEl.style.color = 'var(--err)';
      } finally {
        checking = false;
      }
    };

    document.getElementById('btn-cek-status').onclick = checkStatus;
    payInterval = setInterval(checkStatus, 3000);

  } catch (e) {
    err.textContent = 'Tidak dapat terhubung ke server.';
    err.style.display = 'block';
    btn.disabled = false; btn.textContent = lbl;
  }
}

function syncIdentity() {
  const name = data.profile.full_name, biz = data.business.business_name;
  $$('.av').forEach((a) => { a.textContent = initials(name); });
  const pb = $('.profile b'); if (pb) pb.textContent = name;
  const wn = $('.who-n'); if (wn) wn.textContent = name.trim().split(/\s+/)[0] || name;
  const sub = $('.head-t p'); if (sub) sub.textContent = 'Kelola akun, usaha, dan paket · ' + biz;
}

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

function openChangeEmailModal() {
  modal('Ganti Email', `
    <form id="email-change-form" novalidate>
      <p class="sub-n" style="margin-top:0">Masukkan email baru. Kami akan mengirim kode verifikasi ke email tersebut.</p>
      <div class="fld">
        <label for="new-email">Email baru</label>
        <input id="new-email" name="new_email" type="email" autocomplete="email" maxlength="190" placeholder="nama@email.com">
        <p class="ferr" id="e-new_email"></p>
      </div>
      <p class="ferr" id="e-form-email"></p>
      <div class="modal-f">
        <button class="btn line" type="button" data-act="close">Batal</button>
        <button class="btn" type="submit">Kirim Kode</button>
      </div>
    </form>`);
  $('#new-email').focus();
}

function openVerifyEmailModal(newEmail) {
  emailChangeTarget = newEmail;
  modal('Verifikasi Email Baru', `
    <form id="email-verify-form" novalidate>
      <p class="sub-n" style="margin-top:0">Kode 6 digit telah dikirim ke <b>${esc(newEmail)}</b>. Masukkan kode tersebut untuk menyelesaikan perubahan.</p>
      <div class="fld">
        <label for="email-otp">Kode verifikasi</label>
        <input id="email-otp" name="otp" type="text" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="000000" style="font-size:1.4rem;letter-spacing:8px;text-align:center;font-weight:700;">
        <p class="ferr" id="e-otp"></p>
      </div>
      <p class="ferr" id="e-form-email-verify"></p>
      <div class="modal-f">
        <button class="btn line" type="button" data-act="close">Batal</button>
        <button class="btn" type="submit">Verifikasi</button>
      </div>
    </form>`);
  $('#email-otp').focus();
}

document.addEventListener('submit', async (e) => {
  if (e.target.id === 'email-change-form') {
    e.preventDefault();
    const form = e.target;
    $$('.ferr', form).forEach((el) => { el.textContent = ''; });
    const btn = form.querySelector('button[type=submit]');
    if (btn.disabled) return;
    const newEmail = $('#new-email').value.trim();
    btn.disabled = true; btn.textContent = 'Mengirim…';
    try {
      const { ok, body } = await postJSON('api/email-change.php', { action: 'request', new_email: newEmail });
      if (ok) {
        openVerifyEmailModal(body.new_email || newEmail);
        toast(body.message || 'Kode terkirim.');
        return;
      }
      const errs = body.errors || {};
      Object.keys(errs).forEach((k) => { const box = $('#e-' + k); if (box) box.textContent = errs[k]; });
      if (!Object.keys(errs).length) $('#e-form-email').textContent = body.message || 'Gagal mengirim kode.';
    } catch (err) {
      $('#e-form-email').textContent = 'Tidak dapat terhubung ke server.';
    }
    btn.disabled = false; btn.textContent = 'Kirim Kode';
  }

  if (e.target.id === 'email-verify-form') {
    e.preventDefault();
    const form = e.target;
    $$('.ferr', form).forEach((el) => { el.textContent = ''; });
    const btn = form.querySelector('button[type=submit]');
    if (btn.disabled) return;
    const otp = $('#email-otp').value.replace(/\D/g, '');
    if (otp.length !== 6) { $('#e-otp').textContent = 'Masukkan 6 digit kode.'; return; }
    btn.disabled = true; btn.textContent = 'Memverifikasi…';
    try {
      const { ok, body } = await postJSON('api/email-change.php', { action: 'verify', otp });
      if (ok && body.redirect) {
        toast(body.message || 'Email berhasil diubah.');
        setTimeout(() => { location.href = body.redirect; }, 1500);
        return;
      }
      const errs = body.errors || {};
      Object.keys(errs).forEach((k) => { const box = $('#e-' + k); if (box) box.textContent = errs[k]; });
      if (!Object.keys(errs).length) $('#e-form-email-verify').textContent = body.message || 'Gagal verifikasi.';
    } catch (err) {
      $('#e-form-email-verify').textContent = 'Tidak dapat terhubung ke server.';
    }
    btn.disabled = false; btn.textContent = 'Verifikasi';
  }
});

async function loadStaff() {
  if (!HAS_STAFF) return;
  const tbl = $('#staf-tbl');
  if (!tbl) return;
  try {
    const r = await fetch('api/team-invite.php', { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });
    if (r.status === 401) { location.href = 'login.html'; return; }
    if (r.status === 403) { tbl.innerHTML = '<tr><td colspan="6" class="empty">Anda tidak memiliki akses ke halaman ini.</td></tr>'; return; }
    if (!r.ok) throw new Error('HTTP ' + r.status);
    const j = await r.json();
    const items = j.items || [];

    tbl.innerHTML = items.length ? items.map((it) => {
      const st = STAFF_STATUS[it.status] || [it.status, ''];
      return `<tr>
        <td data-label="Nama">${esc(it.full_name)}</td>
        <td data-label="Email">${esc(it.email)}</td>
        <td data-label="Role">${STAFF_ROLE[it.role] || it.role}</td>
        <td data-label="Status"><span class="badge ${st[1]}">${st[0]}</span></td>
        <td data-label="Bergabung">${esc(it.joined_at || it.invited_at || '-')}</td>
        <td data-label="Aksi"><div class="acts">
          ${it.status === 'active' ? `<button class="link danger" data-staff="disable" data-id="${it.id}">Nonaktifkan</button>` : ''}
          ${it.status === 'disabled' ? `<button class="link" data-staff="enable" data-id="${it.id}">Aktifkan</button>` : ''}
          ${it.status === 'invited' ? `<button class="link" data-staff="resend" data-id="${it.id}">Kirim Ulang</button>` : ''}
          <button class="link danger" data-staff="remove" data-id="${it.id}">Hapus</button>
        </div></td>
      </tr>`;
    }).join('') : '<tr><td colspan="6" class="empty">Belum ada staf. Klik "Tambah Staf" untuk mengundang.</td></tr>';

    const limitEl = $('#staf-limit');
    if (limitEl) {
      limitEl.textContent = j.limit === null
        ? `Paket ${j.plan} · staf tanpa batas (${j.used} terdaftar)`
        : `Paket ${j.plan} · ${j.used} dari ${j.limit} staf terpakai`;
    }
    const addBtn = $('#btn-add-staff');
    if (addBtn) {
      addBtn.disabled = !j.can_add;
      addBtn.title = j.can_add ? '' : 'Batas akun paket tercapai. Upgrade paket untuk menambah staf.';
    }
  } catch {
    tbl.innerHTML = '<tr><td colspan="6" class="empty">Gagal memuat data staf. Coba muat ulang halaman.</td></tr>';
  }
}

function openAddStaff() {
  modal('Tambah Staf', `
    <form id="staff-form" novalidate>
      <p class="sub-n" style="margin-top:0">Undangan dikirim ke email staf. Link berlaku 72 jam. Staf akan membuat kata sandi sendiri saat menerima undangan.</p>
      <div class="fld">
        <label for="staff-email">Email staf</label>
        <input id="staff-email" name="email" type="email" maxlength="190" autocomplete="off" placeholder="staf@email.com">
        <p class="ferr" id="e-email"></p>
      </div>
      <div class="fld">
        <label for="staff-role">Role</label>
        <select id="staff-role" name="role">
          <option value="kasir">Kasir — hanya akses kasir</option>
          <option value="admin">Admin — kasir, produk, transaksi</option>
        </select>
        <p class="hint">Admin bisa kelola produk & lihat laporan. Kasir hanya bisa transaksi.</p>
      </div>
      <p class="ferr" id="e-form-staff"></p>
      <div class="modal-f">
        <button class="btn line" type="button" data-act="close">Batal</button>
        <button class="btn" type="submit">Kirim Undangan</button>
      </div>
    </form>`);
  $('#staff-email').focus();
}

document.addEventListener('submit', async (e) => {
  if (e.target.id !== 'staff-form') return;
  e.preventDefault();
  const form = e.target;
  $$('.ferr', form).forEach((el) => { el.textContent = ''; });
  const btn = form.querySelector('button[type=submit]');
  if (btn.disabled) return;
  const email = form.email.value.trim();
  if (!email) { $('#e-email').textContent = 'Email wajib diisi.'; return; }
  btn.disabled = true; btn.textContent = 'Mengirim…';
  try {
    const { ok, body } = await postJSON('api/team-invite.php', { action: 'invite', email, role: form.role.value });
    if (ok) {
      closeModal();
      toast(body.message || 'Undangan dikirim.');
      if (body.link) {
        modal('Link Undangan (Dev)', `
          <p>Email gagal terkirim atau Anda dalam mode development. Salin link undangan berikut:</p>
          <p style="word-break:break-all; background:var(--bg); padding:10px; border-radius:8px;"><a href="${esc(body.link)}" target="_blank">${esc(body.link)}</a></p>
          <div class="modal-f"><button class="btn" type="button" data-act="close">Tutup</button></div>`);
      }
      loadStaff();
      return;
    }
    const errs = body.errors || {};
    Object.keys(errs).forEach((k) => { const b2 = $('#e-' + k); if (b2) b2.textContent = errs[k]; });
    if (!Object.keys(errs).length) $('#e-form-staff').textContent = body.message || 'Gagal mengirim undangan.';
  } catch {
    $('#e-form-staff').textContent = 'Tidak dapat terhubung ke server.';
  }
  btn.disabled = false; btn.textContent = 'Kirim Undangan';
});

document.addEventListener('click', async (e) => {
  if (e.target.closest('#btn-add-staff')) { openAddStaff(); return; }
  const sb = e.target.closest('[data-staff]');
  if (sb) {
    const act = sb.dataset.staff, id = +sb.dataset.id;
    if (act === 'remove' && !confirm('Hapus staf ini? Akun staf akan dihapus permanen dan tidak bisa login lagi.')) return;
    sb.disabled = true;
    try {
      const { ok, body } = await postJSON('api/team-invite.php', { action: act, id });
      if (ok) {
        toast(body.message || 'Berhasil.');
        if (body.link) {
          modal('Link Undangan', `
            <p style="word-break:break-all; background:var(--bg); padding:10px; border-radius:8px;"><a href="${esc(body.link)}" target="_blank">${esc(body.link)}</a></p>
            <div class="modal-f"><button class="btn" type="button" data-act="close">Tutup</button></div>`);
        }
        loadStaff();
        return;
      }
      toast(body.message || 'Gagal.');
    } catch {
      toast('Tidak dapat terhubung ke server.');
    }
    sb.disabled = false;
  }
});

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
    return;
  }

  if (e.target.closest('#btn-change-email')) {
    openChangeEmailModal();
    return;
  }
});

function setTab(t, writeHash) {
  tab = t;
  $$('[data-tab]').forEach((b) => { const on = b.dataset.tab === t; b.classList.toggle('on', on); b.setAttribute('aria-pressed', on); });
  TABS.forEach((k) => { const el = $(BOX[k]); if (el) el.hidden = k !== t; });
  if (writeHash) { try { history.replaceState(null, '', '#' + t); } catch (e) { } }
  if (t === 'staf') loadStaff();
}

$('#tabs').addEventListener('click', (e) => { const b = e.target.closest('[data-tab]'); if (b && b.dataset.tab !== tab) setTab(b.dataset.tab, true); });
window.addEventListener('hashchange', () => { const t = tabFromHash(); if (t !== tab) setTab(t, false); });

setTab(tabFromHash(), false);
load();
})();