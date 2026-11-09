<?php
// API Verifikasi OTP (api/verify-otp.php) — tahap 2: cek kode lalu buat akun + langganan Free.
require __DIR__ . '/../includes/owner.php';
require_once __DIR__ . '/../includes/otp.php';

try {
    $b     = read_post_json();
    $email = strtolower(clean($b['email'] ?? ''));
    $otp   = preg_replace('/\D/', '', is_string($b['otp'] ?? null) ? $b['otp'] : '');

    if ($email === '' || strlen($otp) !== 6) {
        json_out(400, ['message' => 'Kode OTP harus 6 digit.']);
    }

    $p = db_one($pdo, 'SELECT * FROM pending_registrations WHERE email = ?', [$email]);
    if (!$p) {
        json_out(404, ['message' => 'Pendaftaran tidak ditemukan. Silakan daftar ulang.']);
    }
    if (strtotime($p['otp_expires_at']) < time()) {
        json_out(410, ['message' => 'Kode sudah kedaluwarsa. Silakan kirim ulang kode.']);
    }
    if ((int) $p['attempts'] >= OTP_MAX_ATTEMPTS) {
        json_out(429, ['message' => 'Terlalu banyak percobaan salah. Silakan kirim ulang kode.']);
    }

    if (!password_verify($otp, $p['otp_hash'])) {
        $pdo->prepare('UPDATE pending_registrations SET attempts = attempts + 1 WHERE id = ?')->execute([$p['id']]);
        $left = OTP_MAX_ATTEMPTS - ((int) $p['attempts'] + 1);
        json_out(400, ['message' => $left > 0 ? "Kode salah. Sisa percobaan: {$left}." : 'Kode salah. Silakan kirim ulang kode.']);
    }

    // Kode benar -> buat akun (sama seperti alur register sebelumnya)
    try {
        $pdo->beginTransaction();

        $pdo->prepare('INSERT INTO users (full_name, business_name, password_hash, email, phone, role) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$p['full_name'], $p['business_name'], $p['password_hash'], $p['email'], $p['phone'], 'owner']);
        $newUserId = (int) $pdo->lastInsertId();

        $freePlan = db_one($pdo, "SELECT id FROM plans WHERE code = 'free' LIMIT 1");
        if ($freePlan) {
            $pdo->prepare("INSERT INTO subscriptions (user_id, plan_id, status, started_at) VALUES (?, ?, 'active', NOW())")
                ->execute([$newUserId, (int) $freePlan['id']]);
        }

        $pdo->prepare('DELETE FROM pending_registrations WHERE id = ?')->execute([$p['id']]);
        $pdo->commit();
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if (($ex->errorInfo[1] ?? 0) === 1062) {
            json_out(409, ['message' => 'Email atau Nomor HP sudah digunakan.']);
        }
        throw $ex;
    }

    json_out(201, ['message' => 'Akun berhasil dibuat. Silakan masuk.', 'redirect' => 'login.html?registered=1']);

} catch (Throwable $ex) {
    error_log('[verify-otp] ' . $ex->getMessage());
    json_out(500, ['message' => 'Terjadi kesalahan pada server. Coba lagi nanti.']);
}