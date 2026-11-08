<?php
// Inti sistem Wallet: ledger saldo, biaya QRIS, pelepasan saldo, dan dasar withdrawal.
//
// Aturan yang dijaga di sini:
//  - Setiap perubahan saldo = 1 baris ledger (wallet_transactions) + update wallets, dalam SATU transaksi database.
//  - Baris wallet dikunci (SELECT ... FOR UPDATE) sebelum saldo dihitung, jadi dua proses bersamaan
//    untuk pemilik yang sama berjalan bergantian dan saldo tidak pernah negatif.
//  - idempotency_key UNIQUE: operasi yang sama tidak pernah tercatat dua kali.
//  - Semua nominal Rupiah bulat. Tarif dalam basis points (100 bps = 1%).
//  - Wallet tidak aktif sampai wallet_settings.wallet_start_at diisi.
require_once __DIR__ . '/owner.php';
require_once __DIR__ . '/midtrans.php';

class WalletException extends RuntimeException {}

/* ---------- Dasar ---------- */

// Menjalankan $fn dalam transaksi database. Bila sudah ada transaksi berjalan, ikut di dalamnya.
// Jika MySQL membatalkan transaksi karena deadlock/lock timeout (1213/1205), diulang sampai 4 kali;
// aman karena seluruh isi $fn hanya operasi database yang ikut dibatalkan.
function wallet_tx(PDO $pdo, callable $fn) {
    if ($pdo->inTransaction()) return $fn();
    for ($try = 1; ; $try++) {
        $pdo->beginTransaction();
        try {
            $r = $fn();
            $pdo->commit();
            return $r;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $code = $e instanceof PDOException ? (int) ($e->errorInfo[1] ?? 0) : 0;
            if (($code === 1213 || $code === 1205) && $try < 4) { usleep(random_int(20000, 120000) * $try); continue; }
            throw $e;
        }
    }
}

function wallet_cfg(PDO $pdo, string $key, ?string $default = null): ?string {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db_all($pdo, 'SELECT setting_key, setting_value FROM wallet_settings') as $r) $cache[$r['setting_key']] = $r['setting_value'];
    }
    return $cache[$key] ?? $default;
}

function wallet_ensure(PDO $pdo, int $uid): void {
    $pdo->prepare('INSERT IGNORE INTO wallets (owner_id) VALUES (?)')->execute([$uid]);
}

function wallet_is_active(PDO $pdo): bool {
    return trim((string) wallet_cfg($pdo, 'wallet_start_at', '')) !== '';
}

