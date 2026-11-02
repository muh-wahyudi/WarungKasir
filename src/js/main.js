const $ = (id) => document.getElementById(id);
const alertBox = $('alert');

function showAlert(type, msg) {
  if (!alertBox) return;
  alertBox.className = 'alert show ' + type;
  alertBox.textContent = msg;
}

async function post(url, data) {
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',
    body: JSON.stringify(data),
  });
  let body = {};
  try { body = await res.json(); } catch { /* respons bukan JSON */ }
  return { ok: res.ok, body };
}

// Tampilkan / sembunyikan kata sandi
document.querySelectorAll('.toggle').forEach((btn) => {
  btn.addEventListener('click', () => {
    const input = btn.parentElement.querySelector('input');
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.setAttribute('aria-pressed', String(show));
    btn.setAttribute('aria-label', show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
    btn.firstElementChild.classList.toggle('eye', !show);
    btn.firstElementChild.classList.toggle('eyeoff', show);
  });
});

// Konfirmasi kata sandi harus sama
const pw = $('password'), confirmPw = $('confirm');
if (pw && confirmPw) {
  const check = () => confirmPw.setCustomValidity(confirmPw.value && confirmPw.value !== pw.value ? 'Kata sandi tidak sama.' : '');
  pw.addEventListener('input', check);
  confirmPw.addEventListener('input', check);
}

// Kirim form ke backend; cegah klik ganda
function bindForm(form, url, buildData) {
  const btn = form.querySelector('.btn');
  const label = btn.textContent;
  form.addEventListener('input', (e) => e.target.removeAttribute('aria-invalid'));
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (btn.disabled) return;
    form.querySelectorAll('[aria-invalid]').forEach((el) => el.removeAttribute('aria-invalid'));
    btn.disabled = true;
    btn.textContent = 'Memproses…';
    try {
      const { ok, body } = await post(url, buildData());
      if (ok && body.redirect) {
        showAlert('success', body.message || 'Berhasil.');
        setTimeout(() => { location.href = body.redirect; }, url.includes('register') ? 1200 : 0);
        return;
      }
      const errs = body.errors || {};
      Object.keys(errs).forEach((k) => form.elements[k] && form.elements[k].setAttribute('aria-invalid', 'true'));
      const detail = Object.values(errs).join(' ');
      showAlert('error', detail || body.message || 'Terjadi kesalahan. Coba lagi.');
    } catch {
      showAlert('error', 'Tidak dapat terhubung ke server. Periksa koneksi Anda.');
    }
    btn.disabled = false;
    btn.textContent = label;
  });
}

const loginForm = $('login-form');
if (loginForm) {
  bindForm(loginForm, 'api/login.php', () => ({
    email: loginForm.email.value,
    password: loginForm.password.value,
    remember: loginForm.remember.checked,
  }));
  if (new URLSearchParams(location.search).get('registered')) {
    showAlert('success', 'Akun berhasil dibuat. Silakan masuk.');
  }
  $('forgot').addEventListener('click', (e) => {
    e.preventDefault();
    showAlert('info', 'Fitur lupa kata sandi belum tersedia karena layanan email belum terhubung.');
  });
}

const registerForm = $('register-form');
if (registerForm) {
  bindForm(registerForm, 'api/register.php', () => ({
    name: registerForm.name.value,
    business: registerForm.business.value,
    email: registerForm.email.value,
    phone: registerForm.phone.value,
    password: registerForm.password.value,
    confirm: registerForm.confirm.value,
    terms: registerForm.terms.checked,
  }));
}

// Keluar (halaman dashboard)
const logoutBtn = $('logout');
if (logoutBtn) {
  logoutBtn.addEventListener('click', async () => {
    logoutBtn.disabled = true;
    const { body } = await post('api/logout.php', {});
    location.href = body.redirect || 'login.html';
  });
}
