<?php
// Penerima notifikasi pembayaran Midtrans (Payment Notification URL).
// Alamat ini harus bisa dibuka publik tanpa login, dan diisi di Midtrans Dashboard:
//   Settings > Configuration > Payment Notification URL  ->  https://DOMAIN-ANDA/api/midtrans-webhook.php
//
// Keamanan:
//  1. Signature SHA-512 diverifikasi (order_id + status_code + gross_amount + server key).
//  2. Status TIDAK dipercaya dari isi notifikasi: dicek ulang langsung ke API Midtrans.
//  3. Idempoten: notifikasi yang sama (event_key) dan operasi saldo yang sama tidak diproses dua kali.
// Pesanan 'WK-' = pembayaran kasir (masuk wallet pemilik). Pesanan 'SUB-' = langganan (pendapatan platform).
require __DIR__ . '/../includes/payments.php';
if (function_exists('session_abort')) @session_abort();   // webhook tidak memakai sesi

function hook_subscription(PDO $pdo, string $order, array $mt): string {
    $s = db_one($pdo, 'SELECT s.id, s.user_id, s.status, p.price_monthly FROM subscriptions s
                       JOIN plans p ON p.id = s.plan_id WHERE s.payment_ref = ? LIMIT 1', [$order]);
    if (!$s) return 'unknown_order';
    $c = pay_classify($mt);
    if ($c === 'paid') {
        if ((int) round((float) ($mt['gross_amount'] ?? 0)) !== (int) $s['price_monthly']) return 'needs_review_amount';
        if ($s['status'] === 'active') return 'already_active';
        if ($s['status'] !== 'pending_payment') return 'needs_review_paid_after_cancel';
        return wallet_tx($pdo, function () use ($pdo, $s) {
            $u = $pdo->prepare("UPDATE subscriptions SET status = 'active', started_at = NOW(), ends_at = DATE_ADD(NOW(), INTERVAL 30 DAY)
                                WHERE id = ? AND status = 'pending_payment'");
            $u->execute([(int) $s['id']]);
            if ($u->rowCount() === 0) return 'already_active';
            $pdo->prepare("UPDATE subscriptions SET status = 'expired' WHERE user_id = ? AND status = 'active' AND id <> ?")
                ->execute([(int) $s['user_id'], (int) $s['id']]);
            return 'activated';
        });
    }
    if ($c === 'dead' && $s['status'] === 'pending_payment') {
        $pdo->prepare("UPDATE subscriptions SET status = 'canceled' WHERE id = ? AND status = 'pending_payment'")->execute([(int) $s['id']]);
        return 'canceled';
    }
    return $c === 'pending' ? 'pending' : 'ignored';
}

function hook_kasir(PDO $pdo, string $order, array $mt): string {
    $t = db_one($pdo, 'SELECT id, owner_id, status, total FROM transactions WHERE midtrans_order_id = ? LIMIT 1', [$order]);
    if (!$t) return 'unknown_order';
    $uid = (int) $t['owner_id'];
    $ts = (string) ($mt['transaction_status'] ?? '');

    if (in_array($ts, ['refund', 'partial_refund', 'chargeback'], true)) {
        $f = db_one($pdo, 'SELECT id FROM payment_fees WHERE transaction_id = ?', [(int) $t['id']]);
        return $f ? 'refund_' . wallet_reverse_payment($pdo, (int) $f['id']) : 'refund_no_wallet_entry';
    }

    $c = pay_classify($mt);
    if ($c === 'paid') {
        if ((int) round((float) ($mt['gross_amount'] ?? 0)) !== (int) $t['total']) return 'needs_review_amount';
        if ($t['status'] === 'Dibatalkan') return 'needs_review_paid_after_cancel';   // dana masuk setelah transaksi dibatalkan
        if ($t['status'] === 'Menunggu') { pay_mark_paid($pdo, $uid, (int) $t['id'], $mt); return 'paid'; }
        $r = wallet_credit_payment($pdo, (int) $t['id'], $mt);   // sudah Selesai: pastikan tercatat di wallet
        return 'already_paid_' . $r['result'];
    }
    if ($c === 'dead') {
        if ($t['status'] !== 'Menunggu') return 'ignored';
        pay_cancel_local($pdo, $uid, (int) $t['id']);
        return 'canceled';
    }
    return $c === 'pending' ? 'pending' : 'ignored';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_out(405, ['message' => 'Metode tidak diizinkan.']);
$raw = file_get_contents('php://input');
if ($raw === false || strlen($raw) > 65536) json_out(400, ['message' => 'Permintaan tidak valid.']);
$p = json_decode($raw, true);
if (!is_array($p)) json_out(400, ['message' => 'Permintaan tidak valid.']);
foreach (['order_id', 'status_code', 'gross_amount', 'signature_key', 'transaction_status'] as $k) {
    if (!is_string($p[$k] ?? null) || $p[$k] === '') json_out(400, ['message' => 'Data notifikasi tidak lengkap.']);
}

$expected = hash('sha512', $p['order_id'] . $p['status_code'] . $p['gross_amount'] . MIDTRANS_SERVER_KEY);
if (!hash_equals($expected, strtolower($p['signature_key']))) {
    error_log('[webhook] signature tidak valid untuk order ' . substr($p['order_id'], 0, 60) . ' dari ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
    json_out(403, ['message' => 'Signature tidak valid.']);
}

try {
    $order = $p['order_id'];
    $key = hash('sha256', implode('|', [$order, $p['transaction_status'], $p['status_code'], $p['gross_amount'], (string) ($p['transaction_time'] ?? '')]));
    $pdo->prepare('INSERT IGNORE INTO midtrans_events (event_key, order_id, transaction_id, transaction_status, status_code, gross_amount, signature_valid, payload)
                   VALUES (?, ?, ?, ?, ?, ?, 1, ?)')
        ->execute([$key, substr($order, 0, 100), is_string($p['transaction_id'] ?? null) ? substr($p['transaction_id'], 0, 64) : null,
                   substr($p['transaction_status'], 0, 30), substr($p['status_code'], 0, 10), substr($p['gross_amount'], 0, 20), $raw]);
    $ev = db_one($pdo, 'SELECT id, processed_at FROM midtrans_events WHERE event_key = ?', [$key]);
    if ($ev['processed_at'] !== null) json_out(200, ['ok' => true, 'result' => 'duplicate']);

    // Status asli dari Midtrans (bukan dari isi notifikasi). Gagal menghubungi -> 503 agar Midtrans mengulang.
    $mt = midtrans_get_status($order);
    if (!$mt) json_out(503, ['message' => 'Midtrans belum dapat dihubungi.']);

    if (strncmp($order, 'WK-', 3) === 0) $result = hook_kasir($pdo, $order, $mt);
    elseif (strncmp($order, 'SUB-', 4) === 0) $result = hook_subscription($pdo, $order, $mt);
    else $result = 'ignored_prefix';

    $pdo->prepare('UPDATE midtrans_events SET result = ?, processed_at = NOW() WHERE id = ?')->execute([substr($result, 0, 60), (int) $ev['id']]);
    if (str_starts_with($result, 'needs_review')) error_log("[webhook] PERLU DITINJAU: $order => $result");
    json_out(200, ['ok' => true, 'result' => $result]);
} catch (Throwable $e) {
    error_log('[webhook] ' . $e->getMessage());
    json_out(500, ['message' => 'Terjadi kesalahan pada server.']);   // Midtrans akan mengulang pengiriman
}