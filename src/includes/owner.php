<?php
require_once __DIR__ . '/helpers.php';

function db_one(PDO $pdo, string $sql, array $p = []) { $s = $pdo->prepare($sql); $s->execute($p); return $s->fetch(); }
function db_all(PDO $pdo, string $sql, array $p = []): array { $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchAll(); }

function owner_plan(PDO $pdo, int $uid): array {
    $plan = db_one($pdo, "SELECT p.name, p.max_products, p.history_days, p.max_accounts FROM subscriptions s
                          JOIN plans p ON p.id = s.plan_id WHERE s.user_id = ? AND s.status = 'active'
                          ORDER BY s.started_at DESC, s.id DESC LIMIT 1", [$uid])
        ?: db_one($pdo, "SELECT name, max_products, history_days, max_accounts FROM plans WHERE code = 'free' LIMIT 1");
    $plan['max_products'] = $plan['max_products'] === null ? null : (int) $plan['max_products'];
    return $plan;
}

function owner_api_guard(PDO $pdo): int {
    if (empty($_SESSION['user_id'])) json_out(401, ['message' => 'Sesi berakhir. Silakan masuk kembali.']);
    $me = db_one($pdo, 'SELECT id, role FROM users WHERE id = ? AND is_active = 1 LIMIT 1', [$_SESSION['user_id']]);
    if (!$me) json_out(401, ['message' => 'Sesi tidak valid. Silakan masuk kembali.']);
    if ($me['role'] !== 'owner') json_out(403, ['message' => 'Akses ditolak.']);
    return (int) $me['id'];
}

// Untuk halaman: alihkan jika belum login atau bukan pemilik usaha.
function owner_page_guard(PDO $pdo): array {
    header('Cache-Control: no-store');
    if (empty($_SESSION['user_id'])) { header('Location: login.html'); exit; }
    $u = db_one($pdo, 'SELECT id, full_name, business_name, role FROM users WHERE id = ? AND is_active = 1 LIMIT 1', [$_SESSION['user_id']]);
    if (!$u) { session_destroy(); header('Location: login.html'); exit; }
    if ($u['role'] === 'admin') { header('Location: admin.php'); exit; }
    $u['plan'] = owner_plan($pdo, (int) $u['id'])['name'];
    return $u;
}