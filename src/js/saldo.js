// Halaman Saldo & Withdrawal pemilik. Data lewat api/saldo-data.php.
// Fungsi bersama ($, $$, rp, num, esc, modal, closeModal, toast, ic) ada di owner-common.js.
(() => {
const API = 'api/saldo-data.php';
const TABS = ['saldo', 'riwayat', 'rekening'];
const MON = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const WD_BADGE = { Pending: 'w', Approved: 'w', Processing: 'w', Completed: '', Rejected: 'e', Failed: 'e' };
const WD_INFO = {
  Pending: 'Menunggu persetujuan admin', Approved: 'Disetujui, menunggu diproses', Processing: 'Sedang ditransfer admin',
  Completed: 'Dana sudah ditransfer ke rekening Anda', Rejected: 'Ditolak, saldo dikembalikan', Failed: 'Transfer gagal, saldo dikembalikan',
};
const BANK_BADGE = { pending: ['Menunggu verifikasi', 'w'], verified: ['Terverifikasi', ''], rejected: ['Ditolak', 'e'] };

let data = null, tab = 'saldo', sub = 'pay', wdKey = '';

const fmtDT = (s) => {
  const m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(s || '');
  return m ? `${+m[3]} ${MON[+m[2] - 1]} ${m[1]}, ${m[4]}.${m[5]}` : '-';
};
const fmtD = (s) => { const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s || ''); return m ? `${+m[3]} ${MON[+m[2] - 1]} ${m[1]}` : '-'; };
const mask = (l4) => '•••• ' + esc(l4);
const uuid = () => (window.crypto && crypto.randomUUID ? crypto.randomUUID().replace(/-/g, '') : Array.from({ length: 24 }, () => Math.floor(Math.random() * 36).toString(36)).join(''));
const signed = (n) => (n > 0 ? '+' : n < 0 ? '−' : '') + rp(Math.abs(n));

async function wkPost(body) {   // kirim JSON dengan header penanda permintaan dari halaman sendiri
  const r = await fetch(API, {
    method: 'POST', credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'fetch', 'ngrok-skip-browser-warning': '1' },
    body: JSON.stringify(body),
  });
  let j = {};
  try { j = await r.json(); } catch (e) { /* bukan JSON */ }
  if (r.status === 401) location.href = 'login.html';
  return { ok: r.ok, status: r.status, body: j };
}

/* ---------- Pengambilan data ---------- */
async function load() {
  try {
    const r = await fetch(API, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });
    if (r.status === 401) { location.href = 'login.html'; return; }
    if (r.status === 403) { location.href = 'admin.php'; return; }
    if (!r.ok) throw new Error('HTTP ' + r.status);
    data = await r.json();
    $('#err').hidden = true;
    render();
  } catch (e) {
    if (!data) { $('#err').hidden = false; $('#err').textContent = 'Data belum dapat dimuat. Periksa koneksi Anda lalu muat ulang halaman.'; }
  }
}

function render() {
  $('#inactive').hidden = data.wallet.active;
  renderStats(); renderWdBox(); renderWdTable(); renderPayments(); renderLedger(); renderBanks();
}

/* ---------- Saldo ---------- */
function renderStats() {
  const w = data.wallet;
  $('#stats').innerHTML = [
    ['Saldo Tersedia', w.available, 'Dapat ditarik sekarang', 'wallet', 'g'],
    ['Saldo Pending', w.pending, 'Menunggu settlement', 'layers', 'w'],
    ['Dalam Proses', w.processing, 'Withdrawal berjalan', 'receipt', ''],
    ['Total Dicairkan', w.withdrawn, 'Withdrawal berhasil', 'check', ''],
    ['Total Biaya QRIS', w.fees, 'MDR + pajak', 'money', ''],
  ].map(([l, v, n, i, t]) => `<article class="card stat"><div class="ic ${t}">${ic(i)}</div><div><p class="l">${l}</p><p class="v">${rp(v)}</p><p class="n">${n}</p></div></article>`).join('');
}

function verifiedBanks() { return data.banks.filter((b) => b.status === 'verified'); }