function audit_log(PDO $pdo, int $adminId, string $action, string $entityType, ?int $entityId, array $detail = [], ?string $reason = null): void {
    $pdo->prepare('INSERT INTO admin_audit_log (admin_id, action, entity_type, entity_id, detail, reason, ip) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$adminId, $action, $entityType, $entityId, json_encode($detail, JSON_UNESCAPED_UNICODE), $reason, substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45)]);
}

/* ---------- Biaya QRIS ---------- */

// MDR = gross x tarif; pajak = MDR x tarif pajak; dibulatkan ke Rupiah terdekat (setengah naik).
// Ini ESTIMASI dari konfigurasi. Biaya aktual Midtrans bisa berbeda tipis dan dicocokkan lewat rekonsiliasi.
function wallet_fee_calc(int $gross, int $mdrBps, int $taxBps): array {
    if ($gross <= 0 || $mdrBps < 0 || $taxBps < 0) throw new WalletException('Parameter biaya tidak valid.');
    $mdr = intdiv($gross * $mdrBps + 5000, 10000);
    $tax = intdiv($mdr * $taxBps + 5000, 10000);
    $total = $mdr + $tax;
    if ($total >= $gross) throw new WalletException('Biaya melebihi nominal pembayaran. Periksa tarif di pengaturan wallet.');
    return ['mdr' => $mdr, 'tax' => $tax, 'total' => $total, 'net' => $gross - $total];
}

// Waktu notifikasi Midtrans berzona WIB (Asia/Jakarta); diubah ke zona aplikasi.
function wallet_midtrans_time(array $mt): string {
    $s = $mt['settlement_time'] ?? $mt['transaction_time'] ?? null;
    try {
        $d = new DateTime((string) $s, new DateTimeZone('Asia/Jakarta'));
    } catch (Throwable $e) {
        $d = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
    }
    return $d->setTimezone(new DateTimeZone(APP_TZ))->format('Y-m-d H:i:s');
}

// Saat saldo pending boleh menjadi tersedia: N hari kerja setelah pembayaran (Sabtu/Minggu dilewati,
// hari libur nasional belum dihitung), pukul 12.00.
function wallet_release_at(string $paidAt, int $days): string {
    $d = new DateTime($paidAt, new DateTimeZone(APP_TZ));
    $d->setTime(12, 0, 0);
    for ($left = max(0, $days); $left > 0;) {
        $d->modify('+1 day');
        if ((int) $d->format('N') < 6) $left--;
    }
    return $d->format('Y-m-d H:i:s');
}

/* ---------- Ledger ---------- */

// Mencatat satu perubahan saldo. HARUS dipanggil di dalam transaksi database.
// $d: pending, available, processing (perubahan, boleh negatif), withdrawn, fees (penambah total).
// Mengembalikan ['duplicate' => true] bila idempotency_key sudah pernah dipakai.
function wallet_post(PDO $pdo, int $uid, string $type, array $d, string $key, array $o = []): array {
    if (!$pdo->inTransaction()) throw new LogicException('wallet_post harus dipanggil di dalam transaksi database.');
    // Kunci baris wallet LEBIH DULU. Membuat wallet (INSERT IGNORE) sebelum mengunci menimbulkan deadlock
    // pada proses bersamaan, jadi pembuatan hanya dilakukan bila barisnya belum ada.
    $lock = 'SELECT pending_balance, available_balance, processing_balance FROM wallets WHERE owner_id = ? FOR UPDATE';
    $w = db_one($pdo, $lock, [$uid]);
    if (!$w) { wallet_ensure($pdo, $uid); $w = db_one($pdo, $lock, [$uid]); }
    if (!$w) throw new WalletException('Wallet tidak ditemukan.');

    $dp = (int) ($d['pending'] ?? 0);
    $da = (int) ($d['available'] ?? 0);
    $dr = (int) ($d['processing'] ?? 0);
    $np = (int) $w['pending_balance'] + $dp;
    $na = (int) $w['available_balance'] + $da;
    $nr = (int) $w['processing_balance'] + $dr;
    if ($np < 0 || $na < 0 || $nr < 0) {
        // Pengulangan operasi yang sudah pernah tercatat harus dijawab 'duplicate', bukan 'saldo kurang'.
        // Pembacaan terkunci hanya dilakukan di jalur gagal ini, yang segera membatalkan transaksi.
        if (db_one($pdo, 'SELECT id FROM wallet_transactions WHERE idempotency_key = ? FOR UPDATE', [$key])) return ['duplicate' => true];
        throw new WalletException('Saldo tidak mencukupi.');
    }

    try {
        $pdo->prepare('INSERT INTO wallet_transactions (owner_id, entry_type, pending_delta, available_delta, processing_delta,
                         pending_after, available_after, processing_after, ref_type, ref_id, idempotency_key, description, reason, actor_id)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$uid, $type, $dp, $da, $dr, $np, $na, $nr, $o['ref_type'] ?? null, $o['ref_id'] ?? null, $key,
                       substr((string) ($o['desc'] ?? ''), 0, 255), $o['reason'] ?? null, $o['actor'] ?? null]);
    } catch (PDOException $e) {
        if ((int) ($e->errorInfo[1] ?? 0) === 1062) return ['duplicate' => true];
        throw $e;
    }
    $id = (int) $pdo->lastInsertId();
    $pdo->prepare('UPDATE wallets SET pending_balance = ?, available_balance = ?, processing_balance = ?,
                     total_withdrawn = total_withdrawn + ?, total_fees = total_fees + ? WHERE owner_id = ?')
        ->execute([$np, $na, $nr, (int) ($d['withdrawn'] ?? 0), (int) ($d['fees'] ?? 0), $uid]);
    return ['duplicate' => false, 'id' => $id, 'pending' => $np, 'available' => $na, 'processing' => $nr];
}

function wallet_summary(PDO $pdo, int $uid): array {
    wallet_ensure($pdo, $uid);
    $w = db_one($pdo, 'SELECT pending_balance, available_balance, processing_balance, total_withdrawn, total_fees FROM wallets WHERE owner_id = ?', [$uid]);
    return [
        'pending' => (int) $w['pending_balance'], 'available' => (int) $w['available_balance'],
        'processing' => (int) $w['processing_balance'], 'withdrawn' => (int) $w['total_withdrawn'], 'fees' => (int) $w['total_fees'],
    ];
}

/* ---------- Pembayaran QRIS masuk ---------- */

