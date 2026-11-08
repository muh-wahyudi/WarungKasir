<?php
// Fungsi bersama halaman Transaksi & Laporan (dipakai api/transaksi-data.php dan api/transaksi-export.php).
// Semua data hanya milik akun yang login. Rentang riwayat mengikuti batas paket langganan.
require_once __DIR__ . '/owner.php';

const TRX_PER_PAGE = 10;
const TRX_PERIODS = ['today', '7', '30', 'month', 'all', 'custom'];
const TRX_STATUSES = ['Selesai', 'Menunggu', 'Dibatalkan'];
const TRX_METHODS = ['Tunai', 'QRIS', 'Transfer'];
const TRX_MON = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const TRX_DAY = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

function trx_short(DateTime $d): string { return (int) $d->format('j') . ' ' . TRX_MON[(int) $d->format('n')]; }
function trx_long(DateTime $d): string { return trx_short($d) . ' ' . $d->format('Y'); }
function trx_sql(DateTime $d): string { return $d->format('Y-m-d H:i:s'); }

// Paket aktif: batas riwayat (hari) dan apakah paket berbayar (boleh ekspor).
function trx_plan(PDO $pdo, int $uid): array {
    $row = db_one($pdo, "SELECT p.name, p.history_days, p.price_monthly FROM subscriptions s
                         JOIN plans p ON p.id = s.plan_id WHERE s.user_id = ? AND s.status = 'active'
                         ORDER BY s.started_at DESC, s.id DESC LIMIT 1", [$uid])
        ?: db_one($pdo, "SELECT name, history_days, price_monthly FROM plans WHERE code = 'free' LIMIT 1");
    $hd = $row['history_days'] === null ? null : (int) $row['history_days'];
    return ['name' => $row['name'], 'history_days' => ($hd !== null && $hd < 1) ? null : $hd, 'paid' => (int) $row['price_monthly'] > 0];
}

function trx_parse_date($s, DateTimeZone $tz): ?DateTime {
    if (!is_string($s) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m)) return null;
    if ((int) $m[1] < 2000 || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) return null;
    return new DateTime($s . ' 00:00:00', $tz);
}

function trx_range_info(DateTime $from, DateTime $to): array {
    if ($to <= $from) {
        return ['from' => $from->format('Y-m-d'), 'to' => $from->format('Y-m-d'), 'label' => 'Di luar riwayat yang tersedia pada paket Anda'];
    }
    $last = (clone $to)->modify('-1 day');
    $same = $from->format('Y-m-d') === $last->format('Y-m-d');
    return [
        'from' => $from->format('Y-m-d'),
        'to' => $last->format('Y-m-d'),
        'label' => $same ? trx_long($from) : trx_long($from) . ' – ' . trx_long($last),
    ];
}

