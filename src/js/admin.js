// Dashboard Admin WarungKasir. Semua angka adalah data ilustrasi untuk desain.
// Tidak ada data yang disimpan atau diproses dari halaman ini.
const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];
const rp = (n) => 'Rp' + Math.round(n).toLocaleString('id-ID');
const short = (v) => (v >= 1e6 ? (v / 1e6).toFixed(1).replace('.', ',').replace(',0', '') + ' jt' : v >= 1000 ? Math.round(v / 1000) + ' rb' : String(v));
const pct = (a, b) => (a / b * 100).toFixed(1).replace('.', ',') + '%';
const ic = (n) => `<svg class="i" aria-hidden="true"><use href="#i-${n}"/></svg>`;
const WK = window.WK || {};
const MASTER = !!WK.master;
const PRICE = { Free: 0, Pro: 29000, 'Pro Max': 59000, 'Pro 3 Bulan': 75000 };

/* ---------- Data contoh ---------- */
const U = [
  ['USR-01250', 'Rina Marlina', 'Warung Rina Jaya', 'rina.marlina@example.com', 'Free', '25 Okt 2026', 'Aktif'],
  ['USR-01249', 'Ahmad Fauzi', 'Toko Berkah Fauzi', 'ahmad.fauzi@example.com', 'Pro', '25 Okt 2026', 'Menunggu'],
  ['USR-01248', 'Siti Nurhaliza', 'Kelontong Siti', 'siti.nurhaliza@example.com', 'Pro Max', '24 Okt 2026', 'Aktif'],
  ['USR-01247', 'Bambang Setiawan', 'Warung Bambang', 'bambang.s@example.com', 'Free', '24 Okt 2026', 'Aktif'],
  ['USR-01246', 'Lestari Wulandari', 'Toko Lestari', 'lestari.w@example.com', 'Pro', '23 Okt 2026', 'Aktif'],
  ['USR-01245', 'Agus Prasetyo', 'Sembako Agus', 'agus.prasetyo@example.com', 'Free', '22 Okt 2026', 'Nonaktif'],
  ['USR-01244', 'Dewi Anggraini', 'Warung Dewi Makmur', 'dewi.anggraini@example.com', 'Pro', '21 Okt 2026', 'Aktif'],
  ['USR-01243', 'Hendra Gunawan', 'Toko Sinar Baru', 'hendra.g@example.com', 'Free', '20 Okt 2026', 'Aktif'],
  ['USR-01242', 'Yuni Astuti', 'Kios Yuni', 'yuni.astuti@example.com', 'Pro', '19 Okt 2026', 'Menunggu'],
  ['USR-01241', 'Rizky Ramadhan', 'Warung Rizky', 'rizky.r@example.com', 'Free', '18 Okt 2026', 'Aktif']
];
const S = [
  ['SUB-0320', 'Ahmad Fauzi', 'Pro', '25 Okt 2026', '25 Nov 2026', 'Menunggu pembayaran'],
  ['SUB-0319', 'Siti Nurhaliza', 'Pro Max', '24 Okt 2026', '24 Nov 2026', 'Aktif'],
  ['SUB-0318', 'Lestari Wulandari', 'Pro', '23 Okt 2026', '23 Nov 2026', 'Aktif'],
  ['SUB-0317', 'Dewi Anggraini', 'Pro', '21 Okt 2026', '21 Nov 2026', 'Aktif'],
  ['SUB-0305', 'Budi Hartono', 'Pro', '28 Sep 2026', '28 Okt 2026', 'Aktif'],
  ['SUB-0298', 'Maya Sari', 'Pro Max', '30 Sep 2026', '30 Okt 2026', 'Aktif'],
  ['SUB-0291', 'Tono Wibowo', 'Pro', '26 Sep 2026', '26 Okt 2026', 'Aktif'],
  ['SUB-0270', 'Joko Susilo', 'Pro', '10 Sep 2026', '10 Okt 2026', 'Berakhir']
];
const EXP = [['Tono Wibowo', 'Pro', '26 Okt 2026', '1 hari lagi'], ['Budi Hartono', 'Pro', '28 Okt 2026', '3 hari lagi'], ['Maya Sari', 'Pro Max', '30 Okt 2026', '5 hari lagi']];
const P = [
  ['INV-00891', 'Siti Nurhaliza', 'Pro Max', '24 Okt 2026', 59000, 'QRIS', 'Berhasil'],
  ['INV-00890', 'Lestari Wulandari', 'Pro', '23 Okt 2026', 19000, 'E-Wallet', 'Berhasil'],
  ['INV-00889', 'Ahmad Fauzi', 'Pro', '25 Okt 2026', 29000, 'Virtual Account', 'Menunggu'],
  ['INV-00888', 'Dewi Anggraini', 'Pro', '21 Okt 2026', 19000, 'QRIS', 'Berhasil'],
  ['INV-00887', 'Rudi Pratama', 'Pro 3 Bulan', '21 Okt 2026', 75000, 'Transfer Bank', 'Berhasil'],
  ['INV-00886', 'Nia Kurnia', 'Pro', '20 Okt 2026', 29000, 'QRIS', 'Gagal'],
  ['INV-00885', 'Joko Susilo', 'Pro', '19 Okt 2026', 29000, 'Transfer Bank', 'Dikembalikan'],
  ['INV-00884', 'Maya Sari', 'Pro Max', '18 Okt 2026', 59000, 'Virtual Account', 'Berhasil']
];
const T = [
  ['TKT-1048', 'Budi Hartono', 'Pembayaran berhasil tetapi paket belum aktif', '25 Okt 2026', 'Tinggi', 'Baru'],
  ['TKT-1047', 'Rina Marlina', 'Kesulitan masuk ke akun', '25 Okt 2026', 'Sedang', 'Baru'],
  ['TKT-1046', 'Hendra Gunawan', 'Pertanyaan mengenai batas paket', '24 Okt 2026', 'Rendah', 'Sedang ditangani'],
  ['TKT-1045', 'Yuni Astuti', 'Permintaan bantuan penggunaan aplikasi', '24 Okt 2026', 'Rendah', 'Menunggu balasan'],
  ['TKT-1044', 'Rudi Pratama', 'Pembayaran berhasil tetapi paket belum aktif', '23 Okt 2026', 'Tinggi', 'Sedang ditangani'],
  ['TKT-1043', 'Agus Prasetyo', 'Kesulitan masuk ke akun', '22 Okt 2026', 'Sedang', 'Selesai'],
  ['TKT-1042', 'Dewi Anggraini', 'Pertanyaan mengenai batas paket', '21 Okt 2026', 'Rendah', 'Selesai']
];
const PLANS = [
  { n: 'Free', price: 'Rp0', per: '', u: 930, f: ['Maksimal 30 produk', 'Riwayat transaksi 30 hari', 'Laporan sederhana', 'Satu pengguna'] },
  { n: 'Pro', price: 'Rp29.000', per: '/bulan', u: 290, hero: 1, f: ['Maksimal 200 produk', 'Riwayat transaksi satu tahun', 'Laporan lengkap dan grafik penjualan', 'Ekspor PDF dan Excel', 'Peringatan stok menipis', 'Dua pengguna', 'Analisis produk terlaris'] },
  { n: 'Pro Max', price: 'Rp59.000', per: '/bulan', u: 30, f: ['Produk tidak terbatas', 'Riwayat transaksi tidak terbatas', 'Laporan dan analisis bisnis lanjutan', 'Ekspor PDF dan Excel', 'Lima pengguna', 'Pengaturan hak akses', 'Backup dan dukungan prioritas'] }
];
const DIST = [['Free', 930, '#CBD5E1', 'Rp0'], ['Pro', 290, '#86EFAC', 'Rp29.000/bulan'], ['Pro Max', 30, '#15803D', 'Rp59.000/bulan']];
const TOTAL = DIST.reduce((s, d) => s + d[1], 0);
const RANGES = {
  '7': {
    users: [['Sen', 1212, 'Senin, 19 Okt'], ['Sel', 1220, 'Selasa, 20 Okt'], ['Rab', 1226, 'Rabu, 21 Okt'], ['Kam', 1233, 'Kamis, 22 Okt'], ['Jum', 1240, 'Jumat, 23 Okt'], ['Sab', 1246, 'Sabtu, 24 Okt'], ['Min', 1250, 'Minggu, 25 Okt']],
    rev: [['Sen', 220000, 59000, 'Senin, 19 Okt'], ['Sel', 300000, 118000, 'Selasa, 20 Okt'], ['Rab', 250000, 59000, 'Rabu, 21 Okt'], ['Kam', 360000, 59000, 'Kamis, 22 Okt'], ['Jum', 410000, 118000, 'Jumat, 23 Okt'], ['Sab', 470000, 177000, 'Sabtu, 24 Okt'], ['Min', 280000, 59000, 'Minggu, 25 Okt']],
    note: 'Pembayaran masuk per hari, 19–25 Okt 2026 (contoh).'
  },
  '30': {
    users: [['26 Sep', 1130, '26 Sep 2026'], ['3 Okt', 1165, '3 Okt 2026'], ['10 Okt', 1195, '10 Okt 2026'], ['17 Okt', 1225, '17 Okt 2026'], ['25 Okt', 1250, '25 Okt 2026']],
    rev: [['Mgg 1', 1600000, 413000, '26 Sep–2 Okt'], ['Mgg 2', 1700000, 472000, '3–9 Okt'], ['Mgg 3', 1800000, 472000, '10–16 Okt'], ['Mgg 4', 1880000, 413000, '17–25 Okt']],
    note: 'Pembayaran masuk per minggu, 30 hari terakhir (contoh).'
  },
  '6m': {
    users: [['Mei', 640, 'Mei 2026'], ['Jun', 790, 'Jun 2026'], ['Jul', 910, 'Jul 2026'], ['Agu', 1020, 'Agu 2026'], ['Sep', 1150, 'Sep 2026'], ['Okt', 1250, 'Okt 2026']],
    rev: [['Mei', 2100000, 354000, 'Mei 2026'], ['Jun', 3300000, 590000, 'Jun 2026'], ['Jul', 4500000, 885000, 'Jul 2026'], ['Agu', 5600000, 1180000, 'Agu 2026'], ['Sep', 6300000, 1475000, 'Sep 2026'], ['Okt', 6980000, 1770000, 'Okt 2026']],
    note: 'Oktober 2026 termasuk promo Rp19.000 untuk bulan pertama paket Pro (contoh).'
  }
};
const BADGE = {
  acc: { Aktif: '', Menunggu: 'w', Nonaktif: 'n' },
  sub: { Aktif: '', 'Menunggu pembayaran': 'w', Berakhir: 'n' },
  pay: { Berhasil: '', Menunggu: 'w', Gagal: 'e', Dikembalikan: 'n' },
  pri: { Tinggi: 'e', Sedang: 'w', Rendah: 'n' },
  tk: { Baru: 'b', 'Sedang ditangani': 'w', 'Menunggu balasan': 'n', Selesai: '' },
  plan: { Free: 'n', Pro: '', 'Pro Max': 'b', 'Pro 3 Bulan': '' }
};
const bd = (k, v) => `<span class="badge ${BADGE[k][v] ?? ''}">${v}</span>`;
const act = (a, i, t = 'Detail') => `<button class="link" data-a="${a}" data-i="${i}">${t}</button>`;
const td = (l, v) => `<td data-label="${l}">${v}</td>`;