// Mencatat pembayaran QRIS kasir yang sudah lunas ke saldo PENDING pemilik. Aman dipanggil berulang.
// $mt = balasan status Midtrans (bila null, diambil dari Midtrans). Hasil: credited | duplicate | wallet_inactive | ...
function wallet_credit_payment(PDO $pdo, int $trxId, ?array $mt = null): array {
    $t = db_one($pdo, 'SELECT id, owner_id, code, total, payment_method, status, midtrans_order_id, created_at FROM transactions WHERE id = ?', [$trxId]);
    if (!$t) return ['result' => 'not_found'];
    // Hanya QRIS kasir (WK-). Pembayaran langganan (SUB-) adalah pendapatan platform, bukan saldo warung.
    if ($t['payment_method'] !== 'QRIS' || strncmp((string) $t['midtrans_order_id'], 'WK-', 3) !== 0) return ['result' => 'not_eligible'];
    if (!wallet_is_active($pdo)) return ['result' => 'wallet_inactive'];
    if ($t['created_at'] < trim((string) wallet_cfg($pdo, 'wallet_start_at'))) return ['result' => 'before_start'];
    if ($t['status'] !== 'Selesai') return ['result' => 'not_paid'];
    if (db_one($pdo, 'SELECT id FROM payment_fees WHERE transaction_id = ?', [$trxId])) return ['result' => 'duplicate'];

    if ($mt === null) $mt = midtrans_get_status((string) $t['midtrans_order_id']);   // di luar transaksi database
    if (pay_classify($mt) !== 'paid') return ['result' => 'not_paid'];
    if (($mt['order_id'] ?? '') !== $t['midtrans_order_id']) return ['result' => 'order_mismatch'];
    $gross = (int) round((float) ($mt['gross_amount'] ?? 0));
    if ($gross !== (int) $t['total']) {
        error_log("[wallet] nominal Midtrans ($gross) beda dengan transaksi {$t['code']} ({$t['total']}): tidak dicatat");
        return ['result' => 'amount_mismatch'];
    }
    $mtTx = (string) ($mt['transaction_id'] ?? '');
    if ($mtTx === '') return ['result' => 'no_transaction_id'];

    $mdrBps = (int) wallet_cfg($pdo, 'mdr_bps', '0');
    $taxBps = (int) wallet_cfg($pdo, 'mdr_tax_bps', '0');
    $fee = wallet_fee_calc($gross, $mdrBps, $taxBps);
    $paidAt = wallet_midtrans_time($mt);
    $releaseAt = wallet_release_at($paidAt, (int) wallet_cfg($pdo, 'settlement_delay_days', '1'));
    $uid = (int) $t['owner_id'];

    return wallet_tx($pdo, function () use ($pdo, $t, $uid, $gross, $mtTx, $mdrBps, $taxBps, $fee, $paidAt, $releaseAt, $trxId) {
        db_one($pdo, 'SELECT id FROM transactions WHERE id = ? FOR UPDATE', [$trxId]);   // serialkan proses yang bersamaan
        try {
            $pdo->prepare("INSERT INTO payment_fees (transaction_id, owner_id, midtrans_order_id, midtrans_transaction_id, gross_amount,
                             mdr_bps, tax_bps, mdr_amount, tax_amount, total_fee, net_amount, fee_source, payment_status, paid_at, release_at)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'estimated', 'settlement', ?, ?)")
                ->execute([$trxId, $uid, $t['midtrans_order_id'], $mtTx, $gross, $mdrBps, $taxBps, $fee['mdr'], $fee['tax'], $fee['total'], $fee['net'], $paidAt, $releaseAt]);
        } catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) return ['result' => 'duplicate'];
            throw $e;
        }
        $feeId = (int) $pdo->lastInsertId();
        $pdo->prepare('UPDATE transactions SET midtrans_transaction_id = COALESCE(midtrans_transaction_id, ?), paid_at = COALESCE(paid_at, ?) WHERE id = ?')
            ->execute([$mtTx, $paidAt, $trxId]);
        $r = wallet_post($pdo, $uid, 'payment_pending', ['pending' => $fee['net'], 'fees' => $fee['total']], 'pay:' . $trxId,
            ['ref_type' => 'payment_fee', 'ref_id' => $feeId, 'desc' => 'Pembayaran QRIS ' . $t['code']]);
        return ['result' => $r['duplicate'] ? 'duplicate' : 'credited', 'fee_id' => $feeId, 'net' => $fee['net'], 'fee' => $fee['total']];
    });
}