// Menentukan periode dari parameter permintaan: rentang [dari, sampai), pemangkasan oleh paket, dan periode pembanding.
function trx_context(PDO $pdo, int $uid): array {
    $tz = new DateTimeZone(APP_TZ);
    $plan = trx_plan($pdo, $uid);
    $today = new DateTime('today', $tz);
    $tomorrow = (clone $today)->modify('+1 day');

    $period = is_string($_GET['period'] ?? null) && in_array($_GET['period'], TRX_PERIODS, true) ? $_GET['period'] : '7';
    $from = null;
    $to = clone $tomorrow;

    if ($period === 'custom') {
        $a = trx_parse_date($_GET['from'] ?? null, $tz);
        $b = trx_parse_date($_GET['to'] ?? null, $tz);
        if ($a && $b) {
            if ($a > $b) { [$a, $b] = [$b, $a]; }
            $from = $a;
            $to = (clone $b)->modify('+1 day');
            if ($to > $tomorrow) $to = clone $tomorrow;
            if ($from >= $to) $from = clone $today;
        } else {
            $period = '7';
        }
    }
    if ($from === null) {
        switch ($period) {
            case 'today': $from = clone $today; break;
            case 'month': $from = (clone $today)->modify('first day of this month'); break;
            case '30': $from = (clone $today)->modify('-29 days'); break;
            case 'all':
                $first = db_one($pdo, 'SELECT MIN(created_at) AS m FROM transactions WHERE owner_id = ?', [$uid])['m'] ?? null;
                $from = $first ? (new DateTime($first, $tz))->setTime(0, 0) : clone $today;
                break;
            default:
                $period = '7';
                $from = (clone $today)->modify('-6 days');
        }
    }

    // Batas riwayat paket
    $floor = null;
    $clipped = false;
    if ($plan['history_days'] !== null) {
        $floor = (clone $today)->modify('-' . ($plan['history_days'] - 1) . ' days');
        if ($from < $floor) { $from = clone $floor; $clipped = true; }
    }

    // Periode pembanding: rentang sama panjang tepat sebelumnya (Bulan Ini: awal bulan lalu dengan jumlah hari yang sama)
    $prev = null;
    if ($period !== 'all' && !$clipped && $to > $from) {
        $len = (int) $from->diff($to)->days;
        if ($period === 'month') {
            $pf = (clone $from)->modify('first day of last month');
            $pt = (clone $pf)->modify("+$len days");
            if ($pt > $from) $pt = clone $from;
        } else {
            $pt = clone $from;
            $pf = (clone $from)->modify("-$len days");
        }
        if ($floor === null || $pf >= $floor) {
            $prev = ['from' => $pf, 'to' => $pt, 'label' => trx_range_info($pf, $pt)['label']];
        }
    }

    return [
        'period' => $period, 'from' => $from, 'to' => $to, 'clipped' => $clipped,
        'plan' => $plan, 'prev' => $prev, 'range' => trx_range_info($from, $to), 'today' => $today->format('Y-m-d'),
    ];
}

// Data pendamping yang selalu dikirim bersama hasil
function trx_meta(PDO $pdo, int $uid, array $ctx): array {
    return [
        'period' => $ctx['period'],
        'range' => $ctx['range'],
        'today' => $ctx['today'],
        'clipped' => $ctx['clipped'],
        'plan' => ['name' => $ctx['plan']['name'], 'history_days' => $ctx['plan']['history_days']],
        'can_export' => $ctx['plan']['paid'],
        'pending_total' => (int) db_one($pdo, "SELECT COUNT(*) AS n FROM transactions WHERE owner_id = ? AND status = 'Menunggu'", [$uid])['n'],
    ];
}

/* ---------- Riwayat ---------- */

function trx_filters(): array {
    return [
        'q' => is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 60) : '',
        'status' => is_string($_GET['status'] ?? null) && in_array($_GET['status'], TRX_STATUSES, true) ? $_GET['status'] : '',
        'method' => is_string($_GET['method'] ?? null) && in_array($_GET['method'], TRX_METHODS, true) ? $_GET['method'] : '',
    ];
}

function trx_list_where(int $uid, array $ctx, array $f): array {
    $where = 'owner_id = ? AND created_at >= ? AND created_at < ?';
    $params = [$uid, trx_sql($ctx['from']), trx_sql($ctx['to'])];
    if ($f['q'] !== '') {
        $like = '%' . addcslashes($f['q'], '%_\\') . '%';
        $where .= ' AND (code LIKE ? OR customer_name LIKE ?)';
        array_push($params, $like, $like);
    }
    if ($f['status'] !== '') { $where .= ' AND status = ?'; $params[] = $f['status']; }
    if ($f['method'] !== '') { $where .= ' AND payment_method = ?'; $params[] = $f['method']; }
    return [$where, $params];
}