/* ---------- Baris tabel ---------- */
const userRow = (u, i) => `<tr>${td('ID', `<b>${u[0]}</b>`)}${td('Nama', u[1])}${td('Usaha', u[2])}${td('Email', u[3])}${td('Paket', bd('plan', u[4]))}${td('Bergabung', u[5])}${td('Status', bd('acc', u[6]))}${td('Aksi', act('user', i))}</tr>`;
const subRow = (s, i) => `<tr>${td('ID', `<b>${s[0]}</b>`)}${td('Pelanggan', s[1])}${td('Paket', bd('plan', s[2]))}${td('Mulai', s[3])}${td('Berakhir', s[4])}${td('Status', bd('sub', s[5]))}${td('Aksi', act('sub', i))}</tr>`;
const payRow = (p, i) => `<tr>${td('ID Transaksi', `<b>${p[0]}</b>`)}${td('Pelanggan', p[1])}${td('Paket', bd('plan', p[2]))}${td('Tanggal', p[3])}${td('Nominal', `<b>${rp(p[4])}</b>`)}${td('Metode', p[5])}${td('Status', bd('pay', p[6]))}${td('Aksi', act('pay', i))}</tr>`;
const tkRow = (t, i) => `<tr>${td('ID Tiket', `<b>${t[0]}</b>`)}${td('Pelanggan', t[1])}${td('Subjek', t[2])}${td('Tanggal', t[3])}${td('Prioritas', bd('pri', t[4]))}${td('Status', bd('tk', t[5]))}${td('Aksi', act('tk', i, 'Buka'))}</tr>`;
const fill = (sel, rows, cols) => { $(sel).innerHTML = rows || `<tr><td colspan="${cols}" class="empty">Tidak ada data yang cocok.</td></tr>`; };
const idx = (arr) => arr.map((x, i) => [x, i]);

