// Halaman Transaksi & Laporan. Data diambil dari api/transaksi-data.php, ekspor dari api/transaksi-export.php.
// Fungsi bersama ($, rp, esc, modal, toast, dll.) ada di owner-common.js.
(() => {
const POLL_MS = 30000;
const SB = { Selesai: '', Menunggu: 'w', Dibatalkan: 'e' };
const state = { tab: 'riwayat', p: '7', from: '', to: '', q: '', status: '', method: '', page: 1 };
const store = { riwayat: null, laporan: null };   // data terakhir per bagian
const tok = { riwayat: 0, laporan: 0 };           // penanda permintaan terbaru, agar balasan lama diabaikan
const BOX = { riwayat: '#v-riwayat', laporan: '#v-laporan' };
let autoChecked = false;   // pemeriksaan otomatis transaksi menunggu dilakukan sekali per kunjungan

const tabFromHash = () => (location.hash === '#laporan' ? 'laporan' : 'riwayat');
const periodQuery = () => (state.p === 'custom' ? { period: 'custom', from: state.from, to: state.to } : { period: state.p });
const pct1 = (v) => String(v).replace('.', ',');

/* ---------- Pengambilan data ---------- */
async function load(view) {
  const mine = ++tok[view];
  const query = view === 'riwayat'
    ? { view: 'list', ...periodQuery(), q: state.q, status: state.status, method: state.method, page: state.page }
    : { view: 'report', ...periodQuery() };
  try {
    const r = await fetch('api/transaksi-data.php?' + new URLSearchParams(query), {
      credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' }
    });
    if (r.status === 401) { location.href = 'login.html'; return; }
    if (r.status === 403) { location.href = 'admin.php'; return; }
    if (!r.ok) throw new Error('HTTP ' + r.status);
    const j = await r.json();
    if (mine !== tok[view]) return;   // sudah ada permintaan yang lebih baru
    store[view] = j;
    $('#err').hidden = true;
    if (view === 'riwayat') { state.page = j.page; renderList(); } else { renderReport(); }
    if (view === state.tab) renderMeta(j);
    if (!autoChecked && j.pending_total > 0) { autoChecked = true; checkAll(true); }
  } catch (e) {
    if (!store[view]) {
      $('#err').hidden = false;
      $('#err').textContent = 'Data belum dapat dimuat. Periksa koneksi Anda lalu muat ulang halaman.';
    }
  } finally {
    if (mine === tok[view]) $(BOX[view]).classList.remove('is-loading');
  }
}

function renderMeta(j) {
  $('#range').textContent = 'Periode: ' + j.range.label;
  const note = $('#plan-note');
  note.hidden = !j.clipped;
  if (j.clipped) note.textContent = `Paket ${j.plan.name} menampilkan riwayat ${num(j.plan.history_days)} hari terakhir, jadi transaksi yang lebih lama tidak ditampilkan.`;
  $('#d-from').max = $('#d-to').max = j.today;
  setAlerts(j.pending_total ? [`${num(j.pending_total)} transaksi menunggu pembayaran`] : []);
  $('#pend-note').hidden = !j.pending_total;
  if (j.pending_total) $('#pend-text').textContent = `${num(j.pending_total)} transaksi menunggu pembayaran. Stok barangnya tetap terpotong sampai transaksi selesai atau dibatalkan. Klik transaksinya untuk memeriksa atau membatalkan.`;
}

// Muat ulang data setelah ada transaksi yang berubah status
function reloadAll() {
  store.riwayat = store.laporan = null;
  load(state.tab);
}

function checkSummary(r) {
  if (!r.checked) return 'Tidak ada transaksi QRIS yang perlu diperiksa.';
  const parts = [];
  if (r.selesai) parts.push(`${r.selesai} selesai`);
  if (r.dibatalkan) parts.push(`${r.dibatalkan} dibatalkan`);
  if (r.menunggu) parts.push(`${r.menunggu} masih menunggu`);
  return `${r.checked} transaksi diperiksa: ${parts.join(', ')}.`;
}

// Periksa semua transaksi QRIS yang menunggu ke Midtrans. silent: hanya beri tahu bila ada yang berubah.
async function checkAll(silent) {
  const btn = $('#pend-check'), label = btn.textContent;
  btn.disabled = true; btn.textContent = 'Memeriksa…';
  try {
    const { ok, body } = await postJSON('api/transaksi-data.php', { action: 'check_all' });
    if (!ok) { if (!silent) toast(body.message || 'Pemeriksaan gagal. Coba lagi.'); return; }
    const changed = body.selesai + body.dibatalkan > 0;
    if (!silent || changed) toast(checkSummary(body));
    if (changed) reloadAll();
  } catch (e) {
    if (!silent) toast('Tidak dapat terhubung ke server.');
  } finally {
    btn.disabled = false; btn.textContent = label;
  }
}

/* ---------- Riwayat ---------- */
function renderList() {
  const j = store.riwayat;
  const filtered = state.q || state.status || state.method;
  $('#tx-body').innerHTML = j.items.length
    ? j.items.map((x, i) => `<tr tabindex="0" data-i="${i}"><td data-label="ID Transaksi"><b>${esc(x.code)}</b></td><td data-label="Tanggal &amp; Waktu">${esc(x.time)}</td><td data-label="Pelanggan">${esc(x.customer)}</td><td data-label="Total"><b>${rp(x.total)}</b></td><td data-label="Metode"><span class="badge n">${esc(x.method)}</span></td><td data-label="Status"><span class="badge ${SB[x.status] ?? ''}">${esc(x.status)}</span></td></tr>`).join('')
    : filtered
      ? '<tr><td colspan="6" class="empty">Tidak ada transaksi yang cocok dengan pencarian atau filter ini.<br><br><button class="link" type="button" data-act="reset">Hapus pencarian dan filter</button></td></tr>'
      : '<tr><td colspan="6" class="empty">Belum ada transaksi pada periode ini. Transaksi muncul di sini setelah penjualan dicatat di Kasir.<br><br><a class="btn" href="kasir.php">Buka Kasir</a></td></tr>';

  $('#r-sum').textContent = j.total ? `${num(j.total)} transaksi · penjualan selesai ${rp(j.sales)}` : '';
  const from = j.total ? (j.page - 1) * j.per + 1 : 0, to = from ? from + j.items.length - 1 : 0;
  $('#pg-info').textContent = j.total ? `Menampilkan ${from}–${to} dari ${num(j.total)} transaksi` : '';
  $('#pg-prev').disabled = j.page <= 1;
  $('#pg-next').disabled = j.page >= j.pages;
}

function txDetail(i) {
  const x = store.riwayat && store.riwayat.items[i];
  if (!x) return;
  const pending = x.status === 'Menunggu';
  modal('Detail Transaksi', `<div class="dl"><span>ID</span><b>${esc(x.code)}</b></div><div class="dl"><span>Waktu</span><span>${esc(x.time)}</span></div><div class="dl"><span>Pelanggan</span><span>${esc(x.customer)}</span></div>`
    + x.items.map((it) => `<div class="dl"><span>${esc(it.name)} × ${num(it.qty)}</span><span>${rp(it.qty * it.price)}</span></div>`).join('')
    + `<div class="dl"><span>Total</span><b>${rp(x.total)}</b></div><div class="dl"><span>Metode</span><span>${esc(x.method)}</span></div><div class="dl"><span>Status</span><span class="badge ${SB[x.status] ?? ''}">${esc(x.status)}</span></div>`
    + (pending ? `<p class="sub-n">Menunggu pembayaran. Stok barang sudah dipotong sampai transaksi ini selesai atau dibatalkan.</p>
      <div class="modal-f">${x.online ? `<button class="btn line" type="button" data-pay="check" data-code="${esc(x.code)}" data-i="${i}">Periksa Pembayaran</button>` : ''}<button class="btn danger" type="button" data-pay="cancel" data-code="${esc(x.code)}" data-i="${i}">Batalkan Transaksi</button></div>` : ''));
}

// Konfirmasi sebelum membatalkan: tindakan ini mengembalikan stok dan tidak bisa diulang
function askCancel(i) {
  const x = store.riwayat && store.riwayat.items[i];
  if (!x) return;
  modal('Batalkan transaksi?', `<p>Batalkan transaksi <b>${esc(x.code)}</b> sebesar <b>${rp(x.total)}</b>? Stok barangnya akan dikembalikan${x.online ? ' dan pelanggan tidak dapat membayarnya lagi' : ''}.</p>
    <div class="modal-f"><button class="btn line" type="button" data-pay="back" data-i="${i}">Kembali</button><button class="btn danger" type="button" data-pay="cancel-yes" data-code="${esc(x.code)}" data-i="${i}">Ya, Batalkan</button></div>`);
}

async function payAction(b) {
  const act = b.dataset.pay, i = +b.dataset.i;
  if (act === 'cancel') { askCancel(i); return; }
  if (act === 'back') { txDetail(i); return; }
  const label = b.textContent;
  b.disabled = true; b.textContent = act === 'check' ? 'Memeriksa…' : 'Membatalkan…';
  try {
    const { body } = await postJSON('api/transaksi-data.php', { action: act === 'check' ? 'check' : 'cancel', code: b.dataset.code });
    toast(body.message || 'Selesai.');
    if (body.status && body.status !== 'Menunggu') { closeModal(); reloadAll(); return; }
    if (act === 'cancel-yes') { txDetail(i); return; }   // gagal dibatalkan: kembali ke rincian
  } catch (e) {
    toast('Tidak dapat terhubung ke server.');
    if (act === 'cancel-yes') { txDetail(i); return; }
  }
  b.disabled = false; b.textContent = label;
}

/* ---------- Laporan ---------- */
// Perubahan terhadap periode sebelumnya
function delta(cur, prev) {
  if (prev === 0) return cur > 0 ? { t: 'Naik dari nol', c: 'dl-up' } : { t: 'Sama dengan periode sebelumnya', c: '' };
  const pct = (cur - prev) / prev * 100;
  if (Math.abs(pct) < 0.05) return { t: 'Sama dengan periode sebelumnya', c: '' };
  const s = pct1(Math.abs(pct).toFixed(1)).replace(',0', '');
  return pct > 0 ? { t: `Naik ${s}%`, c: 'dl-up' } : { t: `Turun ${s}%`, c: 'dl-dn' };
}

function renderReport() {
  const d = store.laporan, s = d.stats, c = d.compare;
  const note = (key, plain) => {
    if (!c) return plain;
    const x = delta(s[key], c[key]);
    return `<span class="${x.c}">${x.t}</span>`;
  };
  $('#rp-stats').innerHTML = [
    ['Total Penjualan', rp(s.sales), note('sales', 'Dari transaksi selesai'), 'money', 'g'],
    ['Transaksi Selesai', num(s.transactions) + ' <small>transaksi</small>', c ? note('transactions') : (s.pending ? `${num(s.pending)} masih menunggu pembayaran` : 'Tidak ada yang menunggu'), 'receipt', ''],
    ['Rata-rata per Transaksi', rp(s.avg), note('avg', 'Nilai belanja rata-rata'), 'chart', ''],
    ['Barang Terjual', num(s.items) + ' <small>barang</small>', note('items', 'Total unit terjual'), 'box', '']
  ].map(([l, v, n, i, t]) => `<article class="card stat"><div class="ic ${t}">${ic(i)}</div><div><p class="l">${l}</p><p class="v">${v}</p><p class="n">${n}</p></div></article>`).join('');

  let cmp = '';
  if (c) cmp = `Dibandingkan dengan periode sebelumnya: ${c.label}`;
  else if (state.p !== 'all' && d.plan.history_days !== null) cmp = 'Perbandingan tidak tersedia: periode sebelumnya berada di luar riwayat yang dapat ditampilkan pada paket Anda.';
  $('#rp-cmp').textContent = cmp;
  $('#rp-cmp').hidden = !cmp;

  // Rekap per jam / hari / bulan
  const unit = d.recap.unit;
  $('#h-rp-recap').textContent = 'Rekap Penjualan per ' + { jam: 'Jam', hari: 'Hari', bulan: 'Bulan' }[unit];
  const col1 = { jam: 'Jam', hari: 'Tanggal', bulan: 'Bulan' }[unit];
  $('#rp-col1').textContent = col1;
  $('#rp-recap').innerHTML = s.sales > 0 || d.recap.rows.some((r) => r.n)
    ? d.recap.rows.map((r) => `<tr class="${r.n ? '' : 'zero'}"><td data-label="${col1}">${esc(r.label)}</td><td class="num" data-label="Transaksi">${num(r.n)}</td><td class="num" data-label="Barang Terjual">${num(r.items)}</td><td class="num" data-label="Penjualan"><b>${rp(r.sales)}</b></td><td class="num" data-label="Rata-rata">${rp(r.avg)}</td></tr>`).join('')
    : '<tr><td colspan="5" class="empty">Belum ada penjualan selesai pada periode ini.</td></tr>';
  $('#rp-recap-foot').innerHTML = `<tr><td data-label="">Total</td><td class="num" data-label="Transaksi">${num(s.transactions)}</td><td class="num" data-label="Barang Terjual">${num(s.items)}</td><td class="num" data-label="Penjualan">${rp(s.sales)}</td><td class="num" data-label="Rata-rata">${rp(s.avg)}</td></tr>`;

  $('#rp-methods').innerHTML = d.methods.length
    ? d.methods.map((m) => `<li><div class="rs"><div class="rs-top"><b>${esc(m.method)}</b><span class="val">${rp(m.total)}</span></div><div class="meter g"><i style="width:${Math.max(3, m.pct)}%"></i></div><div class="rs-bot"><span>${num(m.n)} transaksi</span><span>${pct1(m.pct)}%</span></div></div></li>`).join('')
    : '<li class="empty-li">Belum ada penjualan selesai pada periode ini.</li>';

  $('#rp-status').innerHTML = ['Selesai', 'Menunggu', 'Dibatalkan'].map((k) => {
    const v = d.statuses[k] || { n: 0, total: 0 };
    return `<li><div class="grow"><span class="badge ${SB[k]}">${k}</span></div><div class="val">${num(v.n)} transaksi<small class="muted">${rp(v.total)}</small></div></li>`;
  }).join('');

  // Penjualan per produk (semua produk yang terjual pada periode)
  $('#rp-prod-sub').textContent = d.products.length ? `${num(d.products.length)}${d.products_more ? '+' : ''} produk terjual, diurutkan dari yang paling banyak` : '';
  $('#rp-prods').innerHTML = d.products.length
    ? d.products.map((p) => `<tr><td data-label="Produk"><b>${esc(p.name)}</b>${p.sku ? `<br><small class="muted">Kode: ${esc(p.sku)}</small>` : ''}</td><td class="num" data-label="Terjual">${num(p.qty)}</td><td class="num" data-label="Penjualan"><b>${rp(p.revenue)}</b></td><td class="num" data-label="Rata-rata Harga">${rp(p.avg_price)}</td><td class="num" data-label="Porsi">${pct1(p.pct)}%</td></tr>`).join('')
      + (d.products_more ? '<tr><td colspan="5" class="empty">Hanya 200 produk teratas yang ditampilkan.</td></tr>' : '')
    : '<tr><td colspan="5" class="empty">Belum ada produk terjual pada periode ini.</td></tr>';
}

/* ---------- Tab, periode, dan tanggal ---------- */
function setTab(t, writeHash) {
  state.tab = t;
  $$('[data-tab]').forEach((b) => { const on = b.dataset.tab === t; b.classList.toggle('on', on); b.setAttribute('aria-pressed', on); });
  $('#v-riwayat').hidden = t !== 'riwayat';
  $('#v-laporan').hidden = t !== 'laporan';
  if (writeHash) { try { history.replaceState(null, '', '#' + t); } catch (e) { /* alamat tidak bisa diubah: abaikan, data tetap dimuat */ } }
  if (store[t]) renderMeta(store[t]); else $(BOX[t]).classList.add('is-loading');
  load(t);
}

function markPeriod() {
  $$('#periods [data-p]').forEach((b) => { const on = b.dataset.p === state.p; b.classList.toggle('on', on); b.setAttribute('aria-pressed', on); });
}

function setPeriod(p) {
  if (p === state.p && p !== 'custom') return;
  state.p = p;
  state.page = 1;
  markPeriod();
  $('#custom').hidden = true;
  $('#btn-custom').setAttribute('aria-expanded', 'false');
  store.riwayat = store.laporan = null;   // data periode lama tidak dipakai lagi
  $(BOX[state.tab]).classList.add('is-loading');
  load(state.tab);
}

function toggleCustom() {
  const box = $('#custom');
  const open = box.hidden;
  box.hidden = !open;
  $('#btn-custom').setAttribute('aria-expanded', open);
  if (open) {   // isi awal dengan rentang yang sedang tampil
    const cur = store[state.tab];
    if (!$('#d-from').value && cur) { $('#d-from').value = cur.range.from; $('#d-to').value = cur.range.to; }
    $('#d-from').focus();
  }
}

function applyCustom() {
  const f = $('#d-from').value, t = $('#d-to').value;
  if (!f || !t) { toast('Isi tanggal mulai dan tanggal akhir.'); return; }
  if (f > t) { toast('Tanggal mulai tidak boleh setelah tanggal akhir.'); return; }
  state.from = f; state.to = t;
  setPeriod('custom');
}

function resetFilters() {
  state.q = ''; state.status = ''; state.method = ''; state.page = 1;
  $('#q').value = ''; $('#f-status').value = ''; $('#f-method').value = '';
  load('riwayat');
}

/* ---------- Ekspor Excel ---------- */
async function exportXlsx() {
  const cur = store[state.tab];
  if (cur && !cur.can_export) {
    modal('Ekspor Excel', '<p>Ekspor ke Excel tersedia untuk paket <b>Pro</b> dan <b>Pro Max</b>.</p><p class="sub-n">Paket Anda saat ini: <b>' + esc(cur.plan.name) + '</b>.</p>');
    return;
  }
  const view = state.tab === 'riwayat' ? 'list' : 'report';
  const query = { view, ...periodQuery() };
  if (view === 'list') Object.assign(query, { q: state.q, status: state.status, method: state.method });
  const btn = $('#export'), label = btn.textContent;
  btn.disabled = true; btn.textContent = 'Menyiapkan…';
  try {
    const r = await fetch('api/transaksi-export.php?' + new URLSearchParams(query), { credentials: 'same-origin', cache: 'no-store' });
    if (r.status === 401) { location.href = 'login.html'; return; }
    if (!r.ok) {
      let m = 'Ekspor gagal. Coba lagi.';
      try { m = (await r.json()).message || m; } catch (e) { /* bukan JSON */ }
      toast(m);
      return;
    }
    const blob = await r.blob();
    const name = (/filename="([^"]+)"/.exec(r.headers.get('Content-Disposition') || '') || [])[1] || 'transaksi.xlsx';
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = name;
    document.body.appendChild(a); a.click(); a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 2000);
    toast('Berkas Excel diunduh.');
  } catch (e) {
    toast('Ekspor gagal. Periksa koneksi Anda.');
  } finally {
    btn.disabled = false; btn.textContent = label;
  }
}

