<?php
require __DIR__ . '/../includes/owner.php';

const SCAN_TTL_SECONDS = 300;

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
            $row = db_one($pdo, "SELECT id FROM scan_requests
                                 WHERE user_id = ? AND status = 'pending' AND expires_at > NOW()
                                 ORDER BY id DESC LIMIT 1", [$uid]);
            json_out(200, ['pending' => $row ? ['id' => (int) $row['id']] : null]);
        }

        if (!empty($_GET['check_barcodes'])) {
            $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) json_out(400, ['message' => 'ID tidak valid.']);
            $row = scan_one($pdo, $id, $uid);
            if (!$row) json_out(404, ['message' => 'Sesi tidak ditemukan.']);
            if ($row['status'] === 'completed') {
                $barcodes = $row['barcode'] ? explode(',', $row['barcode']) : [];
                $pdo->prepare("UPDATE scan_requests SET status = 'approved', barcode = NULL WHERE id = ?")->execute([$id]);
                json_out(200, ['barcodes' => $barcodes]);
            }
            json_out(200, ['barcodes' => []]);
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
            
            $existing = db_one($pdo, "SELECT id FROM scan_requests
                                      WHERE user_id = ? AND status IN ('pending','approved') AND expires_at > NOW()
                                      ORDER BY id DESC LIMIT 1", [$uid]);
            
            if ($existing) {
                json_out(201, ['message' => 'Melanjutkan sesi scan yang sudah ada.', 'id' => (int) $existing['id']]);
                return;
            }

            $exp = (new DateTime('now', new DateTimeZone(APP_TZ)))->modify('+' . SCAN_TTL_SECONDS . ' seconds')->format('Y-m-d H:i:s');
            $pdo->prepare('INSERT INTO scan_requests (user_id, status, expires_at) VALUES (?, ?, ?)')
                ->execute([$uid, 'pending', $exp]);
            $id = (int) $pdo->lastInsertId();
            json_out(201, ['message' => 'Sesi scan dibuat.', 'id' => $id]);
        }

        $id = filter_var($b['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) json_out(400, ['message' => 'ID tidak valid.']);
        $row = scan_one($pdo, $id, $uid);
        if (!$row) json_out(404, ['message' => 'Sesi tidak ditemukan.']);

        if ($action === 'approve') {
            if ($row['status'] !== 'pending') json_out(409, ['message' => 'Sesi sudah tidak pending.']);
            $pdo->prepare("UPDATE scan_requests SET status = 'approved' WHERE id = ? AND user_id = ?")->execute([$id, $uid]);
            json_out(200, ['message' => 'Sesi disetujui.']);
        }

        if ($action === 'scan') {
            $barcode = is_string($b['barcode'] ?? null) ? trim($b['barcode']) : '';
            if ($barcode === '' || strlen($barcode) > 100) json_out(400, ['message' => 'Barcode tidak valid.']);
            
            $existing = $row['barcode'] ? explode(',', $row['barcode']) : [];
            $existing[] = $barcode;
            $newBarcodeStr = implode(',', $existing);
            
            $pdo->prepare("UPDATE scan_requests SET status = 'completed', barcode = ? WHERE id = ? AND user_id = ?")
                ->execute([$newBarcodeStr, $id, $uid]);
            json_out(200, ['message' => 'Barcode terkirim.']);
        }

        if ($action === 'close') {
            $pdo->prepare("UPDATE scan_requests SET status = 'canceled' WHERE id = ? AND user_id = ?")->execute([$id, $uid]);
            json_out(200, ['message' => 'Sesi ditutup.']);
        }

        json_out(400, ['message' => 'Aksi tidak dikenal.']);
    }
} catch (Throwable $ex) {
    error_log('[scan-request] ' . $ex->getMessage());
    json_out(500, ['message' => 'Terjadi kesalahan pada server.']);
}