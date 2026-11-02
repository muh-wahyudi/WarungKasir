<?php
// Data dashboard pemilik usaha (api/dashboard-data.php). Semua data diambil dari database dan hanya milik akun yang login.
require __DIR__ . '/../includes/helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') json_out(405, ['message' => 'Metode tidak diizinkan.']);
if (empty($_SESSION['user_id'])) json_out(401, ['message' => 'Sesi berakhir. Silakan masuk kembali.']);

function one(PDO $pdo, string $sql, array $p = []) { $s = $pdo->prepare($sql); $s->execute($p); return $s->fetch(); }
function all(PDO $pdo, string $sql, array $p = []): array { $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchAll(); }

try {
    $me = one($pdo, 'SELECT id, role FROM users WHERE id = ? AND is_active = 1 LIMIT 1', [$_SESSION['user_id']]);
    if (!$me) json_out(401, ['message' => 'Sesi tidak valid. Silakan masuk kembali.']);
    if ($me['role'] !== 'owner') json_out(403, ['message' => 'Akses ditolak.']);
    $uid = (int) $me['id'];   // selalu dari sesi, tidak pernah dari permintaan

    $period = is_string($_GET['period'] ?? null) ? $_GET['period'] : 'today';
    $days = ['today' => 1, '7' => 7, '30' => 30][$period] ?? null;
    if ($days === null) { $period = 'today'; $days = 1; }

    $tz = new DateTimeZone(APP_TZ);
    $now = new DateTime('now', $tz);
    $from = (new DateTime('today', $tz))->modify('-' . ($days - 1) . ' days');
    $to = (new DateTime('today', $tz))->modify('+1 day');
    $F = $from->format('Y-m-d H:i:s');
    $T = $to->format('Y-m-d H:i:s');

    $DAY = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $DAYS = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
    $MON = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $fmtDate = fn(DateTime $d) => (int) $d->format('j') . ' ' . $MON[(int) $d->format('n')];

    // Penjualan dan jumlah transaksi pada periode (hanya yang berstatus Selesai)
    $s = one($pdo, "SELECT COALESCE(SUM(total), 0) AS sales, COUNT(*) AS n FROM transactions
                    WHERE owner_id = ? AND status = 'Selesai' AND created_at >= ? AND created_at < ?", [$uid, $F, $T]);

    // Grafik
    $chart = [];
    if ($period === 'today') {
        $b = array_fill(0, 12, 0);
        foreach (all($pdo, "SELECT HOUR(created_at) AS h, SUM(total) AS v FROM transactions
                            WHERE owner_id = ? AND status = 'Selesai' AND created_at >= ? AND created_at < ? GROUP BY h", [$uid, $F, $T]) as $r) {
            $b[intdiv((int) $r['h'], 2)] += (int) $r['v'];
        }
        $nz = array_keys(array_filter($b));
        $lo = min(3, $nz ? min($nz) : 3);
        $hi = min(11, max(10, $nz ? max($nz) : 10, intdiv((int) $now->format('G'), 2)));
        for ($i = $lo; $i <= $hi; $i++) {
            $chart[] = ['label' => sprintf('%02d', $i * 2), 'value' => $b[$i], 'tip' => sprintf('Pukul %02d.00–%02d.59', $i * 2, $i * 2 + 1)];
        }
    } else {
        $map = [];
        foreach (all($pdo, "SELECT DATE(created_at) AS d, SUM(total) AS v FROM transactions
                            WHERE owner_id = ? AND status = 'Selesai' AND created_at >= ? AND created_at < ? GROUP BY d", [$uid, $F, $T]) as $r) {
            $map[$r['d']] = (int) $r['v'];
        }
        for ($i = 0; $i < $days; $i++) {
            $d = (clone $from)->modify("+$i days");
            $w = (int) $d->format('w');
            $chart[] = [
                'label' => $days === 7 ? $DAYS[$w] : ($i % 5 === 4 ? $fmtDate($d) : ''),
                'value' => $map[$d->format('Y-m-d')] ?? 0,
                'tip' => $DAY[$w] . ', ' . $fmtDate($d),
            ];
        }
    }

    // Transaksi terbaru (semua status) beserta itemnya untuk detail
    $recent = all($pdo, "SELECT id, code, customer_name, total, payment_method, status, created_at FROM transactions
                         WHERE owner_id = ? ORDER BY created_at DESC, id DESC LIMIT 6", [$uid]);
    $items = [];
    if ($recent) {
        $ids = array_column($recent, 'id');
        $ph = implode(',', array_fill(0, count($ids), '?'));
        foreach (all($pdo, "SELECT transaction_id, product_name, qty, unit_price FROM transaction_items WHERE transaction_id IN ($ph) ORDER BY id", $ids) as $r) {
            $items[$r['transaction_id']][] = ['name' => $r['product_name'], 'qty' => (int) $r['qty'], 'price' => (int) $r['unit_price']];
        }
    }
    $recentOut = array_map(function ($r) use ($items, $tz, $fmtDate) {
        $d = new DateTime($r['created_at'], $tz);
        return [
            'code' => $r['code'], 'time' => $fmtDate($d) . ' ' . $d->format('Y') . ', ' . $d->format('H:i'),
            'customer' => $r['customer_name'], 'total' => (int) $r['total'], 'method' => $r['payment_method'],
            'status' => $r['status'], 'items' => $items[$r['id']] ?? [],
        ];
    }, $recent);

    // Produk terlaris pada periode
    $top = all($pdo, "SELECT i.product_name AS name, SUM(i.qty) AS qty, SUM(i.qty * i.unit_price) AS revenue, MAX(p.image) AS image
                      FROM transaction_items i JOIN transactions t ON t.id = i.transaction_id
                      LEFT JOIN products p ON p.id = i.product_id AND p.owner_id = t.owner_id
                      WHERE t.owner_id = ? AND t.status = 'Selesai' AND t.created_at >= ? AND t.created_at < ?
                      GROUP BY i.product_name ORDER BY qty DESC, revenue DESC LIMIT 5", [$uid, $F, $T]);

    // Stok
    $products = (int) one($pdo, 'SELECT COUNT(*) AS n FROM products WHERE owner_id = ? AND is_active = 1', [$uid])['n'];
    $lowCount = (int) one($pdo, 'SELECT COUNT(*) AS n FROM products WHERE owner_id = ? AND is_active = 1 AND stock <= min_stock', [$uid])['n'];
    $restock = all($pdo, 'SELECT name, stock, min_stock FROM products WHERE owner_id = ? AND is_active = 1 AND stock <= min_stock
                          ORDER BY stock ASC, name ASC LIMIT 5', [$uid]);
    $pending = (int) one($pdo, "SELECT COUNT(*) AS n FROM transactions WHERE owner_id = ? AND status = 'Menunggu'", [$uid])['n'];

    // Paket langganan
    $plan = one($pdo, "SELECT p.name, p.max_products, p.history_days, p.max_accounts FROM subscriptions s
                       JOIN plans p ON p.id = s.plan_id WHERE s.user_id = ? AND s.status = 'active'
                       ORDER BY s.started_at DESC, s.id DESC LIMIT 1", [$uid])
        ?: one($pdo, "SELECT name, max_products, history_days, max_accounts FROM plans WHERE code = 'free' LIMIT 1");
    $nullInt = fn($v) => $v === null ? null : (int) $v;
    $others = all($pdo, 'SELECT name, price_monthly FROM plans WHERE price_monthly > 0 ORDER BY price_monthly');

    json_out(200, [
        'period' => $period,
        'stats' => ['sales' => (int) $s['sales'], 'transactions' => (int) $s['n'], 'products' => $products, 'low_stock' => $lowCount, 'pending' => $pending],
        'chart' => $chart,
        'recent' => $recentOut,
        'top' => array_map(fn($r) => ['name' => $r['name'], 'qty' => (int) $r['qty'], 'revenue' => (int) $r['revenue'], 'image' => $r['image'] ? 'uploads/' . $r['image'] : null], $top),
        'restock' => array_map(fn($r) => ['name' => $r['name'], 'stock' => (int) $r['stock'], 'min_stock' => (int) $r['min_stock']], $restock),
        'plan' => ['name' => $plan['name'], 'max_products' => $nullInt($plan['max_products']), 'history_days' => $nullInt($plan['history_days']),
                   'max_accounts' => $nullInt($plan['max_accounts']), 'used_products' => $products],
        'other_plans' => array_map(fn($r) => ['name' => $r['name'], 'price' => (int) $r['price_monthly']], $others),
        'server_time' => $now->format('H:i:s'),
    ]);
} catch (Throwable $e) {
    error_log('[dashboard] ' . $e->getMessage());
    json_out(500, ['message' => 'Terjadi kesalahan pada server.']);
}