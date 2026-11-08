<?php
// API admin untuk Withdrawal, verifikasi rekening, saldo warung, pengaturan wallet, dan audit log.
// Hanya akun admin. Penyesuaian saldo dan perubahan pengaturan hanya untuk master admin.
require __DIR__ . '/../includes/withdrawal.php';

const SETTING_RULES = [   // kunci => [min, max, label]
    'mdr_bps' => [0, 1000, 'Tarif MDR'],
    'mdr_tax_bps' => [0, 3000, 'Pajak atas MDR'],
    'settlement_delay_days' => [0, 14, 'Jeda settlement'],
    'min_withdrawal' => [1, 1000000000, 'Minimum withdrawal'],
    'withdrawal_fee_flat' => [0, 1000000, 'Biaya transfer'],
    'max_open_withdrawals' => [1, 20, 'Maksimum pengajuan terbuka'],
];
const SETTING_DEFAULTS = ['max_open_withdrawals' => '1'];

function aw_status_filter(array $allowed): string {
    $s = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
    return in_array($s, $allowed, true) ? $s : '';
}

function aw_summary(PDO $pdo): array {
    $l = db_one($pdo, 'SELECT COALESCE(SUM(pending_balance),0) p, COALESCE(SUM(available_balance),0) a, COALESCE(SUM(processing_balance),0) r,
                               COALESCE(SUM(total_withdrawn),0) w, COALESCE(SUM(total_fees),0) f FROM wallets');
    $c = [];
    foreach (db_all($pdo, 'SELECT status, COUNT(*) n FROM withdrawals GROUP BY status') as $r) $c[$r['status']] = (int) $r['n'];
    $bp = (int) db_one($pdo, "SELECT COUNT(*) n FROM bank_accounts WHERE status = 'pending'")['n'];
    return [
        'liability' => ['pending' => (int) $l['p'], 'available' => (int) $l['a'], 'processing' => (int) $l['r'], 'total' => (int) $l['p'] + (int) $l['a'] + (int) $l['r']],
        'withdrawn_total' => (int) $l['w'], 'fees_total' => (int) $l['f'],
        'wd_counts' => $c, 'banks_pending' => $bp,
    ];
}

function aw_withdrawals(PDO $pdo): array {
    $st = aw_status_filter(['Pending', 'Approved', 'Processing', 'Completed', 'Rejected', 'Failed']);
    $rows = db_all($pdo, 'SELECT w.id, w.owner_id, u.full_name, u.business_name, u.email, w.amount, w.transfer_fee, w.net_amount, w.status,
                                 w.bank_name, w.account_last4, w.account_holder, w.reject_reason, w.failure_reason, w.transfer_reference,
                                 (w.proof_file IS NOT NULL) AS has_proof, w.requested_at, w.approved_at, w.processed_at, w.completed_at
                          FROM withdrawals w JOIN users u ON u.id = w.owner_id' . ($st ? ' WHERE w.status = ?' : '') . '
                          ORDER BY w.id DESC LIMIT 100', $st ? [$st] : []);
    return ['items' => array_map(fn($r) => [
        'id' => (int) $r['id'], 'owner_id' => (int) $r['owner_id'], 'owner' => $r['full_name'], 'business' => $r['business_name'], 'email' => $r['email'],
        'amount' => (int) $r['amount'], 'fee' => (int) $r['transfer_fee'], 'net' => (int) $r['net_amount'], 'status' => $r['status'],
        'bank_name' => $r['bank_name'], 'last4' => $r['account_last4'], 'holder' => $r['account_holder'],
        'reject_reason' => $r['reject_reason'], 'failure_reason' => $r['failure_reason'], 'transfer_reference' => $r['transfer_reference'],
        'has_proof' => (bool) $r['has_proof'], 'requested_at' => $r['requested_at'], 'approved_at' => $r['approved_at'],
        'processed_at' => $r['processed_at'], 'completed_at' => $r['completed_at'],
    ], $rows)];
}

function aw_banks(PDO $pdo): array {
    $st = aw_status_filter(['pending', 'verified', 'rejected', 'disabled']);
    $rows = db_all($pdo, 'SELECT b.id, b.owner_id, u.full_name, u.business_name, b.bank_name, b.account_last4, b.account_holder, b.status,
                                 b.reject_reason, b.created_at, b.verified_at
                          FROM bank_accounts b JOIN users u ON u.id = b.owner_id' . ($st ? ' WHERE b.status = ?' : '') . '
                          ORDER BY (b.status = \'pending\') DESC, b.id DESC LIMIT 100', $st ? [$st] : []);
    return ['items' => array_map(fn($r) => [
        'id' => (int) $r['id'], 'owner_id' => (int) $r['owner_id'], 'owner' => $r['full_name'], 'business' => $r['business_name'],
        'bank_name' => $r['bank_name'], 'last4' => $r['account_last4'], 'holder' => $r['account_holder'], 'status' => $r['status'],
        'reject_reason' => $r['reject_reason'], 'created_at' => $r['created_at'], 'verified_at' => $r['verified_at'],
    ], $rows)];
}

function aw_wallets(PDO $pdo): array {
    $rows = db_all($pdo, "SELECT u.id, u.full_name, u.business_name, w.pending_balance, w.available_balance, w.processing_balance, w.total_withdrawn, w.total_fees
                          FROM wallets w JOIN users u ON u.id = w.owner_id WHERE u.role = 'owner'
                          ORDER BY (w.pending_balance + w.available_balance + w.processing_balance) DESC, u.id LIMIT 200");
    return ['items' => array_map(fn($r) => [
        'id' => (int) $r['id'], 'owner' => $r['full_name'], 'business' => $r['business_name'],
        'pending' => (int) $r['pending_balance'], 'available' => (int) $r['available_balance'], 'processing' => (int) $r['processing_balance'],
        'withdrawn' => (int) $r['total_withdrawn'], 'fees' => (int) $r['total_fees'],
    ], $rows)];
}

function aw_pending(PDO $pdo, int $uid): array {
    $rows = db_all($pdo, 'SELECT f.id, t.code, f.gross_amount, f.total_fee, f.net_amount, f.paid_at, f.release_at
                          FROM payment_fees f JOIN transactions t ON t.id = f.transaction_id
                          WHERE f.owner_id = ? AND f.released_at IS NULL AND f.reversed_at IS NULL ORDER BY f.id', [$uid]);
    return ['items' => array_map(fn($r) => [
        'id' => (int) $r['id'], 'code' => $r['code'], 'gross' => (int) $r['gross_amount'], 'fee' => (int) $r['total_fee'],
        'net' => (int) $r['net_amount'], 'paid_at' => $r['paid_at'], 'release_at' => $r['release_at'],
    ], $rows)];
}

function aw_settings(PDO $pdo): array {
    $cur = [];
    foreach (db_all($pdo, 'SELECT setting_key, setting_value, description FROM wallet_settings WHERE setting_key <> \'enc_key_dev\'') as $r) $cur[$r['setting_key']] = $r;
    $out = [];
    foreach (SETTING_RULES as $k => [$min, $max, $label]) {
        $out[] = ['key' => $k, 'label' => $label, 'value' => $cur[$k]['setting_value'] ?? (SETTING_DEFAULTS[$k] ?? '0'), 'min' => $min, 'max' => $max, 'desc' => $cur[$k]['description'] ?? ''];
    }
    return [
        'items' => $out,
        'wallet_start_at' => $cur['wallet_start_at']['setting_value'] ?? '',
        'payout_mode' => $cur['payout_mode']['setting_value'] ?? 'manual',
        'key_dev' => wd_key_is_dev(),
    ];
}

function aw_audit(PDO $pdo): array {
    $rows = db_all($pdo, 'SELECT a.id, a.action, a.entity_type, a.entity_id, a.detail, a.reason, a.created_at, u.full_name
                          FROM admin_audit_log a JOIN users u ON u.id = a.admin_id ORDER BY a.id DESC LIMIT 100');
    return ['items' => array_map(fn($r) => [
        'id' => (int) $r['id'], 'admin' => $r['full_name'], 'action' => $r['action'], 'entity' => $r['entity_type'] . ($r['entity_id'] ? ' #' . $r['entity_id'] : ''),
        'detail' => $r['detail'], 'reason' => $r['reason'], 'time' => $r['created_at'],
    ], $rows)];
}

function aw_save_settings(PDO $pdo, array $admin, array $b): void {
    $in = is_array($b['settings'] ?? null) ? $b['settings'] : [];
    $errors = [];
    $new = [];
    foreach (SETTING_RULES as $k => [$min, $max, $label]) {
        $v = $in[$k] ?? null;
        if (!is_string($v) && !is_int($v)) { $errors[$k] = $label . ' wajib diisi.'; continue; }
        $v = trim((string) $v);
        if (!preg_match('/^\d{1,10}$/', $v) || (int) $v < $min || (int) $v > $max) { $errors[$k] = $label . ' harus angka bulat ' . number_format($min, 0, ',', '.') . '–' . number_format($max, 0, ',', '.') . '.'; continue; }
        $new[$k] = (string) (int) $v;
    }
    $start = is_string($in['wallet_start_at'] ?? null) ? trim($in['wallet_start_at']) : '';
    if ($start !== '') {
        $d = DateTime::createFromFormat('Y-m-d H:i:s', $start);
        if (!$d || $d->format('Y-m-d H:i:s') !== $start) $errors['wallet_start_at'] = 'Format waktu harus YYYY-MM-DD JJ:MM:DD, contoh 2026-11-07 00:00:00.';
    }
    $new['wallet_start_at'] = $start;
    if ($errors) json_out(400, ['message' => 'Periksa kembali isian.', 'errors' => $errors]);

    wallet_tx($pdo, function () use ($pdo, $admin, $new) {
        $before = [];
        foreach (db_all($pdo, 'SELECT setting_key, setting_value FROM wallet_settings') as $r) $before[$r['setting_key']] = $r['setting_value'];
        $changed = [];
        foreach ($new as $k => $v) {
            $old = $before[$k] ?? (SETTING_DEFAULTS[$k] ?? null);
            if ($old === $v && isset($before[$k])) continue;
            $pdo->prepare('INSERT INTO wallet_settings (setting_key, setting_value, description, updated_by) VALUES (?, ?, ?, ?)
                           ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)')
                ->execute([$k, $v, $k === 'max_open_withdrawals' ? 'Jumlah maksimum pengajuan withdrawal yang boleh terbuka bersamaan per warung.' : '', $admin['id']]);
            if ($old !== $v) $changed[$k] = ['dari' => $old, 'menjadi' => $v];
        }
        if ($changed) audit_log($pdo, $admin['id'], 'wallet_settings', 'settings', null, $changed);
    });
}

try {
    $m = $_SERVER['REQUEST_METHOD'] ?? '';

    if ($m === 'GET') {
        admin_api_guard($pdo);
        $view = $_GET['view'] ?? 'summary';
        if ($view === 'withdrawals') json_out(200, aw_withdrawals($pdo));
        if ($view === 'banks') json_out(200, aw_banks($pdo));
        if ($view === 'wallets') json_out(200, aw_wallets($pdo));
        if ($view === 'settings') json_out(200, aw_settings($pdo));
        if ($view === 'pending') json_out(200, aw_pending($pdo, (int) ($_GET['owner_id'] ?? 0)));
        if ($view === 'audit') json_out(200, aw_audit($pdo));
        json_out(200, aw_summary($pdo));
    }
    if ($m !== 'POST') json_out(405, ['message' => 'Metode tidak diizinkan.']);

    wd_require_xhr();
    $multipart = stripos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') === 0;
    if ($multipart) {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]) json_out(403, ['message' => 'Permintaan ditolak.']);
        $b = $_POST;
    } else {
        $b = read_post_json();
    }
    $action = is_string($b['action'] ?? null) ? $b['action'] : '';
    $master = in_array($action, ['adjust', 'save_settings', 'release_payment'], true);
    $admin = admin_api_guard($pdo, $master);

    try {
        if (in_array($action, ['approve', 'reject', 'process', 'complete', 'fail'], true)) {
            $w = wd_admin_transition($pdo, $admin['id'], (int) ($b['id'] ?? 0), $action, $b);
            json_out(200, ['message' => 'Withdrawal #' . $w['id'] . ' sekarang berstatus ' . $w['status'] . '.', 'status' => $w['status']]);
        }
        if ($action === 'reveal_account') json_out(200, wd_reveal_account($pdo, $admin['id'], (int) ($b['id'] ?? 0)));
        if ($action === 'upload_proof') {
            wd_save_proof($pdo, $admin['id'], (int) ($b['id'] ?? 0), $_FILES['proof'] ?? null);
            json_out(200, ['message' => 'Bukti transfer tersimpan.']);
        }
        if ($action === 'bank_verify' || $action === 'bank_reject') {
            wd_review_bank($pdo, $admin['id'], (int) ($b['id'] ?? 0), $action === 'bank_verify' ? 'verify' : 'reject', (string) ($b['reason'] ?? ''));
            json_out(200, ['message' => $action === 'bank_verify' ? 'Rekening diverifikasi.' : 'Rekening ditolak.']);
        }
        if ($action === 'adjust') {
            $uid = (int) ($b['owner_id'] ?? 0);
            if (!db_one($pdo, "SELECT id FROM users WHERE id = ? AND role = 'owner'", [$uid])) throw new WdError('Warung tidak ditemukan.', 404, 'owner_id');
            $d = $b['delta'] ?? '';
            if (!(is_int($d) || (is_string($d) && preg_match('/^-?\d{1,10}$/', $d))) || (int) $d === 0 || abs((int) $d) > 1000000000) throw new WdError('Nominal penyesuaian harus angka bulat, tidak nol.', 400, 'delta');
            $key = is_string($b['request_key'] ?? null) && preg_match('/^[A-Za-z0-9_-]{16,64}$/', $b['request_key']) ? $b['request_key'] : null;
            if (!$key) throw new WdError('Permintaan tidak valid. Muat ulang halaman.', 400);
            try {
                $r = wallet_admin_adjust($pdo, $admin['id'], $uid, (string) ($b['bucket'] ?? ''), (int) $d, (string) ($b['reason'] ?? ''), $key);
            } catch (WalletException $e) {
                throw new WdError($e->getMessage(), 400, 'reason');
            }
            json_out(200, ['message' => $r['duplicate'] ? 'Penyesuaian ini sudah tercatat sebelumnya.' : 'Penyesuaian saldo tercatat di ledger.']);
        }
        if ($action === 'release_payment') {
            $reason = clean($b['reason'] ?? '');
            if (mb_strlen($reason) < 5) throw new WdError('Alasan wajib diisi (minimal 5 karakter).', 400, 'reason');
            try {
                $res = wallet_release_fee($pdo, (int) ($b['id'] ?? 0), $admin['id'], mb_substr($reason, 0, 255));
            } catch (WalletException $e) {
                throw new WdError('Saldo pending warung lebih kecil dari pembayaran ini (mungkin sudah disesuaikan manual). Periksa mutasi saldo warung.', 409);
            }
            if ($res === 'not_found') throw new WdError('Pembayaran tidak ditemukan.', 404);
            json_out(200, ['result' => $res, 'message' => [
                'ok' => 'Saldo dilepas ke tersedia.', 'skip' => 'Pembayaran ini sudah dilepas sebelumnya.',
                'reversed' => 'Pembayaran ini sudah dibalik (refund), tidak bisa dilepas.',
            ][$res] ?? 'Selesai.']);
        }
        if ($action === 'save_settings') {
            aw_save_settings($pdo, $admin, $b);
            json_out(200, ['message' => 'Pengaturan wallet disimpan.']);
        }
    } catch (WdError $e) {
        wd_fail($e);
    }
    json_out(400, ['message' => 'Aksi tidak dikenal.']);
} catch (Throwable $ex) {
    error_log('[admin-wallet] ' . $ex->getMessage());
    json_out(500, ['message' => 'Terjadi kesalahan pada server.']);
}