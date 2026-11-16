<?php
require __DIR__ . '/../includes/owner.php';
require_once __DIR__ . '/../includes/otp.php';

const TEAM_INVITE_TTL_HOURS = 72;
const TEAM_ROLES = ['admin', 'kasir'];

function team_invite(PDO $pdo, array $me, array $b): void {
    owner_require($me, 'staf');
    $ownerId = (int) $me['owner_id'];

    $email = strtolower(clean($b['email'] ?? ''));
    $role = in_array($b['role'] ?? '', TEAM_ROLES, true) ? $b['role'] : 'kasir';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_out(400, ['message' => 'Format email tidak valid.', 'errors' => ['email' => 'Format email tidak valid.']]);
    }
    if (strlen($email) > 190) {
        json_out(400, ['message' => 'Email terlalu panjang.', 'errors' => ['email' => 'Email terlalu panjang.']]);
    }

    $plan = owner_plan($pdo, $ownerId);
    if (!owner_team_can_add($pdo, $ownerId)) {
        json_out(403, ['code' => 'limit', 'message' => "Paket {$plan['name']} maksimal {$plan['max_accounts']} akun. Upgrade paket untuk menambah staf."]);
    }

    $existingUser = db_one($pdo, 'SELECT id, role FROM users WHERE email = ? LIMIT 1', [$email]);
    if ($existingUser) {
        if ($existingUser['role'] !== 'kasir') {
            json_out(409, ['message' => 'Email ini sudah dipakai akun lain.', 'errors' => ['email' => 'Email ini sudah dipakai akun lain.']]);
        }
        $inTeam = db_one($pdo, 'SELECT id FROM team_members WHERE user_id = ? LIMIT 1', [(int) $existingUser['id']]);
        if ($inTeam) {
            json_out(409, ['message' => 'Email ini sudah menjadi staf di warung lain.', 'errors' => ['email' => 'Email ini sudah menjadi staf di warung lain.']]);
        }
    }

    $pending = db_one($pdo, "SELECT id FROM team_members WHERE owner_id = ? AND status = 'invited' AND user_id IN (SELECT id FROM users WHERE email = ?) LIMIT 1", [$ownerId, $email]);
    if ($pending) {
        json_out(409, ['message' => 'Undangan untuk email ini masih menunggu. Kirim ulang dari daftar staf.', 'errors' => ['email' => 'Undangan masih menunggu.']]);
    }

    $token = bin2hex(random_bytes(32));
    $expires = (new DateTime('now', new DateTimeZone(APP_TZ)))->modify('+' . TEAM_INVITE_TTL_HOURS . ' hours')->format('Y-m-d H:i:s');

    $pdo->beginTransaction();
    try {
        $userId = null;
        if ($existingUser) {
            $userId = (int) $existingUser['id'];
        } else {
            $randomPass = bin2hex(random_bytes(16));
            $pdo->prepare('INSERT INTO users (full_name, business_name, password_hash, email, phone, role, is_active) VALUES (?, ?, ?, ?, ?, ?, 0)')
                ->execute(['Staf Baru', $me['business_name'], password_hash($randomPass, PASSWORD_DEFAULT), $email, '', 'kasir']);
            $userId = (int) $pdo->lastInsertId();
        }

        $pdo->prepare('INSERT INTO team_members (owner_id, user_id, role, status, invite_token, invite_expires_at, invited_at)
                       VALUES (?, ?, ?, ?, ?, ?, NOW())
                       ON DUPLICATE KEY UPDATE role = VALUES(role), status = VALUES(status), invite_token = VALUES(invite_token), invite_expires_at = VALUES(invite_expires_at), invited_at = NOW()')
            ->execute([$ownerId, $userId, $role, 'invited', $token, $expires]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    $link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/terima-undangan.html?token=' . $token;
    $sent = otp_send_team_invite($email, $me['business_name'], $role, $link);

    json_out(200, [
        'message' => $sent ? 'Undangan telah dikirim ke ' . $email . '.' : 'Undangan dibuat, tetapi email gagal dikirim. Salin link manual.',
        'email' => $email,
        'role' => $role,
        'link' => getenv('APP_ENV') === 'development' ? $link : null,
        'email_sent' => $sent,
    ]);
}

function team_list(PDO $pdo, array $me): void {
    owner_require($me, 'staf');
    $ownerId = (int) $me['owner_id'];

    $rows = db_all($pdo, "SELECT tm.id, tm.role, tm.status, tm.invited_at, tm.joined_at,
                                 u.id AS user_id, u.full_name, u.email
                          FROM team_members tm JOIN users u ON u.id = tm.user_id
                          WHERE tm.owner_id = ? ORDER BY tm.id DESC", [$ownerId]);

    $plan = owner_plan($pdo, $ownerId);
    $max = $plan['max_accounts'];
    $limit = $max === null ? null : max(0, (int) $max - 1);
    $used = owner_team_count($pdo, $ownerId);

    $tz = new DateTimeZone(APP_TZ);
    $fmt = fn($dt) => $dt ? (new DateTime($dt, $tz))->format('j M Y, H:i') : null;

    json_out(200, [
        'items' => array_map(fn($r) => [
            'id' => (int) $r['id'],
            'user_id' => (int) $r['user_id'],
            'full_name' => $r['full_name'],
            'email' => $r['email'],
            'role' => $r['role'],
            'status' => $r['status'],
            'invited_at' => $fmt($r['invited_at']),
            'joined_at' => $fmt($r['joined_at']),
        ], $rows),
        'limit' => $limit,
        'used' => $used,
        'plan' => $plan['name'],
        'can_add' => owner_team_can_add($pdo, $ownerId),
    ]);
}

function team_action(PDO $pdo, array $me, array $b): void {
    owner_require($me, 'staf');
    $ownerId = (int) $me['owner_id'];
    $action = is_string($b['action'] ?? null) ? $b['action'] : '';
    $id = (int) ($b['id'] ?? 0);

    $tm = db_one($pdo, 'SELECT tm.id, tm.user_id, tm.status, u.email FROM team_members tm JOIN users u ON u.id = tm.user_id WHERE tm.id = ? AND tm.owner_id = ? LIMIT 1', [$id, $ownerId]);
    if (!$tm) json_out(404, ['message' => 'Staf tidak ditemukan.']);

    if ($action === 'disable') {
        if ($tm['status'] === 'disabled') json_out(200, ['message' => 'Staf sudah nonaktif.']);
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE team_members SET status = 'disabled' WHERE id = ?")->execute([$id]);
            $pdo->prepare('UPDATE users SET is_active = 0 WHERE id = ?')->execute([(int) $tm['user_id']]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        json_out(200, ['message' => 'Staf dinonaktifkan.']);
    }

    if ($action === 'enable') {
        if ($tm['status'] !== 'disabled') json_out(200, ['message' => 'Staf sudah aktif.']);
        if (!owner_team_can_add($pdo, $ownerId)) {
            json_out(403, ['code' => 'limit', 'message' => 'Batas akun paket tercapai. Upgrade paket untuk mengaktifkan staf ini.']);
        }
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE team_members SET status = 'active' WHERE id = ?")->execute([$id]);
            $pdo->prepare('UPDATE users SET is_active = 1 WHERE id = ?')->execute([(int) $tm['user_id']]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        json_out(200, ['message' => 'Staf diaktifkan.']);
    }

    if ($action === 'remove') {
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM team_members WHERE id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM users WHERE id = ? AND role = ?')->execute([(int) $tm['user_id'], 'kasir']);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        json_out(200, ['message' => 'Staf dihapus.']);
    }

    if ($action === 'resend') {
        if ($tm['status'] !== 'invited') json_out(409, ['message' => 'Undangan hanya bisa dikirim ulang untuk status invited.']);
        $token = bin2hex(random_bytes(32));
        $expires = (new DateTime('now', new DateTimeZone(APP_TZ)))->modify('+' . TEAM_INVITE_TTL_HOURS . ' hours')->format('Y-m-d H:i:s');
        $pdo->prepare('UPDATE team_members SET invite_token = ?, invite_expires_at = ?, invited_at = NOW() WHERE id = ?')->execute([$token, $expires, $id]);
        $link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/terima-undangan.html?token=' . $token;
        $sent = otp_send_team_invite($tm['email'], $me['business_name'], 'kasir', $link);
        json_out(200, ['message' => $sent ? 'Undangan dikirim ulang.' : 'Email gagal dikirim. Salin link manual.', 'link' => getenv('APP_ENV') === 'development' ? $link : null]);
    }

    json_out(400, ['message' => 'Aksi tidak dikenal.']);
}

try {
    $me = owner_api_guard($pdo);
    $method = $_SERVER['REQUEST_METHOD'] ?? '';

    if ($method === 'GET') {
        team_list($pdo, $me);
    }
    if ($method !== 'POST') json_out(405, ['message' => 'Metode tidak diizinkan.']);

    $b = read_post_json();
    $action = is_string($b['action'] ?? null) ? $b['action'] : '';

    if ($action === 'invite') team_invite($pdo, $me, $b);
    if (in_array($action, ['disable', 'enable', 'remove', 'resend'], true)) team_action($pdo, $me, $b);

    json_out(400, ['message' => 'Aksi tidak dikenal.']);
} catch (Throwable $ex) {
    error_log('[team-invite] ' . $ex->getMessage());
    json_out(500, ['message' => 'Terjadi kesalahan pada server.']);
}