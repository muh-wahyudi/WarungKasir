<?php
require __DIR__ . '/../includes/owner.php';

const SCAN_TTL_SECONDS = 300;

function scan_purge_expired(PDO $pdo): void {
    $pdo->exec("UPDATE scan_requests SET status = 'expired'
                WHERE status IN ('pending','approved') AND expires_at < NOW()");
}

function scan_parse(?string $s): array {
    $m = [];
    foreach (($s === null || $s === '') ? [] : explode(',', $s) as $part) {
        $part = trim($part);
        if ($part === '') continue;
        $pos = strrpos($part, ':');
        if ($pos === false) { $code = $part; $n = 1; }
        else { $code = substr($part, 0, $pos); $n = max(1, (int) substr($part, $pos + 1)); }
        if ($code === '') continue;
        $m[$code] = ($m[$code] ?? 0) + $n;
    }
    return $m;
}

function scan_format(array $m): string {
    $o = [];
    foreach ($m as $code => $n) $o[] = $code . ':' . $n;
    return implode(',', $o);
}

function scan_one(PDO $pdo, int $id, int $uid) {
    return db_one($pdo, 'SELECT id, status, scan_mode, barcode, created_at, expires_at
                         FROM scan_requests WHERE id = ? AND user_id = ? LIMIT 1', [$id, $uid]);
}

try {
    $me = owner_api_guard($pdo);
    $uid = (int) $me['owner_id'];
    owner_require($me, 'kasir');
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

            $map = scan_parse($row['barcode']);
            $scans = [];
            foreach ($map as $code => $n) $scans[] = ['code' => (string) $code, 'n' => (int) $n];
            json_out(200, [
                'status' => $row['status'],
                'barcodes' => array_map(fn($c) => (string) $c, array_keys($map)),
                'scans' => $scans,
            ]);
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
            $mode = ($b['mode'] ?? '') === 'produk' ? 'produk' : 'kasir';

            $existing = db_one($pdo, "SELECT id, status, scan_mode FROM scan_requests
                                      WHERE user_id = ? AND status IN ('pending','approved') AND expires_at > NOW()
                                      ORDER BY id DESC LIMIT 1", [$uid]);

            if ($existing) {
                if ($existing['status'] === 'pending') {
                    if ($existing['scan_mode'] === $mode) {
                        json_out(201, ['message' => 'Melanjutkan sesi pending.', 'id' => (int) $existing['id']]);
                        return;
                    }
                    $pdo->prepare("UPDATE scan_requests SET status = 'canceled' WHERE id = ? AND user_id = ?")
                        ->execute([(int) $existing['id'], $uid]);
                }
            }

            $exp = (new DateTime('now', new DateTimeZone(APP_TZ)))->modify('+' . SCAN_TTL_SECONDS . ' seconds')->format('Y-m-d H:i:s');
            $pdo->prepare('INSERT INTO scan_requests (user_id, status, scan_mode, expires_at) VALUES (?, ?, ?, ?)')
                ->execute([$uid, 'pending', $mode, $exp]);
            $id = (int) $pdo->lastInsertId();
            json_out(201, ['message' => 'Sesi scan baru dibuat.', 'id' => $id]);
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
            if ($row['status'] !== 'approved') json_out(409, ['message' => 'Sesi scan tidak aktif.']);

            if (preg_match('/[,:]/', $barcode)) json_out(400, ['message' => 'Barcode tidak valid.']);

            $code = $barcode;
            if ($row['scan_mode'] === 'kasir') {
                $p = db_one($pdo, 'SELECT sku, stock FROM products WHERE owner_id = ? AND is_active = 1 AND sku = ? LIMIT 1', [$uid, $barcode]);
                if (!$p) json_out(404, ['message' => 'Produk tidak terdaftar.']);
                if ((int) $p['stock'] <= 0) json_out(409, ['message' => 'Stok produk habis.']);
                $code = (string) $p['sku'];
            }

            $pdo->beginTransaction();
            try {
                $cur = db_one($pdo, 'SELECT status, barcode FROM scan_requests WHERE id = ? AND user_id = ? FOR UPDATE', [$id, $uid]);
                if (!$cur || $cur['status'] !== 'approved') {
                    $pdo->rollBack();
                    json_out(409, ['message' => 'Sesi scan tidak aktif.']);
                }
                $map = scan_parse($cur['barcode']);
                $map[$code] = ($map[$code] ?? 0) + 1;
                $pdo->prepare('UPDATE scan_requests SET barcode = ? WHERE id = ?')->execute([scan_format($map), $id]);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            json_out(200, ['message' => 'Barcode terkirim.', 'count' => $map[$code]]);
        }

        if ($action === 'complete') {
            $pdo->prepare("UPDATE scan_requests SET status = 'completed'
                           WHERE id = ? AND user_id = ? AND status IN ('pending','approved')")
                ->execute([$id, $uid]);
            json_out(200, ['message' => 'Sesi selesai.']);
        }

        if ($action === 'close' || $action === 'cancel') {
            $pdo->prepare("UPDATE scan_requests SET status = 'canceled'
                           WHERE id = ? AND user_id = ? AND status IN ('pending','approved')")
                ->execute([$id, $uid]);
            json_out(200, ['message' => 'Sesi ditutup.']);
        }

        json_out(400, ['message' => 'Aksi tidak dikenal.']);
    }
} catch (Throwable $ex) {
    error_log('[scan-request] ' . $ex->getMessage());
    json_out(500, ['message' => 'Terjadi kesalahan pada server.']);
}