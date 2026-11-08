// Admin: Withdrawal, verifikasi rekening, saldo warung, pengaturan wallet, audit log. Data lewat api/admin-wallet-data.php.
(() => {
const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];
const num = (n) => Number(n).toLocaleString('id-ID');
const rp = (n) => (n < 0 ? '−' : '') + 'Rp' + num(Math.abs(Math.round(n)));
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const ic = (n) => `<svg class="i" aria-hidden="true"><use href="#i-${n}"/></svg>`;
const MON = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const fmtDT = (s) => { const m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(s || ''); return m ? `${+m[3]} ${MON[+m[2] - 1]} ${m[1]}, ${m[4]}.${m[5]}` : '-'; };
const uuid = () => (window.crypto && crypto.randomUUID ? crypto.randomUUID().replace(/-/g, '') : Array.from({ length: 24 }, () => Math.floor(Math.random() * 36).toString(36)).join(''));
const MASTER = !!(window.WK || {}).master;
const API = 'api/admin-wallet-data.php';
const WD_BADGE = { Pending: 'w', Approved: 'w', Processing: 'w', Completed: '', Rejected: 'e', Failed: 'e' };
const BANK_BADGE = { pending: ['Menunggu verifikasi', 'w'], verified: ['Terverifikasi', ''], rejected: ['Ditolak', 'e'], disabled: ['Nonaktif', 'n'] };

let tab = 'wd', wdItems = [], bankItems = [], walItems = [], settings = null;

/* ---------- Dasar: dialog, toast, jaringan ---------- */
const dlg = $('#dlg');
const modal = (title, html) => { $('#dlg-t').textContent = title; $('#dlg-b').innerHTML = html; if (!dlg.open) dlg.showModal(); };
const closeModal = () => { if (dlg.open) dlg.close(); };
dlg.addEventListener('click', (e) => { if (e.target === dlg) dlg.close(); });
$('#dlg-x').addEventListener('click', closeModal);
function toast(msg) { const t = $('#toast'); t.textContent = msg; t.classList.add('show'); clearTimeout(toast.id); toast.id = setTimeout(() => t.classList.remove('show'), 3500); }

async function get(view, extra = '') {
  const r = await fetch(`${API}?view=${view}${extra}`, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });
  if (r.status === 401) { location.href = 'login.html'; throw new Error('401'); }
  if (r.status === 403) { location.href = 'owner-dashboard.php'; throw new Error('403'); }
  if (!r.ok) throw new Error('HTTP ' + r.status);
  return r.json();
}
async function post(body, isForm) {
  const r = await fetch(API, {
    method: 'POST', credentials: 'same-origin',
    headers: Object.assign({ 'X-Requested-With': 'fetch', 'ngrok-skip-browser-warning': '1' }, isForm ? {} : { 'Content-Type': 'application/json' }),
    body: isForm ? body : JSON.stringify(body),
  });
  let j = {};
  try { j = await r.json(); } catch (e) { /* bukan JSON */ }
  if (r.status === 401) location.href = 'login.html';
  return { ok: r.ok, status: r.status, body: j };
}
// Tampilkan galat server pada elemen #e-<kolom> / #e-form di dalam dialog
function showErrors(body, root = document) {
  const errs = body.errors || {};
  Object.keys(errs).forEach((k) => { const b = $('#e-' + k, root), i = root.querySelector ? root.querySelector(`[name="${k}"]`) : null; if (b) b.textContent = errs[k]; if (i) i.setAttribute('aria-invalid', 'true'); });
  if (!Object.keys(errs).length) { const f = $('#e-form', root); if (f) f.textContent = body.message || 'Gagal memproses.'; }
}
function clearErrors(root) { $$('.ferr', root).forEach((e) => { e.textContent = ''; }); $$('[aria-invalid]', root).forEach((e) => e.removeAttribute('aria-invalid')); }
async function guarded(btn, fn) {   // cegah klik ganda
  if (btn.disabled) return;
  btn.disabled = true; const lbl = btn.textContent;
  try { await fn(); } catch (e) { const f = $('#e-form'); if (f) f.textContent = 'Tidak dapat terhubung ke server.'; }
  btn.disabled = false; btn.textContent = lbl;
}

