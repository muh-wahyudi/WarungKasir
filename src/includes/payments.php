<?php
// Penyelesaian dan pembatalan transaksi QRIS yang berstatus "Menunggu".
// Dipakai api/kasir-data.php (modal pembayaran) dan api/transaksi-data.php (halaman Transaksi & Laporan).
//
// Aturan penting:
//  - Transaksi yang sudah dibayar di Midtrans TIDAK pernah dibatalkan; ia diselesaikan.
//  - Pembatalan lokal (status Dibatalkan + stok dikembalikan) hanya setelah Midtrans memastikan
//    pesanan itu batal/kedaluwarsa/tidak ada. Bila Midtrans tidak dapat dihubungi, tidak ada yang diubah.
//  - Semua perubahan status memakai penjaga "status masih Menunggu", jadi aman dipanggil berulang
//    dan stok tidak pernah dikembalikan dua kali.
require_once __DIR__ . '/owner.php';
require_once __DIR__ . '/midtrans.php';
require_once __DIR__ . '/wallet.php';

const PAY_STALE_MINUTES = 10;   // pesanan yang belum tercatat di Midtrans dianggap gagal dibuat setelah selama ini

function pay_find(PDO $pdo, int $uid, string $code): ?array {
    return db_one($pdo, 'SELECT id, code, status, total, midtrans_order_id, created_at FROM transactions WHERE owner_id = ? AND code = ? LIMIT 1', [$uid, $code]) ?: null;
}

function pay_find_by_order(PDO $pdo, int $uid, string $orderId): ?array {
    return db_one($pdo, 'SELECT id, code, status, total, midtrans_order_id, created_at FROM transactions WHERE owner_id = ? AND midtrans_order_id = ? LIMIT 1', [$uid, $orderId]) ?: null;
}

function pay_status(PDO $pdo, int $trxId): string {
    return (string) (db_one($pdo, 'SELECT status FROM transactions WHERE id = ?', [$trxId])['status'] ?? '');
}

// Membaca balasan status Midtrans: paid | dead | pending | missing | unknown
function pay_classify(array $r): string {
    if (!$r) return 'unknown';   // gagal terhubung atau balasan bukan JSON
    $ts = (string) ($r['transaction_status'] ?? '');
    if ($ts === 'settlement' || ($ts === 'capture' && ($r['fraud_status'] ?? 'accept') === 'accept')) return 'paid';
    if (in_array($ts, ['expire', 'cancel', 'deny', 'failure'], true)) return 'dead';
    if ($ts === 'pending') return 'pending';
    if ((string) ($r['status_code'] ?? '') === '404') return 'missing';
    return 'unknown';
}

// Menandai selesai. Hanya berlaku jika masih Menunggu. $mt = balasan status Midtrans (bila ada).
// Setelah itu pembayaran QRIS dicatat ke saldo pending pemilik. Pencatatan aman diulang (idempoten);
// bila gagal, penjualan tetap selesai dan wallet-cron.php melengkapinya.
function pay_mark_paid(PDO $pdo, int $uid, int $trxId, array $mt = []): void {
    $pdo->prepare("UPDATE transactions SET status = 'Selesai' WHERE id = ? AND owner_id = ? AND status = 'Menunggu'")->execute([$trxId, $uid]);
    try {
        wallet_credit_payment($pdo, $trxId, $mt ?: null);
    } catch (Throwable $e) {
        error_log('[wallet] pencatatan trx ' . $trxId . ' gagal: ' . $e->getMessage());
    }
}