function renderWdBox() {
  const w = data.wallet, c = data.config, vb = verifiedBanks();
  let hint = '', can = true;
  if (!vb.length) { can = false; hint = data.banks.length ? 'Rekening Anda masih menunggu verifikasi admin.' : 'Tambahkan rekening bank terlebih dahulu.'; }
  else if (data.open_withdrawals >= c.max_open) { can = false; hint = 'Masih ada pengajuan withdrawal yang belum selesai.'; }
  else if (w.available < c.min_withdrawal) { can = false; hint = `Saldo tersedia belum mencapai minimum withdrawal ${rp(c.min_withdrawal)}.`; }
  $('#wd-box').innerHTML = `
    <div class="card-h"><h2 id="h-wd">Tarik Saldo</h2></div>
    <div class="dl"><span>Saldo tersedia</span><b>${rp(w.available)}</b></div>
    <div class="dl"><span>Minimum withdrawal</span><span>${rp(c.min_withdrawal)}</span></div>
    <div class="dl"><span>Biaya transfer</span><span>${c.withdrawal_fee ? rp(c.withdrawal_fee) : 'Gratis'}</span></div>
    <div class="dl"><span>Pencairan</span><span>Diproses manual oleh admin</span></div>
    <button class="btn" type="button" id="wd-open"${can ? '' : ' disabled'}>Ajukan Withdrawal</button>
    ${hint ? `<p class="hint">${esc(hint)}${!vb.length ? ' <button class="link" type="button" data-tab-go="rekening">Kelola rekening</button>' : ''}</p>` : ''}`;
}

function renderWdTable() {
  const rows = data.withdrawals;
  $('#wd-tbl').innerHTML = rows.length ? rows.map((r, i) => `<tr tabindex="0" data-wd="${i}">
    <td data-label="ID"><b>#${r.id}</b></td><td data-label="Diajukan">${esc(fmtDT(r.requested_at))}</td>
    <td data-label="Nominal"><b>${rp(r.amount)}</b></td><td data-label="Biaya">${r.fee ? rp(r.fee) : '-'}</td>
    <td data-label="Diterima">${rp(r.net)}</td><td data-label="Rekening">${esc(r.bank_name)} ${mask(r.last4)}</td>
    <td data-label="Status"><span class="badge ${WD_BADGE[r.status] ?? ''}">${esc(r.status)}</span></td></tr>`).join('')
    : '<tr><td colspan="7" class="empty">Belum ada pengajuan withdrawal.</td></tr>';
}

function wdDetail(i) {
  const r = data.withdrawals[i]; if (!r) return;
  modal('Detail Withdrawal #' + r.id, `
    <div class="dl"><span>Status</span><span class="badge ${WD_BADGE[r.status] ?? ''}">${esc(r.status)}</span></div>
    <p class="sub-n" style="margin:6px 0 10px">${esc(WD_INFO[r.status] || '')}</p>
    <div class="dl"><span>Diajukan</span><span>${esc(fmtDT(r.requested_at))}</span></div>
    <div class="dl"><span>Nominal</span><b>${rp(r.amount)}</b></div>
    <div class="dl"><span>Biaya transfer</span><span>${r.fee ? rp(r.fee) : 'Gratis'}</span></div>
    <div class="dl"><span>Dana diterima</span><b>${rp(r.net)}</b></div>
    <div class="dl"><span>Rekening</span><span>${esc(r.bank_name)} ${mask(r.last4)}</span></div>
    <div class="dl"><span>Atas nama</span><span>${esc(r.holder)}</span></div>
    ${r.completed_at ? `<div class="dl"><span>Selesai</span><span>${esc(fmtDT(r.completed_at))}</span></div>` : ''}
    ${r.transfer_reference ? `<div class="dl"><span>No. referensi transfer</span><b>${esc(r.transfer_reference)}</b></div>` : ''}
    ${r.reject_reason ? `<div class="dl"><span>Alasan penolakan</span><span>${esc(r.reject_reason)}</span></div>` : ''}
    ${r.failure_reason ? `<div class="dl"><span>Alasan gagal</span><span>${esc(r.failure_reason)}</span></div>` : ''}
    ${r.has_proof ? `<div class="dl"><span>Bukti transfer</span><a class="link" href="api/wd-bukti.php?id=${r.id}" target="_blank" rel="noopener">Lihat bukti</a></div>` : ''}
    <div class="modal-f"><button class="btn line" type="button" data-act="close">Tutup</button></div>`);
}