/* ---------- Interaksi ---------- */
$('#tabs').addEventListener('click', (e) => { const b = e.target.closest('[data-tab]'); if (b && b.dataset.tab !== state.tab) setTab(b.dataset.tab, true); });
$('#periods').addEventListener('click', (e) => {
  const b = e.target.closest('[data-p]'); if (!b) return;
  if (b.dataset.p === 'custom') toggleCustom(); else setPeriod(b.dataset.p);
});
$('#d-apply').addEventListener('click', applyCustom);
$('#export').addEventListener('click', exportXlsx);
$('#pend-check').addEventListener('click', () => checkAll(false));
$('#dlg-b').addEventListener('click', (e) => { const b = e.target.closest('[data-pay]'); if (b) payAction(b); });

let timer;
$('#q').addEventListener('input', (e) => { clearTimeout(timer); timer = setTimeout(() => { state.q = e.target.value.trim(); state.page = 1; load('riwayat'); }, 300); });
$('#f-status').addEventListener('change', (e) => { state.status = e.target.value; state.page = 1; load('riwayat'); });
$('#f-method').addEventListener('change', (e) => { state.method = e.target.value; state.page = 1; load('riwayat'); });
$('#pg-prev').addEventListener('click', () => { if (state.page > 1) { state.page -= 1; load('riwayat'); } });
$('#pg-next').addEventListener('click', () => { state.page += 1; load('riwayat'); });

$('#tx-body').addEventListener('click', (e) => {
  if (e.target.closest('[data-act="reset"]')) { resetFilters(); return; }
  const r = e.target.closest('tr[data-i]'); if (r) txDetail(+r.dataset.i);
});
$('#tx-body').addEventListener('keydown', (e) => {
  const r = e.target.closest && e.target.closest('tr[data-i]');
  if (r && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); txDetail(+r.dataset.i); }
});
window.addEventListener('hashchange', () => { const t = tabFromHash(); if (t !== state.tab) setTab(t, false); });

/* ---------- Mulai & perbarui otomatis ---------- */
const refresh = () => { if (document.visibilityState === 'visible' && !dlg.open) load(state.tab); };
setTab(tabFromHash(), false);
setInterval(refresh, POLL_MS);
document.addEventListener('visibilitychange', refresh);
addEventListener('online', refresh);
})();