<?php
require __DIR__ . '/includes/owner_layout.php';
$u = owner_page_guard($pdo);
owner_layout_start($u, 'pengaturan', 'Pengaturan', 'Kelola akun, usaha, dan paket · ' . e($u['business_name']));
?>
      <style>
        .st-bar { margin-bottom: 16px; }
        .st-bar .seg { max-width: 100%; overflow-x: auto; }
        .st-bar .seg button { white-space: nowrap; }
        .st-card { max-width: 620px; }
        .st-lead { margin: -4px 0 18px; color: var(--mu); font-size: .92rem; }
        .fld textarea { width: 100%; min-height: 84px; padding: 10px 12px; border: 1.5px solid #CBD5E1; border-radius: 10px; background: #fff; font: inherit; resize: vertical; }
        .fld textarea:focus { outline: none; border-color: var(--g); box-shadow: 0 0 0 4px rgba(22,163,74,.15); }
        .fld textarea[aria-invalid=true] { border-color: var(--err); }
        .fld input[readonly] { background: var(--bg); color: var(--mu); cursor: not-allowed; }
        .st-actions { display: flex; align-items: center; gap: 12px; margin-top: 4px; }
        .pk-cur { max-width: 620px; margin-bottom: 18px; }
        .pk-cur .use { margin-top: 14px; }
        .pk-cur ul { margin: 14px 0 0; padding-left: 18px; color: #334155; font-size: .92rem; }
        .pl-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
        .pl { position: relative; display: flex; flex-direction: column; gap: 10px; }
        .pl.cur { border: 2px solid var(--g); }
        .pl h3 { margin: 0; font-size: 1.1rem; }
        .pl .price { font-size: 1.6rem; font-weight: 800; letter-spacing: -.02em; }
        .pl .price small { font-size: .9rem; font-weight: 500; color: var(--mu); }
        .pl ul { flex: 1; margin: 0; padding-left: 18px; color: #334155; font-size: .92rem; }
        .pl li { margin-bottom: 4px; }
        .pl .btn, .pl .badge { align-self: flex-start; }
        .email-row { display: flex; gap: 8px; align-items: stretch; }
        .email-row input { flex: 1; }
        .email-row .btn { white-space: nowrap; }
        @media (max-width: 900px) { .pl-grid { grid-template-columns: 1fr; } }
        @media (max-width: 720px) {
          .st-bar { flex-direction: column; align-items: stretch; }
          .st-bar .seg { display: flex; }
          .st-bar .seg button { flex: 1 1 auto; }
          .fld textarea { font-size: 16px; }
          .st-actions .btn { width: 100%; }
          .email-row { flex-direction: column; }
        }
      </style>

      <div class="note-e" id="err" role="alert" hidden></div>

      <div class="sec-h st-bar">
        <div class="seg" id="tabs" role="group" aria-label="Bagian pengaturan">
          <button type="button" data-tab="profil" class="on" aria-pressed="true">Profil Saya</button>
          <button type="button" data-tab="usaha" aria-pressed="false">Usaha</button>
          <button type="button" data-tab="keamanan" aria-pressed="false">Keamanan</button>
          <button type="button" data-tab="paket" aria-pressed="false">Paket Langganan</button>
        </div>
      </div>

      <section id="v-profil" class="card st-card" aria-labelledby="h-profil">
        <div class="card-h"><h2 id="h-profil">Profil Saya</h2></div>
        <p class="st-lead">Data akun pemilik. Email dipakai untuk masuk.</p>
        <form id="f-profil" novalidate>
          <div class="fld"><label for="p-name">Nama lengkap</label><input id="p-name" name="full_name" maxlength="100" autocomplete="name" required><p class="ferr" id="e-full_name"></p></div>
          <div class="fld">
            <label for="p-email">Email</label>
            <div class="email-row">
              <input id="p-email" type="email" readonly aria-describedby="h-email">
              <button class="btn line" type="button" id="btn-change-email">Ganti Email</button>
            </div>
            <p class="hint" id="h-email">Email dipakai untuk login. Perubahan butuh verifikasi kode OTP yang dikirim ke email baru.</p>
          </div>
          <div class="fld"><label for="p-phone">Nomor HP</label><input id="p-phone" name="phone" type="tel" inputmode="tel" maxlength="20" autocomplete="tel" required><p class="ferr" id="e-phone"></p></div>
          <p class="ferr" id="e-form-profil"></p>
          <div class="st-actions"><button class="btn" type="submit">Simpan Perubahan</button><span class="hint" id="p-since"></span></div>
        </form>
      </section>

      <section id="v-usaha" class="card st-card" aria-labelledby="h-usaha" hidden>
        <div class="card-h"><h2 id="h-usaha">Data Usaha</h2></div>
        <p class="st-lead">Nama usaha tampil di menu dan di halaman Anda.</p>
        <form id="f-usaha" novalidate>
          <div class="fld"><label for="b-name">Nama usaha atau warung</label><input id="b-name" name="business_name" maxlength="120" autocomplete="organization" required><p class="ferr" id="e-business_name"></p></div>
          <div class="fld"><label for="b-address">Alamat usaha <span class="opt">(opsional)</span></label><textarea id="b-address" name="alamat" maxlength="255" autocomplete="street-address"></textarea><p class="ferr" id="e-alamat"></p></div>
          <p class="ferr" id="e-form-usaha"></p>
          <div class="st-actions"><button class="btn" type="submit">Simpan Perubahan</button></div>
        </form>
      </section>

      <section id="v-keamanan" class="card st-card" aria-labelledby="h-keamanan" hidden>
        <div class="card-h"><h2 id="h-keamanan">Ubah Kata Sandi</h2></div>
        <p class="st-lead">Gunakan kata sandi yang kuat dan tidak dipakai di tempat lain.</p>
        <form id="f-keamanan" novalidate>
          <div class="fld"><label for="s-current">Kata sandi saat ini</label><input id="s-current" name="current" type="password" autocomplete="current-password" maxlength="200" required><p class="ferr" id="e-current"></p></div>
          <div class="fld"><label for="s-new">Kata sandi baru</label><input id="s-new" name="password" type="password" autocomplete="new-password" maxlength="200" required><p class="hint">Minimal 8 karakter.</p><p class="ferr" id="e-password"></p></div>
          <div class="fld"><label for="s-confirm">Ulangi kata sandi baru</label><input id="s-confirm" name="confirm" type="password" autocomplete="new-password" maxlength="200" required><p class="ferr" id="e-confirm"></p></div>
          <p class="ferr" id="e-form-keamanan"></p>
          <div class="st-actions"><button class="btn" type="submit">Ubah Kata Sandi</button></div>
        </form>
      </section>

      <section id="v-paket" aria-label="Paket langganan" hidden>
        <section class="card pk-cur" id="pk-cur" aria-live="polite"></section>
        <div class="sec-h"><div class="sec-t"><h2>Pilihan Paket</h2></div></div>
        <div class="pl-grid" id="pk-plans"></div>
        <p class="sub-n">Pembayaran langganan sedang disiapkan. Paket berbayar akan dapat dipilih di sini setelah tersedia.</p>
      </section>
<?php owner_layout_end(['js/pengaturan.js']); ?>