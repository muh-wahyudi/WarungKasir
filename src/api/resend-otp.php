<?php
// API Kirim Ulang OTP (api/resend-otp.php)
require __DIR__ . '/../includes/owner.php';
require_once __DIR__ . '/../includes/otp.php';

try {
    $b     = read_post_json();
    $email = strtolower(clean($b['email'] ?? ''));

    $p = db_one($pdo, 'SELECT * FROM pending_registrations WHERE email = ?', [$email]);
    if (!$p) {
        json_out(404, ['message' => 'Pendaftaran tidak ditemukan. Silakan daftar ulang.']);
    }

    $wait = OTP_RESEND_SECONDS - (time() - strtotime($p['last_sent_at']));
    if ($wait > 0) {
        json_out(429, ['message' => "Tunggu {$wait} detik sebelum kirim ulang."]);
    }

    $otp = otp_generate();
    $pdo->prepare(
        'UPDATE pending_registrations
         SET otp_hash = ?, otp_expires_at = DATE_ADD(NOW(), INTERVAL ' . OTP_TTL_MINUTES . ' MINUTE),
             attempts = 0, last_sent_at = NOW()
         WHERE id = ?'
    )->execute([password_hash($otp, PASSWORD_DEFAULT), $p['id']]);

    if (!otp_send_email($p['email'], $p['full_name'], $otp)) {
        json_out(500, ['message' => 'Gagal mengirim email. Coba lagi beberapa saat.']);
    }

    json_out(200, ['message' => 'Kode baru telah dikirim.']);

} catch (Throwable $ex) {
    error_log('[resend-otp] ' . $ex->getMessage());
    json_out(500, ['message' => 'Terjadi kesalahan pada server. Coba lagi nanti.']);
}