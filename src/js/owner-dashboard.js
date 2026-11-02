// Dashboard pemilik usaha. Data diambil dari api/dashboard-data.php dan diperbarui otomatis.
// Dashboard pemilik usaha. Data diambil dari api/dashboard-data.php dan diperbarui otomatis.
// Fungsi bersama ($, rp, esc, modal, toast, dll.) ada di owner-common.js.
const initials = (n) => esc(n.split(/\s+/).slice(0, 2).map((w) => w[0] || '').join('').toUpperCase());

const POLL_MS = 15000;
const LABEL = { today: 'Hari ini', 7: '7 hari terakhir', 30: '30 hari terakhir' };
const TRX = { today: 'Transaksi hari ini', 7: 'Transaksi 7 hari terakhir', 30: 'Transaksi 30 hari terakhir' };
const SB = { Selesai: '', Menunggu: 'w', Dibatalkan: 'e' };
let period = 'today', data = null, busy = false;

function niceStep(range) {
  const raw = range / 4, mag = 10 ** Math.floor(Math.log10(raw));
  return [1, 2, 2.5, 5, 10].map((m) => m * mag).find((s) => s >= raw);
}

/* ---------- Pengambilan data ---------- */
function setLive(ok, time) {
  const el = $('#live');
  el.className = 'live ' + (ok ? 'ok' : 'err');
  $('#live-t').textContent = ok ? `Diperbarui ${time}` : 'Gagal memperbarui, mencoba lagi…';
  const err = $('#err');
  err.hidden = ok || !!data;
  if (!ok && !data) err.textContent = 'Data belum dapat dimuat. Periksa koneksi Anda, halaman akan mencoba lagi otomatis.';
}

async function load() {
  if (busy) return;
  busy = true;
  const p = period;
  try {
    const r = await fetch(`api/dashboard-data.php?period=${p}`, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });
    if (r.status === 401) { location.href = 'login.html'; return; }
    if (r.status === 403) { location.href = 'admin.php'; return; }
    if (!r.ok) throw new Error('HTTP ' + r.status);
    const j = await r.json();
    if (p === period) { data = j; render(); setLive(true, j.server_time); }
  } catch (e) {
    setLive(false);
  } finally {
    busy = false;
    $$('.is-loading').forEach((x) => x.classList.remove('is-loading'));
    if (p !== period) load();   // periode diganti saat menunggu
  }
}

function setPeriod(p) {
  if (p === period) return;
  period = p;
  $$('[data-p]').forEach((b) => { const on = b.dataset.p === p; b.classList.toggle('on', on); b.setAttribute('aria-pressed', on); });
  ['#stats', '.plot', '#top'].forEach((s) => $(s).classList.add('is-loading'));
  load();
}

