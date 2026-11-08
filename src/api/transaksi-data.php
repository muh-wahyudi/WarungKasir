<?php
// Riwayat transaksi dan laporan penjualan pemilik usaha.
// Logika periode, paket, dan perhitungan ada di includes/transaksi_lib.php; pembayaran di includes/payments.php.
require __DIR__ . '/../includes/transaksi_lib.php';
require __DIR__ . '/../includes/payments.php';

function view_list(PDO $pdo, int $uid, array $meta, array $ctx): void {
    $f = trx_filters();
    $page = max(1, (int) ($_GET['page'] ?? 1));
    [$where, $params] = trx_list_where($uid, $ctx, $f);
    $c = trx_list_count($pdo, $where, $params);
    $pages = max(1, (int) ceil($c['total'] / TRX_PER_PAGE));
    $page = min($page, $pages);
    $rows = trx_list_fetch($pdo, $where, $params, TRX_PER_PAGE, ($page - 1) * TRX_PER_PAGE);

    $tz = new DateTimeZone(APP_TZ);
    json_out(200, $meta + [
        'view' => 'list',
        'items' => array_map(function ($r) use ($tz) {
            $d = new DateTime($r['created_at'], $tz);
            return [
                'code' => $r['code'],
                'time' => trx_short($d) . ' ' . $d->format('Y') . ', ' . $d->format('H:i'),
                'customer' => $r['customer_name'],
                'total' => (int) $r['total'],
                'method' => $r['payment_method'],
                'status' => $r['status'],
                'online' => !empty($r['midtrans_order_id']),
                'items' => $r['items'],
            ];
        }, $rows),
        'total' => $c['total'],
        'sales' => $c['sales'],
        'page' => $page,
        'pages' => $pages,
        'per' => TRX_PER_PAGE,
    ]);
}

function payment_action(PDO $pdo, int $uid): void {
    $b = read_post_json();
    $action = is_string($b['action'] ?? null) ? $b['action'] : '';

    if ($action === 'check_all') {
        $rows = db_all($pdo, "SELECT id, code, status, total, midtrans_order_id, created_at FROM transactions
                              WHERE owner_id = ? AND status = 'Menunggu' AND midtrans_order_id IS NOT NULL
                              ORDER BY id LIMIT 30", [$uid]);
        $n = ['Selesai' => 0, 'Dibatalkan' => 0, 'Menunggu' => 0];
        foreach ($rows as $r) {
            $st = pay_reconcile($pdo, $uid, $r);
            $n[$st] = ($n[$st] ?? 0) + 1;
        }
        json_out(200, [
            'checked' => count($rows),
            'selesai' => $n['Selesai'],
            'dibatalkan' => $n['Dibatalkan'],
            'menunggu' => $n['Menunggu'],
        ]);
    }

    $code = clean($b['code'] ?? '');
    $trx = $code === '' ? null : pay_find($pdo, $uid, $code);
    if (!$trx) json_out(404, ['message' => 'Transaksi tidak ditemukan.']);

    if ($action === 'check') {
        if (empty($trx['midtrans_order_id'])) {
            json_out(200, ['status' => $trx['status'], 'message' => 'Transaksi ini tidak punya pembayaran online yang bisa diperiksa.']);
        }
        $st = pay_reconcile($pdo, $uid, $trx);
        $msg = [
            'Selesai' => 'Pembayaran diterima. Transaksi selesai.',
            'Dibatalkan' => 'Pembayaran kedaluwarsa atau dibatalkan. Transaksi dibatalkan dan stok barang dikembalikan.',
            'Menunggu' => 'Pembayaran belum diterima.',
        ][$st] ?? 'Status transaksi: ' . $st . '.';
        json_out(200, ['status' => $st, 'message' => $msg]);
    }

    if ($action === 'cancel') {
        $r = pay_cancel($pdo, $uid, $trx);
        json_out($r['ok'] || $r['status'] === 'Selesai' ? 200 : 503, [
            'status' => $r['status'],
            'message' => $r['message'],
        ]);
    }

    json_out(400, ['message' => 'Aksi tidak dikenal.']);
}

try {
    $uid = owner_api_guard($pdo);
    $httpMethod = $_SERVER['REQUEST_METHOD'] ?? '';
    if ($httpMethod === 'POST') payment_action($pdo, $uid);
    if ($httpMethod !== 'GET') json_out(405, ['message' => 'Metode tidak diizinkan.']);

    $ctx = trx_context($pdo, $uid);
    $meta = trx_meta($pdo, $uid, $ctx);

    if (($_GET['view'] ?? '') === 'report') {
        json_out(200, $meta + ['view' => 'report'] + trx_report($pdo, $uid, $ctx));
    }
    view_list($pdo, $uid, $meta, $ctx);
} catch (Throwable $ex) {
    error_log('[transaksi] ' . $ex->getMessage());
    json_out(500, ['message' => 'Terjadi kesalahan pada server.']);
}