/* ---------- Pengajuan withdrawal ---------- */
function openWithdraw() {
  const vb = verifiedBanks(), w = data.wallet, c = data.config;
  wdKey = uuid();
  modal('Ajukan Withdrawal', `
    <form id="wd-form" novalidate>
      <div class="fld"><label for="wd-bank">Rekening tujuan</label>
        <select id="wd-bank" name="bank_id">${vb.map((b) => `<option value="${b.id}">${esc(b.bank_name)} ${mask(b.last4)} · ${esc(b.holder)}</option>`).join('')}</select>
        <p class="ferr" id="e-bank_id"></p></div>
      <div class="fld"><label for="wd-amt">Nominal withdrawal (Rp)</label>
        <input id="wd-amt" name="amount" inputmode="numeric" autocomplete="off" placeholder="0">
        <p class="hint">Saldo tersedia ${rp(w.available)} · minimum ${rp(c.min_withdrawal)} · <button class="link" type="button" id="wd-all">Tarik semua</button></p>
        <p class="ferr" id="e-amount"></p></div>
      <div class="wd-sum">
        <div class="dl"><span>Biaya transfer</span><span id="wd-fee">${c.withdrawal_fee ? rp(c.withdrawal_fee) : 'Gratis'}</span></div>
        <div class="dl"><span>Dana yang diterima</span><b id="wd-net">Rp0</b></div>
      </div>
      <p class="ferr" id="e-form"></p>
      <div class="modal-f"><button class="btn line" type="button" data-act="close">Batal</button><button class="btn" type="submit">Lanjut</button></div>
    </form>`);
  $('#wd-amt').focus();
}

const amountOf = () => { const v = ($('#wd-amt').value || '').replace(/\D/g, ''); return v ? parseInt(v, 10) : 0; };

function updateNet() {
  const a = amountOf(), f = data.config.withdrawal_fee;
  $('#wd-net').textContent = rp(Math.max(0, a - f));
}

function clientCheck() {
  const a = amountOf(), c = data.config, w = data.wallet;
  const set = (id, msg) => { $('#e-' + id).textContent = msg || ''; };
  set('amount', ''); set('bank_id', ''); set('form', '');
  if (!a) return set('amount', 'Masukkan nominal withdrawal.') || false;
  if (a < c.min_withdrawal) return set('amount', `Nominal minimum ${rp(c.min_withdrawal)}.`) || false;
  if (a > w.available) return set('amount', `Nominal melebihi saldo tersedia (${rp(w.available)}).`) || false;
  if (a - c.withdrawal_fee <= 0) return set('amount', 'Nominal harus lebih besar dari biaya transfer.') || false;
  return true;
}

function confirmWithdraw() {
  const a = amountOf(), bank = data.banks.find((b) => b.id === +$('#wd-bank').value), f = data.config.withdrawal_fee;
  const bankId = bank.id;
  modal('Konfirmasi Withdrawal', `
    <p class="sub-n" style="margin:0 0 10px">Periksa kembali sebelum mengajukan. Saldo akan dikunci sampai admin selesai memproses.</p>
    <div class="dl"><span>Rekening tujuan</span><span>${esc(bank.bank_name)} ${mask(bank.last4)}</span></div>
    <div class="dl"><span>Atas nama</span><span>${esc(bank.holder)}</span></div>
    <div class="dl"><span>Nominal</span><b>${rp(a)}</b></div>
    <div class="dl"><span>Biaya transfer</span><span>${f ? rp(f) : 'Gratis'}</span></div>
    <div class="dl"><span>Dana yang diterima</span><b style="color:var(--gd)">${rp(a - f)}</b></div>
    <p class="ferr" id="e-form"></p>
    <div class="modal-f"><button class="btn line" type="button" id="wd-back">Kembali</button><button class="btn" type="button" id="wd-send">Ya, Ajukan</button></div>`);
  $('#wd-back').addEventListener('click', () => { openWithdraw(); $('#wd-bank').value = bankId; $('#wd-amt').value = num(a); updateNet(); });
  $('#wd-send').addEventListener('click', async (e) => {
    const btn = e.currentTarget; if (btn.disabled) return;
    btn.disabled = true; const lbl = btn.textContent; btn.textContent = 'Mengajukan…';
    try {
      const { ok, body } = await wkPost({ action: 'request_withdrawal', bank_id: bankId, amount: String(a), idempotency_key: wdKey });
      if (ok) { closeModal(); toast(body.message || 'Withdrawal diajukan.'); await load(); return; }
      $('#e-form').textContent = body.message || 'Gagal mengajukan withdrawal.';
    } catch (err) { $('#e-form').textContent = 'Tidak dapat terhubung ke server.'; }
    btn.disabled = false; btn.textContent = lbl;
  });
}