// Pembayaran dibalik (refund) saat saldonya MASIH pending. Bila sudah tersedia, tidak diubah otomatis.
function wallet_reverse_payment(PDO $pdo, int $feeId): string {
    return wallet_tx($pdo, function () use ($pdo, $feeId) {
        $f = db_one($pdo, 'SELECT id, owner_id, net_amount, released_at, reversed_at FROM payment_fees WHERE id = ? FOR UPDATE', [$feeId]);
        if (!$f) return 'not_found';
        if ($f['reversed_at'] !== null) return 'duplicate';
        if ($f['released_at'] !== null) return 'needs_review';   // dana sudah tersedia: perlu keputusan admin
        $r = wallet_post($pdo, (int) $f['owner_id'], 'payment_reversal', ['pending' => -(int) $f['net_amount']], 'rev:' . $feeId,
            ['ref_type' => 'payment_fee', 'ref_id' => $feeId, 'desc' => 'Pembayaran dibalik (refund)']);
        $pdo->prepare('UPDATE payment_fees SET reversed_at = NOW() WHERE id = ?')->execute([$feeId]);
        return $r['duplicate'] ? 'duplicate' : 'reversed';
    });
}

/* ---------- Pending -> Available ---------- */

// Melepas SATU pembayaran: pending -> tersedia. Dipakai tugas berkala dan tombol "Lepas sekarang" admin.
// Idempoten: pembayaran yang sudah dilepas/dibalik dilewati. Jika $adminId diisi, tercatat di audit log.
// Mengembalikan: ok | skip | reversed | not_found
function wallet_release_fee(PDO $pdo, int $feeId, ?int $adminId = null, ?string $reason = null): string {
    return wallet_tx($pdo, function () use ($pdo, $feeId, $adminId, $reason) {
        $r = db_one($pdo, 'SELECT id, owner_id, net_amount, released_at, reversed_at FROM payment_fees WHERE id = ? FOR UPDATE', [$feeId]);
        if (!$r) return 'not_found';
        if ($r['reversed_at'] !== null) return 'reversed';
        if ($r['released_at'] !== null) return 'skip';
        $p = wallet_post($pdo, (int) $r['owner_id'], 'settlement_release',
            ['pending' => -(int) $r['net_amount'], 'available' => (int) $r['net_amount']], 'rel:' . $r['id'],
            ['ref_type' => 'payment_fee', 'ref_id' => (int) $r['id'], 'actor' => $adminId, 'reason' => $reason,
             'desc' => $adminId ? 'Saldo dilepas manual oleh admin' : 'Saldo tersedia setelah settlement']);
        if ($p['duplicate']) return 'skip';
        $pdo->prepare('UPDATE payment_fees SET released_at = NOW() WHERE id = ? AND released_at IS NULL')->execute([(int) $r['id']]);
        if ($adminId) audit_log($pdo, $adminId, 'release_payment', 'payment_fee', (int) $r['id'], ['owner_id' => (int) $r['owner_id'], 'net' => (int) $r['net_amount'], 'ledger_id' => $p['id']], $reason);
        return 'ok';
    });
}