/* ---------- Kartu statistik ---------- */
const stat = ([l, v, n, i, t, tr]) => `<article class="card stat"><div class="ic ${t}">${ic(i)}</div><div><p class="l">${l}</p><p class="v">${v}</p><p class="n">${n}</p>${tr ? `<p class="trend">${tr}</p>` : ''}</div></article>`;
$('#dash-stats').innerHTML = [
  ['Total Pengguna', '1.250', 'Total akun yang terdaftar', 'users', 'g', '↑ +8,7% dari bulan lalu'],
  ['Pengguna Aktif', '980', 'Pengguna aktif pada periode contoh', 'activity', '', `${pct(980, 1250)} dari total pengguna`],
  ['Subscription Berbayar', '320', 'Pelanggan paket Pro dan Pro Max', 'repeat', '', '↑ +27 dari bulan lalu'],
  ['Pendapatan Berlangganan', 'Rp8.750.000', 'Pendapatan Oktober 2026 (contoh)', 'money', 'g', '↑ +12,5% dari bulan lalu']
].map(stat).join('');
$('#user-stats').innerHTML = [['Total Pengguna', '1.250', 'Semua akun terdaftar', 'users', 'g'], ['Aktif', '980', pct(980, 1250) + ' dari total', 'activity', ''], ['Menunggu', '47', 'Perlu verifikasi atau pembayaran', 'alert', 'w'], ['Nonaktif', '223', 'Akun tidak aktif', 'users', '']].map(stat).join('');
$('#sub-stats').innerHTML = [['Subscription Aktif', '320', 'Pro dan Pro Max', 'repeat', 'g'], ['Paket Free', '930', pct(930, 1250) + ' dari pengguna', 'users', ''], ['Paket Pro', '290', 'Rp29.000/bulan', 'repeat', ''], ['Paket Pro Max', '30', 'Rp59.000/bulan', 'repeat', '']].map(stat).join('');
$('#pay-stats').innerHTML = [['Pembayaran Berhasil', 'Rp8.750.000', 'Oktober 2026 (contoh)', 'money', 'g'], ['Menunggu', '14', 'Belum dikonfirmasi', 'alert', 'w'], ['Gagal', '9', 'Perlu tindak lanjut', 'alert', ''], ['Pengembalian Dana', '3', 'Transaksi dikembalikan', 'card', '']].map(stat).join('');
$('#tk-stats').innerHTML = [['Tiket Baru', '12', 'Belum ditangani', 'chat', 'w'], ['Sedang Ditangani', '8', 'Dalam proses', 'chat', ''], ['Menunggu Balasan', '5', 'Menunggu pelanggan', 'chat', ''], ['Selesai', '146', 'Bulan ini', 'chat', 'g']].map(stat).join('');
$('#tk-sum').innerHTML = [['12', 'Tiket baru'], ['8', 'Sedang ditangani'], ['5', 'Menunggu balasan'], ['146', 'Selesai']].map(([n, l]) => `<div><b>${n}</b><span>${l}</span></div>`).join('');

