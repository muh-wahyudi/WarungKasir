<?php
// API Reset Kata Sandi (api/reset-password.php) — cek kode, lalu simpan kata sandi baru.
require __DIR__ . '/../includes/owner.php';
require_once __DIR__ . '/../includes/otp.php';

try {
    $b        = read_post_json();
    $email    = strtolower(clean($b['email'] ?? ''));
    $otp      = preg_replace('/\D/', '', is_string($b['otp'] ?? null) ? $b['otp'] : '');
    $password = is_string($b['password'] ?? null) ? $b['password'] : '';
    $confirm  = is_string($b['confirm'] ?? null) ? $b['confirm'] : '';

    $errors = [];
    if (strlen($otp) !== 6)        $errors['otp'] = 'Kode OTP harus 6 digit.';
    if (strlen($password) < 8)     $errors['password'] = 'Kata sandi minimal 8 karakter.';
    elseif (strlen($password) > 200) $errors['password'] = 'Kata sandi maksimal 200 karakter.';
    if ($confirm !== $password)    $errors['confirm'] = 'Konfirmasi kata sandi tidak sama.';
    if ($errors) json_out(400, ['message' => 'Periksa kembali data Anda.', 'errors' => $errors]);

    $r = db_one($pdo, 'SELECT * FROM password_resets WHERE email = ?', [$email]);
    if (!$r) json_out(404, ['message' => 'Permintaan reset tidak ditemukan. Silakan minta kode baru.']);
    if (strtotime($r['expires_at']) < time()) json_out(410, ['message' => 'Kode sudah kedaluwarsa. Silakan minta kode baru.']);
    if ((int) $r['attempts'] >= OTP_MAX_ATTEMPTS) json_out(429, ['message' => 'Terlalu banyak percobaan salah. Silakan minta kode baru.']);

    if (!password_verify($otp, $r['otp_hash'])) {
        $pdo->prepare('UPDATE password_resets SET attempts = attempts + 1 WHERE id = ?')->execute([$r['id']]);
        $left = OTP_MAX_ATTEMPTS - ((int) $r['attempts'] + 1);
        json_out(400, ['message' => $left > 0 ? "Kode salah. Sisa percobaan: {$left}." : 'Kode salah. Silakan minta kode baru.']);
    }

    $pdo->beginTransaction();
    $pdo->prepare('UPDATE users SET password_hash = ? WHERE email = ? AND is_active = 1')
        ->execute([password_hash($password, PASSWORD_DEFAULT), $email]);
    $pdo->prepare('DELETE FROM password_resets WHERE id = ?')->execute([$r['id']]);
    $pdo->commit();

    json_out(200, ['message' => 'Kata sandi berhasil diubah. Silakan masuk.', 'redirect' => 'login.html?reset=1']);

} catch (Throwable $ex) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('[reset-password] ' . $ex->getMessage());
    json_out(500, ['message' => 'Terjadi kesalahan pada server. Coba lagi nanti.']);
}