/* ---------- Tampilan ---------- */
function render() {
  const d = data, p = String(d.period), s = d.stats;
  $$('[data-p]').forEach((b) => { const on = b.dataset.p === p; b.classList.toggle('on', on); b.setAttribute('aria-pressed', on); });

  $('#stats').innerHTML = [
    ['Total Penjualan', rp(s.sales), LABEL[p], 'money', 'g'],
    ['Total Transaksi', num(s.transactions) + ' <small>transaksi</small>', TRX[p], 'receipt', ''],
    ['Total Produk', num(s.products) + ' <small>produk</small>', 'Produk terdaftar', 'box', ''],
    ['Stok Menipis', num(s.low_stock) + ' <small>produk</small>', s.low_stock ? 'Perlu diperiksa' : 'Semua stok aman', 'alert', s.low_stock ? 'w' : '']
  ].map(([l, v, n, i, t]) => `<article class="card stat"><div class="ic ${t}">${ic(i)}</div><div><p class="l">${l}</p><p class="v">${v}</p><p class="n">${n}</p></div></article>`).join('');

  const max = Math.max(0, ...d.chart.map((c) => c.value));
  const nice = max > 0 ? niceStep(max) * 4 : 100000;
  $('#y').innerHTML = [1, .75, .5, .25, 0].map((f) => `<span>${f ? short(nice * f) : '0'}</span>`).join('');
  $('#cols').innerHTML = d.chart.map((c, i) => {
    const h = (c.value / nice * 100).toFixed(1) + '%';
    return `<div class="col${i === d.chart.length - 1 ? ' top' : ''}" tabindex="0" style="--h:${h}" aria-label="${esc(c.tip)}: ${rp(c.value)}"><span class="tip">${esc(c.tip)} · ${rp(c.value)}</span><i style="height:${h}"></i>${c.label ? `<em>${esc(c.label)}</em>` : ''}</div>`;
  }).join('');
  $$('#cols .col').forEach((c) => {
    const fit = () => {   // geser tooltip bila hampir keluar layar
      const t = $('.tip', c); t.style.display = 'block'; t.style.marginLeft = '0px';
      const r = t.getBoundingClientRect(), W = document.documentElement.clientWidth;
      t.style.marginLeft = (r.left < 8 ? 8 - r.left : r.right > W - 8 ? W - 8 - r.right : 0) + 'px'; t.style.display = '';
    };
    c.addEventListener('mouseenter', fit); c.addEventListener('focus', fit);
  });
  $('#chart-sub').textContent = `${LABEL[p]} · total ${rp(s.sales)}`;
  $('#chart-note').hidden = s.sales > 0;
  $('#top-sub').textContent = LABEL[p];

  $('#tx').innerHTML = d.recent.length ? d.recent.map((x, i) => `<tr tabindex="0" data-i="${i}"><td data-label="ID Transaksi"><b>${esc(x.code)}</b></td><td data-label="Tanggal &amp; Waktu">${esc(x.time)}</td><td data-label="Pelanggan">${esc(x.customer)}</td><td data-label="Total"><b>${rp(x.total)}</b></td><td data-label="Metode"><span class="badge n">${esc(x.method)}</span></td><td data-label="Status"><span class="badge ${SB[x.status] ?? ''}">${esc(x.status)}</span></td></tr>`).join('')
    : '<tr><td colspan="6" class="empty">Belum ada transaksi. Transaksi akan tampil di sini setelah penjualan tercatat.</td></tr>';

  $('#top').innerHTML = d.top.length ? d.top.map((t) => `<li><div class="thumb">${t.image ? `<img src="${esc(t.image)}" alt="" loading="lazy">` : initials(t.name)}</div><div class="grow"><b>${esc(t.name)}</b><small>${num(t.qty)} terjual</small></div><div class="val">${rp(t.revenue)}</div></li>`).join('')
    : '<li class="empty-li">Belum ada penjualan pada periode ini.</li>';

  $('#rs').innerHTML = d.restock.length ? d.restock.map((r) => {
    const w = r.stock > 0 ? Math.max(6, Math.min(100, Math.round(r.stock / Math.max(r.min_stock * 2, 1) * 100))) : 0;
    return `<li><div class="rs"><div class="rs-top"><b>${esc(r.name)}</b><span class="badge ${r.stock > 0 ? 'w' : 'e'}">${r.stock > 0 ? 'Stok Menipis' : 'Stok Habis'}</span></div><div class="meter ${r.stock > 0 ? '' : 'e'}"><i style="width:${w}%"></i></div><div class="rs-bot"><span>Sisa ${num(r.stock)}</span><button class="link" data-soon="Produk" type="button">Lihat Produk</button></div></div></li>`;
  }).join('') : '<li class="empty-li">Semua stok aman. Tidak ada produk yang perlu direstok.</li>';

  const pl = d.plan, used = pl.used_products, mx = pl.max_products, ratio = mx ? used / mx : 0;
  $('#plan-badge').textContent = $('#side-plan').textContent = pl.name;
  $('#use').textContent = mx == null ? `${num(used)} produk (tanpa batas)` : `${num(used)} dari ${num(mx)} produk`;
  $('#use-bar').style.width = Math.min(100, ratio * 100) + '%';
  $('#use-bar').parentElement.classList.toggle('warn', ratio >= .9 && ratio < 1);
  $('#use-bar').parentElement.classList.toggle('full', ratio >= 1);
  const hist = pl.history_days == null ? 'Riwayat transaksi tidak terbatas' : pl.history_days === 365 ? 'Riwayat transaksi satu tahun' : `Riwayat transaksi ${pl.history_days} hari`;
  $('#plan-feat').innerHTML = [mx == null ? 'Produk tidak terbatas' : `Maksimal ${num(mx)} produk`, hist, `${pl.max_accounts ?? '-'} akun pengguna`].map((f) => `<li>${f}</li>`).join('');
  const others = d.other_plans.filter((o) => o.name !== pl.name);
  $('#plan-other').hidden = !others.length;
  $('#plan-other').innerHTML = others.map((o) => `<div><span>${esc(o.name)}</span><b>${rp(o.price)}/bulan</b></div>`).join('');

  const alerts = [];
  const out = d.restock.filter((r) => r.stock <= 0).length;
  if (s.low_stock) alerts.push(`${num(s.low_stock)} produk perlu direstok${out ? ` (${num(out)} sudah habis)` : ''}`);
  if (s.pending) alerts.push(`${num(s.pending)} transaksi menunggu pembayaran`);
  if (mx && ratio >= .9) alerts.push(`Penggunaan produk ${num(used)} dari ${num(mx)} pada paket ${pl.name}`);
  setAlerts(alerts);
}

/* ---------- Detail transaksi ---------- */
function txDetail(i) {
  const x = data.recent[i]; if (!x) return;
  modal('Detail Transaksi', `<div class="dl"><span>ID</span><b>${esc(x.code)}</b></div><div class="dl"><span>Waktu</span><span>${esc(x.time)}</span></div><div class="dl"><span>Pelanggan</span><span>${esc(x.customer)}</span></div>`
    + x.items.map((it) => `<div class="dl"><span>${esc(it.name)} × ${num(it.qty)}</span><span>${rp(it.qty * it.price)}</span></div>`).join('')
    + `<div class="dl"><span>Total</span><b>${rp(x.total)}</b></div><div class="dl"><span>Metode</span><span>${esc(x.method)}</span></div><div class="dl"><span>Status</span><span class="badge ${SB[x.status] ?? ''}">${esc(x.status)}</span></div>`);
}

/* ---------- Interaksi ---------- */
document.addEventListener('click', (e) => { const p = e.target.closest('[data-p]'); if (p) setPeriod(p.dataset.p); });
document.addEventListener('keydown', (e) => {
  const r = e.target.closest && e.target.closest('#tx tr[data-i]');
  if (r && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); txDetail(+r.dataset.i); }
});
$('#tx').addEventListener('click', (e) => { const r = e.target.closest('tr[data-i]'); if (r) txDetail(+r.dataset.i); });
$('#all-tx').addEventListener('click', () => soon('Transaksi'));

/* ---------- Mulai & perbarui otomatis ---------- */
$('#stats').innerHTML = '<div class="card stat sk"></div>'.repeat(4);
load();
setInterval(() => { if (document.visibilityState === 'visible') load(); }, POLL_MS);
document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') load(); });
addEventListener('online', load);