/* ---------- Grafik ---------- */
function niceStep(range) {
  const raw = range / 4, mag = 10 ** Math.floor(Math.log10(raw));
  return [1, 2, 2.5, 5, 10].map((m) => m * mag).find((s) => s >= raw);
}
function lineChart(el, data) {
  const W = el.clientWidth || 520, H = innerWidth < 720 ? 250 : 320, L = 52, R = 16, T0 = 16, B = 32;
  const vs = data.map((d) => d[1]), mn = Math.min(...vs), mx = Math.max(...vs);
  const step = niceStep(Math.max(mx - mn, 1)), lo = Math.floor(mn / step) * step, hi = Math.ceil(mx / step) * step || lo + step;
  const gap = (W - L - R) / (data.length - 1);
  const x = (i) => L + i * gap, y = (v) => T0 + (hi - v) / (hi - lo) * (H - T0 - B);
  let s = `<svg viewBox="0 0 ${W} ${H}" width="${W}" height="${H}" role="img" aria-label="Grafik pertumbuhan pengguna">`;
  for (let v = lo; v <= hi + 1e-9; v += step) {
    s += `<line x1="${L}" x2="${W - R}" y1="${y(v)}" y2="${y(v)}" stroke="#E2E8F0"/><text x="${L - 8}" y="${y(v) + 4}" text-anchor="end" font-size="12" fill="#64748B">${Math.round(v).toLocaleString('id-ID')}</text>`;
  }
  const pts = data.map((d, i) => `${x(i)},${y(d[1])}`).join(' ');
  s += `<polygon points="${x(0)},${H - B} ${pts} ${x(data.length - 1)},${H - B}" fill="#DCFCE7" opacity=".7"/><polyline points="${pts}" fill="none" stroke="#16A34A" stroke-width="2.5" stroke-linejoin="round"/>`;
  data.forEach((d, i) => { s += `<circle cx="${x(i)}" cy="${y(d[1])}" r="4.5" fill="#fff" stroke="#16A34A" stroke-width="2.5"/><text x="${x(i)}" y="${H - 10}" text-anchor="middle" font-size="12" fill="#64748B">${d[0]}</text>`; });
  data.forEach((d, i) => { s += `<rect class="hit" data-i="${i}" x="${x(i) - gap / 2}" y="${T0}" width="${gap}" height="${H - T0 - B}" fill="transparent" tabindex="0"/>`; });
  el.innerHTML = s + '</svg><div class="tip"></div>';
  const tip = $('.tip', el);
  $$('.hit', el).forEach((r) => {
    const show = () => { const i = +r.dataset.i; tip.textContent = `${data[i][2]} · ${data[i][1].toLocaleString('id-ID')} pengguna`; tip.style.left = Math.min(Math.max(x(i), 80), W - 80) + 'px'; tip.style.top = y(data[i][1]) + 'px'; tip.classList.add('on'); };
    const hide = () => tip.classList.remove('on');
    r.addEventListener('mouseenter', show); r.addEventListener('focus', show); r.addEventListener('mouseleave', hide); r.addEventListener('blur', hide);
  });
}
function revenueChart(data) {
  const mx = Math.max(...data.map((d) => d[1] + d[2])), nice = niceStep(mx) * 4;
  $('#rev-y').innerHTML = [1, .75, .5, .25, 0].map((f) => `<span>${f ? short(nice * f) : '0'}</span>`).join('');
  $('#rev-cols').innerHTML = data.map(([l, p, m, t]) => {
    const h = ((p + m) / nice * 100).toFixed(1) + '%';
    return `<div class="col" tabindex="0" style="--h:${h}" aria-label="${t}: ${rp(p + m)}"><span class="tip">${t}<br>Pro: ${rp(p)}<br>Pro Max: ${rp(m)}<br><b>Total: ${rp(p + m)}</b></span><div class="stk" style="height:${h}"><b class="p" style="height:${p / (p + m) * 100}%"></b><b class="m" style="height:${m / (p + m) * 100}%"></b></div><em>${l}</em></div>`;
  }).join('');
}
let range = '6m';
function charts(r = range) {
  range = r;
  $$('[data-r]').forEach((b) => { b.classList.toggle('on', b.dataset.r === r); b.setAttribute('aria-pressed', b.dataset.r === r); });
  lineChart($('#lc-users'), RANGES[r].users);
  revenueChart(RANGES[r].rev);
  $('#rev-note').textContent = RANGES[r].note;
}
function donut() {
  let a = 0;
  const g = DIST.map(([, v, c]) => { const s = a; a += v / TOTAL * 360; return `${c} ${s}deg ${a}deg`; }).join(',');
  $('#donut').style.background = `conic-gradient(${g})`;
  $('#lg').innerHTML = DIST.map(([n, v, c, p]) => `<div><span class="sw" style="background:${c}"></span><span class="g"><b>${n}</b><small>${p}</small></span><span><b>${v.toLocaleString('id-ID')}</b><small>${pct(v, TOTAL)}</small></span></div>`).join('');
}