/* ---------- Ringkasan ---------- */
async function loadSummary() {
  const s = await get('summary');
  const c = s.wd_counts || {};
  $('#stats').innerHTML = [
    ['Total Kewajiban Saldo', rp(s.liability.total), `Tersedia ${rp(s.liability.available)} · Pending ${rp(s.liability.pending)} · Diproses ${rp(s.liability.processing)}`, 'wallet', 'g'],
    ['Menunggu Persetujuan', num(c.Pending || 0) + ' <small>withdrawal</small>', 'Status Pending', 'alert', (c.Pending || 0) ? 'w' : ''],
    ['Perlu Ditransfer', num((c.Approved || 0) + (c.Processing || 0)) + ' <small>withdrawal</small>', 'Approved + Processing', 'money', ''],
    ['Rekening Menunggu', num(s.banks_pending) + ' <small>rekening</small>', 'Perlu diverifikasi', 'check', s.banks_pending ? 'w' : ''],
  ].map(([l, v, n, i, t]) => `<article class="card stat"><div class="ic ${t}">${ic(i)}</div><div><p class="l">${l}</p><p class="v">${v}</p><p class="n">${n}</p></div></article>`).join('');
  return s;
}

/* ---------- Withdrawal ---------- */
async function loadWd() {
  const st = $('#f-wd').value;
  const d = await get('withdrawals', st ? '&status=' + encodeURIComponent(st) : '');
  wdItems = d.items;
  $('#wd-tbl').innerHTML = wdItems.length ? wdItems.map((w) => `<tr tabindex="0" data-wd="${w.id}">
    <td data-label="ID"><b>#${w.id}</b></td>
    <td data-label="Warung"><b>${esc(w.business)}</b><br><span class="mut">${esc(w.owner)}</span></td>
    <td data-label="Nominal" class="num"><b>${rp(w.amount)}</b></td><td data-label="Biaya" class="num">${w.fee ? rp(w.fee) : '-'}</td><td data-label="Diterima" class="num">${rp(w.net)}</td>
    <td data-label="Rekening">${esc(w.bank_name)} •••• ${esc(w.last4)}<br><span class="mut">a.n. ${esc(w.holder)}</span></td>
    <td data-label="Diajukan">${esc(fmtDT(w.requested_at))}</td>
    <td data-label="Status"><span class="badge ${WD_BADGE[w.status] ?? ''}">${esc(w.status)}</span></td>
    <td data-label="Aksi"><button class="link" type="button" data-wd="${w.id}">Kelola</button></td></tr>`).join('')
    : '<tr><td colspan="9" class="empty">Tidak ada withdrawal.</td></tr>';
}

