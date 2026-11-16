<?php
require __DIR__ . '/../includes/owner.php';

function team_accept_info(PDO $pdo): void {
    $token = is_string($_GET['token'] ?? null) ? $_GET['token'] : '';
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) json_out(400, ['message' => 'Token tidak valid.']);

    $tm = db_one($pdo, "SELECT tm.id, tm.role, tm.status, tm.invite_expires_at, u.full_name, u.email, uo.business_name AS owner_business
                         FROM team_members tm
                         JOIN users u ON u.id = tm.user_id
                         JOIN users uo ON uo.id = tm.owner_id
                         WHERE tm.invite_token = ? LIMIT 1", [$token]);
    if (!$tm) json_out(404, ['message' => 'Undangan tidak ditemukan atau sudah dipakai.']);
    if ($tm['status'] === 'active') json_out(409, ['message' => 'Undangan sudah diterima. Silakan login.']);
    if ($tm['status'] !== 'invited') json_out(409, ['message' => 'Undangan tidak dapat diterima (status: ' . $tm['status'] . ').']);
    if ($tm['invite_expires_at'] && strtotime($tm['invite_expires_at']) < time()) json_out(410, ['message' => 'Undangan sudah kedaluwarsa. Minta pemilik mengirim ulang.']);

    json_out(200, [
        'email' => $tm['email'],
        'role' => $tm['role'],
        'business_name' => $tm['owner_business'],
        'expires_at' => $tm['invite_expires_at'],
    ]);
}

function team_accept_submit(PDO $pdo, array $b): void {
    $token = is_string($b['token'] ?? null) ? $b['token'] : '';
    $name = clean($b['full_name'] ?? '');
    $phone = clean($b['phone'] ?? '');
    $password = is_string($b['password'] ?? null) ? $b['password'] : '';
    $confirm = is_string($b['confirm'] ?? null) ? $b['confirm'] : '';

    if (!preg_match('/^[a-f0-9]{64}$/', $token)) json_out(400, ['message' => 'Token tidak valid.']);

    $errors = [];
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) $errors['full_name'] = 'Nama lengkap harus 2–100 karakter.';
    $phoneClean = preg_replace('/[\s\-\.\(\)]/', '', $phone);
    if (!preg_match('/^\+?\d{9,15}$/', $phoneClean)) {
        $errors['phone'] = 'Nomor HP tidak valid. Gunakan 9–15 angka.';
    } else {
        $phoneClean = ltrim($phoneClean, '+');
        if (str_starts_with($phoneClean, '62')) $phoneClean = '0' . substr($phoneClean, 2);
    }
    if (strlen($password) < 8) $errors['password'] = 'Kata sandi minimal 8 karakter.';
    elseif (strlen($password) > 200) $errors['password'] = 'Kata sandi maksimal 200 karakter.';
    if ($confirm !== $password) $errors['confirm'] = 'Konfirmasi kata sandi tidak sama.';
    if ($errors) json_out(400, ['message' => 'Periksa kembali data Anda.', 'errors' => $errors]);

    $tm = db_one($pdo, "SELECT tm.id, tm.user_id, tm.status, tm.invite_expires_at, u.email
                         FROM team_members tm JOIN users u ON u.id = tm.user_id
                         WHERE tm.invite_token = ? LIMIT 1", [$token]);
    if (!$tm) json_out(404, ['message' => 'Undangan tidak ditemukan atau sudah dipakai.']);
    if ($tm['status'] !== 'invited') json_out(409, ['message' => 'Undangan tidak dapat diterima.']);
    if ($tm['invite_expires_at'] && strtotime($tm['invite_expires_at']) < time()) json_out(410, ['message' => 'Undangan sudah kedaluwarsa.']);

    $dup = db_one($pdo, 'SELECT id FROM users WHERE phone = ? AND id <> ? LIMIT 1', [$phoneClean, (int) $tm['user_id']]);
    if ($dup) json_out(409, ['message' => 'Nomor HP ini sudah dipakai akun lain.', 'errors' => ['phone' => 'Nomor HP ini sudah dipakai akun lain.']]);

    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE users SET full_name = ?, phone = ?, password_hash = ?, is_active = 1 WHERE id = ?')
            ->execute([$name, $phoneClean, password_hash($password, PASSWORD_DEFAULT), (int) $tm['user_id']]);
        $pdo->prepare("UPDATE team_members SET status = 'active', joined_at = NOW(), invite_token = NULL, invite_expires_at = NULL WHERE id = ?")
            ->execute([(int) $tm['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    json_out(200, ['message' => 'Akun staf berhasil diaktifkan. Silakan masuk.', 'redirect' => 'login.html?staff=1']);
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if ($method === 'GET') team_accept_info($pdo);
    if ($method !== 'POST') json_out(405, ['message' => 'Metode tidak diizinkan.']);
    $b = read_post_json();
    team_accept_submit($pdo, $b);
} catch (Throwable $ex) {
    error_log('[team-accept] ' . $ex->getMessage());
    json_out(500, ['message' => 'Terjadi kesalahan pada server.']);
}