/* ---------- Render tabel + filter ---------- */
function renderUsers() {
  const q = $('#u-q').value.trim().toLowerCase(), p = $('#u-plan').value, s = $('#u-st').value;
  const r = idx(U).filter(([u]) => (!p || u[4] === p) && (!s || u[6] === s) && (!q || (u[0] + u[1] + u[2] + u[3]).toLowerCase().includes(q)));
  fill('#u-tbl', r.map(([u, i]) => userRow(u, i)).join(''), 8);
  $('#u-count').textContent = `Menampilkan ${r.length} dari ${U.length} pengguna contoh`;
}
function renderSubs() {
  const p = $('#s-plan').value, s = $('#s-st').value;
  fill('#s-tbl', idx(S).filter(([x]) => (!p || x[2] === p) && (!s || x[5] === s)).map(([x, i]) => subRow(x, i)).join(''), 7);
}
function renderPays() {
  const q = $('#p-q').value.trim().toLowerCase(), d = $('#p-date').value, p = $('#p-plan').value, m = $('#p-method').value, s = $('#p-st').value;
  const ok = (x) => (!q || x[0].toLowerCase().includes(q)) && (!p || x[2] === p) && (!m || x[5] === m) && (!s || x[6] === s) && (d !== '7' || 25 - parseInt(x[3], 10) <= 6);
  fill('#p-tbl', idx(P).filter(([x]) => ok(x)).map(([x, i]) => payRow(x, i)).join(''), 8);
}
let tkFilter = '';
function renderTickets() {
  fill('#t-tbl', idx(T).filter(([t]) => !tkFilter || t[5] === tkFilter).map(([t, i]) => tkRow(t, i)).join(''), 7);
}
$('#d-users').innerHTML = idx(U).slice(0, 6).map(([u, i]) => userRow(u, i)).join('');
$('#d-pays').innerHTML = idx(P).slice(0, 6).map(([x, i]) => payRow(x, i)).join('');
$('#d-tk').innerHTML = idx(T).slice(0, 4).map(([t, i]) => tkRow(t, i)).join('');
$('#exp').innerHTML = EXP.map(([n, p, d, l]) => `<li><div class="grow"><b>${n}</b><small>${p} · berakhir ${d}</small></div><span class="badge w">${l}</span></li>`).join('');
['u-q', 'u-plan', 'u-st'].forEach((id) => $('#' + id).addEventListener('input', renderUsers));
['s-plan', 's-st'].forEach((id) => $('#' + id).addEventListener('input', renderSubs));
['p-q', 'p-date', 'p-plan', 'p-method', 'p-st'].forEach((id) => $('#' + id).addEventListener('input', renderPays));
$('#t-tabs').addEventListener('click', (e) => {
  const b = e.target.closest('[data-f]'); if (!b) return;
  tkFilter = b.dataset.f; $$('#t-tabs button').forEach((x) => x.classList.toggle('on', x === b)); renderTickets();
});