function openWd(id) {
  const w = wdItems.find((x) => x.id === id); if (!w) return;
  let actions = '';
  if (w.status === 'Pending') actions = `<button class="btn line" type="button" data-wact="reject" data-id="${id}">Tolak</button><button class="btn" type="button" data-wact="approve" data-id="${id}">Setujui</button>`;
  if (w.status === 'Approved') actions = `<button class="btn line" type="button" data-wact="reject" data-id="${id}">Tolak</button><button class="btn" type="button" data-wact="process" data-id="${id}">Tandai Sedang Diproses</button>`;
  if (w.status === 'Processing') actions = `<button class="btn danger" type="button" data-wact="fail" data-id="${id}">Transfer Gagal</button><button class="btn" type="button" data-wact="complete" data-id="${id}">Selesaikan</button>`;
  const proof = (w.status === 'Processing' || w.status === 'Completed') ? `
    <div class="proof-box">
      <b>Bukti transfer</b> ${w.has_proof ? `· <a class="link" href="api/wd-bukti.php?id=${id}" target="_blank" rel="noopener">Lihat bukti</a>` : '<span class="mut">belum ada</span>'}
      <div class="fld" style="margin:10px 0 0"><input type="file" id="wd-proof" name="proof" accept="image/jpeg,image/png,application/pdf"><p class="hint">JPG, PNG, atau PDF, maksimal 3 MB.</p><p class="ferr" id="e-proof"></p></div>
      <button class="btn line" type="button" data-wupload="${id}">Unggah Bukti</button>
    </div>` : '';
  const ref = w.status === 'Processing' ? `<div class="fld" style="margin-top:14px"><label for="wd-ref">Nomor referensi transfer</label><input id="wd-ref" name="transfer_reference" maxlength="100" autocomplete="off" placeholder="Wajib diisi untuk menyelesaikan"><p class="ferr" id="e-transfer_reference"></p></div>` : '';
  modal('Withdrawal #' + id, `
    <div class="dl"><span>Status</span><span class="badge ${WD_BADGE[w.status] ?? ''}">${esc(w.status)}</span></div>
    <div class="dl"><span>Warung</span><span>${esc(w.business)}</span></div>
    <div class="dl"><span>Pemilik</span><span>${esc(w.owner)} · ${esc(w.email)}</span></div>
    <div class="dl"><span>Nominal</span><b>${rp(w.amount)}</b></div>
    <div class="dl"><span>Biaya transfer</span><span>${w.fee ? rp(w.fee) : 'Gratis'}</span></div>
    <div class="dl"><span><b>Transfer ke rekening</b></span><b style="color:var(--gd)">${rp(w.net)}</b></div>
    <div class="dl"><span>Bank</span><span>${esc(w.bank_name)}</span></div>
    <div class="dl"><span>Atas nama</span><span>${esc(w.holder)}</span></div>
    <div class="dl"><span>No. rekening</span><span id="acct-val">•••• ${esc(w.last4)} <button class="link" type="button" data-reveal="${id}">Tampilkan lengkap</button></span></div>
    <div class="dl"><span>Diajukan</span><span>${esc(fmtDT(w.requested_at))}</span></div>
    ${w.approved_at ? `<div class="dl"><span>Disetujui</span><span>${esc(fmtDT(w.approved_at))}</span></div>` : ''}
    ${w.processed_at ? `<div class="dl"><span>Diproses</span><span>${esc(fmtDT(w.processed_at))}</span></div>` : ''}
    ${w.completed_at ? `<div class="dl"><span>Selesai</span><span>${esc(fmtDT(w.completed_at))}</span></div>` : ''}
    ${w.transfer_reference ? `<div class="dl"><span>No. referensi</span><b>${esc(w.transfer_reference)}</b></div>` : ''}
    ${w.reject_reason ? `<div class="dl"><span>Alasan penolakan</span><span>${esc(w.reject_reason)}</span></div>` : ''}
    ${w.failure_reason ? `<div class="dl"><span>Alasan gagal</span><span>${esc(w.failure_reason)}</span></div>` : ''}
    ${ref}${proof}
    <p class="ferr" id="e-form" style="margin-top:10px"></p>
    <div class="modal-f"><button class="btn line" type="button" data-act="close">Tutup</button>${actions}</div>`);
}

function askReason(id, act) {
  const fail = act === 'fail';
  modal(fail ? 'Transfer Gagal' : 'Tolak Withdrawal', `
    <form id="reason-form" data-id="${id}" data-act-name="${act}" novalidate>
      <p class="sub" style="margin-top:0">${fail ? 'Saldo akan dikembalikan ke pemilik. Tulis alasan yang akan terlihat oleh pemilik warung.' : 'Saldo akan dikembalikan ke pemilik. Tulis alasan penolakan yang akan terlihat oleh pemilik warung.'}</p>
      <div class="fld"><label for="rs-text">Alasan</label><textarea id="rs-text" name="reason" maxlength="255"></textarea><p class="ferr" id="e-reason"></p></div>
      <p class="ferr" id="e-form"></p>
      <div class="modal-f"><button class="btn line" type="button" data-back="${id}">Kembali</button><button class="btn danger" type="submit">${fail ? 'Catat Gagal' : 'Tolak Withdrawal'}</button></div>
    </form>`);
  $('#rs-text').focus();
}

async function wdAction(btn, id, act, extra = {}) {
  clearErrors($('#dlg-b'));
  await guarded(btn, async () => {
    btn.textContent = 'Memproses…';
    const { ok, body } = await post(Object.assign({ action: act, id }, extra));
    if (ok) { closeModal(); toast(body.message || 'Berhasil.'); await refresh(); return; }
    showErrors(body, $('#dlg-b'));
  });
}

