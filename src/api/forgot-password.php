<?php
// API Lupa Kata Sandi (api/forgot-password.php) — kirim kode reset ke email.
// Respons selalu sama, baik email terdaftar maupun tidak, agar email orang lain tidak bisa ditebak.
require __DIR__ . '/../includes/owner.php';
require_once __DIR__ . '/../includes/otp.php';

try {
    $b     = read_post_json();
    $email = strtolower(clean($b['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_out(400, ['message' => 'Format email tidak valid.', 'errors' => ['email' => 'Format email tidak valid.']]);
    }

    $generic = ['message' => 'Jika email terdaftar, kode reset telah dikirim.', 'email' => $email];

    $user = db_one($pdo, 'SELECT id, full_name FROM users WHERE email = ? AND is_active = 1 LIMIT 1', [$email]);
    if (!$user) json_out(200, $generic);

    $row = db_one($pdo, 'SELECT last_sent_at FROM password_resets WHERE email = ?', [$email]);
    if ($row) {
        $wait = OTP_RESEND_SECONDS - (time() - strtotime($row['last_sent_at']));
        if ($wait > 0) json_out(429, ['message' => "Tunggu {$wait} detik sebelum meminta kode lagi."]);
    }

    $otp = otp_generate();
    $pdo->prepare(
        'INSERT INTO password_resets (email, otp_hash, expires_at, attempts, last_sent_at)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ' . RESET_TTL_MINUTES . ' MINUTE), 0, NOW())
         ON DUPLICATE KEY UPDATE otp_hash = VALUES(otp_hash), expires_at = VALUES(expires_at), attempts = 0, last_sent_at = NOW()'
    )->execute([$email, password_hash($otp, PASSWORD_DEFAULT)]);

    if (!otp_send_email($email, $user['full_name'], $otp, 'reset')) {
        json_out(500, ['message' => 'Gagal mengirim email. Coba lagi beberapa saat.']);
    }

    json_out(200, $generic);

} catch (Throwable $ex) {
    error_log('[forgot-password] ' . $ex->getMessage());
    json_out(500, ['message' => 'Terjadi kesalahan pada server. Coba lagi nanti.']);
}