/* ---------- Paket ---------- */
$('#plan-cards').innerHTML = PLANS.map((p, i) => `<article class="card pc${p.hero ? ' hero' : ''}">${p.hero ? '<span class="badge tag">Paling banyak dipilih</span>' : ''}<h2>${p.n}</h2><div class="price">${p.price}<small>${p.per}</small></div><p class="sub" style="margin:0">${p.u.toLocaleString('id-ID')} pengguna contoh</p><ul>${p.f.map((f) => `<li>${f}</li>`).join('')}</ul><div class="acts">${act('plan', i, 'Detail Paket')}${act('editplan', i, 'Edit Paket')}</div></article>`).join('');

/* ---------- Modal ---------- */
const dlg = $('#dlg');
const modal = (title, html) => { $('#dlg-t').textContent = title; $('#dlg-b').innerHTML = html; dlg.showModal(); };
const dl = (rows) => rows.map(([k, v]) => `<div class="dl"><span>${k}</span><span>${v}</span></div>`).join('');
const note = '<p class="sample">Data contoh untuk tampilan desain. Tidak ada perubahan yang disimpan.</p>';
dlg.addEventListener('click', (e) => { if (e.target === dlg) dlg.close(); });
$('#dlg-x').addEventListener('click', () => dlg.close());
function toast(m) { const t = $('#toast'); t.textContent = m; t.classList.add('show'); clearTimeout(toast.id); toast.id = setTimeout(() => t.classList.remove('show'), 3000); }

const MODALS = {
  user(i) {
    const u = U[i], paid = u[4] !== 'Free';
    modal('Detail Pengguna', `<div class="tabs" data-for="um"><button class="on" data-t="0">Profil</button><button data-t="1">Status Akun</button></div>
      <div id="um-0">${dl([['ID Pengguna', u[0]], ['Nama', u[1]], ['Nama usaha', u[2]], ['Email', u[3]], ['Tanggal bergabung', u[5]]])}</div>
      <div id="um-1" hidden>${dl([['Status akun', bd('acc', u[6])], ['Paket', bd('plan', u[4])], ['Status subscription', paid ? bd('sub', u[6] === 'Menunggu' ? 'Menunggu pembayaran' : 'Aktif') : 'Tidak ada langganan berbayar'], ['Mulai', u[5]]])}</div>${note}`);
  },
  sub(i) {
    const s = S[i];
    modal('Detail Langganan', dl([['ID langganan', s[0]], ['Pelanggan', s[1]], ['Paket', bd('plan', s[2])], ['Harga', rp(PRICE[s[2]]) + '/bulan'], ['Periode mulai', s[3]], ['Periode berakhir', s[4]], ['Status', bd('sub', s[5])]]) + note);
  },
  pay(i) {
    const p = P[i], fee = Math.round(p[4] * 0.007);
    modal('Detail Transaksi', dl([['ID transaksi', p[0]], ['Pelanggan', p[1]], ['Paket', bd('plan', p[2])], ['Tanggal pembayaran', p[3]], ['Nominal', rp(p[4])], ['Metode', p[5]], ['Status', bd('pay', p[6])], ['Dana bersih (asumsi biaya 0,7%)', p[6] === 'Berhasil' ? rp(p[4] - fee) : '-']]) + '<p class="sample">Data contoh. Tidak terhubung ke payment gateway dan tidak memproses refund.</p>');
  },
  tk(i) {
    const t = T[i];
    modal('Tiket ' + t[0], dl([['Pelanggan', t[1]], ['Tanggal', t[3]], ['Prioritas', bd('pri', t[4])], ['Status', bd('tk', t[5])]]) + `<div class="msg"><small>${t[1]}</small>Halo, saya butuh bantuan: ${t[2].toLowerCase()}.</div><div class="msg me"><small>Tim Dukungan (contoh)</small>Terima kasih, kami sedang memeriksa laporan Anda.</div>${note}`);
  },
  plan(i) {
    const p = PLANS[i];
    modal('Paket ' + p.n, dl([['Harga', p.price + p.per], ['Pengguna contoh', p.u.toLocaleString('id-ID')]]) + `<ul>${p.f.map((f) => `<li>${f}</li>`).join('')}</ul>${note}`);
  },
  editplan(i) {
    const p = PLANS[i];
    modal('Edit Paket ' + p.n, `<div class="fld"><label for="ep-h">Harga per bulan</label><input id="ep-h" value="${p.price}"></div><div class="fld"><label for="ep-f">Fitur utama</label><input id="ep-f" value="${p.f[0]}"></div><p class="sample">Mode desain: harga dan fitur tidak benar-benar diubah.</p><div class="modal-f"><button class="btn line" data-a="close">Batal</button><button class="btn" data-a="save">Simpan Perubahan</button></div>`);
  },
  save() { dlg.open && dlg.close(); toast('Mode desain: tidak ada data yang disimpan.'); },
  close() { dlg.close(); },
  addadmin() { toast('Mode desain: admin belum dibuat. Fitur ini dihubungkan ke database pada tahap berikutnya.'); }
};