/* ---------- Rekening bank ---------- */
async function loadBank() {
  const st = $('#f-bank').value;
  const d = await get('banks', st ? '&status=' + encodeURIComponent(st) : '');
  bankItems = d.items;
  $('#bank-tbl').innerHTML = bankItems.length ? bankItems.map((b) => {
    const [label, cls] = BANK_BADGE[b.status] || [b.status, 'n'];
    return `<tr class="nolink"><td data-label="Warung"><b>${esc(b.business)}</b><br><span class="mut">${esc(b.owner)}</span></td>
      <td data-label="Bank">${esc(b.bank_name)}</td><td data-label="Atas Nama">${esc(b.holder)}</td><td data-label="No. Rekening">•••• ${esc(b.last4)}</td>
      <td data-label="Diajukan">${esc(fmtDT(b.created_at))}</td>
      <td data-label="Status"><span class="badge ${cls}">${label}</span>${b.reject_reason ? `<br><span class="mut">${esc(b.reject_reason)}</span>` : ''}</td>
      <td data-label="Aksi">${b.status === 'pending' ? `<div class="acts"><button class="link" type="button" data-bank="verify" data-id="${b.id}">Verifikasi</button><button class="link" type="button" data-bank="reject" data-id="${b.id}">Tolak</button></div>` : '-'}</td></tr>`;
  }).join('') : '<tr><td colspan="7" class="empty">Tidak ada rekening.</td></tr>';
}

function openBank(id, act) {
  const b = bankItems.find((x) => x.id === id); if (!b) return;
  const info = `<div class="dl"><span>Warung</span><span>${esc(b.business)}</span></div><div class="dl"><span>Pemilik akun</span><span>${esc(b.owner)}</span></div>
    <div class="dl"><span>Bank</span><span>${esc(b.bank_name)} •••• ${esc(b.last4)}</span></div><div class="dl"><span>Atas nama rekening</span><b>${esc(b.holder)}</b></div>`;
  if (act === 'verify') {
    modal('Verifikasi Rekening', info + `<p class="sub">Pastikan nama pemilik rekening sesuai dengan pemilik warung sebelum memverifikasi.</p><p class="ferr" id="e-form"></p>
      <div class="modal-f"><button class="btn line" type="button" data-act="close">Batal</button><button class="btn" type="button" data-bankgo="bank_verify" data-id="${id}">Verifikasi Rekening</button></div>`);
  } else {
    modal('Tolak Rekening', info + `<div class="fld" style="margin-top:12px"><label for="bk-reason">Alasan penolakan</label><textarea id="bk-reason" name="reason" maxlength="255"></textarea><p class="ferr" id="e-reason"></p></div><p class="ferr" id="e-form"></p>
      <div class="modal-f"><button class="btn line" type="button" data-act="close">Batal</button><button class="btn danger" type="button" data-bankgo="bank_reject" data-id="${id}">Tolak Rekening</button></div>`);
  }
}

/* ---------- Saldo warung ---------- */
async function loadWallets(sum) {
  const s = sum || await get('summary');
  const d = await get('wallets');
  walItems = d.items;
  $('#liab').innerHTML = `<div class="card" style="box-shadow:none;margin-bottom:14px"><h2 style="margin-bottom:8px">Total Kewajiban Saldo Seluruh Warung</h2>
    <div class="dl"><span>Saldo pending</span><span class="num">${rp(s.liability.pending)}</span></div>
    <div class="dl"><span>Saldo tersedia</span><span class="num">${rp(s.liability.available)}</span></div>
    <div class="dl"><span>Dalam proses withdrawal</span><span class="num">${rp(s.liability.processing)}</span></div>
    <div class="dl"><span><b>Total kewajiban</b></span><b class="num">${rp(s.liability.total)}</b></div>
    <div class="dl"><span>Total sudah dicairkan</span><span class="num">${rp(s.withdrawn_total)}</span></div>
    <p class="sub">Kewajiban ini harus dapat ditutup oleh dana yang benar-benar ada di akun Midtrans dan rekening platform.</p></div>`;
  $('#wal-tbl').innerHTML = walItems.length ? walItems.map((w) => `<tr class="nolink"><td data-label="Warung"><b>${esc(w.business)}</b><br><span class="mut">${esc(w.owner)}</span></td>
    <td data-label="Pending" class="num">${rp(w.pending)}</td><td data-label="Tersedia" class="num"><b>${rp(w.available)}</b></td><td data-label="Diproses" class="num">${rp(w.processing)}</td>
    <td data-label="Total Dicairkan" class="num">${rp(w.withdrawn)}</td><td data-label="Biaya QRIS" class="num">${rp(w.fees)}</td>
    <td data-label="Aksi">${MASTER ? `<div class="acts"><button class="link" type="button" data-pending="${w.id}">Saldo Pending</button><button class="link" type="button" data-adjust="${w.id}">Sesuaikan</button></div>` : '<span class="mut">Master</span>'}</td></tr>`).join('')
    : '<tr><td colspan="7" class="empty">Belum ada wallet.</td></tr>';
}