// Membatalkan secara lokal dan mengembalikan stok, dalam satu transaksi database.
function pay_cancel_local(PDO $pdo, int $uid, int $trxId): void {
    $pdo->beginTransaction();
    try {
        $t = db_one($pdo, 'SELECT status FROM transactions WHERE id = ? AND owner_id = ? FOR UPDATE', [$trxId, $uid]);
        if ($t && $t['status'] === 'Menunggu') {
            $pdo->prepare("UPDATE transactions SET status = 'Dibatalkan' WHERE id = ?")->execute([$trxId]);
            $restock = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ? AND owner_id = ?');
            foreach (db_all($pdo, 'SELECT product_id, qty FROM transaction_items WHERE transaction_id = ? AND product_id IS NOT NULL', [$trxId]) as $it) {
                $restock->execute([(int) $it['qty'], (int) $it['product_id'], $uid]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function pay_is_stale(array $trx): bool {
    $created = new DateTime($trx['created_at'], new DateTimeZone(APP_TZ));
    return (new DateTime('now', new DateTimeZone(APP_TZ)))->getTimestamp() - $created->getTimestamp() >= PAY_STALE_MINUTES * 60;
}

// Mencocokkan transaksi Menunggu dengan Midtrans. Mengembalikan status terakhir.
// Transaksi tanpa nomor pesanan Midtrans tidak disentuh (tidak ada yang bisa diperiksa).
function pay_reconcile(PDO $pdo, int $uid, array $trx): string {
    if ($trx['status'] !== 'Menunggu' || empty($trx['midtrans_order_id'])) return $trx['status'];
    $mt = midtrans_get_status($trx['midtrans_order_id']);
    $c = pay_classify($mt);
    if ($c === 'paid') pay_mark_paid($pdo, $uid, (int) $trx['id'], $mt);
    elseif ($c === 'dead' || ($c === 'missing' && pay_is_stale($trx))) pay_cancel_local($pdo, $uid, (int) $trx['id']);
    return pay_status($pdo, (int) $trx['id']);
}

// Pembatalan oleh pemilik. Hasil: ['ok' => bool, 'status' => ..., 'message' => ...]
function pay_cancel(PDO $pdo, int $uid, array $trx): array {
    $id = (int) $trx['id'];
    if ($trx['status'] === 'Dibatalkan') return ['ok' => true, 'status' => 'Dibatalkan', 'message' => 'Transaksi sudah dibatalkan.'];
    if ($trx['status'] === 'Selesai') return ['ok' => false, 'status' => 'Selesai', 'message' => 'Transaksi sudah selesai dan tidak dapat dibatalkan.'];

    $paid = fn() => ['ok' => false, 'status' => 'Selesai', 'message' => 'Pembayaran sudah diterima, jadi transaksi diselesaikan dan tidak dibatalkan.'];
    $done = fn() => ['ok' => true, 'status' => 'Dibatalkan', 'message' => 'Transaksi dibatalkan dan stok barang dikembalikan.'];

    if (empty($trx['midtrans_order_id'])) {   // tidak ada pembayaran online yang perlu dibatalkan
        pay_cancel_local($pdo, $uid, $id);
        return pay_status($pdo, $id) === 'Dibatalkan' ? $done() : ['ok' => false, 'status' => pay_status($pdo, $id), 'message' => 'Status transaksi sudah berubah.'];
    }

    $order = $trx['midtrans_order_id'];
    $mt = midtrans_get_status($order);
    $c = pay_classify($mt);
    if ($c === 'paid') { pay_mark_paid($pdo, $uid, $id, $mt); return $paid(); }
    if ($c === 'dead' || $c === 'missing') { pay_cancel_local($pdo, $uid, $id); return $done(); }
    if ($c === 'pending') {
        $r = midtrans_cancel($order);
        $ts = (string) ($r['transaction_status'] ?? '');
        if (in_array($ts, ['cancel', 'expire'], true)) { pay_cancel_local($pdo, $uid, $id); return $done(); }
        // Pembatalan ditolak: bisa jadi pelanggan baru saja membayar. Periksa ulang sebelum menyerah.
        $mt2 = midtrans_get_status($order);
        $c2 = pay_classify($mt2);
        if ($c2 === 'paid') { pay_mark_paid($pdo, $uid, $id, $mt2); return $paid(); }
        if ($c2 === 'dead' || $c2 === 'missing') { pay_cancel_local($pdo, $uid, $id); return $done(); }
        return ['ok' => false, 'status' => 'Menunggu', 'message' => 'Midtrans belum dapat membatalkan pembayaran ini. Coba lagi beberapa saat.'];
    }
    return ['ok' => false, 'status' => 'Menunggu', 'message' => 'Tidak dapat menghubungi Midtrans. Transaksi belum diubah, coba lagi.'];
}