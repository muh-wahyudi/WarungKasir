<?php
require __DIR__ . '/../includes/owner.php';

function admin_users_guard(PDO $pdo): array {
    if (empty($_SESSION['user_id'])) json_out(401, ['message' => 'Sesi berakhir.']);
    $me = db_one($pdo, 'SELECT id, role, is_master FROM users WHERE id = ? AND is_active = 1 LIMIT 1', [(int) $_SESSION['user_id']]);
    if (!$me || $me['role'] !== 'admin') json_out(403, ['message' => 'Akses ditolak.']);
    return ['id' => (int) $me['id'], 'master' => (int) $me['is_master'] === 1];
}

function users_list(PDO $pdo): void {
    $q = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
    $plan = is_string($_GET['plan'] ?? null) ? $_GET['plan'] : '';
    $status = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per = 20;

    $where = "u.role = 'owner'";
    $params = [];
    if ($q !== '') {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $where .= ' AND (u.full_name LIKE ? OR u.business_name LIKE ? OR u.email LIKE ?)';
        array_push($params, $like, $like, $like);
    }
    if (in_array($status, ['active', 'inactive'], true)) {
        $where .= ' AND u.is_active = ?';
        $params[] = $status === 'active' ? 1 : 0;
    }
    if (in_array($plan, ['free', 'pro', 'promax'], true)) {
        $where .= ' AND p.code = ?';
        $params[] = $plan;
    }

    $total = (int) db_one($pdo, "SELECT COUNT(*) AS n FROM users u
                                LEFT JOIN subscriptions s ON s.user_id = u.id AND s.status = 'active'
                                LEFT JOIN plans p ON p.id = s.plan_id
                                WHERE $where", $params)['n'];
    $pages = max(1, (int) ceil($total / $per));
    $page = min($page, $pages);
    $offset = ($page - 1) * $per;

    $rows = db_all($pdo, "SELECT u.id, u.full_name, u.business_name, u.email, u.created_at, u.is_active,
                                COALESCE(p.name, 'Free') AS plan_name, COALESCE(p.code, 'free') AS plan_code
                         FROM users u
                         LEFT JOIN subscriptions s ON s.user_id = u.id AND s.status = 'active'
                         LEFT JOIN plans p ON p.id = s.plan_id
                         WHERE $where ORDER BY u.created_at DESC LIMIT $per OFFSET $offset", $params);

    $sum = db_one($pdo, "SELECT COUNT(*) AS total,
                                COALESCE(SUM(u.is_active = 1), 0) AS active,
                                COALESCE(SUM(u.is_active = 0), 0) AS inactive
                         FROM users u WHERE u.role = 'owner'");
    $paid = (int) db_one($pdo, "SELECT COUNT(*) AS n FROM subscriptions s JOIN plans p ON p.id = s.plan_id
                                WHERE s.status = 'active' AND p.price_monthly > 0")['n'];

    $tz = new DateTimeZone(APP_TZ);
    $fmt = fn($dt) => $dt ? (new DateTime($dt, $tz))->format('j M Y') : null;

    json_out(200, [
        'items' => array_map(fn($r) => [
            'id' => (int) $r['id'], 'full_name' => $r['full_name'], 'business_name' => $r['business_name'],
            'email' => $r['email'], 'plan_name' => $r['plan_name'], 'plan_code' => $r['plan_code'],
            'joined' => $fmt($r['created_at']), 'status' => (int) $r['is_active'] === 1 ? 'active' : 'inactive',
        ], $rows),
        'total' => $total, 'page' => $page, 'pages' => $pages, 'per' => $per,
        'summary' => ['total' => (int) $sum['total'], 'active' => (int) $sum['active'], 'inactive' => (int) $sum['inactive'], 'paid' => $paid],
    ]);
}

function user_toggle(PDO $pdo, array $me, array $b): void {
    $id = (int) ($b['id'] ?? 0);
    if ($id <= 0) json_out(400, ['message' => 'ID pengguna tidak valid.']);
    if (!$me['master']) json_out(403, ['message' => 'Hanya master admin yang dapat menonaktifkan pengguna.']);

    $u = db_one($pdo, "SELECT id, full_name, is_active FROM users WHERE id = ? AND role = 'owner' LIMIT 1", [$id]);
    if (!$u) json_out(404, ['message' => 'Pengguna tidak ditemukan.']);

    $newStatus = (int) $u['is_active'] === 1 ? 0 : 1;
    $pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?')->execute([$newStatus, $id]);
    $label = $newStatus === 1 ? 'diaktifkan' : 'dinonaktifkan';

    db_one($pdo, 'INSERT INTO admin_audit_log (admin_id, action, entity_type, entity_id, detail, ip) VALUES (?, ?, ?, ?, ?, ?)',
        [$me['id'], $newStatus === 1 ? 'user_enable' : 'user_disable', 'user', $id, json_encode(['name' => $u['full_name']]), $_SERVER['REMOTE_ADDR'] ?? '']);

    json_out(200, ['message' => "Pengguna {$u['full_name']} berhasil {$label}.", 'status' => $newStatus === 1 ? 'active' : 'inactive']);
}

try {
    $me = admin_users_guard($pdo);
    $method = $_SERVER['REQUEST_METHOD'] ?? '';

    if ($method === 'GET') users_list($pdo);
    if ($method !== 'POST') json_out(405, ['message' => 'Metode tidak diizinkan.']);

    $b = read_post_json();
    $action = is_string($b['action'] ?? null) ? $b['action'] : '';

    if ($action === 'toggle') user_toggle($pdo, $me, $b);

    json_out(400, ['message' => 'Aksi tidak dikenal.']);
} catch (Throwable $e) {
    error_log('[admin-users] ' . $e->getMessage());
    json_out(500, ['message' => 'Terjadi kesalahan pada server.']);
}