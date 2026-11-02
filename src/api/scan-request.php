<?php
require __DIR__ . '/../includes/owner.php';

const SCAN_TTL_SECONDS = 120;

function scan_purge_expired(PDO $pdo): void {
    $pdo->exec("UPDATE scan_requests SET status = 'expired'
                WHERE status IN ('pending','approved') AND expires_at < NOW()");
}

function scan_one(PDO $pdo, int $id, int $uid) {
    return db_one($pdo, 'SELECT id, status, barcode, created_at, expires_at
                         FROM scan_requests WHERE id = ? AND user_id = ? LIMIT 1', [$id, $uid]);
}

try {
    $uid = owner_api_guard($pdo);
    $method = $_SERVER['REQUEST_METHOD'] ?? '';

    if ($method === 'GET') {
        scan_purge_expired($pdo);

        if (!empty($_GET['pending'])) {
            $row = db_one($pdo, "SELECT id, created_at, expires_at FROM scan_requests
                                 WHERE user_id = ? AND status = 'pending' AND expires_at > NOW()
                                 ORDER BY id DESC LIMIT 1", [$uid]);
            json_out(200, ['pending' => $row ? ['id' => (int) $row['id'], 'created_at' => $row['created_at']] : null]);
        }

        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) json_out(400, ['message' => 'ID permintaan tidak valid.']);
        $row = scan_one($pdo, $id, $uid);
        if (!$row) json_out(404, ['message' => 'Permintaan scan tidak ditemukan.']);
        json_out(200, [
            'id' => (int) $row['id'],
            'status' => $row['status'],
            'barcode' => $row['barcode'],
            'expires_at' => $row['expires_at'],
        ]);
    }

    if ($method === 'POST') {
        $b = read_post_json();
        $action = is_string($b['action'] ?? null) ? $b['action'] : '';

        if ($action === 'create') {
            scan_purge_expired($pdo);
            $exists = db_one($pdo, "SELECT id FROM scan_requests
                                    WHERE user_id = ? AND status IN ('pending','approved') AND expires_at > NOW()
                                    LIMIT 1", [$uid]);
            if ($exists) json_out(409, ['message' => 'Sudah ada permintaan scan yang sedang berjalan.']);

            $exp = (new DateTime('now', new DateTimeZone(APP_TZ)))->modify('+' . SCAN_TTL_SECONDS . ' seconds')->format('Y-m-d H:i:s');
            $pdo->prepare('INSERT INTO scan_requests (user_id, status, expires_at) VALUES (?, ?, ?)')
                ->execute([$uid, 'pending', $exp]);
            $id = (int) $pdo->lastInsertId();
            json_out(201, ['message' => 'Permintaan scan dibuat.', 'id' => $id, 'expires_in' => SCAN_TTL_SECONDS]);
        }

        $id = filter_var($b['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) json_out(400, ['message' => 'ID permintaan tidak valid.']);
        $row = scan_one($pdo, $id, $uid);
        if (!$row) json_out(404, ['message' => 'Permintaan scan tidak ditemukan.']);

        if ($action === 'approve') {
            if ($row['status'] !== 'pending') json_out(409, ['message' => 'Permintaan sudah tidak pending.']);
            $pdo->prepare("UPDATE scan_requests SET status = 'approved' WHERE id = ? AND user_id = ?")->execute([$id, $uid]);
            json_out(200, ['message' => 'Permintaan disetujui.']);
        }

        if ($action === 'complete') {
            if (!in_array($row['status'], ['pending', 'approved'], true)) json_out(409, ['message' => 'Permintaan sudah selesai.']);
            $barcode = is_string($b['barcode'] ?? null) ? trim($b['barcode']) : '';
            if ($barcode === '' || strlen($barcode) > 100) json_out(400, ['message' => 'Barcode tidak valid.']);
            $pdo->prepare("UPDATE scan_requests SET status = 'completed', barcode = ? WHERE id = ? AND user_id = ?")
                ->execute([$barcode, $id, $uid]);
            json_out(200, ['message' => 'Barcode terkirim.']);
        }

        if ($action === 'reject') {
            if (!in_array($row['status'], ['pending', 'approved'], true)) json_out(409, ['message' => 'Permintaan sudah selesai.']);
            $pdo->prepare("UPDATE scan_requests SET status = 'rejected' WHERE id = ? AND user_id = ?")->execute([$id, $uid]);
            json_out(200, ['message' => 'Permintaan ditolak.']);
        }

        if ($action === 'cancel') {
            if (!in_array($row['status'], ['pending', 'approved'], true)) json_out(409, ['message' => 'Permintaan sudah selesai.']);
            $pdo->prepare("UPDATE scan_requests SET status = 'canceled' WHERE id = ? AND user_id = ?")->execute([$id, $uid]);
            json_out(200, ['message' => 'Permintaan dibatalkan.']);
        }

        json_out(400, ['message' => 'Aksi tidak dikenal.']);
    }

    json_out(405, ['message' => 'Metode tidak diizinkan.']);
} catch (Throwable $ex) {
    error_log('[scan-request] ' . $ex->getMessage());
    json_out(500, ['message' => 'Terjadi kesalahan pada server.']);
}