// Memindahkan saldo pending yang sudah melewati jeda settlement ke tersedia. Sebelum dipindah,
// status pembayaran di Midtrans diperiksa ulang; bila bukan 'settlement' lagi (mis. refund), saldo ditahan.
// Galat pada satu pembayaran dicatat dan tidak menghentikan pembayaran lain.
// $verify (opsional, untuk pengujian): fn(string $orderId): string  => hasil seperti pay_classify().
function wallet_release_due(PDO $pdo, ?callable $verify = null, int $limit = 200): array {
    $verify = $verify ?? fn($order) => pay_classify(midtrans_get_status($order));
    $out = ['released' => 0, 'held' => 0, 'skipped' => 0, 'errors' => 0];
    $rows = db_all($pdo, 'SELECT id, midtrans_order_id FROM payment_fees
                          WHERE released_at IS NULL AND reversed_at IS NULL AND release_at <= NOW()
                          ORDER BY id LIMIT ' . (int) $limit);
    foreach ($rows as $f) {
        try {
            if ($verify((string) $f['midtrans_order_id']) !== 'paid') {
                $out['held']++;
                error_log('[wallet] ' . $f['midtrans_order_id'] . ' tidak lagi settlement di Midtrans: saldo ditahan, perlu ditinjau.');
                continue;
            }
            $res = wallet_release_fee($pdo, (int) $f['id']);
            $out[$res === 'ok' ? 'released' : 'skipped']++;
        } catch (Throwable $e) {
            $out['errors']++;
            error_log('[wallet] pelepasan payment_fee ' . $f['id'] . ' gagal: ' . $e->getMessage() . ' (saldo pending mungkin sudah diubah manual; periksa ledger)');
        }
    }
    return $out;
}

// Melengkapi pembayaran QRIS yang sudah Selesai tetapi belum tercatat di wallet
// (misalnya pencatatan sempat gagal). Aman diulang.
function wallet_backfill_missing(PDO $pdo, int $limit = 100): array {
    $out = ['credited' => 0, 'other' => 0];
    if (!wallet_is_active($pdo)) return $out;
    $rows = db_all($pdo, "SELECT t.id FROM transactions t
                          WHERE t.payment_method = 'QRIS' AND t.status = 'Selesai' AND t.midtrans_order_id LIKE 'WK-%'
                            AND t.created_at >= ? AND NOT EXISTS (SELECT 1 FROM payment_fees f WHERE f.transaction_id = t.id)
                          ORDER BY t.id LIMIT " . (int) $limit, [trim((string) wallet_cfg($pdo, 'wallet_start_at'))]);
    foreach ($rows as $r) {
        try {
            $res = wallet_credit_payment($pdo, (int) $r['id']);
            $out[$res['result'] === 'credited' ? 'credited' : 'other']++;
        } catch (Throwable $e) {
            error_log('[wallet] backfill trx ' . $r['id'] . ': ' . $e->getMessage());
            $out['other']++;
        }
    }
    return $out;
}

/* ---------- Withdrawal (operasi saldo; alur pengajuan dan admin dibuat di tahap berikutnya) ---------- */

// Mengunci saldo tersedia untuk pengajuan: available -> processing.
function wallet_withdrawal_hold(PDO $pdo, int $uid, int $wdId, int $amount): array {
    if ($amount <= 0) throw new WalletException('Nominal tidak valid.');
    return wallet_tx($pdo, fn() => wallet_post($pdo, $uid, 'withdrawal_hold', ['available' => -$amount, 'processing' => $amount],
        "wd:$wdId:hold", ['ref_type' => 'withdrawal', 'ref_id' => $wdId, 'desc' => 'Saldo dikunci untuk withdrawal #' . $wdId]));
}

// Transfer berhasil: saldo processing dipotong permanen.
function wallet_withdrawal_complete(PDO $pdo, int $uid, int $wdId, int $amount): array {
    return wallet_tx($pdo, fn() => wallet_post($pdo, $uid, 'withdrawal_complete', ['processing' => -$amount, 'withdrawn' => $amount],
        "wd:$wdId:complete", ['ref_type' => 'withdrawal', 'ref_id' => $wdId, 'desc' => 'Withdrawal #' . $wdId . ' berhasil']));
}

// Ditolak atau gagal: saldo kembali ke tersedia.
function wallet_withdrawal_release(PDO $pdo, int $uid, int $wdId, int $amount, string $why): array {
    return wallet_tx($pdo, fn() => wallet_post($pdo, $uid, 'withdrawal_release', ['processing' => -$amount, 'available' => $amount],
        "wd:$wdId:release", ['ref_type' => 'withdrawal', 'ref_id' => $wdId, 'desc' => 'Withdrawal #' . $wdId . ' ' . $why]));
}

// Penyesuaian manual oleh admin: wajib alasan, tercatat di ledger dan audit log.
function wallet_admin_adjust(PDO $pdo, int $adminId, int $uid, string $bucket, int $delta, string $reason, string $requestKey): array {
    $reason = trim($reason);
    if (!in_array($bucket, ['pending', 'available'], true) || $delta === 0) throw new WalletException('Penyesuaian tidak valid.');
    if (mb_strlen($reason) < 5) throw new WalletException('Alasan penyesuaian wajib diisi (minimal 5 karakter).');
    return wallet_tx($pdo, function () use ($pdo, $adminId, $uid, $bucket, $delta, $reason, $requestKey) {
        $r = wallet_post($pdo, $uid, 'manual_adjustment', [$bucket => $delta], 'adj:' . $requestKey,
            ['reason' => $reason, 'actor' => $adminId, 'desc' => 'Penyesuaian manual admin']);
        if (!$r['duplicate']) audit_log($pdo, $adminId, 'wallet_adjust', 'wallet', $uid, ['bucket' => $bucket, 'delta' => $delta, 'ledger_id' => $r['id']], $reason);
        return $r;
    });
}

require_once __DIR__ . '/payments.php';   // pay_classify() dan kawan-kawan