<?php
require __DIR__ . '/../includes/helpers.php';
$b = read_post_json();

$name  = clean($b['name'] ?? '');
$biz   = clean($b['business'] ?? '');
$email = strtolower(clean($b['email'] ?? ''));
$phone = preg_replace('/[\s-]/', '', clean($b['phone'] ?? ''));
$pw    = is_string($b['password'] ?? null) ? $b['password'] : '';
$cf    = is_string($b['confirm'] ?? null) ? $b['confirm'] : '';

$e = [];
if (mb_strlen($name) < 3 || mb_strlen($name) > 100) $e['name'] = 'Nama lengkap harus 3–100 karakter.';
if (mb_strlen($biz) < 2 || mb_strlen($biz) > 120)   $e['business'] = 'Nama usaha harus 2–120 karakter.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) $e['email'] = 'Format email tidak valid.';
if (!preg_match('/^(\+62|62|0)8\d{8,12}$/', $phone)) $e['phone'] = 'Nomor HP tidak valid. Contoh: 081234567890.';
if (strlen($pw) < 8)       $e['password'] = 'Kata sandi minimal 8 karakter.';
elseif (strlen($pw) > 72)  $e['password'] = 'Kata sandi maksimal 72 karakter.';
elseif (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) $e['password'] = 'Kata sandi harus mengandung huruf dan angka.';
if ($pw !== $cf) $e['confirm'] = 'Konfirmasi kata sandi tidak cocok.';
if (($b['terms'] ?? false) !== true) $e['terms'] = 'Anda harus menyetujui Syarat dan Ketentuan.';
if ($e) json_out(400, ['message' => 'Periksa kembali data Anda.', 'errors' => $e]);

$hash = password_hash($pw, PASSWORD_DEFAULT);

try {
    $pdo->beginTransaction();
    $planId = $pdo->query("SELECT id FROM plans WHERE code = 'free' AND is_active = 1 LIMIT 1")->fetchColumn();
    if (!$planId) throw new RuntimeException('Paket Free tidak ditemukan. Jalankan migrasi.');

    $pdo->prepare('INSERT INTO users (full_name, business_name, email, phone, password_hash) VALUES (?, ?, ?, ?, ?)')
        ->execute([$name, $biz, $email, $phone, $hash]);
    $userId = (int) $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO subscriptions (user_id, plan_id, status, started_at) VALUES (?, ?, 'active', NOW())")
        ->execute([$userId, $planId]);

    $pdo->commit(); // user + paket Free tersimpan bersama, atau tidak sama sekali
    json_out(201, ['message' => 'Akun berhasil dibuat. Silakan masuk.', 'redirect' => 'login.html?registered=1']);
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($ex instanceof PDOException && ($ex->errorInfo[1] ?? 0) === 1062) {
        $msg = (string) $ex->getMessage();
        if (str_contains($msg, 'uq_users_phone')) {
            json_out(409, ['message' => 'Nomor HP sudah terdaftar.', 'errors' => ['phone' => 'Nomor HP ini sudah digunakan. Gunakan nomor lain atau masuk.']]);
        }
        // Default: email duplikat
        json_out(409, ['message' => 'Email sudah terdaftar.', 'errors' => ['email' => 'Email ini sudah digunakan. Gunakan email lain atau masuk.']]);
    }
    error_log('[register] ' . $ex->getMessage());
    json_out(500, ['message' => 'Terjadi kesalahan pada server. Coba lagi nanti.']);
}