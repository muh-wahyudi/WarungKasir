<?php
require __DIR__ . '/../includes/owner.php';

function admin_dash_guard(PDO $pdo): array {
    if (empty($_SESSION['user_id'])) json_out(401, ['message' => 'Sesi berakhir.']);
    $me = db_one($pdo, 'SELECT id, role, is_master FROM users WHERE id = ? AND is_active = 1 LIMIT 1', [(int) $_SESSION['user_id']]);
    if (!$me || $me['role'] !== 'admin') json_out(403, ['message' => 'Akses ditolak.']);
    return ['id' => (int) $me['id'], 'master' => (int) $me['is_master'] === 1];
}

try {
    admin_dash_guard($pdo);

    $period = is_string($_GET['period'] ?? null) ? $_GET['period'] : '6m';
    $days = ['7' => 7, '30' => 30, '6m' => 180][$period] ?? 180;
    $from = (new DateTime('now', new DateTimeZone(APP_TZ)))->modify("-$days days")->format('Y-m-d H:i:s');

    $totalUsers = (int) db_one($pdo, "SELECT COUNT(*) AS n FROM users WHERE role = 'owner'")['n'];
    $activeUsers = (int) db_one($pdo, "SELECT COUNT(*) AS n FROM users WHERE role = 'owner' AND is_active = 1")['n'];
    $pendingUsers = (int) db_one($pdo, "SELECT COUNT(*) AS n FROM users WHERE role = 'owner' AND is_active = 0")['n'];
    $inactiveUsers = 0;

    $plans = [];
    foreach (db_all($pdo, "SELECT p.code, p.name, p.price_monthly, COUNT(s.id) AS n
                           FROM plans p LEFT JOIN subscriptions s ON s.plan_id = p.id AND s.status = 'active'
                           GROUP BY p.id ORDER BY p.price_monthly") as $r) {
        $plans[$r['code']] = ['name' => $r['name'], 'count' => (int) $r['n'], 'price' => (int) $r['price_monthly']];
    }

    $paidSubs = (int) db_one($pdo, "SELECT COUNT(*) AS n FROM subscriptions s JOIN plans p ON p.id = s.plan_id
                                    WHERE s.status = 'active' AND p.price_monthly > 0")['n'];
    $freeSubs = (int) db_one($pdo, "SELECT COUNT(*) AS n FROM subscriptions s JOIN plans p ON p.id = s.plan_id
                                    WHERE s.status = 'active' AND p.price_monthly = 0")['n'];

    $revenue = (int) db_one($pdo, "SELECT COALESCE(SUM(p.price_monthly), 0) AS total
                                   FROM subscriptions s JOIN plans p ON p.id = s.plan_id
                                   WHERE s.status = 'active' AND p.price_monthly > 0")['total'];

    $totalTrx = (int) db_one($pdo, "SELECT COUNT(*) AS n FROM transactions WHERE status = 'Selesai'")['n'];
    $totalTrxAll = (int) db_one($pdo, "SELECT COUNT(*) AS n FROM transactions")['n'];

    $growth = [];
    $groups = ['7' => '%Y-%m-%d', '30' => '%Y-%m-%d', '6m' => '%Y-%m'][$period] ?? '%Y-%m';
    $rows = db_all($pdo, "SELECT DATE_FORMAT(created_at, '$groups') AS label, COUNT(*) AS n
                          FROM users WHERE role = 'owner' AND created_at >= ? GROUP BY label ORDER BY label", [$from]);
    foreach ($rows as $r) $growth[] = ['label' => $r['label'], 'value' => (int) $r['n']];

    $recentUsers = db_all($pdo, "SELECT u.id, u.full_name, u.business_name, u.email, u.created_at, u.is_active,
                                        COALESCE(p.name, 'Free') AS plan_name
                                 FROM users u
                                 LEFT JOIN subscriptions s ON s.user_id = u.id AND s.status = 'active'
                                 LEFT JOIN plans p ON p.id = s.plan_id
                                 WHERE u.role = 'owner' ORDER BY u.created_at DESC LIMIT 10");

    $recentPays = db_all($pdo, "SELECT s.id, s.payment_ref, s.status, s.started_at, s.ends_at,
                                       u.full_name, u.email, p.name AS plan_name, p.price_monthly
                                FROM subscriptions s
                                JOIN users u ON u.id = s.user_id
                                JOIN plans p ON p.id = s.plan_id
                                WHERE s.payment_ref IS NOT NULL ORDER BY s.created_at DESC LIMIT 10");

    $expiring = db_all($pdo, "SELECT s.id, u.full_name, p.name AS plan_name, s.ends_at,
                                     DATEDIFF(s.ends_at, NOW()) AS days_left
                              FROM subscriptions s
                              JOIN users u ON u.id = s.user_id
                              JOIN plans p ON p.id = s.plan_id
                              WHERE s.status = 'active' AND s.ends_at IS NOT NULL
                                AND s.ends_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
                              ORDER BY s.ends_at LIMIT 5");

    $tz = new DateTimeZone(APP_TZ);
    $fmtDate = fn($dt) => $dt ? (new DateTime($dt, $tz))->format('j M Y') : null;

    json_out(200, [
        'stats' => [
            'users_total' => $totalUsers,
            'users_active' => $activeUsers,
            'users_pending' => $pendingUsers,
            'users_inactive' => $inactiveUsers,
            'subs_paid' => $paidSubs,
            'subs_free' => $freeSubs,
            'revenue_monthly' => $revenue,
            'trx_total' => $totalTrx,
            'trx_all' => $totalTrxAll,
        ],
        'plans' => array_map(fn($code, $p) => ['code' => $code, 'name' => $p['name'], 'count' => $p['count'], 'price' => $p['price']], array_keys($plans), $plans),
        'growth' => $growth,
        'recent_users' => array_map(fn($r) => [
            'id' => (int) $r['id'], 'full_name' => $r['full_name'], 'business_name' => $r['business_name'],
            'email' => $r['email'], 'plan_name' => $r['plan_name'],
            'joined' => $fmtDate($r['created_at']), 'status' => (int) $r['is_active'] === 1 ? 'Aktif' : 'Nonaktif',
        ], $recentUsers),
        'recent_pays' => array_map(fn($r) => [
            'id' => (int) $r['id'], 'payment_ref' => $r['payment_ref'], 'full_name' => $r['full_name'],
            'email' => $r['email'], 'plan_name' => $r['plan_name'], 'amount' => (int) $r['price_monthly'],
            'status' => $r['status'], 'date' => $fmtDate($r['started_at']),
        ], $recentPays),
        'expiring' => array_map(fn($r) => [
            'id' => (int) $r['id'], 'full_name' => $r['full_name'], 'plan_name' => $r['plan_name'],
            'ends_at' => $fmtDate($r['ends_at']), 'days_left' => (int) $r['days_left'],
        ], $expiring),
        'period' => $period,
    ]);
} catch (Throwable $e) {
    error_log('[admin-dashboard] ' . $e->getMessage());
    json_out(500, ['message' => 'Terjadi kesalahan pada server.']);
}