/* ---------- Pengaturan ---------- */
const fld = (id, l, v = '', extra = '') => `<div class="fld"><label for="${id}">${l}</label><input id="${id}" value="${v}" ${extra}></div>`;
const chk = (l, on) => `<label class="chk"><input type="checkbox" ${on ? 'checked' : ''}>${l}</label>`;
const saveBtn = '<button class="btn" data-a="save" type="button">Simpan Perubahan</button>';
$('#set-0').innerHTML = `<div class="fg">${fld('sp-n', 'Nama platform', 'WarungKasir')}${fld('sp-m', 'Email dukungan', 'dukungan@example.com')}</div><div class="fg"><div class="fld"><label for="sp-z">Zona waktu</label><select id="sp-z"><option>WIB (UTC+7)</option><option>WITA (UTC+8)</option><option>WIT (UTC+9)</option></select></div>${fld('sp-c', 'Mata uang', 'Rupiah (IDR)', 'readonly')}</div>${saveBtn}`;
$('#set-1').innerHTML = `<div class="fg">${fld('pa-n', 'Nama', WK.name || 'Administrator')}${fld('pa-u', 'Username login', WK.email || '', 'readonly')}</div>${fld('pa-r', 'Peran', MASTER ? 'Admin Master' : 'Admin', 'readonly')}<div class="fg"><div class="fld"><label for="pa-p">Kata sandi baru</label><input id="pa-p" type="password" autocomplete="new-password"></div><div class="fld"><label for="pa-c">Konfirmasi kata sandi</label><input id="pa-c" type="password" autocomplete="new-password"></div></div>${saveBtn}`;
$('#set-2').innerHTML = chk('Pembayaran berhasil tetapi paket belum aktif', 1) + chk('Tiket dengan prioritas tinggi', 1) + chk('Langganan akan berakhir dalam 7 hari', 1) + chk('Pengguna baru mendaftar', 0) + chk('Ringkasan harian lewat email', 0) + saveBtn;
$('#set-3').innerHTML = MASTER
  ? `<p class="sub" style="margin-top:0">Hanya akun master yang dapat menambah admin. Admin lain tidak melihat menu ini.</p><div class="fg">${fld('na-n', 'Nama lengkap', '', 'placeholder="Nama admin baru"')}${fld('na-u', 'Username (diisi di kolom email saat login)', '', 'placeholder="admin2@warungkasir.local"')}</div><div class="fg"><div class="fld"><label for="na-p">Kata sandi sementara</label><input id="na-p" type="password" autocomplete="new-password"></div><div class="fld"><label for="na-r">Peran</label><select id="na-r"><option>Admin</option></select></div></div><button class="btn" data-a="addadmin" type="button">Tambah Admin</button>
     <h2 style="margin:22px 0 10px">Daftar Admin</h2><div class="wrap"><table class="tbl"><thead><tr><th>Nama</th><th>Username</th><th>Peran</th></tr></thead><tbody><tr>${td('Nama', 'Administrator')}${td('Username', 'admin@warungkasir.local')}${td('Peran', '<span class="badge">Master</span>')}</tr></tbody></table></div>`
  : '<div class="lock"><b>Akses dibatasi.</b> Hanya akun master yang dapat menambah admin baru.</div>';
