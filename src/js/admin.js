const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];
const rp = (n) => 'Rp' + Math.round(n).toLocaleString('id-ID');
const num = (n) => Number(n).toLocaleString('id-ID');
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const ic = (n) => `<svg class="i" aria-hidden="true"><use href="#i-${n}"/></svg>`;
const WK = window.WK || {};
const MASTER = !!WK.master;

const state = { period: '6m' };

function toast(msg) {
  const t = $('#toast'); t.textContent = msg; t.classList.add('show');
  clearTimeout(toast.id); toast.id = setTimeout(() => t.classList.remove('show'), 3000);
}

async function loadDashboard() {
  try {
    const r = await fetch('api/admin-dashboard-data.php?period=' + state.period, { credentials: 'same-origin', cache: 'no-store' });
    if (r.status === 401) { location.href = 'login.html'; return; }
    if (r.status === 403) { location.href = 'owner-dashboard.php'; return; }
    if (!r.ok) throw new Error('HTTP ' + r.status);
    const d = await r.json();
    renderDashboard(d);
  } catch (e) {
    $('#dash-stats').innerHTML = '<div class="card stat"><p class="l">Gagal memuat data. Coba refresh.</p></div>';
  }
}

function renderDashboard(d) {
  const s = d.stats;
  const planMap = {};
  d.plans.forEach((p) => planMap[p.code] = p);

  $('#dash-stats').innerHTML = [
    ['Total Pengguna', num(s.users_total), 'Akun terdaftar', 'users', 'g'],
    ['Pengguna Aktif', num(s.users_active), `${s.users_total ? Math.round(s.users_active / s.users_total * 100) : 0}% dari total`, 'activity', ''],
    ['Langganan Berbayar', num(s.subs_paid), 'Pro & Pro Max', 'repeat', ''],
    ['Pendapatan Bulanan', rp(s.revenue_monthly), 'Dari langganan aktif', 'money', 'g'],
  ].map(([l, v, n, i, t]) => `<article class="card stat"><div class="ic ${t}">${ic(i)}</div><div><p class="l">${l}</p><p class="v">${v}</p><p class="n">${n}</p></div></article>`).join('');

  $('#user-stats').innerHTML = [
    ['Total Pengguna', num(s.users_total), 'Semua akun', 'users', 'g'],
    ['Aktif', num(s.users_active), `${s.users_total ? Math.round(s.users_active / s.users_total * 100) : 0}% dari total`, 'activity', ''],
    ['Menunggu', num(s.users_pending), 'Belum aktif', 'alert', 'w'],
    ['Total Transaksi', num(s.trx_total), 'Transaksi selesai', 'receipt', ''],
  ].map(([l, v, n, i, t]) => `<article class="card stat"><div class="ic ${t}">${ic(i)}</div><div><p class="l">${l}</p><p class="v">${v}</p><p class="n">${n}</p></div></article>`).join('');

  $('#sub-stats').innerHTML = [
    ['Langganan Aktif', num(s.subs_paid + s.subs_free), 'Semua paket', 'repeat', 'g'],
    ['Paket Free', num(planMap.free?.count || 0), 'Pengguna gratis', 'users', ''],
    ['Paket Pro', num(planMap.pro?.count || 0), 'Rp29.000/bulan', 'repeat', ''],
    ['Paket Pro Max', num(planMap.promax?.count || 0), 'Rp59.000/bulan', 'repeat', ''],
  ].map(([l, v, n, i, t]) => `<article class="card stat"><div class="ic ${t}">${ic(i)}</div><div><p class="l">${l}</p><p class="v">${v}</p><p class="n">${n}</p></div></article>`).join('');

  $('#pay-stats').innerHTML = [
    ['Pendapatan Bulanan', rp(s.revenue_monthly), 'Dari langganan aktif', 'money', 'g'],
    ['Total Transaksi', num(s.trx_total), 'Transaksi selesai', 'card', ''],
    ['Total Transaksi (All)', num(s.trx_all), 'Termasuk pending', 'receipt', ''],
    ['Langganan Berbayar', num(s.subs_paid), 'Pro & Pro Max', 'repeat', ''],
  ].map(([l, v, n, i, t]) => `<article class="card stat"><div class="ic ${t}">${ic(i)}</div><div><p class="l">${l}</p><p class="v">${v}</p><p class="n">${n}</p></div></article>`).join('');

  const STATUS_MAP = { active: 'Aktif', expired: 'Berakhir', pending_payment: 'Menunggu', canceled: 'Dibatalkan' };
  const BADGE_MAP = { active: '', expired: 'n', pending_payment: 'w', canceled: 'e' };

  $('#d-users').innerHTML = d.recent_users.length ? d.recent_users.map((u) => `<tr>
    <td data-label="ID">#${u.id}</td>
    <td data-label="Nama">${esc(u.full_name)}</td>
    <td data-label="Usaha">${esc(u.business_name)}</td>
    <td data-label="Email">${esc(u.email)}</td>
    <td data-label="Paket"><span class="badge ${u.plan_name === 'Free' ? 'n' : ''}">${esc(u.plan_name)}</span></td>
    <td data-label="Bergabung">${esc(u.joined)}</td>
    <td data-label="Status"><span class="badge ${u.status === 'Aktif' ? '' : 'n'}">${esc(u.status)}</span></td>
    <td data-label="Aksi"><a href="admin.php#users" class="link">Detail</a></td>
  </tr>`).join('') : '<tr><td colspan="8" class="empty">Belum ada pengguna.</td></tr>';

  $('#d-pays').innerHTML = d.recent_pays.length ? d.recent_pays.map((p) => `<tr>
    <td data-label="ID">${esc(p.payment_ref || '-')}</td>
    <td data-label="Pelanggan">${esc(p.full_name)}</td>
    <td data-label="Paket"><span class="badge">${esc(p.plan_name)}</span></td>
    <td data-label="Tanggal">${esc(p.date || '-')}</td>
    <td data-label="Nominal"><b>${rp(p.amount)}</b></td>
    <td data-label="Metode">QRIS</td>
    <td data-label="Status"><span class="badge ${BADGE_MAP[p.status] || ''}">${STATUS_MAP[p.status] || p.status}</span></td>
    <td data-label="Aksi"><a href="admin.php#subs" class="link">Detail</a></td>
  </tr>`).join('') : '<tr><td colspan="8" class="empty">Belum ada pembayaran.</td></tr>';

  $('#exp').innerHTML = d.expiring.length ? d.expiring.map((e) => `<li>
    <div class="grow"><b>${esc(e.full_name)}</b><small>${esc(e.plan_name)} · berakhir ${esc(e.ends_at)}</small></div>
    <span class="badge w">${e.days_left} hari lagi</span>
  </li>`).join('') : '<li class="empty-li">Tidak ada langganan yang akan berakhir dalam 7 hari.</li>';

  $('#d-tk').innerHTML = '<tr><td colspan="7" class="empty">Fitur tiket dukungan belum tersedia.</td></tr>';
  $('#tk-sum').innerHTML = '<div><b>0</b><span>Tiket baru</span></div><div><b>0</b><span>Sedang ditangani</span></div><div><b>0</b><span>Menunggu balasan</span></div><div><b>0</b><span>Selesai</span></div>';

  renderChart(d.growth);
  renderDonut(d.plans);
}

