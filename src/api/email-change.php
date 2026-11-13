<?php
require __DIR__ . '/../includes/owner.php';
require_once __DIR__ . '/../includes/otp.php';

const EMAIL_CHANGE_TTL_MINUTES = 10;
const EMAIL_CHANGE_MAX_ATTEMPTS = 5;
const EMAIL_CHANGE_RESEND_SECONDS = 60;
const EMAIL_CHANGE_MAX_PER_DAY = 3;

function email_change_request(PDO $pdo, int $uid, array $b): void {
    $newEmail = strtolower(clean($b['new_email'] ?? ''));
    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        json_out(400, ['message' => 'Format email tidak valid.', 'errors' => ['new_email' => 'Format email tidak valid.']]);
    }
    if (strlen($newEmail) > 190) {
        json_out(400, ['message' => 'Email terlalu panjang.', 'errors' => ['new_email' => 'Email terlalu panjang.']]);
    }

    $me = db_one($pdo, 'SELECT email, full_name FROM users WHERE id = ?', [$uid]);
    if (strtolower($me['email']) === $newEmail) {
        json_out(400, ['message' => 'Email baru harus berbeda dari email saat ini.', 'errors' => ['new_email' => 'Email baru harus berbeda dari email saat ini.']]);
    }

    $dup = db_one($pdo, 'SELECT id FROM users WHERE email = ? LIMIT 1', [$newEmail]);
    if ($dup) {
        json_out(409, ['message' => 'Email ini sudah dipakai akun lain.', 'errors' => ['new_email' => 'Email ini sudah dipakai akun lain.']]);
    }

    $existing = db_one($pdo, 'SELECT last_sent_at, created_at FROM email_changes WHERE user_id = ?', [$uid]);
    if ($existing) {
        $wait = EMAIL_CHANGE_RESEND_SECONDS - (time() - strtotime($existing['last_sent_at']));
        if ($wait > 0) {
            json_out(429, ['message' => "Tunggu {$wait} detik sebelum meminta kode lagi."]);
        }
    }

    $today = (int) db_one($pdo, "SELECT COUNT(*) AS n FROM email_changes WHERE user_id = ? AND created_at > NOW() - INTERVAL 1 DAY", [$uid])['n'];
    if ($today >= EMAIL_CHANGE_MAX_PER_DAY) {
        json_out(429, ['message' => 'Terlalu banyak permintaan ganti email hari ini. Coba lagi besok.']);
    }

    $otp = otp_generate();
    $pdo->prepare(
        'INSERT INTO email_changes (user_id, old_email, new_email, otp_hash, expires_at, attempts, last_sent_at)
         VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ' . EMAIL_CHANGE_TTL_MINUTES . ' MINUTE), 0, NOW())
         ON DUPLICATE KEY UPDATE old_email = VALUES(old_email), new_email = VALUES(new_email),
           otp_hash = VALUES(otp_hash), expires_at = VALUES(expires_at), attempts = 0, last_sent_at = NOW()'
    )->execute([$uid, $me['email'], $newEmail, password_hash($otp, PASSWORD_DEFAULT)]);

    if (!otp_send_email($newEmail, $me['full_name'], $otp, 'email_change')) {
        json_out(500, ['message' => 'Gagal mengirim email verifikasi ke alamat baru. Coba lagi beberapa saat.']);
    }

    json_out(200, ['message' => 'Kode verifikasi telah dikirim ke email baru.', 'new_email' => $newEmail]);
}

function email_change_verify(PDO $pdo, int $uid, array $b): void {
    $otp = preg_replace('/\D/', '', is_string($b['otp'] ?? null) ? $b['otp'] : '');
    if (strlen($otp) !== 6) {
        json_out(400, ['message' => 'Kode OTP harus 6 digit.', 'errors' => ['otp' => 'Kode OTP harus 6 digit.']]);
    }

    $row = db_one($pdo, 'SELECT id, old_email, new_email, otp_hash, expires_at, attempts FROM email_changes WHERE user_id = ?', [$uid]);
    if (!$row) {
        json_out(404, ['message' => 'Permintaan ganti email tidak ditemukan. Silakan minta kode baru.']);
    }
    if (strtotime($row['expires_at']) < time()) {
        json_out(410, ['message' => 'Kode sudah kedaluwarsa. Silakan minta kode baru.']);
    }
    if ((int) $row['attempts'] >= EMAIL_CHANGE_MAX_ATTEMPTS) {
        json_out(429, ['message' => 'Terlalu banyak percobaan salah. Silakan minta kode baru.']);
    }

    if (!password_verify($otp, $row['otp_hash'])) {
        $pdo->prepare('UPDATE email_changes SET attempts = attempts + 1 WHERE id = ?')->execute([$row['id']]);
        $left = EMAIL_CHANGE_MAX_ATTEMPTS - ((int) $row['attempts'] + 1);
        json_out(400, ['message' => $left > 0 ? "Kode salah. Sisa percobaan: {$left}." : 'Kode salah. Silakan minta kode baru.', 'errors' => ['otp' => 'Kode salah.']]);
    }

    $dup = db_one($pdo, 'SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1', [$row['new_email'], $uid]);
    if ($dup) {
        $pdo->prepare('DELETE FROM email_changes WHERE id = ?')->execute([$row['id']]);
        json_out(409, ['message' => 'Email ini sudah dipakai akun lain. Silakan minta kode dengan email lain.']);
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE users SET email = ? WHERE id = ?')->execute([$row['new_email'], $uid]);
        $pdo->prepare('DELETE FROM email_changes WHERE id = ?')->execute([$row['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    otp_send_email($row['old_email'], 'Pemilik Akun', '', 'email_changed');

    session_regenerate_id(true);

    json_out(200, [
        'message' => 'Email berhasil diubah. Silakan masuk kembali dengan email baru.',
        'redirect' => 'login.html?email_changed=1',
    ]);
}

try {
    $uid = owner_api_guard($pdo);
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_out(405, ['message' => 'Metode tidak diizinkan.']);

    $b = read_post_json();
    $action = is_string($b['action'] ?? null) ? $b['action'] : '';

    if ($action === 'request') email_change_request($pdo, $uid, $b);
    if ($action === 'verify') email_change_verify($pdo, $uid, $b);

    json_out(400, ['message' => 'Aksi tidak dikenal.']);
} catch (Throwable $ex) {
    error_log('[email-change] ' . $ex->getMessage());
    json_out(500, ['message' => 'Terjadi kesalahan pada server. Coba lagi nanti.']);
}