function openAdjust(id) {
  const w = walItems.find((x) => x.id === id); if (!w) return;
  modal('Penyesuaian Saldo', `
    <form id="adj-form" data-id="${id}" data-key="${uuid()}" novalidate>
      <div class="dl"><span>Warung</span><b>${esc(w.business)}</b></div>
      <div class="dl"><span>Tersedia / Pending</span><span>${rp(w.available)} / ${rp(w.pending)}</span></div>
      <div class="lock" style="margin:12px 0">Penyesuaian berlaku <b>langsung</b> dan tercatat permanen di ledger dan audit log atas nama Anda. Gunakan hanya untuk koreksi yang jelas dasarnya. <b>Jangan dipakai untuk memindahkan saldo Pending ke Tersedia</b>; untuk itu gunakan tombol “Saldo Pending” → “Lepas sekarang”.</div>
      <div class="fld"><label for="adj-bucket">Saldo yang disesuaikan</label><select id="adj-bucket" name="bucket"><option value="available">Tersedia</option><option value="pending">Pending</option></select></div>
      <div class="fld"><label for="adj-delta">Nominal (Rp)</label><input id="adj-delta" name="delta" inputmode="numeric" autocomplete="off" placeholder="contoh 50000 atau -50000"><p class="hint">Tanda minus (−) untuk mengurangi saldo.</p><p class="ferr" id="e-delta"></p></div>
      <div class="fld"><label for="adj-reason">Alasan (wajib)</label><textarea id="adj-reason" name="reason" maxlength="255"></textarea><p class="ferr" id="e-reason"></p></div>
      <p class="ferr" id="e-form"></p>
      <div class="modal-f"><button class="btn line" type="button" data-act="close">Batal</button><button class="btn" type="submit">Catat Penyesuaian</button></div>
    </form>`);
}

async function openPending(ownerId) {
  const w = walItems.find((x) => x.id === ownerId); if (!w) return;
  modal('Saldo Pending · ' + w.business, '<p class="sub">Memuat…</p>');
  let d;
  try { d = await get('pending', '&owner_id=' + ownerId); } catch (e) { $('#dlg-b').innerHTML = '<p class="ferr" style="display:block">Data belum dapat dimuat.</p>'; return; }
  modal('Saldo Pending · ' + w.business, `
    <div data-owner="${ownerId}" id="pend-box">
    <p class="sub" style="margin-top:0">Saldo pending otomatis menjadi tersedia setelah jeda settlement. Lepas lebih awal <b>hanya</b> jika Anda sudah memastikan dana pembayaran itu benar-benar masuk (cek dashboard Midtrans). Tindakan ini tercatat di ledger dan audit log.</p>
    ${d.items.length ? `
    <div class="fld"><label for="rl-reason">Alasan melepas lebih awal (wajib)</label><textarea id="rl-reason" name="reason" maxlength="255"></textarea><p class="ferr" id="e-reason"></p></div>
    <div class="wrap"><table class="tbl"><thead><tr><th>Transaksi</th><th>Dibayar</th><th>Bersih</th><th>Tersedia otomatis</th><th></th></tr></thead><tbody>
      ${d.items.map((p) => `<tr class="nolink"><td data-label="Transaksi"><b>${esc(p.code)}</b></td><td data-label="Dibayar">${esc(fmtDT(p.paid_at))}</td>
        <td data-label="Bersih" class="num"><b>${rp(p.net)}</b></td><td data-label="Tersedia otomatis">${esc(fmtDT(p.release_at))}</td>
        <td data-label=""><button class="link" type="button" data-release="${p.id}">Lepas sekarang</button></td></tr>`).join('')}
    </tbody></table></div>` : '<p class="empty">Tidak ada saldo pending untuk warung ini.</p>'}
    <p class="ferr" id="e-form"></p>
    <div class="modal-f"><button class="btn line" type="button" data-act="close">Tutup</button></div></div>`);
}