/* ---------- Riwayat ---------- */
function payRow(p) {
  const badge = p.status === 'Tersedia' ? '' : p.status === 'Dibalik' ? 'e' : 'w';
  return `<tr class="nolink"><td data-label="Transaksi"><b>${esc(p.code)}</b></td><td data-label="Dibayar">${esc(fmtDT(p.paid_at))}</td>
    <td data-label="Pembayaran">${rp(p.gross)}</td><td data-label="MDR">${rp(p.mdr)} <span class="mut">(${String(p.mdr_pct).replace('.', ',')}%)</span></td>
    <td data-label="Pajak">${rp(p.tax)} <span class="mut">(${String(p.tax_pct).replace('.', ',')}%)</span></td>
    <td data-label="Total Biaya">${rp(p.fee)}</td><td data-label="Saldo Bersih"><b>${rp(p.net)}</b></td>
    <td data-label="Status"><span class="badge ${badge}">${esc(p.status)}</span>${p.status === 'Pending' ? `<br><span class="mut">tersedia ±${esc(fmtD(p.release_at))}</span>` : ''}</td></tr>`;
}

function renderPayments(append) {
  const rows = data.payments.items;
  const html = rows.map(payRow).join('');
  if (append) $('#pay-tbl').insertAdjacentHTML('beforeend', append.map(payRow).join(''));
  else $('#pay-tbl').innerHTML = html || '<tr><td colspan="8" class="empty">Belum ada pembayaran QRIS yang tercatat di saldo.</td></tr>';
  $('#pay-more').hidden = !data.payments.more;
  const est = rows.some((p) => p.estimated);
  $('#pay-note').textContent = est ? 'Biaya adalah perkiraan berdasarkan tarif yang berlaku saat pembayaran (MDR + pajak atas MDR). Biaya aktual dari Midtrans dapat berbeda tipis dan disesuaikan saat rekonsiliasi. Biaya QRIS ditanggung warung, tidak dibebankan ke pelanggan.' : '';
}

function ledRow(l) {
  const chips = [['Pending', l.pending], ['Tersedia', l.available], ['Diproses', l.processing]].filter(([, v]) => v !== 0)
    .map(([n, v]) => `<span class="chip-d ${v > 0 ? 'up' : 'dn'}">${n} ${signed(v)}</span>`).join('');
  return `<tr class="nolink"><td data-label="Waktu">${esc(fmtDT(l.time))}</td><td data-label="Jenis"><b>${esc(l.type)}</b></td>
    <td data-label="Keterangan">${esc(l.desc || '')}${l.reason ? `<br><span class="mut">Alasan: ${esc(l.reason)}</span>` : ''}</td>
    <td data-label="Perubahan"><div class="chips">${chips}</div></td></tr>`;
}

function renderLedger(append) {
  const rows = data.ledger.items;
  if (append) $('#led-tbl').insertAdjacentHTML('beforeend', append.map(ledRow).join(''));
  else $('#led-tbl').innerHTML = rows.length ? rows.map(ledRow).join('') : '<tr><td colspan="4" class="empty">Belum ada mutasi saldo.</td></tr>';
  $('#led-more').hidden = !data.ledger.more;
}

