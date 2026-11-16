(() => {
const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

const side = $('#side'), scrim = $('#scrim'), dd = $('#dd'), who = $('#who');
const dlg = $('#dlg');

function openSide(on) {
  if (!side) return;
  side.classList.toggle('open', on);
  if (scrim) scrim.classList.toggle('on', on);
  const btn = $('#open-side');
  if (btn) btn.setAttribute('aria-expanded', on);
}
function openDd(on) {
  if (!dd) return;
  dd.hidden = !on;
  if (who) who.setAttribute('aria-expanded', on);
}
function closeModal() { if (dlg && dlg.open) dlg.close(); }
function toast(msg) {
  const t = $('#toast');
  if (!t) { alert(msg); return; }
  t.textContent = msg;
  t.classList.add('show');
  clearTimeout(toast.id);
  toast.id = setTimeout(() => t.classList.remove('show'), 3000);
}

$('#open-side')?.addEventListener('click', () => openSide(true));
$('#close-side')?.addEventListener('click', () => openSide(false));
scrim?.addEventListener('click', () => openSide(false));
who?.addEventListener('click', (e) => { e.stopPropagation(); openDd(dd.hidden); });
dlg?.addEventListener('click', (e) => { if (e.target === dlg) dlg.close(); });
$('#dlg-x')?.addEventListener('click', closeModal);

document.addEventListener('click', (e) => {
  if (dd && !dd.contains(e.target) && who && !who.contains(e.target)) openDd(false);
  if (e.target.closest('[data-act="close"]')) { closeModal(); return; }
});
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { openSide(false); openDd(false); } });

$('#logout')?.addEventListener('click', (e) => {
  e.preventDefault();
  fetch('api/logout.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}', credentials: 'same-origin' })
    .catch(() => {}).finally(() => { location.href = 'login.html'; });
});

$('#bell')?.addEventListener('click', () => {
  if (!dlg) return;
  $('#dlg-t').textContent = 'Notifikasi';
  $('#dlg-b').innerHTML = '<p>Belum ada notifikasi baru.</p><div class="modal-f"><button class="btn" type="button" data-act="close">Tutup</button></div>';
  dlg.showModal();
});

const form = $('#form');
const id = +form.dataset.id || 0;

const quill = new Quill('#editor', {
  theme: 'snow',
  modules: {
    toolbar: [
      [{ header: [2, 3, 4, false] }],
      ['bold', 'italic', 'underline', 'strike'],
      [{ list: 'ordered' }, { list: 'bullet' }],
      ['blockquote', 'code-block'],
      ['link', 'image'],
      [{ align: [] }],
      ['clean']
    ]
  }
});

async function loadCategories() {
  try {
    const r = await fetch('api/admin-article.php', { credentials: 'same-origin' });
    if (!r.ok) return;
    const j = await r.json();
    if (j.categories) {
      $('#f-cat').innerHTML = '<option value="">— Tanpa kategori —</option>' + j.categories.map((c) => `<option value="${c.id}">${esc(c.name)}</option>`).join('');
    }
  } catch {}
}

async function loadArticle() {
  if (!id) return;
  try {
    const r = await fetch('api/admin-article.php?action=get&id=' + id, { credentials: 'same-origin' });
    if (!r.ok) { toast('Artikel tidak ditemukan.'); return; }
    const a = await r.json();
    $('#f-title').value = a.title;
    $('#f-excerpt').value = a.excerpt;
    $('#f-slug').value = a.slug;
    $('#f-cat').value = a.category_id || '';
    $('#f-tags').value = (a.tags || []).join(', ');
    $('#f-status').value = a.status;
    $('#f-meta-title').value = a.meta_title || '';
    $('#f-meta-desc').value = a.meta_description || '';
    quill.root.innerHTML = a.content || '';
    if (a.cover_image) $('#cover-preview').innerHTML = `<img src="${esc(a.cover_image)}" class="cover-preview">`;
  } catch {}
}

$('#f-cover')?.addEventListener('change', async (e) => {
  const f = e.target.files[0];
  if (!f || !id) return;
  const fd = new FormData();
  fd.append('action', 'upload_cover');
  fd.append('id', id);
  fd.append('cover', f);
  try {
    const r = await fetch('api/admin-article.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    const j = await r.json();
    if (r.ok) { toast('Cover tersimpan.'); $('#cover-preview').innerHTML = `<img src="${esc(j.url)}" class="cover-preview">`; return; }
    toast(j.message || 'Gagal upload cover.');
  } catch { toast('Tidak dapat terhubung ke server.'); }
});

form?.addEventListener('submit', async (e) => {
  e.preventDefault();
  $$('.ferr').forEach((el) => { el.textContent = ''; });
  const btn = form.querySelector('button[type=submit]');
  btn.disabled = true; btn.textContent = 'Menyimpan…';

  const payload = {
    action: 'save',
    id: id || undefined,
    title: $('#f-title').value.trim(),
    excerpt: $('#f-excerpt').value.trim(),
    slug: $('#f-slug').value.trim(),
    category_id: $('#f-cat').value,
    tags: $('#f-tags').value.split(',').map((s) => s.trim()).filter(Boolean),
    status: $('#f-status').value,
    meta_title: $('#f-meta-title').value.trim(),
    meta_description: $('#f-meta-desc').value.trim(),
    content: quill.root.innerHTML
  };

  try {
    const r = await fetch('api/admin-article.php', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const j = await r.json();
    if (r.ok) {
      toast('Artikel disimpan.');
      if (!id) { location.href = 'admin-artikel-edit.php?id=' + j.id; return; }
      return;
    }
    if (j.errors) {
      Object.keys(j.errors).forEach((k) => { const el = $('#e-' + k); if (el) el.textContent = j.errors[k]; });
    }
    toast(j.message || 'Gagal menyimpan.');
  } catch {
    toast('Tidak dapat terhubung ke server.');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Simpan Artikel';
  }
});

loadCategories().then(loadArticle);
})();