(() => {
const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];
const num = (n) => Number(n).toLocaleString('id-ID');
const short = (v) => (v >= 1e6 ? (v / 1e6).toFixed(1).replace('.', ',').replace(',0', '') + ' jt' : v >= 1000 ? (v / 1000).toFixed(1).replace('.', ',').replace(',0', '') + ' rb' : String(v));
const rp = (n) => 'Rp' + Math.round(n).toLocaleString('id-ID');
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const ic = (n) => `<svg class="i" aria-hidden="true"><use href="#i-${n}"/></svg>`;
const state = { period: '6m', loaded: false, busy: false };

const STATUS_MAP = { active: 'Aktif', expired: 'Berakhir', pending_payment: 'Menunggu', canceled: 'Dibatalkan' };
const BADGE_MAP = { active: '', expired: 'n', pending_payment: 'w', canceled: 'e' };
const PLAN_COLORS = { free: '#CBD5E1', pro: '#86EFAC', promax: '#15803D' };

function niceStep(range) {
  const raw = range / 4, mag = 10 ** Math.floor(Math.log10(raw || 1));
  return [1, 2, 2.5, 5, 10].map((m) => m * mag).find((s) => s >= raw) || 1;
}

function showSkeleton() {
  const box = '<div style="min-height:96px;border-radius:14px;background:#F1F5F9;opacity:.8"></div>';
  $('#dash-stats').innerHTML = box.repeat(4);
  $('#lc-users').innerHTML = '<div style="height:240px;border-radius:14px;background:#F1F5F9;opacity:.8"></div>';
}

function showError(reason) {
  $('#dash-stats').innerHTML = `<div class="card stat" role="alert" style="grid-column:1/-1;display:flex;flex-wrap:wrap;align-items:center;gap:12px">
    <p class="l" style="margin:0">Data dashboard belum bisa dimuat. Periksa koneksi, lalu coba lagi.${reason ? `<br><small>Penyebab: ${esc(reason)}</small>` : ''}</p>
    <button type="button" data-retry style="margin-left:auto;padding:8px 14px;border-radius:10px;border:1px solid #CBD5E1;background:#fff;font:inherit;font-weight:600;cursor:pointer">Muat ulang</button>
  </div>`;
}

async function loadDashboard() {
  if (state.busy) return;
  state.busy = true;
  const stats = $('#dash-stats');
  if (!state.loaded) showSkeleton(); else stats.style.opacity = '.6';
  try {
    const r = await fetch('api/admin-dashboard-data.php?period=' + encodeURIComponent(state.period), { credentials: 'same-origin', cache: 'no-store' });
    if (r.status === 401) { location.href = 'login.html'; return; }
    if (r.status === 403) { location.href = 'owner-dashboard.php'; return; }
    if (!r.ok) throw new Error('HTTP ' + r.status);
    let data;
    try { data = await r.json(); } catch (_) { throw new Error('Respons API bukan JSON valid (cek error PHP di api/admin-dashboard-data.php)'); }
    renderDashboard(data);
    state.loaded = true;
  } catch (e) {
    // Jika auto-refresh gagal, tampilan lama dipertahankan.
    console.error('[admin-dashboard]', e);
    if (!state.loaded) showError(e && e.message);
  } finally {
    stats.style.opacity = '';
    state.busy = false;
  }
}

function renderDashboard(d) {
  const list = (v) => (Array.isArray(v) ? v : []);
  d = {
    ...d,
    recent_users: list(d.recent_users), recent_pays: list(d.recent_pays),
    expiring: list(d.expiring), growth: list(d.growth), plans: list(d.plans),
  };
  const s = { users_total: 0, users_active: 0, subs_paid: 0, revenue_monthly: 0, ...(d.stats || {}) };
  const pct = s.users_total ? Math.round(s.users_active / s.users_total * 100) : 0;

  $('#dash-stats').innerHTML = [
    ['Total pengguna', num(s.users_total), 'Akun terdaftar', 'users', 'g'],
    ['Pengguna aktif', num(s.users_active), `${pct}% dari total`, 'activity', ''],
    ['Langganan berbayar', num(s.subs_paid), 'Pro & Pro Max', 'repeat', ''],
    ['Pendapatan bulanan', rp(s.revenue_monthly), 'Dari langganan aktif', 'money', 'g'],
  ].map(([l, v, n, i, t]) => `<article class="card stat"><div class="ic ${t}">${ic(i)}</div><div><p class="l">${l}</p><p class="v">${v}</p><p class="n">${n}</p></div></article>`).join('');

  $('#d-users').innerHTML = d.recent_users.length ? d.recent_users.map((u) => `<tr>
    <td data-label="ID">#${esc(u.id)}</td>
    <td data-label="Nama"><b>${esc(u.full_name)}</b></td>
    <td data-label="Usaha">${esc(u.business_name || '-')}</td>
    <td data-label="Email">${esc(u.email)}</td>
    <td data-label="Paket"><span class="badge ${u.plan_name === 'Free' ? 'n' : ''}">${esc(u.plan_name)}</span></td>
    <td data-label="Bergabung">${esc(u.joined)}</td>
    <td data-label="Status"><span class="badge ${u.status === 'Aktif' ? '' : 'n'}">${esc(u.status)}</span></td>
  </tr>`).join('') : '<tr><td colspan="7" class="empty">Belum ada pengguna terdaftar.</td></tr>';

  $('#d-pays').innerHTML = d.recent_pays.length ? d.recent_pays.map((p) => `<tr>
    <td data-label="ID">${esc(p.payment_ref || '-')}</td>
    <td data-label="Pelanggan"><b>${esc(p.full_name)}</b></td>
    <td data-label="Paket"><span class="badge">${esc(p.plan_name)}</span></td>
    <td data-label="Tanggal">${esc(p.date || '-')}</td>
    <td data-label="Nominal"><b>${rp(p.amount)}</b></td>
    <td data-label="Metode">QRIS</td>
    <td data-label="Status"><span class="badge ${BADGE_MAP[p.status] || ''}">${esc(STATUS_MAP[p.status] || p.status)}</span></td>
  </tr>`).join('') : '<tr><td colspan="7" class="empty">Belum ada pembayaran.</td></tr>';

  $('#exp').innerHTML = d.expiring.length ? d.expiring.map((e) => {
    const left = Number(e.days_left);
    const label = left <= 0 ? 'Hari ini' : `${left} hari lagi`;
    return `<li>
      <div class="grow"><b>${esc(e.full_name)}</b><small>${esc(e.plan_name)} · berakhir ${esc(e.ends_at)}</small></div>
      <span class="badge ${left <= 2 ? 'e' : 'w'}">${label}</span>
    </li>`;
  }).join('') : '<li class="empty-li">Tidak ada langganan yang berakhir dalam 7 hari ke depan.</li>';

  renderLineChart(d.growth);
  renderDonut(d.plans);
}


// API hanya mengirim periode yang ada penggunanya (GROUP BY), jadi periode kosong
// dilengkapi di sini dengan nilai 0 agar batangnya tetap tampil.
const HARI = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const p2 = (n) => String(n).padStart(2, '0');

function fillSeries(growth, period) {
  const byKey = {};
  growth.forEach((g) => { byKey[g.label] = Number(g.value) || 0; });
  const now = new Date(), out = [];

  if (period === '6m') {
    for (let i = 5; i >= 0; i--) {
      const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
      const key = `${d.getFullYear()}-${p2(d.getMonth() + 1)}`;
      out.push({ label: BULAN[d.getMonth()], tip: `${BULAN[d.getMonth()]} ${d.getFullYear()}`, value: byKey[key] || 0 });
    }
    return out;
  }

  const n = period === '30' ? 30 : 7;
  for (let i = n - 1; i >= 0; i--) {
    const d = new Date(now.getFullYear(), now.getMonth(), now.getDate() - i);
    const key = `${d.getFullYear()}-${p2(d.getMonth() + 1)}-${p2(d.getDate())}`;
    const full = `${d.getDate()} ${BULAN[d.getMonth()]}`;
    out.push({ label: n === 7 ? HARI[d.getDay()] : full, tip: n === 7 ? `${HARI[d.getDay()]}, ${full}` : full, value: byKey[key] || 0 });
  }
  return out;
}

let lastGrowth = [];

function renderLineChart(raw) {
  const el = $('#lc-users'); if (!el) return;
  lastGrowth = raw;
  const g = fillSeries(raw, state.period);
  const n = g.length;
  const W = Math.max(el.clientWidth, 280), H = W < 480 ? 220 : 260;
  const L = W < 480 ? 40 : 48, R = 16, T = 14, B = 30;
  const iw = W - L - R, ih = H - T - B, pad = 12;
  const max = Math.max(0, ...g.map((c) => c.value));
  const top = max > 0 ? niceStep(max) * 4 : 4;
  const X = (i) => L + pad + (n === 1 ? (iw - 2 * pad) / 2 : i * (iw - 2 * pad) / (n - 1));
  const Y = (v) => T + ih - (v / top) * ih;
  // Jumlah label sumbu-x mengikuti lebar grafik; dihitung mundur dari hari terakhir
  // agar label terakhir selalu tampil dan tidak bertabrakan dengan label sebelumnya.
  const gap = state.period === '30' ? 70 : 42;
  const every = Math.max(1, Math.ceil(n / Math.max(2, Math.floor(iw / gap))));

  const grid = [0, .25, .5, .75, 1].map((f) => {
    const y = T + ih - f * ih;
    return `<line x1="${L}" x2="${W - R}" y1="${y}" y2="${y}" style="stroke:var(--bd)" stroke-width="1"/>
      <text x="${L - 8}" y="${y + 4}" text-anchor="end" font-size="12" style="fill:var(--mu)">${f ? short(Math.round(top * f)) : '0'}</text>`;
  }).join('');

  const pts = g.map((c, i) => `${X(i).toFixed(1)},${Y(c.value).toFixed(1)}`);
  const linePath = 'M' + pts.join(' L');
  const areaPath = `${linePath} L${X(n - 1).toFixed(1)},${T + ih} L${X(0).toFixed(1)},${T + ih} Z`;

  const xLabels = g.map((c, i) => ((n - 1 - i) % every === 0)
    ? `<text x="${X(i)}" y="${H - 8}" text-anchor="middle" font-size="12" style="fill:var(--mu)">${esc(c.label)}</text>` : '').join('');

  const dots = g.map((c, i) => (n <= 12 || i === n - 1)
    ? `<circle cx="${X(i)}" cy="${Y(c.value)}" r="${i === n - 1 ? 5 : 3.5}" fill="${i === n - 1 ? '#15803D' : '#fff'}" stroke="#15803D" stroke-width="2"/>` : '').join('');

  const hits = g.map((c, i) => {
    const x0 = i === 0 ? L : (X(i - 1) + X(i)) / 2;
    const x1 = i === n - 1 ? W - R : (X(i) + X(i + 1)) / 2;
    return `<rect class="hit" data-i="${i}" tabindex="0" x="${x0}" y="${T}" width="${x1 - x0}" height="${ih}" fill="transparent" aria-label="${esc(c.tip)}: ${num(c.value)} pengguna"/>`;
  }).join('');

  el.innerHTML = `<svg width="${W}" height="${H}" viewBox="0 0 ${W} ${H}" role="img" aria-label="Grafik garis pertumbuhan pengguna">
    ${grid}
    <path d="${areaPath}" fill="#86EFAC" fill-opacity=".28"/>
    <path d="${linePath}" fill="none" stroke="#15803D" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
    ${dots}
    <line id="lc-guide" y1="${T}" y2="${T + ih}" stroke="#15803D" stroke-width="1" stroke-dasharray="4 3" opacity="0"/>
    <circle id="lc-dot" r="6" fill="#15803D" stroke="#fff" stroke-width="2.5" opacity="0"/>
    ${xLabels}
    ${hits}
  </svg><div class="tip" id="lc-tip" style="bottom:auto"></div>`;

  const tip = $('#lc-tip', el), guide = $('#lc-guide', el), dot = $('#lc-dot', el);
  const show = (i) => {
    const c = g[i], x = X(i), y = Y(c.value);
    guide.setAttribute('x1', x); guide.setAttribute('x2', x); guide.setAttribute('opacity', '.6');
    dot.setAttribute('cx', x); dot.setAttribute('cy', y); dot.setAttribute('opacity', '1');
    tip.innerHTML = `${esc(c.tip)} · <b>${num(c.value)}</b> pengguna`;
    tip.style.left = Math.min(Math.max(x, 80), W - 80) + 'px';
    tip.style.top = y + 'px';
    tip.classList.add('on');
  };
  const hide = () => {
    guide.setAttribute('opacity', '0'); dot.setAttribute('opacity', '0'); tip.classList.remove('on');
  };
  $$('.hit', el).forEach((h) => {
    const i = Number(h.dataset.i);
    h.addEventListener('mouseenter', () => show(i));
    h.addEventListener('focus', () => show(i));
    h.addEventListener('blur', hide);
  });
  $('svg', el).addEventListener('mouseleave', hide);
}

let rzTimer;
window.addEventListener('resize', () => {
  clearTimeout(rzTimer);
  rzTimer = setTimeout(() => { if (state.loaded) renderLineChart(lastGrowth); }, 150);
});

let donutPlans = [], donutTotal = 0;

function renderDonut(plans) {
  const el = $('#donut'); if (!el) return;
  const total = plans.reduce((sum, p) => sum + p.count, 0);
  donutPlans = plans; donutTotal = total;
  if (!total) {
    el.style.background = '#F1F5F9';
    el.innerHTML = '<div><b>0</b><small>pengguna</small></div>';
    $('#lg').innerHTML = '<p class="empty">Belum ada data paket.</p>';
    return;
  }
  let a = 0;
  const g = plans.filter((p) => p.count > 0).map((p) => {
    const start = a;
    a += (p.count / total) * 360;
    return `${PLAN_COLORS[p.code] || '#94A3B8'} ${start}deg ${a}deg`;
  }).join(',');
  el.style.background = `conic-gradient(${g})`;
  el.setAttribute('role', 'img');
  el.setAttribute('aria-label', 'Distribusi paket: ' + plans.map((p) => `${p.name} ${p.count}`).join(', '));
  el.innerHTML = `<div><b>${num(total)}</b><small>pengguna</small></div>`;
  $('#lg').innerHTML = plans.map((p, i) => `<div data-i="${i}" tabindex="0" style="border-radius:8px;transition:background .12s"><span class="sw" style="background:${PLAN_COLORS[p.code] || '#94A3B8'}"></span><span class="g"><b>${esc(p.name)}</b><small>${p.price ? rp(p.price) + '/bln' : 'Gratis'}</small></span><span><b>${num(p.count)}</b><small>${Math.round(p.count / total * 100)}%</small></span></div>`).join('');
}

// Tooltip jumlah pengguna saat kursor di donut atau legenda (Free, Pro, Pro Max)
const gt = document.createElement('div');
gt.setAttribute('role', 'tooltip');
gt.style.cssText = 'position:fixed;z-index:100;left:0;top:0;padding:6px 10px;border-radius:10px;background:#0F172A;color:#fff;font-size:.82rem;line-height:1.45;white-space:nowrap;pointer-events:none;opacity:0;transition:opacity .12s';
document.body.appendChild(gt);

function showGt(i, x, y) {
  const p = donutPlans[i];
  if (!p) return hideGt();
  const pct = donutTotal ? Math.round(p.count / donutTotal * 100) : 0;
  gt.innerHTML = `<b>${esc(p.name)}</b><br>${num(p.count)} pengguna · ${pct}%`;
  gt.style.opacity = '1';
  const w = gt.offsetWidth, h = gt.offsetHeight;
  let l = x + 14, t = y - h - 12;
  if (l + w > window.innerWidth - 8) l = x - w - 14;
  if (l < 8) l = 8;
  if (t < 8) t = y + 16;
  gt.style.left = l + 'px'; gt.style.top = t + 'px';
}
function hideGt() { gt.style.opacity = '0'; }

function planAtAngle(e) {
  const r = $('#donut').getBoundingClientRect();
  const dx = e.clientX - (r.left + r.width / 2), dy = e.clientY - (r.top + r.height / 2);
  const dist = Math.hypot(dx, dy), R = r.width / 2;
  if (!donutTotal || dist > R || dist < R * 0.62) return -1; // di luar cincin / di lubang tengah
  let ang = Math.atan2(dx, -dy) * 180 / Math.PI;
  if (ang < 0) ang += 360;
  let a = 0;
  for (let i = 0; i < donutPlans.length; i++) {
    const sweep = donutPlans[i].count / donutTotal * 360;
    if (sweep > 0 && ang >= a && ang < a + sweep) return i;
    a += sweep;
  }
  return -1;
}

let hlRow = null;
const clearHl = () => { if (hlRow) hlRow.style.background = ''; hlRow = null; };

const donutEl = $('#donut'), legendEl = $('#lg');
if (donutEl) {
  donutEl.addEventListener('mousemove', (e) => { const i = planAtAngle(e); i < 0 ? hideGt() : showGt(i, e.clientX, e.clientY); });
  donutEl.addEventListener('mouseleave', hideGt);
}
if (legendEl) {
  legendEl.addEventListener('mousemove', (e) => {
    const row = e.target.closest('[data-i]');
    if (!row) { clearHl(); return hideGt(); }
    if (row !== hlRow) { clearHl(); hlRow = row; row.style.background = '#F1F5F9'; }
    showGt(Number(row.dataset.i), e.clientX, e.clientY);
  });
  legendEl.addEventListener('mouseleave', () => { clearHl(); hideGt(); });
  legendEl.addEventListener('focusin', (e) => {
    const row = e.target.closest('[data-i]'); if (!row) return;
    const r = row.getBoundingClientRect();
    clearHl(); hlRow = row; row.style.background = '#F1F5F9';
    showGt(Number(row.dataset.i), r.left + r.width / 2, r.top);
  });
  legendEl.addEventListener('focusout', () => { clearHl(); hideGt(); });
}

function setPeriod(p) {
  state.period = p;
  $$('[data-r]').forEach((b) => {
    const on = b.dataset.r === p;
    b.classList.toggle('on', on);
    b.setAttribute('aria-pressed', on ? 'true' : 'false');
  });
}

document.addEventListener('click', (e) => {
  if (e.target.closest('[data-retry]')) { state.loaded = false; loadDashboard(); return; }
  const r = e.target.closest('[data-r]');
  if (r && r.dataset.r !== state.period) { setPeriod(r.dataset.r); loadDashboard(); }
});

setPeriod(state.period);
loadDashboard();
setInterval(() => { if (document.visibilityState === 'visible') loadDashboard(); }, 60000);
})();