function renderChart(growth) {
  const el = $('#lc-users'); if (!el) return;
  const W = el.clientWidth || 520, H = 320, L = 52, R = 16, T0 = 16, B = 32;
  if (!growth.length) { el.innerHTML = '<p class="empty">Belum ada data.</p>'; return; }
  const vs = growth.map((d) => d.value), mn = Math.min(...vs), mx = Math.max(...vs);
  const step = Math.max(1, Math.ceil((mx - mn) / 4));
  const lo = Math.floor(mn / step) * step, hi = Math.ceil(mx / step) * step || lo + step;
  const gap = (W - L - R) / Math.max(1, growth.length - 1);
  const x = (i) => L + i * gap, y = (v) => T0 + (hi - v) / (hi - lo) * (H - T0 - B);
  let s = `<svg viewBox="0 0 ${W} ${H}" width="${W}" height="${H}">`;
  for (let v = lo; v <= hi + 1e-9; v += step) {
    s += `<line x1="${L}" x2="${W - R}" y1="${y(v)}" y2="${y(v)}" stroke="#E2E8F0"/><text x="${L - 8}" y="${y(v) + 4}" text-anchor="end" font-size="12" fill="#64748B">${Math.round(v)}</text>`;
  }
  const pts = growth.map((d, i) => `${x(i)},${y(d.value)}`).join(' ');
  s += `<polygon points="${x(0)},${H - B} ${pts} ${x(growth.length - 1)},${H - B}" fill="#DCFCE7" opacity=".7"/><polyline points="${pts}" fill="none" stroke="#16A34A" stroke-width="2.5"/>`;
  growth.forEach((d, i) => { s += `<circle cx="${x(i)}" cy="${y(d.value)}" r="4" fill="#fff" stroke="#16A34A" stroke-width="2"/>`; });
  el.innerHTML = s + '</svg>';
}

function renderDonut(plans) {
  const el = $('#donut'); if (!el) return;
  const total = plans.reduce((sum, p) => sum + p.count, 0);
  if (!total) { $('#lg').innerHTML = '<p class="empty">Belum ada data.</p>'; return; }
  const colors = { free: '#CBD5E1', pro: '#86EFAC', promax: '#15803D' };
  let a = 0;
  const g = plans.map((p) => {
    const start = a;
    a += (p.count / total) * 360;
    return `${colors[p.code] || '#94A3B8'} ${start}deg ${a}deg`;
  }).join(',');
  el.style.background = `conic-gradient(${g})`;
  el.innerHTML = `<div><b>${num(total)}</b><small>pengguna</small></div>`;
  $('#lg').innerHTML = plans.map((p) => `<div><span class="sw" style="background:${colors[p.code] || '#94A3B8'}"></span><span class="g"><b>${esc(p.name)}</b><small>${p.price ? rp(p.price) + '/bln' : 'Gratis'}</small></span><span><b>${num(p.count)}</b><small>${total ? Math.round(p.count / total * 100) : 0}%</small></span></div>`).join('');
}

$('#bell')?.addEventListener('click', () => {
  toast('Notifikasi: fitur ini belum tersedia.');
});

$('#logout')?.addEventListener('click', (e) => {
  e.preventDefault();
  fetch('api/logout.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}', credentials: 'same-origin' })
    .catch(() => {}).finally(() => { location.href = 'login.html'; });
});

document.addEventListener('click', (e) => {
  const r = e.target.closest('[data-r]');
  if (r) {
    state.period = r.dataset.r;
    $$('[data-r]').forEach((b) => b.classList.toggle('on', b.dataset.r === state.period));
    loadDashboard();
  }
});

loadDashboard();
setInterval(() => { if (document.visibilityState === 'visible') loadDashboard(); }, 60000);