<?php
// API Saldo & Withdrawal untuk pemilik warung. Semua data dibatasi ke akun yang login (owner_id dari sesi).
require __DIR__ . '/../includes/withdrawal.php';

const SALDO_PAGE = 30;

function saldo_ledger_label(string $t): string {
    return [
        'payment_pending' => 'Pembayaran QRIS masuk', 'payment_reversal' => 'Pembayaran dibalik (refund)',
        'settlement_release' => 'Saldo menjadi tersedia', 'withdrawal_hold' => 'Withdrawal diajukan',
        'withdrawal_complete' => 'Withdrawal berhasil', 'withdrawal_release' => 'Withdrawal dikembalikan',
        'manual_adjustment' => 'Penyesuaian admin',
    ][$t] ?? $t;
}

function saldo_payments(PDO $pdo, int $uid, int $before): array {
    $rows = db_all($pdo, 'SELECT f.id, t.code, f.gross_amount, f.mdr_amount, f.tax_amount, f.total_fee, f.net_amount, f.mdr_bps, f.tax_bps,
                                 f.fee_source, f.paid_at, f.release_at, f.released_at, f.reversed_at
                          FROM payment_fees f JOIN transactions t ON t.id = f.transaction_id
                          WHERE f.owner_id = ?' . ($before ? ' AND f.id < ' . (int) $before : '') . '
                          ORDER BY f.id DESC LIMIT ' . (SALDO_PAGE + 1), [$uid]);
    $more = count($rows) > SALDO_PAGE;
    $rows = array_slice($rows, 0, SALDO_PAGE);
    return ['more' => $more, 'items' => array_map(fn($r) => [
        'id' => (int) $r['id'], 'code' => $r['code'], 'gross' => (int) $r['gross_amount'], 'mdr' => (int) $r['mdr_amount'],
        'tax' => (int) $r['tax_amount'], 'fee' => (int) $r['total_fee'], 'net' => (int) $r['net_amount'],
        'mdr_pct' => $r['mdr_bps'] / 100, 'tax_pct' => $r['tax_bps'] / 100, 'estimated' => $r['fee_source'] === 'estimated',
        'paid_at' => $r['paid_at'], 'release_at' => $r['release_at'],
        'status' => $r['reversed_at'] ? 'Dibalik' : ($r['released_at'] ? 'Tersedia' : 'Pending'),
    ], $rows)];
}

function saldo_ledger(PDO $pdo, int $uid, int $before): array {
    $rows = db_all($pdo, 'SELECT id, entry_type, pending_delta, available_delta, processing_delta, description, reason, created_at
                          FROM wallet_transactions WHERE owner_id = ?' . ($before ? ' AND id < ' . (int) $before : '') . '
                          ORDER BY id DESC LIMIT ' . (SALDO_PAGE + 1), [$uid]);
    $more = count($rows) > SALDO_PAGE;
    $rows = array_slice($rows, 0, SALDO_PAGE);
    return ['more' => $more, 'items' => array_map(fn($r) => [
        'id' => (int) $r['id'], 'type' => saldo_ledger_label($r['entry_type']), 'desc' => $r['description'], 'reason' => $r['reason'],
        'pending' => (int) $r['pending_delta'], 'available' => (int) $r['available_delta'], 'processing' => (int) $r['processing_delta'],
        'time' => $r['created_at'],
    ], $rows)];
}

function saldo_overview(PDO $pdo, int $uid): array {
    $w = wallet_summary($pdo, $uid);
    $banks = db_all($pdo, "SELECT id, bank_code, bank_name, account_last4, account_holder, status, reject_reason, created_at
                           FROM bank_accounts WHERE owner_id = ? AND status <> 'disabled' ORDER BY id DESC", [$uid]);
    $wds = db_all($pdo, 'SELECT id, amount, transfer_fee, net_amount, status, bank_name, account_last4, account_holder, reject_reason, failure_reason,
                                transfer_reference, (proof_file IS NOT NULL) AS has_proof, requested_at, completed_at
                         FROM withdrawals WHERE owner_id = ? ORDER BY id DESC LIMIT 30', [$uid]);
    $catalog = [];
    foreach (wd_banks() as $c => $n) $catalog[] = ['code' => $c, 'name' => $n];
    return [
        'wallet' => $w + ['active' => wallet_is_active($pdo)],
        'config' => [
            'min_withdrawal' => max(1, (int) wallet_cfg($pdo, 'min_withdrawal', '10000')),
            'withdrawal_fee' => max(0, (int) wallet_cfg($pdo, 'withdrawal_fee_flat', '0')),
            'max_open' => max(1, (int) wallet_cfg($pdo, 'max_open_withdrawals', '1')),
            'payout_mode' => wallet_cfg($pdo, 'payout_mode', 'manual'),
        ],
        'open_withdrawals' => count(array_filter($wds, fn($r) => in_array($r['status'], WD_OPEN, true))),
        'banks' => array_map(fn($b) => [
            'id' => (int) $b['id'], 'bank_code' => $b['bank_code'], 'bank_name' => $b['bank_name'], 'last4' => $b['account_last4'],
            'holder' => $b['account_holder'], 'status' => $b['status'], 'reject_reason' => $b['reject_reason'], 'created_at' => $b['created_at'],
        ], $banks),
        'bank_catalog' => $catalog,
        'withdrawals' => array_map(fn($r) => [
            'id' => (int) $r['id'], 'amount' => (int) $r['amount'], 'fee' => (int) $r['transfer_fee'], 'net' => (int) $r['net_amount'],
            'status' => $r['status'], 'bank_name' => $r['bank_name'], 'last4' => $r['account_last4'], 'holder' => $r['account_holder'],
            'reject_reason' => $r['reject_reason'], 'failure_reason' => $r['failure_reason'], 'transfer_reference' => $r['transfer_reference'],
            'has_proof' => (bool) $r['has_proof'], 'requested_at' => $r['requested_at'], 'completed_at' => $r['completed_at'],
        ], $wds),
        'payments' => saldo_payments($pdo, $uid, 0),
        'ledger' => saldo_ledger($pdo, $uid, 0),
    ];
}

try {
    $uid = owner_api_guard($pdo);
    $m = $_SERVER['REQUEST_METHOD'] ?? '';

    if ($m === 'GET') {
        $part = $_GET['part'] ?? '';
        $before = max(0, (int) ($_GET['before'] ?? 0));
        if ($part === 'payments') json_out(200, saldo_payments($pdo, $uid, $before));
        if ($part === 'ledger') json_out(200, saldo_ledger($pdo, $uid, $before));
        json_out(200, saldo_overview($pdo, $uid));
    }
    if ($m !== 'POST') json_out(405, ['message' => 'Metode tidak diizinkan.']);

    wd_require_xhr();
    $b = read_post_json();
    $action = is_string($b['action'] ?? null) ? $b['action'] : '';

    try {
        if ($action === 'add_bank') {
            wd_add_bank($pdo, $uid, $b);
            json_out(200, ['message' => 'Rekening ditambahkan. Menunggu verifikasi admin.']);
        }
        if ($action === 'disable_bank') {
            wd_disable_bank($pdo, $uid, (int) ($b['id'] ?? 0));
            json_out(200, ['message' => 'Rekening dinonaktifkan.']);
        }
        if ($action === 'request_withdrawal') {
            $r = wd_request($pdo, $uid, $b['bank_id'] ?? 0, $b['amount'] ?? 0, $b['idempotency_key'] ?? '');
            json_out(200, [
                'message' => $r['duplicate'] ? 'Pengajuan ini sudah tercatat sebelumnya.' : 'Withdrawal berhasil diajukan. Menunggu persetujuan admin.',
                'id' => (int) $r['withdrawal']['id'], 'duplicate' => $r['duplicate'],
            ]);
        }
    } catch (WdError $e) {
        wd_fail($e);
    }
    json_out(400, ['message' => 'Aksi tidak dikenal.']);
} catch (Throwable $ex) {
    error_log('[saldo] ' . $ex->getMessage());
    json_out(500, ['message' => 'Terjadi kesalahan pada server.']);
}