function trx_list_count(PDO $pdo, string $where, array $params): array {
    $agg = db_one($pdo, "SELECT COUNT(*) AS n, COALESCE(SUM(CASE WHEN status = 'Selesai' THEN total END), 0) AS sales
                         FROM transactions WHERE $where", $params);
    return ['total' => (int) $agg['n'], 'sales' => (int) $agg['sales']];
}

// Transaksi terbaru lebih dulu, masing-masing disertai daftar barangnya (kunci 'items')
function trx_list_fetch(PDO $pdo, string $where, array $params, int $limit, int $offset): array {
    $rows = db_all($pdo, "SELECT id, code, customer_name, total, payment_method, status, midtrans_order_id, created_at FROM transactions
                          WHERE $where ORDER BY created_at DESC, id DESC LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset, $params);
    if (!$rows) return [];
    $ids = array_column($rows, 'id');
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $items = [];
    foreach (db_all($pdo, "SELECT transaction_id, product_name, qty, unit_price FROM transaction_items
                           WHERE transaction_id IN ($ph) ORDER BY id", $ids) as $r) {
        $items[$r['transaction_id']][] = ['name' => $r['product_name'], 'qty' => (int) $r['qty'], 'price' => (int) $r['unit_price']];
    }
    foreach ($rows as $i => $r) $rows[$i]['items'] = $items[$r['id']] ?? [];
    return $rows;
}

/* ---------- Laporan ---------- */

// Penjualan selesai pada rentang tertentu: nilai, jumlah transaksi, rata-rata, dan jumlah barang
function trx_window_totals(PDO $pdo, int $uid, DateTime $from, DateTime $to): array {
    $p = [$uid, trx_sql($from), trx_sql($to)];
    $r = db_one($pdo, "SELECT COUNT(*) AS n, COALESCE(SUM(total), 0) AS sales FROM transactions
                       WHERE owner_id = ? AND status = 'Selesai' AND created_at >= ? AND created_at < ?", $p);
    $q = db_one($pdo, "SELECT COALESCE(SUM(i.qty), 0) AS q FROM transaction_items i JOIN transactions t ON t.id = i.transaction_id
                       WHERE t.owner_id = ? AND t.status = 'Selesai' AND t.created_at >= ? AND t.created_at < ?", $p);
    $n = (int) $r['n'];
    $s = (int) $r['sales'];
    return ['sales' => $s, 'transactions' => $n, 'avg' => $n > 0 ? (int) round($s / $n) : 0, 'items' => (int) $q['q']];
}

function trx_report(PDO $pdo, int $uid, array $ctx, int $productLimit = 200): array {
    $from = $ctx['from'];
    $to = $ctx['to'];
    $F = trx_sql($from);
    $T = trx_sql($to);
    $p = [$uid, $F, $T];

    // Ringkasan per status
    $byStatus = [];
    foreach (TRX_STATUSES as $s) $byStatus[$s] = ['n' => 0, 'total' => 0];
    foreach (db_all($pdo, 'SELECT status, COUNT(*) AS n, COALESCE(SUM(total), 0) AS total FROM transactions
                           WHERE owner_id = ? AND created_at >= ? AND created_at < ? GROUP BY status', $p) as $r) {
        $byStatus[$r['status']] = ['n' => (int) $r['n'], 'total' => (int) $r['total']];
    }
    $stats = trx_window_totals($pdo, $uid, $from, $to) + ['pending' => $byStatus['Menunggu']['n'], 'canceled' => $byStatus['Dibatalkan']['n']];
    $sales = $stats['sales'];

    $compare = null;
    if ($ctx['prev']) {
        $compare = ['label' => $ctx['prev']['label']] + trx_window_totals($pdo, $uid, $ctx['prev']['from'], $ctx['prev']['to']);
    }

    // Metode pembayaran (hanya transaksi selesai)
    $methods = [];
    foreach (db_all($pdo, "SELECT payment_method AS m, COUNT(*) AS n, COALESCE(SUM(total), 0) AS total FROM transactions
                           WHERE owner_id = ? AND created_at >= ? AND created_at < ? AND status = 'Selesai'
                           GROUP BY payment_method ORDER BY total DESC", $p) as $r) {
        $methods[] = ['method' => $r['m'], 'n' => (int) $r['n'], 'total' => (int) $r['total'],
                      'pct' => $sales > 0 ? round((int) $r['total'] / $sales * 100, 1) : 0];
    }

    // Rekap per jam (satu hari), per hari (sampai 62 hari), atau per bulan; yang terbaru di atas
    $bucket = function (string $expr) use ($pdo, $uid, $F, $T): array {
        $out = [];
        foreach (db_all($pdo, "SELECT $expr AS b, COUNT(*) AS n, COALESCE(SUM(total), 0) AS v FROM transactions
                               WHERE owner_id = ? AND status = 'Selesai' AND created_at >= ? AND created_at < ? GROUP BY b", [$uid, $F, $T]) as $r) {
            $out[(string) $r['b']] = ['n' => (int) $r['n'], 'sales' => (int) $r['v'], 'items' => 0];
        }
        foreach (db_all($pdo, 'SELECT ' . str_replace('created_at', 't.created_at', $expr) . " AS b, COALESCE(SUM(i.qty), 0) AS q
                               FROM transaction_items i JOIN transactions t ON t.id = i.transaction_id
                               WHERE t.owner_id = ? AND t.status = 'Selesai' AND t.created_at >= ? AND t.created_at < ? GROUP BY b", [$uid, $F, $T]) as $r) {
            $k = (string) $r['b'];
            if (isset($out[$k])) $out[$k]['items'] = (int) $r['q'];
        }
        return $out;
    };
    $row = fn(string $label, array $v) => [
        'label' => $label, 'n' => $v['n'], 'items' => $v['items'], 'sales' => $v['sales'],
        'avg' => $v['n'] > 0 ? (int) round($v['sales'] / $v['n']) : 0,
    ];
    $zero = ['n' => 0, 'sales' => 0, 'items' => 0];
    $days = (int) $from->diff($to)->days;
    $rows = [];
    if ($days === 1) {
        $unit = 'jam';
        $map = $bucket('HOUR(created_at)');
        for ($h = 23; $h >= 0; $h--) {
            if (isset($map[(string) $h])) $rows[] = $row(sprintf('%02d.00–%02d.59', $h, $h), $map[(string) $h]);
        }
    } elseif ($days <= 62) {
        $unit = 'hari';
        $map = $bucket('DATE(created_at)');
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = (clone $from)->modify("+$i days");
            $rows[] = $row(TRX_DAY[(int) $d->format('w')] . ', ' . trx_long($d), $map[$d->format('Y-m-d')] ?? $zero);
        }
    } else {
        $unit = 'bulan';
        $map = $bucket("DATE_FORMAT(created_at, '%Y-%m')");
        $cur = (clone $from)->modify('first day of this month');
        while ($cur < $to) {
            $rows[] = $row(TRX_MON[(int) $cur->format('n')] . ' ' . $cur->format('Y'), $map[$cur->format('Y-m')] ?? $zero);
            $cur->modify('first day of next month');
        }
        $rows = array_reverse($rows);
    }

    // Penjualan per produk (semua produk, bukan hanya yang teratas)
    $prod = db_all($pdo, "SELECT i.product_name AS name, MAX(p.sku) AS sku, SUM(i.qty) AS qty, SUM(i.qty * i.unit_price) AS revenue
                          FROM transaction_items i JOIN transactions t ON t.id = i.transaction_id
                          LEFT JOIN products p ON p.id = i.product_id AND p.owner_id = t.owner_id
                          WHERE t.owner_id = ? AND t.status = 'Selesai' AND t.created_at >= ? AND t.created_at < ?
                          GROUP BY i.product_name ORDER BY qty DESC, revenue DESC LIMIT " . ($productLimit + 1), $p);
    $more = count($prod) > $productLimit;
    $prod = array_slice($prod, 0, $productLimit);

    return [
        'stats' => $stats,
        'compare' => $compare,
        'statuses' => $byStatus,
        'methods' => $methods,
        'recap' => ['unit' => $unit, 'rows' => $rows],
        'products' => array_map(fn($r) => [
            'name' => $r['name'], 'sku' => $r['sku'], 'qty' => (int) $r['qty'], 'revenue' => (int) $r['revenue'],
            'avg_price' => (int) $r['qty'] > 0 ? (int) round((int) $r['revenue'] / (int) $r['qty']) : 0,
            'pct' => $sales > 0 ? round((int) $r['revenue'] / $sales * 100, 1) : 0,
        ], $prod),
        'products_more' => $more,
    ];
}