$('#set-4').innerHTML = `<div class="fg"><div class="fld"><label for="tp-d">Kepadatan tabel</label><select id="tp-d"><option>Nyaman</option><option>Ringkas</option></select></div><div class="fld"><label for="tp-b">Bahasa</label><select id="tp-b"><option>Indonesia</option></select></div></div>${chk('Tampilkan label "Data ilustrasi"', 1)}${saveBtn}`;
$('#set-5').innerHTML = dl([['Email dukungan', 'dukungan@example.com'], ['Jam layanan', 'Senin–Jumat, 08.00–17.00 WIB'], ['Versi panel', 'Desain frontend (belum terhubung database)'], ['Dokumentasi', 'Akan tersedia pada tahap berikutnya']]);

/* ---------- Navigasi ---------- */
const VIEWS = { dash: 'Dashboard Admin', users: 'Pengguna', subs: 'Langganan', pay: 'Pembayaran', plans: 'Paket', support: 'Dukungan Pelanggan', settings: 'Pengaturan Platform' };
const CRUMB = { dash: 'Dashboard' };
function go(id, tab) {
  if (!VIEWS[id]) id = 'dash';
  $$('.view').forEach((v) => v.classList.toggle('on', v.id === 'v-' + id));
  $$('.nav a').forEach((a) => { const on = a.dataset.go === id; a.classList.toggle('on', on); on ? a.setAttribute('aria-current', 'page') : a.removeAttribute('aria-current'); });
  $('#title').textContent = VIEWS[id];
  $('#crumb').textContent = 'Admin / ' + (CRUMB[id] || VIEWS[id]);
  history.replaceState(null, '', '#' + id);
  openSide(false); scrollTo(0, 0);
  if (id === 'dash') charts();
  if (tab !== undefined) $(`#v-settings .tabs [data-t="${tab}"]`).click();
}
const side = $('#side'), scrim = $('#scrim'), dd = $('#dd'), who = $('#who');
function openSide(on) { side.classList.toggle('open', on); scrim.classList.toggle('on', on); $('#open-side').setAttribute('aria-expanded', on); }
function openDd(on) { dd.hidden = !on; who.setAttribute('aria-expanded', on); }
$('#open-side').addEventListener('click', () => openSide(true));
$('#close-side').addEventListener('click', () => openSide(false));
scrim.addEventListener('click', () => openSide(false));
who.addEventListener('click', (e) => { e.stopPropagation(); openDd(dd.hidden); });

document.addEventListener('click', (e) => {
  if (!dd.contains(e.target)) openDd(false);
  const g = e.target.closest('[data-go]');
  if (g) { e.preventDefault(); openDd(false); go(g.dataset.go, g.dataset.tab); return; }
  const t = e.target.closest('.tabs [data-t]');
  if (t) {
    const box = t.closest('.tabs'), f = box.dataset.for;
    $$('[data-t]', box).forEach((b) => b.classList.toggle('on', b === t));
    $$(`[id^="${f}-"]`).forEach((p) => { p.hidden = p.id !== `${f}-${t.dataset.t}`; });
    return;
  }
  const r = e.target.closest('[data-r]');
  if (r) { charts(r.dataset.r); return; }
  const a = e.target.closest('[data-a]');
  if (a && MODALS[a.dataset.a]) MODALS[a.dataset.a](+a.dataset.i);
});
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { openSide(false); openDd(false); } });
$$('.search input').forEach((inp) => inp.addEventListener('keydown', (e) => {
  if (e.key !== 'Enter') return;
  go('users'); $('#u-q').value = e.target.value; renderUsers();
}));
$('#bell').addEventListener('click', () => modal('Notifikasi', dl([['1 pembayaran berhasil, paket belum aktif', ''], ['2 tiket prioritas tinggi menunggu', ''], ['3 langganan berakhir dalam 7 hari', '']]) + '<p class="sample">Notifikasi contoh.</p>'));
$('#logout').addEventListener('click', () => {
  fetch('api/logout.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}', credentials: 'same-origin' })
    .catch(() => {}).finally(() => { location.href = 'login.html'; });
});
let rz; addEventListener('resize', () => { clearTimeout(rz); rz = setTimeout(() => $('#v-dash').classList.contains('on') && charts(), 150); });

donut(); renderUsers(); renderSubs(); renderPays(); renderTickets();
go(location.hash.slice(1));