async function loadMore(part) {
  const list = data[part === 'payments' ? 'payments' : 'ledger'];
  const last = list.items[list.items.length - 1];
  const btn = $(part === 'payments' ? '#pay-more' : '#led-more');
  btn.disabled = true;
  try {
    const r = await fetch(`${API}?part=${part}&before=${last.id}`, { credentials: 'same-origin', cache: 'no-store' });
    if (!r.ok) throw new Error('HTTP ' + r.status);
    const j = await r.json();
    list.items = list.items.concat(j.items); list.more = j.more;
    part === 'payments' ? renderPayments(j.items) : renderLedger(j.items);
  } catch (e) { toast('Gagal memuat data. Coba lagi.'); }
  btn.disabled = false;
}

/* ---------- Rekening bank ---------- */
function renderBanks() {
  $('#bank-list').innerHTML = data.banks.length ? data.banks.map((b) => {
    const [label, cls] = BANK_BADGE[b.status] || [b.status, 'n'];
    return `<li><div class="thumb">${esc(b.bank_code.slice(0, 3))}</div>
      <div class="grow"><b>${esc(b.bank_name)} ${mask(b.last4)}</b><small>a.n. ${esc(b.holder)}${b.reject_reason ? ' · Ditolak: ' + esc(b.reject_reason) : ''}</small></div>
      <span class="badge ${cls}">${label}</span><button class="link" type="button" data-disable="${b.id}">Nonaktifkan</button></li>`;
  }).join('') : '<li class="empty-li">Belum ada rekening. Klik “Tambah Rekening” untuk mulai.</li>';
}

function openAddBank() {
  modal('Tambah Rekening', `
    <form id="bank-form" novalidate autocomplete="off">
      <div class="fld"><label for="bk-code">Bank atau dompet digital</label>
        <select id="bk-code" name="bank_code"><option value="">Pilih…</option>${data.bank_catalog.map((b) => `<option value="${esc(b.code)}">${esc(b.name)}</option>`).join('')}</select>
        <p class="ferr" id="e-bank_code"></p></div>
      <div class="fld"><label for="bk-num">Nomor rekening</label><input id="bk-num" name="account_number" inputmode="numeric" maxlength="24" autocomplete="off"><p class="ferr" id="e-account_number"></p></div>
      <div class="fld"><label for="bk-holder">Nama pemilik rekening</label><input id="bk-holder" name="account_holder" maxlength="100" autocomplete="off"><p class="hint">Harus sama dengan nama di buku tabungan.</p><p class="ferr" id="e-account_holder"></p></div>
      <div class="fld"><label for="bk-pw">Kata sandi akun Anda</label><input id="bk-pw" name="password" type="password" maxlength="200" autocomplete="current-password"><p class="hint">Diminta untuk konfirmasi keamanan.</p><p class="ferr" id="e-password"></p></div>
      <p class="ferr" id="e-form"></p>
      <div class="modal-f"><button class="btn line" type="button" data-act="close">Batal</button><button class="btn" type="submit">Simpan Rekening</button></div>
    </form>`);
}

async function submitBank(form) {
  $$('.ferr', form).forEach((e) => { e.textContent = ''; });
  $$('[aria-invalid]', form).forEach((e) => e.removeAttribute('aria-invalid'));
  const btn = form.querySelector('button[type=submit]'); if (btn.disabled) return;
  const lbl = btn.textContent; btn.disabled = true; btn.textContent = 'Menyimpan…';
  try {
    const el = form.elements;
    const { ok, body } = await wkPost({ action: 'add_bank', bank_code: el.bank_code.value, account_number: el.account_number.value, account_holder: el.account_holder.value, password: el.password.value });
    if (ok) { closeModal(); toast(body.message || 'Rekening ditambahkan.'); await load(); return; }
    const errs = body.errors || {};
    Object.keys(errs).forEach((k) => { const box = $('#e-' + k), inp = form.elements[k]; if (box) box.textContent = errs[k]; if (inp) inp.setAttribute('aria-invalid', 'true'); });
    if (!Object.keys(errs).length) $('#e-form').textContent = body.message || 'Gagal menyimpan rekening.';
    else { const f = form.querySelector('[aria-invalid]'); if (f) f.focus(); }
  } catch (e) { $('#e-form').textContent = 'Tidak dapat terhubung ke server.'; }
  btn.disabled = false; btn.textContent = lbl;
}

