<?php
require __DIR__ . '/../includes/helpers.php';
$b = read_post_json();

$email    = strtolower(clean($b['email'] ?? ''));
$password = is_string($b['password'] ?? null) ? $b['password'] : '';
$remember = ($b['remember'] ?? false) === true;
$ip       = $_SERVER['REMOTE_ADDR'] ?? '';

try {
    $pdo->exec("DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 1 DAY");
    $st = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > NOW() - INTERVAL 15 MINUTE');
    $st->execute([$ip]);
    if ((int) $st->fetchColumn() >= 10) json_out(429, ['message' => 'Terlalu banyak percobaan masuk. Coba lagi dalam 15 menit.']);

    $user = false;
    if (filter_var($email, FILTER_VALIDATE_EMAIL) && $password !== '' && strlen($password) <= 200) {
        $st = $pdo->prepare('SELECT id, password_hash, is_active, role FROM users WHERE email = ? LIMIT 1');
        $st->execute([$email]);
        $user = $st->fetch();
    }
    // Tetap hitung hash jika email tidak ada, agar waktu respons sama
    $ok = $user ? password_verify($password, $user['password_hash']) : (bool) password_hash('x', PASSWORD_DEFAULT) && false;

    if (!$user || !$ok || !$user['is_active']) {
        $pdo->prepare('INSERT INTO login_attempts (ip, email) VALUES (?, ?)')->execute([$ip, substr($email, 0, 190)]);
        json_out(401, ['message' => 'Email atau kata sandi salah.']);
    }

    $pdo->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    if ($remember) {
        setcookie(session_name(), session_id(), [
            'expires' => time() + 30 * 86400, 'path' => '/', 'httponly' => true,
            'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']),
        ]);
    }
    json_out(200, ['message' => 'Berhasil masuk.', 'redirect' => $user['role'] === 'admin' ? 'admin.php' : 'owner-dashboard.php']);
} catch (PDOException $ex) {
    error_log('[login] ' . $ex->getMessage());
    json_out(500, ['message' => 'Terjadi kesalahan pada server. Coba lagi nanti.']);
}