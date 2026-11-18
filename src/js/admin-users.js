(() => {
const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];
const num = (n) => Number(n).toLocaleString('id-ID');
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const ic = (n) => `<svg class="i" aria-hidden="true"><use href="#i-${n}"/></svg>`;
const MASTER = !!(window.WK || {}).master;
const state = { q: '', plan: '', status: '', page: 1 };

function toast(msg) {
  const t = $('#toast');
  if (!t) { alert(msg); return; }
  t.textContent = msg; t.classList.add('show');
  clearTimeout(toast.id); toast.id = setTimeout(() => t.classList.remove('show'), 3000);
}

async function load() {
  try {
    const r = await fetch('api/admin-users-data.php?' + new URLSearchParams(state), { credentials: 'same-origin', cache: 'no-store' });
    if (r.status === 401) { location.href = 'login.html'; return; }
    if (r.status === 403) { location.href = 'owner-dashboard.php'; return; }
    if (!r.ok) throw new Error('HTTP ' + r.status);
    const j = await r.json();
    render(j);
  } catch (e) {
    $('#u-tbl').innerHTML = '<tr><td colspan="8" class="empty">Gagal memuat pengguna. Coba refresh.</td></tr>';
  }
}

function render(j) {
  const s = j.summary;

  $('#user-stats').innerHTML = [
    ['Total Pengguna', num(s.total), 'Akun terdaftar', 'users', 'g'],
    ['Aktif', num(s.active), `${s.total ? Math.round(s.active / s.total * 100) : 0}% dari total`, 'activity', ''],
    ['Nonaktif', num(s.inactive), 'Tidak bisa login', 'alert', 'w'],
    ['Langganan Berbayar', num(s.paid), 'Pro & Pro Max', 'repeat', ''],
  ].map(([l, v, n, i, t]) => `<article class="card stat"><div class="ic ${t}">${ic(i)}</div><div><p class="l">${l}</p><p class="v">${v}</p><p class="n">${n}</p></div></article>`).join('');

  $('#u-tbl').innerHTML = j.items.length ? j.items.map((u) => `<tr>
    <td data-label="ID">#${u.id}</td>
    <td data-label="Nama"><b>${esc(u.full_name)}</b></td>
    <td data-label="Usaha">${esc(u.business_name)}</td>
    <td data-label="Email">${esc(u.email)}</td>
    <td data-label="Paket"><span class="badge ${u.plan_code === 'free' ? 'n' : ''}">${esc(u.plan_name)}</span></td>
    <td data-label="Bergabung">${esc(u.joined || '-')}</td>
    <td data-label="Status"><span class="badge ${u.status === 'active' ? '' : 'n'}">${u.status === 'active' ? 'Aktif' : 'Nonaktif'}</span></td>
    <td data-label="Aksi">${MASTER ? `<button class="link ${u.status === 'active' ? 'danger' : ''}" type="button" data-toggle="${u.id}" data-name="${esc(u.full_name)}" data-active="${u.status === 'active' ? '1' : '0'}">${u.status === 'active' ? 'Nonaktifkan' : 'Aktifkan'}</button>` : '-'}</td>
  </tr>`).join('') : '<tr><td colspan="8" class="empty">Tidak ada pengguna yang cocok.</td></tr>';

  const from = j.total ? (j.page - 1) * j.per + 1 : 0;
  const to = from ? from + j.items.length - 1 : 0;
  $('#pg-info').textContent = j.total ? `Menampilkan ${from}–${to} dari ${num(j.total)} pengguna` : '';
  $('#pg-prev').disabled = j.page <= 1;
  $('#pg-next').disabled = j.page >= j.pages;
}

document.addEventListener('click', async (e) => {
  const t = e.target.closest('[data-toggle]');
  if (!t) return;
  const id = +t.dataset.toggle;
  const name = t.dataset.name;
  const isActive = t.dataset.active === '1';
  const label = isActive ? 'nonaktifkan' : 'aktifkan';
  if (!confirm(`${isActive ? 'Nonaktifkan' : 'Aktifkan'} pengguna "${name}"?`)) return;
  t.disabled = true;
  try {
    const r = await fetch('api/admin-users-data.php', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'toggle', id }),
    });
    const j = await r.json();
    if (r.ok) { toast(j.message || 'Berhasil.'); load(); return; }
    toast(j.message || `Gagal ${label}.`);
  } catch { toast('Tidak dapat terhubung ke server.'); }
  t.disabled = false;
});

$('#u-q')?.addEventListener('input', (e) => { clearTimeout(window._t); window._t = setTimeout(() => { state.q = e.target.value.trim(); state.page = 1; load(); }, 300); });
$('#u-plan')?.addEventListener('change', (e) => { state.plan = e.target.value; state.page = 1; load(); });
$('#u-st')?.addEventListener('change', (e) => { state.status = e.target.value; state.page = 1; load(); });
$('#pg-prev')?.addEventListener('click', () => { if (state.page > 1) { state.page--; load(); } });
$('#pg-next')?.addEventListener('click', () => { state.page++; load(); });

load();
})();