/* ---------- Pengaturan ---------- */
const SET_HINT = {
  mdr_bps: 'Dalam bps: 70 = 0,70%. Sesuaikan dengan ketentuan Midtrans untuk akun Anda.',
  mdr_tax_bps: 'Dalam bps: 1100 = 11%. Pajak dikenakan atas nilai MDR.',
  settlement_delay_days: 'Jumlah hari kerja sebelum saldo pending menjadi tersedia.',
  min_withdrawal: 'Nominal minimum satu pengajuan (Rp).',
  withdrawal_fee_flat: 'Biaya transfer per withdrawal (Rp), dipotong dari dana yang diterima warung.',
  max_open_withdrawals: 'Jumlah pengajuan yang boleh terbuka bersamaan untuk satu warung.',
};
async function loadSettings() {
  settings = await get('settings');
  $('#devkey').hidden = !settings.key_dev;
  const dis = MASTER ? '' : ' disabled';
  $('#set-form').innerHTML = `
    ${MASTER ? '' : '<div class="lock" style="margin-bottom:14px"><b>Akses dibatasi.</b> Hanya master admin yang dapat mengubah pengaturan wallet.</div>'}
    <div class="set-grid">
      ${settings.items.map((s) => `<div class="fld"><label for="st-${s.key}">${esc(s.label)}</label>
        <input id="st-${s.key}" name="${s.key}" inputmode="numeric" value="${esc(s.value)}"${dis} autocomplete="off">
        <p class="hint">${esc(SET_HINT[s.key] || '')}${s.key === 'mdr_bps' || s.key === 'mdr_tax_bps' ? ` <b data-pct="${s.key}">= ${(s.value / 100).toLocaleString('id-ID')}%</b>` : ''}</p>
        <p class="ferr" id="e-${s.key}"></p></div>`).join('')}
      <div class="fld" style="grid-column:1/-1"><label for="st-start">Wallet aktif sejak</label>
        <input id="st-start" name="wallet_start_at" value="${esc(settings.wallet_start_at)}" placeholder="2026-11-07 00:00:00"${dis} autocomplete="off">
        <p class="hint">Kosong = wallet belum aktif (tidak ada pembayaran yang dicatat). Hanya pembayaran QRIS <b>setelah</b> waktu ini yang masuk saldo. Format YYYY-MM-DD JJ:MM:DD.</p><p class="ferr" id="e-wallet_start_at"></p></div>
      <div class="fld"><label>Mode pencairan</label><input value="Manual (admin mentransfer sendiri)" readonly></div>
    </div>
    <p class="ferr" id="e-form"></p>
    ${MASTER ? '<button class="btn" type="submit">Simpan Pengaturan</button>' : ''}`;
}

function collectSettings() {
  const f = $('#set-form'), o = {};
  settings.items.forEach((s) => { o[s.key] = f.elements[s.key].value.trim(); });
  o.wallet_start_at = f.elements.wallet_start_at.value.trim();
  return o;
}
async function saveSettings(btn) {
  const f = $('#set-form'); clearErrors(f);
  await guarded(btn, async () => {
    btn.textContent = 'Menyimpan…';
    const { ok, body } = await post({ action: 'save_settings', settings: collectSettings() });
    if (ok) { toast(body.message || 'Disimpan.'); closeModal(); await loadSettings(); return; }
    showErrors(body, f);
    const first = f.querySelector('[aria-invalid]'); if (first) first.focus();
  });
}

/* ---------- Audit ---------- */
async function loadAudit() {
  const d = await get('audit');
  $('#aud-tbl').innerHTML = d.items.length ? d.items.map((a) => `<tr class="nolink"><td data-label="Waktu">${esc(fmtDT(a.time))}</td><td data-label="Admin">${esc(a.admin)}</td>
    <td data-label="Aksi"><b>${esc(a.action)}</b></td><td data-label="Objek">${esc(a.entity)}</td><td data-label="Alasan">${esc(a.reason || '-')}</td>
    <td data-label="Detail"><div class="pre">${esc(a.detail || '')}</div></td></tr>`).join('') : '<tr><td colspan="6" class="empty">Belum ada aktivitas admin.</td></tr>';
}

