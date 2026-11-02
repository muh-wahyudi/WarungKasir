<?php
// Halaman pintu masuk. Mengarahkan pengunjung ke tempat yang tepat:
// - Sudah login sebagai admin -> admin.php
// - Sudah login sebagai pemilik -> owner-dashboard.php
// - Belum login -> login.html
require __DIR__ . '/includes/helpers.php';

if (empty($_SESSION['user_id'])) {
    // Belum login, arahkan ke halaman login
    header('Location: login.html');
    exit;
}

// Jika ada sesi, cek peran (role) pengguna di database
$st = $pdo->prepare('SELECT role FROM users WHERE id = ? AND is_active = 1 LIMIT 1');
$st->execute([$_SESSION['user_id']]);
$u = $st->fetch();

if (!$u) {
    // Sesi ada tapi akun sudah tidak aktif/dihapus, hancurkan sesi
    session_destroy();
    header('Location: login.html');
    exit;
}

// Arahkan sesuai peran
if ($u['role'] === 'admin') {
    header('Location: admin.php');
} else {
    header('Location: owner-dashboard.php');
}
exit;