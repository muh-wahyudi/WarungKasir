<?php
// API publik (tanpa login): statistik dan daftar paket untuk halaman depan.
// Hanya mengembalikan data agregat dan paket, tidak ada data pribadi.
require __DIR__ . '/../includes/owner.php';

try {
    $ni = fn($v) => $v === null ? null : (int) $v;

    $warung = (int) db_one($pdo, "SELECT COUNT(*) AS n FROM users WHERE role = 'owner' AND is_active = 1")['n'];
    $trx = (int) db_one($pdo, "SELECT COUNT(*) AS n FROM transactions WHERE status = 'Selesai'")['n'];

    // Samakan dengan halaman Pengaturan: semua paket ditampilkan.
    // Untuk menyembunyikan paket nonaktif, tambahkan "WHERE is_active = 1" pada query ini.
    $plans = array_map(fn($r) => [
        'code' => $r['code'],
        'name' => $r['name'],
        'price_monthly' => (int) $r['price_monthly'],
        'max_products' => $ni($r['max_products']),
        'history_days' => $ni($r['history_days']),
        'max_accounts' => $ni($r['max_accounts']),
    ], db_all($pdo, 'SELECT code, name, price_monthly, max_products, history_days, max_accounts FROM plans ORDER BY price_monthly, id'));

    json_out(200, ['warung' => $warung, 'transaksi' => $trx, 'plans' => $plans]);
} catch (Throwable $e) {
    error_log('[stats-publik] ' . $e->getMessage());
    json_out(500, ['message' => 'Terjadi kesalahan pada server.']);
}