/* ---------- Tab & muat ulang ---------- */
const LOAD = { wd: loadWd, bank: loadBank, wallet: () => loadWallets(), set: loadSettings, audit: loadAudit };
async function showTab(t) {
  tab = t;
  $$('#tabs [data-tab]').forEach((b) => b.classList.toggle('on', b.dataset.tab === t));
  ['wd', 'bank', 'wallet', 'set', 'audit'].forEach((k) => { $('#t-' + k).hidden = k !== t; });
  try { await LOAD[t](); } catch (e) { $('#err').hidden = false; $('#err').textContent = 'Data belum dapat dimuat. Muat ulang halaman.'; return; }
  $('#err').hidden = true;
}
async function refresh() { try { await Promise.all([loadSummary(), LOAD[tab]()]); } catch (e) { /* tampilan tetap */ } }

/* ---------- Interaksi ---------- */
document.addEventListener('click', async (e) => {
  const t = e.target;
  if (t.closest('[data-act="close"]')) { closeModal(); return; }
  const tb = t.closest('#tabs [data-tab]'); if (tb) { showTab(tb.dataset.tab); return; }
  const back = t.closest('[data-back]'); if (back) { openWd(+back.dataset.back); return; }
  const row = t.closest('[data-wd]'); if (row && !t.closest('[data-wact]')) { openWd(+row.dataset.wd); return; }

  const rev = t.closest('[data-reveal]');
  if (rev) {
    await guarded(rev, async () => {
      const { ok, body } = await post({ action: 'reveal_account', id: +rev.dataset.reveal });
      if (ok) { $('#acct-val').innerHTML = `<b class="num">${esc(body.account_number)}</b> <span class="mut">(pembukaan ini tercatat di audit log)</span>`; return; }
      $('#e-form').textContent = body.message || 'Gagal membuka nomor rekening.';
    });
    return;
  }
  const up = t.closest('[data-wupload]');
  if (up) {
    clearErrors($('#dlg-b'));
    const file = $('#wd-proof').files[0];
    if (!file) { $('#e-proof').textContent = 'Pilih file bukti transfer.'; return; }
    await guarded(up, async () => {
      up.textContent = 'Mengunggah…';
      const fd = new FormData(); fd.append('action', 'upload_proof'); fd.append('id', up.dataset.wupload); fd.append('proof', file);
      const { ok, body } = await post(fd, true);
      if (ok) { toast(body.message || 'Bukti tersimpan.'); const id = +up.dataset.wupload; await refresh(); openWd(id); return; }
      showErrors(body, $('#dlg-b'));
    });
    return;
  }
  const wa = t.closest('[data-wact]');
  if (wa) {
    const id = +wa.dataset.id, act = wa.dataset.wact;
    if (act === 'reject' || act === 'fail') { askReason(id, act); return; }
    if (act === 'complete') { await wdAction(wa, id, act, { transfer_reference: ($('#wd-ref').value || '').trim() }); return; }
    await wdAction(wa, id, act); return;
  }
  const bk = t.closest('[data-bank]'); if (bk) { openBank(+bk.dataset.id, bk.dataset.bank); return; }
  const bg = t.closest('[data-bankgo]');
  if (bg) {
    clearErrors($('#dlg-b'));
    await guarded(bg, async () => {
      const reason = $('#bk-reason') ? $('#bk-reason').value : '';
      const { ok, body } = await post({ action: bg.dataset.bankgo, id: +bg.dataset.id, reason });
      if (ok) { closeModal(); toast(body.message || 'Berhasil.'); await refresh(); return; }
      showErrors(body, $('#dlg-b'));
    });
    return;
  }
  const adj = t.closest('[data-adjust]'); if (adj) { openAdjust(+adj.dataset.adjust); return; }
  const pend = t.closest('[data-pending]'); if (pend) { openPending(+pend.dataset.pending); return; }
  const rel = t.closest('[data-release]');
  if (rel) {
    const ownerId = +$('#pend-box').dataset.owner, reason = ($('#rl-reason').value || '').trim();
    clearErrors($('#dlg-b'));
    if (reason.length < 5) { $('#e-reason').textContent = 'Alasan wajib diisi (minimal 5 karakter).'; $('#rl-reason').focus(); return; }
    await guarded(rel, async () => {
      rel.textContent = 'Memproses…';
      const { ok, body } = await post({ action: 'release_payment', id: +rel.dataset.release, reason });
      if (ok) { toast(body.message || 'Saldo dilepas.'); await refresh(); await openPending(ownerId); return; }
      showErrors(body, $('#dlg-b'));
    });
    return;
  }
});