function confirmDisable(id) {
  const b = data.banks.find((x) => x.id === id); if (!b) return;
  modal('Nonaktifkan Rekening', `
    <p>Nonaktifkan rekening <b>${esc(b.bank_name)} ${mask(b.last4)}</b> a.n. ${esc(b.holder)}? Rekening tidak akan bisa dipakai untuk withdrawal.</p>
    <p class="ferr" id="e-form"></p>
    <div class="modal-f"><button class="btn line" type="button" data-act="close">Batal</button><button class="btn danger" type="button" id="do-disable" data-id="${id}">Nonaktifkan</button></div>`);
}

/* ---------- Tab ---------- */
const tabFromHash = () => { const t = location.hash.replace('#', ''); return TABS.includes(t) ? t : 'saldo'; };
function setTab(t, writeHash) {
  tab = t;
  $$('#tabs [data-tab]').forEach((b) => { const on = b.dataset.tab === t; b.classList.toggle('on', on); b.setAttribute('aria-pressed', on); });
  TABS.forEach((k) => { $('#v-' + k).hidden = k !== t; });
  if (writeHash) { try { history.replaceState(null, '', '#' + t); } catch (e) { /* abaikan */ } }
}
function setSub(s) {
  sub = s;
  $$('#subtabs [data-sub]').forEach((b) => b.classList.toggle('on', b.dataset.sub === s));
  $('#s-pay').hidden = s !== 'pay'; $('#s-ledger').hidden = s !== 'ledger';
}

/* ---------- Interaksi ---------- */
document.addEventListener('click', async (e) => {
  const t = e.target;
  if (t.closest('[data-act="close"]')) { closeModal(); return; }
  const go = t.closest('[data-tab-go]'); if (go) { setTab(go.dataset.tabGo, true); return; }
  if (t.closest('#wd-open')) { if (data) openWithdraw(); return; }
  if (t.closest('#wd-all')) { $('#wd-amt').value = num(data.wallet.available); updateNet(); return; }
  if (t.closest('#add-bank')) { if (data) openAddBank(); return; }
  if (t.closest('#pay-more')) { loadMore('payments'); return; }
  if (t.closest('#led-more')) { loadMore('ledger'); return; }
  const dis = t.closest('[data-disable]'); if (dis) { confirmDisable(+dis.dataset.disable); return; }
  const doDis = t.closest('#do-disable');
  if (doDis) {
    doDis.disabled = true;
    try {
      const { ok, body } = await wkPost({ action: 'disable_bank', id: +doDis.dataset.id });
      if (ok) { closeModal(); toast(body.message || 'Rekening dinonaktifkan.'); await load(); return; }
      $('#e-form').textContent = body.message || 'Gagal menonaktifkan rekening.';
    } catch (err) { $('#e-form').textContent = 'Tidak dapat terhubung ke server.'; }
    doDis.disabled = false; return;
  }
  const sb = t.closest('#subtabs [data-sub]'); if (sb) { setSub(sb.dataset.sub); return; }
  const tb = t.closest('#tabs [data-tab]'); if (tb && tb.dataset.tab !== tab) { setTab(tb.dataset.tab, true); return; }
  const row = t.closest('tr[data-wd]'); if (row) wdDetail(+row.dataset.wd);
});
document.addEventListener('keydown', (e) => {
  const r = e.target.closest && e.target.closest('tr[data-wd]');
  if (r && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); wdDetail(+r.dataset.wd); }
});
document.addEventListener('input', (e) => {
  if (e.target.id !== 'wd-amt') return;
  const v = e.target.value.replace(/\D/g, '').slice(0, 12);
  e.target.value = v ? num(parseInt(v, 10)) : '';
  updateNet();
});
document.addEventListener('submit', (e) => {
  if (e.target.id === 'wd-form') { e.preventDefault(); if (clientCheck()) confirmWithdraw(); }
  if (e.target.id === 'bank-form') { e.preventDefault(); submitBank(e.target); }
});
window.addEventListener('hashchange', () => { const t = tabFromHash(); if (t !== tab) setTab(t, false); });
document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible' && !$('#dlg').open) load(); });
setInterval(() => { if (document.visibilityState === 'visible' && !$('#dlg').open) load(); }, 30000);

setTab(tabFromHash(), false);
setSub('pay');
load();
})();