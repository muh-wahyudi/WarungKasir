(() => {
const $ = (s, r = document) => r.querySelector(s);
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
  toast.id = setTimeout(() => t.classList.remove('show'), 3200);
}

window.adminOpenSide = openSide;
window.adminOpenDd = openDd;
window.adminCloseModal = closeModal;
window.adminToast = toast;

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
})();