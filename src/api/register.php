<?php
// API Registrasi (api/register.php) — tahap 1: validasi, simpan sementara, kirim OTP ke email.
// Akun baru dibuat di api/verify-otp.php setelah kode benar.
require __DIR__ . '/../includes/owner.php';
require_once __DIR__ . '/../includes/otp.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_out(405, ['message' => 'Metode tidak diizinkan.']);
    }

    $b = read_post_json();

    // 1. Ambil & bersihkan input
    $name     = clean($b['name'] ?? '');
    $business = clean($b['business'] ?? '');
    $email    = strtolower(clean($b['email'] ?? ''));
    $phoneRaw = clean($b['phone'] ?? '');
    $password = is_string($b['password'] ?? null) ? $b['password'] : '';
    $confirm  = is_string($b['confirm'] ?? null) ? $b['confirm'] : '';
    $terms    = ($b['terms'] ?? false) === true;

    // 2. Validasi
    $errors = [];

    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errors['name'] = 'Nama lengkap harus 2–100 karakter.';
    }
    if (mb_strlen($business) < 2 || mb_strlen($business) > 120) {
        $errors['business'] = 'Nama usaha harus 2–120 karakter.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Format email tidak valid.';
    } elseif (strlen($email) > 190) {
        $errors['email'] = 'Email terlalu panjang.';
    }

    $phone = preg_replace('/[\s\-\.\(\)]/', '', $phoneRaw);
    if (!preg_match('/^\+?\d{9,15}$/', $phone)) {
        $errors['phone'] = 'Nomor HP tidak valid. Gunakan 9–15 angka.';
    } else {
        $phone = ltrim($phone, '+');
        if (str_starts_with($phone, '62')) $phone = '0' . substr($phone, 2);
    }

    if (strlen($password) < 8) {
        $errors['password'] = 'Kata sandi minimal 8 karakter.';
    } elseif (strlen($password) > 200) {
        $errors['password'] = 'Kata sandi maksimal 200 karakter.';
    }
    if ($confirm !== $password) {
        $errors['confirm'] = 'Konfirmasi kata sandi tidak sama.';
    }
    if (!$terms) {
        $errors['terms'] = 'Anda harus menyetujui Syarat dan Ketentuan.';
    }

    if ($errors) {
        json_out(400, ['message' => 'Periksa kembali data Anda.', 'errors' => $errors]);
    }

    // 3. Cek duplikasi di akun yang sudah aktif
    $dup = db_one($pdo, 'SELECT email, phone FROM users WHERE email = ? OR phone = ? LIMIT 1', [$email, $phone]);
    if ($dup) {
        if (strtolower($dup['email']) === $email) $errors['email'] = 'Email ini sudah terdaftar.';
        if ($dup['phone'] === $phone)             $errors['phone'] = 'Nomor HP ini sudah terdaftar.';
        json_out(409, ['message' => 'Email atau Nomor HP sudah digunakan.', 'errors' => $errors]);
    }

    // 4. Batasi pengiriman berulang untuk email yang sama
    $pend = db_one($pdo, 'SELECT last_sent_at FROM pending_registrations WHERE email = ?', [$email]);
    if ($pend) {
        $wait = OTP_RESEND_SECONDS - (time() - strtotime($pend['last_sent_at']));
        if ($wait > 0) {
            json_out(429, ['message' => "Tunggu {$wait} detik sebelum mencoba lagi."]);
        }
    }

    // 5. Simpan sementara + kirim OTP
    $otp = otp_generate();
    $pdo->prepare(
        'INSERT INTO pending_registrations
           (email, full_name, business_name, phone, password_hash, otp_hash, otp_expires_at, attempts, last_sent_at)
         VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ' . OTP_TTL_MINUTES . ' MINUTE), 0, NOW())
         ON DUPLICATE KEY UPDATE
           full_name = VALUES(full_name), business_name = VALUES(business_name), phone = VALUES(phone),
           password_hash = VALUES(password_hash), otp_hash = VALUES(otp_hash),
           otp_expires_at = VALUES(otp_expires_at), attempts = 0, last_sent_at = NOW()'
    )->execute([$email, $name, $business, $phone, password_hash($password, PASSWORD_DEFAULT), password_hash($otp, PASSWORD_DEFAULT)]);

    if (!otp_send_email($email, $name, $otp)) {
        json_out(500, ['message' => 'Gagal mengirim email verifikasi. Coba lagi beberapa saat.']);
    }

    json_out(200, [
        'message'  => 'Kode OTP telah dikirim ke email Anda.',
        'email'    => $email,
        'redirect' => 'otp.html',
    ]);

} catch (Throwable $ex) {
    error_log('[register] ' . $ex->getMessage());
    json_out(500, ['message' => 'Terjadi kesalahan pada server. Coba lagi nanti.']);
}