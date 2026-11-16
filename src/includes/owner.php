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
    $plan['max_accounts'] = $plan['max_accounts'] === null ? null : (int) $plan['max_accounts'];
    return $plan;
}

function owner_context(PDO $pdo, int $userId): ?array {
    $u = db_one($pdo, 'SELECT id, full_name, business_name, email, role, is_master FROM users WHERE id = ? AND is_active = 1 LIMIT 1', [$userId]);
    if (!$u) return null;

    if ($u['role'] === 'owner') {
        $u['owner_id'] = (int) $u['id'];
        $u['team_role'] = 'owner';
        $u['plan'] = owner_plan($pdo, (int) $u['id'])['name'];
        return $u;
    }

    if ($u['role'] === 'kasir') {
        $t = db_one($pdo, "SELECT tm.owner_id, tm.role AS team_role, tm.status, uo.business_name AS owner_business
                           FROM team_members tm
                           JOIN users uo ON uo.id = tm.owner_id AND uo.is_active = 1
                           WHERE tm.user_id = ? AND tm.status = 'active' LIMIT 1", [(int) $u['id']]);
        if (!$t) return null;
        $u['owner_id'] = (int) $t['owner_id'];
        $u['team_role'] = $t['team_role'];
        $u['business_name'] = $t['owner_business'];
        $u['plan'] = owner_plan($pdo, (int) $t['owner_id'])['name'];
        return $u;
    }

    return null;
}

function owner_api_guard(PDO $pdo): array {
    if (empty($_SESSION['user_id'])) json_out(401, ['message' => 'Sesi berakhir. Silakan masuk kembali.']);
    $u = owner_context($pdo, (int) $_SESSION['user_id']);
    if (!$u) json_out(403, ['message' => 'Akses ditolak. Akun tidak memiliki akses ke warung.']);
    return $u;
}

function owner_page_guard(PDO $pdo): array {
    header('Cache-Control: no-store');
    if (empty($_SESSION['user_id'])) { header('Location: login.html'); exit; }
    $u = owner_context($pdo, (int) $_SESSION['user_id']);
    if (!$u) {
        if (!empty($_SESSION['user_id'])) {
            $raw = db_one($pdo, 'SELECT role FROM users WHERE id = ? AND is_active = 1', [(int) $_SESSION['user_id']]);
            if ($raw && $raw['role'] === 'admin') { header('Location: admin.php'); exit; }
        }
        session_destroy();
        header('Location: login.html');
        exit;
    }
    return $u;
}

function owner_can(array $u, string $permission): bool {
    $role = $u['team_role'] ?? 'owner';
    $map = [
        'owner' => ['kasir','produk','transaksi','saldo','pengaturan','staf','langganan','dashboard'],
        'admin' => ['kasir','produk','transaksi','dashboard'],
        'kasir' => ['kasir'],
    ];
    return in_array($permission, $map[$role] ?? [], true);
}

function owner_require(array $u, string $permission): void {
    if (!owner_can($u, $permission)) {
        json_out(403, ['message' => 'Anda tidak memiliki akses untuk tindakan ini.']);
    }
}

function owner_team_count(PDO $pdo, int $ownerId): int {
    return (int) db_one($pdo, "SELECT COUNT(*) AS n FROM team_members WHERE owner_id = ? AND status IN ('invited','active')", [$ownerId])['n'];
}

function owner_team_limit(PDO $pdo, int $ownerId): ?int {
    $plan = owner_plan($pdo, $ownerId);
    $max = $plan['max_accounts'];
    if ($max === null) return null;
    return max(0, $max - 1);
}

function owner_team_enforce_limit(PDO $pdo, int $ownerId): array {
    $plan = owner_plan($pdo, $ownerId);
    $max = $plan['max_accounts'];
    if ($max === null) return ['disabled' => 0, 'kept' => 0];

    $limit = max(0, (int) $max - 1);

    $active = db_all($pdo, "SELECT tm.id, tm.user_id, tm.joined_at, tm.invited_at
                            FROM team_members tm
                            WHERE tm.owner_id = ? AND tm.status IN ('active','invited')
                            ORDER BY COALESCE(tm.joined_at, tm.invited_at) ASC, tm.id ASC", [$ownerId]);

    $kept = array_slice($active, 0, $limit);
    $disabled = array_slice($active, $limit);

    if (!$disabled) return ['disabled' => 0, 'kept' => count($kept)];

    $ids = array_column($disabled, 'id');
    $userIds = array_column($disabled, 'user_id');
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $uph = implode(',', array_fill(0, count($userIds), '?'));

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE team_members SET status = 'disabled' WHERE id IN ($ph)")->execute($ids);
        $pdo->prepare("UPDATE users SET is_active = 0 WHERE id IN ($uph) AND role = 'kasir'")->execute($userIds);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    return ['disabled' => count($disabled), 'kept' => count($kept)];
}

function owner_team_can_add(PDO $pdo, int $ownerId): bool {
    $limit = owner_team_limit($pdo, $ownerId);
    if ($limit === null) return true;
    return owner_team_count($pdo, $ownerId) < $limit;
}