document.addEventListener('keydown', (e) => {
  const r = e.target.closest && e.target.closest('tr[data-wd]');
  if (r && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); openWd(+r.dataset.wd); }
  if (e.key === 'Escape') { openSide(false); openDd(false); }
});

document.addEventListener('submit', async (e) => {
  const f = e.target;
  if (f.id === 'reason-form') {
    e.preventDefault(); clearErrors(f);
    await wdAction(f.querySelector('button[type=submit]'), +f.dataset.id, f.dataset.actName, { reason: f.elements.reason.value });
  }
  if (f.id === 'adj-form') {
    e.preventDefault(); clearErrors(f);
    const btn = f.querySelector('button[type=submit]');
    await guarded(btn, async () => {
      btn.textContent = 'Mencatat…';
      const { ok, body } = await post({ action: 'adjust', owner_id: +f.dataset.id, bucket: f.elements.bucket.value, delta: f.elements.delta.value.replace(/\s/g, '').replace('−', '-'), reason: f.elements.reason.value, request_key: f.dataset.key });
      if (ok) { closeModal(); toast(body.message || 'Tercatat.'); await refresh(); return; }
      showErrors(body, f);
    });
  }
  if (f.id === 'set-form') {
    e.preventDefault();
    const o = collectSettings(), old = settings.wallet_start_at || '';
    const btn = f.querySelector('button[type=submit]');
    if (o.wallet_start_at !== old && (old === '' || o.wallet_start_at === '' || o.wallet_start_at < old)) {
      modal('Ubah Waktu Aktif Wallet', `<p>${o.wallet_start_at === '' ? 'Mengosongkan waktu ini akan <b>menonaktifkan pencatatan</b> pembayaran baru ke wallet.' : `Wallet akan mencatat pembayaran QRIS sejak <b>${esc(o.wallet_start_at)}</b>. Pembayaran lama setelah waktu itu yang belum tercatat <b>akan dikreditkan ke saldo pending</b> oleh tugas berkala.`} Lanjutkan?</p><p class="ferr" id="e-form"></p>
        <div class="modal-f"><button class="btn line" type="button" data-act="close">Batal</button><button class="btn" type="button" id="set-confirm">Ya, Simpan</button></div>`);
      $('#set-confirm').addEventListener('click', (ev) => saveSettings(ev.currentTarget));
      return;
    }
    saveSettings(btn);
  }
});

document.addEventListener('input', (e) => {
  if (e.target.name === 'mdr_bps' || e.target.name === 'mdr_tax_bps') {
    const el = $(`[data-pct="${e.target.name}"]`); const v = parseInt(e.target.value, 10);
    if (el) el.textContent = Number.isFinite(v) ? '= ' + (v / 100).toLocaleString('id-ID') + '%' : '';
  }
});
$('#f-wd').addEventListener('change', () => loadWd().catch(() => {}));
$('#f-bank').addEventListener('change', () => loadBank().catch(() => {}));

/* ---------- Menu samping, menu akun, keluar ---------- */
const side = $('#side'), scrim = $('#scrim'), dd = $('#dd'), who = $('#who');
function openSide(on) { side.classList.toggle('open', on); scrim.classList.toggle('on', on); $('#open-side').setAttribute('aria-expanded', on); }
function openDd(on) { dd.hidden = !on; who.setAttribute('aria-expanded', on); }
$('#open-side').addEventListener('click', () => openSide(true));
$('#close-side').addEventListener('click', () => openSide(false));
scrim.addEventListener('click', () => openSide(false));
who.addEventListener('click', (e) => { e.stopPropagation(); openDd(dd.hidden); });
document.addEventListener('click', (e) => { if (!dd.contains(e.target)) openDd(false); });
$('#logout').addEventListener('click', (e) => {
  e.preventDefault();
  fetch('api/logout.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}', credentials: 'same-origin' })
    .catch(() => {}).finally(() => { location.href = 'login.html'; });
});

setInterval(() => { if (document.visibilityState === 'visible' && !dlg.open && tab !== 'set') refresh(); }, 30000);
(async () => { try { await loadSummary(); } catch (e) { $('#err').hidden = false; $('#err').textContent = 'Data belum dapat dimuat. Muat ulang halaman.'; return; } showTab('wd'); })();
})();