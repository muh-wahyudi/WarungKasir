(() => {
const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

const state = { q: '', status: '', category: '', page: 1 };
let categories = [];
let tab = 'artikel';

function toast(msg) {
  const t = $('#toast');
  if (!t) { alert(msg); return; }
  t.textContent = msg;
  t.classList.add('show');
  clearTimeout(toast.id);
  toast.id = setTimeout(() => t.classList.remove('show'), 3000);
}

async function loadArticleList() {
  try {
    const r = await fetch('api/admin-article.php?' + new URLSearchParams(state), { credentials: 'same-origin', cache: 'no-store' });
    if (r.status === 401) { location.href = 'login.html'; return; }
    if (r.status === 403) { location.href = 'owner-dashboard.php'; return; }
    if (!r.ok) throw new Error('HTTP ' + r.status);
    const j = await r.json();
    const STATUS = { draft: 'w', published: '', archived: 'n' };
    const LABEL = { draft: 'Draft', published: 'Published', archived: 'Archived' };

    $('#list').innerHTML = j.items.length ? j.items.map((a) => `<tr>
      <td data-label="Judul"><b>${esc(a.title)}</b><br><small class="mut">${esc(a.slug)}</small></td>
      <td data-label="Kategori">${a.category ? esc(a.category.name) : '-'}</td>
      <td data-label="Status"><span class="badge ${STATUS[a.status] || ''}">${LABEL[a.status] || a.status}</span></td>
      <td data-label="Views">${a.views}</td>
      <td data-label="Tanggal">${esc(a.published_at || a.created_at || '-')}</td>
      <td data-label="Aksi"><a class="link" href="admin-artikel-edit.php?id=${a.id}">Edit</a> <button class="link danger" data-del="${a.id}" data-title="${esc(a.title)}">Hapus</button></td>
    </tr>`).join('') : '<tr><td colspan="6" class="empty">Belum ada artikel.</td></tr>';

    const from = j.total ? (j.page - 1) * j.per + 1 : 0;
    const to = from ? from + j.items.length - 1 : 0;
    $('#pg-info').textContent = j.total ? `Menampilkan ${from}–${to} dari ${j.total}` : '';
    $('#pg-prev').disabled = j.page <= 1;
    $('#pg-next').disabled = j.page >= j.pages;

    if (j.categories) {
      categories = j.categories;
      const sel = $('#f-cat');
      sel.innerHTML = '<option value="">Semua kategori</option>' + j.categories.map((c) => `<option value="${c.id}">${esc(c.name)}</option>`).join('');
      sel.value = state.category;
      loadCategoryList();
    }
  } catch (e) {
    $('#list').innerHTML = '<tr><td colspan="6" class="empty">Gagal memuat artikel.</td></tr>';
  }
}

function loadCategoryList() {
  const q = ($('#kat-q')?.value || '').trim().toLowerCase();
  const filtered = categories.filter((c) => !q || c.name.toLowerCase().includes(q) || c.slug.toLowerCase().includes(q));
  $('#kat-list').innerHTML = filtered.length ? filtered.map((c) => `<tr>
    <td data-label="Nama"><b>${esc(c.name)}</b></td>
    <td data-label="Slug"><code>${esc(c.slug)}</code></td>
    <td data-label="Deskripsi">${esc(c.description || '-')}</td>
    <td data-label="Urutan">${c.sort_order || 0}</td>
    <td data-label="Aksi"><button class="link" data-edit-kat="${c.id}">Edit</button> <button class="link danger" data-del-kat="${c.id}" data-name="${esc(c.name)}">Hapus</button></td>
  </tr>`).join('') : '<tr><td colspan="5" class="empty">Belum ada kategori.</td></tr>';
}

async function loadCategories() {
  try {
    const r = await fetch('api/admin-category.php', { credentials: 'same-origin', cache: 'no-store' });
    if (!r.ok) throw new Error('HTTP ' + r.status);
    const j = await r.json();
    categories = j.items || [];
    loadCategoryList();
    return categories;
  } catch {
    $('#kat-list').innerHTML = '<tr><td colspan="5" class="empty">Gagal memuat kategori.</td></tr>';
    return [];
  }
}

function openCategoryModal(cat = null) {
  const isEdit = !!cat;
  modal(isEdit ? 'Edit Kategori' : 'Tambah Kategori', `
    <form id="kat-form" data-id="${cat ? cat.id : ''}" novalidate>
      <div class="fld"><label for="kat-name">Nama kategori</label><input id="kat-name" name="name" maxlength="100" required value="${cat ? esc(cat.name) : ''}"><p class="ferr" id="e-name"></p></div>
      <div class="fld"><label for="kat-slug">Slug (URL, opsional)</label><input id="kat-slug" name="slug" maxlength="100" value="${cat ? esc(cat.slug) : ''}" placeholder="otomatis dari nama"><p class="ferr" id="e-slug"></p></div>
      <div class="fld"><label for="kat-desc">Deskripsi (opsional)</label><textarea id="kat-desc" name="description" maxlength="255">${cat ? esc(cat.description || '') : ''}</textarea></div>
      <div class="fld"><label for="kat-sort">Urutan</label><input id="kat-sort" name="sort_order" type="number" min="0" max="999" value="${cat ? cat.sort_order : 0}"></div>
      <p class="ferr" id="e-form"></p>
      <div class="modal-f"><button class="btn line" type="button" data-act="close">Batal</button><button class="btn" type="submit">Simpan</button></div>
    </form>`);
  $('#kat-name').focus();
}

async function saveCategory(form) {
  $$('.ferr', form).forEach((el) => { el.textContent = ''; });
  const btn = form.querySelector('button[type=submit]');
  if (btn.disabled) return;
  btn.disabled = true; btn.textContent = 'Menyimpan…';
  const payload = {
    action: form.dataset.id ? 'update' : 'create',
    id: form.dataset.id ? +form.dataset.id : undefined,
    name: $('#kat-name').value.trim(),
    slug: $('#kat-slug').value.trim(),
    description: $('#kat-desc').value.trim(),
    sort_order: +$('#kat-sort').value || 0,
  };
  try {
    const r = await fetch('api/admin-category.php', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const j = await r.json();
    if (r.ok) {
      toast('Kategori disimpan.');
      closeModal();
      await loadCategories();
      await loadArticleList();
      return;
    }
    if (j.errors) Object.keys(j.errors).forEach((k) => { const el = $('#e-' + k); if (el) el.textContent = j.errors[k]; });
    else $('#e-form').textContent = j.message || 'Gagal menyimpan.';
  } catch {
    $('#e-form').textContent = 'Tidak dapat terhubung ke server.';
  }
  btn.disabled = false; btn.textContent = 'Simpan';
}

document.addEventListener('click', async (e) => {
  const t = e.target;

  const tb = t.closest('#tabs [data-tab]');
  if (tb) {
    tab = tb.dataset.tab;
    $$('#tabs [data-tab]').forEach((b) => b.classList.toggle('on', b.dataset.tab === tab));
    $('#t-artikel').hidden = tab !== 'artikel';
    $('#t-kategori').hidden = tab !== 'kategori';
    if (tab === 'kategori') loadCategories();
    return;
  }

  if (t.closest('#btn-add-kategori')) { openCategoryModal(); return; }

  const ek = t.closest('[data-edit-kat]');
  if (ek) {
    const cat = categories.find((c) => c.id === +ek.dataset.editKat);
    if (cat) openCategoryModal(cat);
    return;
  }

  const dk = t.closest('[data-del-kat]');
  if (dk) {
    if (!confirm(`Hapus kategori "${dk.dataset.name}"? Artikel dengan kategori ini akan menjadi "tanpa kategori".`)) return;
    dk.disabled = true;
    try {
      const r = await fetch('api/admin-category.php', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete', id: +dk.dataset.delKat }),
      });
      const j = await r.json();
      if (r.ok) { toast('Kategori dihapus.'); await loadCategories(); await loadArticleList(); return; }
      toast(j.message || 'Gagal menghapus.');
    } catch { toast('Tidak dapat terhubung ke server.'); }
    dk.disabled = false;
    return;
  }

  const del = t.closest('[data-del]');
  if (del) {
    if (!confirm(`Hapus artikel "${del.dataset.title}"? Tidak bisa dibatalkan.`)) return;
    del.disabled = true;
    try {
      const r = await fetch('api/admin-article.php', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete', id: +del.dataset.del }),
      });
      const j = await r.json();
      if (r.ok) { toast('Artikel dihapus.'); loadArticleList(); return; }
      toast(j.message || 'Gagal menghapus.');
    } catch { toast('Tidak dapat terhubung ke server.'); }
    del.disabled = false;
  }
});

document.addEventListener('submit', (e) => {
  if (e.target.id === 'kat-form') {
    e.preventDefault();
    saveCategory(e.target);
  }
});

$('#q')?.addEventListener('input', (e) => { clearTimeout(window._t); window._t = setTimeout(() => { state.q = e.target.value.trim(); state.page = 1; loadArticleList(); }, 300); });
$('#f-status')?.addEventListener('change', (e) => { state.status = e.target.value; state.page = 1; loadArticleList(); });
$('#f-cat')?.addEventListener('change', (e) => { state.category = e.target.value; state.page = 1; loadArticleList(); });
$('#pg-prev')?.addEventListener('click', () => { if (state.page > 1) { state.page--; loadArticleList(); } });
$('#pg-next')?.addEventListener('click', () => { state.page++; loadArticleList(); });
$('#kat-q')?.addEventListener('input', loadCategoryList);

const dlg = $('#dlg');
function modal(title, html) {
  if (!dlg) return;
  $('#dlg-t').textContent = title;
  $('#dlg-b').innerHTML = html;
  if (!dlg.open) dlg.showModal();
}
function closeModal() { if (dlg && dlg.open) dlg.close(); }
if (dlg) {
  dlg.addEventListener('click', (e) => { if (e.target === dlg) dlg.close(); });
  $('#dlg-x')?.addEventListener('click', closeModal);
}

loadArticleList();
})();