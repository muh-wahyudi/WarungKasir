<?php
// Ekspor Excel (.xlsx) riwayat transaksi atau laporan penjualan (api/transaksi-export.php).
// Hanya untuk paket berbayar (Pro dan Pro Max). Periode dan filter sama dengan halaman Transaksi & Laporan.
require __DIR__ . '/../includes/transaksi_lib.php';
require __DIR__ . '/../includes/xlsx.php';

const EXPORT_MAX_TRX = 5000;

function pct_change(int $cur, int $prev) {
    if ($prev === 0) return $cur > 0 ? '-' : 0.0;
    return round(($cur - $prev) / $prev * 100, 1);
}

function export_list(PDO $pdo, int $uid, array $ctx): array {
    $f = trx_filters();
    [$where, $params] = trx_list_where($uid, $ctx, $f);
    $c = trx_list_count($pdo, $where, $params);
    $rows = trx_list_fetch($pdo, $where, $params, EXPORT_MAX_TRX, 0);

    $trx = [[['v' => 'ID Transaksi', 'bold' => true], ['v' => 'Tanggal & Waktu', 'bold' => true], ['v' => 'Pelanggan', 'bold' => true],
             ['v' => 'Metode', 'bold' => true], ['v' => 'Status', 'bold' => true], ['v' => 'Total (Rp)', 'bold' => true]]];
    $items = [[['v' => 'ID Transaksi', 'bold' => true], ['v' => 'Produk', 'bold' => true], ['v' => 'Jumlah', 'bold' => true],
               ['v' => 'Harga Satuan (Rp)', 'bold' => true], ['v' => 'Subtotal (Rp)', 'bold' => true]]];
    foreach ($rows as $r) {
        $trx[] = [$r['code'], substr($r['created_at'], 0, 16), $r['customer_name'], $r['payment_method'], $r['status'], (int) $r['total']];
        foreach ($r['items'] as $it) $items[] = [$r['code'], $it['name'], $it['qty'], $it['price'], $it['qty'] * $it['price']];
    }
    if ($c['total'] > EXPORT_MAX_TRX) {
        $trx[] = [];
        $trx[] = ['Catatan: hanya ' . number_format(EXPORT_MAX_TRX, 0, ',', '.') . ' transaksi terbaru dari ' . number_format($c['total'], 0, ',', '.') . ' yang diekspor. Persempit periode atau filter untuk data lainnya.'];
    }
    return [
        'file' => 'transaksi_' . $ctx['range']['from'] . '_' . $ctx['range']['to'] . '.xlsx',
        'sheets' => [
            ['name' => 'Transaksi', 'rows' => $trx, 'widths' => [16, 20, 26, 12, 14, 16], 'freeze' => true],
            ['name' => 'Rincian Barang', 'rows' => $items, 'widths' => [16, 34, 10, 18, 16], 'freeze' => true],
        ],
    ];
}

function export_report(PDO $pdo, int $uid, array $ctx): array {
    $d = trx_report($pdo, $uid, $ctx, 2000);
    $s = $d['stats'];
    $b = fn($t) => ['v' => $t, 'bold' => true];

    $sum = [
        [$b('Laporan Penjualan')],
        ['Periode', $ctx['range']['label']],
        ['Paket', $ctx['plan']['name']],
        ['Dibuat', (new DateTime('now', new DateTimeZone(APP_TZ)))->format('Y-m-d H:i')],
        [],
        [$b('Ringkasan'), $b('Nilai')],
        ['Total Penjualan (Rp)', $s['sales']],
        ['Transaksi Selesai', $s['transactions']],
        ['Rata-rata per Transaksi (Rp)', $s['avg']],
        ['Barang Terjual', $s['items']],
    ];
    if ($d['compare']) {
        $c = $d['compare'];
        $sum[] = [];
        $sum[] = [$b('Perbandingan dengan periode sebelumnya'), $c['label']];
        $sum[] = [$b(''), $b('Periode ini'), $b('Periode sebelumnya'), $b('Perubahan (%)')];
        foreach ([['Total Penjualan (Rp)', 'sales'], ['Transaksi Selesai', 'transactions'], ['Rata-rata per Transaksi (Rp)', 'avg'], ['Barang Terjual', 'items']] as [$label, $k]) {
            $pc = pct_change($s[$k], $c[$k]);
            $sum[] = [$label, $s[$k], $c[$k], is_string($pc) ? $pc : ['v' => $pc, 'pct' => true]];
        }
    }
    $sum[] = [];
    $sum[] = [$b('Metode Pembayaran'), $b('Transaksi'), $b('Penjualan (Rp)'), $b('Porsi')];
    foreach ($d['methods'] as $m) $sum[] = [$m['method'], $m['n'], $m['total'], ['v' => (float) $m['pct'], 'pct' => true]];
    $sum[] = [];
    $sum[] = [$b('Status Transaksi'), $b('Transaksi'), $b('Nilai (Rp)')];
    foreach ($d['statuses'] as $name => $v) $sum[] = [$name, $v['n'], $v['total']];

    $unit = ['jam' => 'Jam', 'hari' => 'Tanggal', 'bulan' => 'Bulan'][$d['recap']['unit']];
    $rec = [[$b($unit), $b('Transaksi'), $b('Barang Terjual'), $b('Penjualan (Rp)'), $b('Rata-rata (Rp)')]];
    foreach ($d['recap']['rows'] as $r) $rec[] = [$r['label'], $r['n'], $r['items'], $r['sales'], $r['avg']];
    $rec[] = [$b('Total'), ['v' => $s['transactions'], 'bold' => true], ['v' => $s['items'], 'bold' => true], ['v' => $s['sales'], 'bold' => true], ['v' => $s['avg'], 'bold' => true]];

    $prod = [[$b('Produk'), $b('Kode'), $b('Terjual'), $b('Penjualan (Rp)'), $b('Rata-rata Harga (Rp)'), $b('Porsi')]];
    foreach ($d['products'] as $p) $prod[] = [$p['name'], $p['sku'] ?? '', $p['qty'], $p['revenue'], $p['avg_price'], ['v' => (float) $p['pct'], 'pct' => true]];
    if ($d['products_more']) {
        $prod[] = [];
        $prod[] = ['Catatan: hanya 2.000 produk teratas yang diekspor.'];
    }
    return [
        'file' => 'laporan_' . $ctx['range']['from'] . '_' . $ctx['range']['to'] . '.xlsx',
        'sheets' => [
            ['name' => 'Ringkasan', 'rows' => $sum, 'widths' => [38, 20, 20, 16]],
            ['name' => 'Rekap', 'rows' => $rec, 'widths' => [26, 12, 16, 18, 16], 'freeze' => true],
            ['name' => 'Produk', 'rows' => $prod, 'widths' => [34, 16, 10, 18, 20, 10], 'freeze' => true],
        ],
    ];
}

try {
    $uid = owner_api_guard($pdo);
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') json_out(405, ['message' => 'Metode tidak diizinkan.']);

    $ctx = trx_context($pdo, $uid);
    if (!$ctx['plan']['paid']) json_out(403, ['message' => 'Ekspor Excel tersedia untuk paket Pro dan Pro Max.']);

    $out = ($_GET['view'] ?? '') === 'report' ? export_report($pdo, $uid, $ctx) : export_list($pdo, $uid, $ctx);
    $bin = xlsx_build($out['sheets']);

    http_response_code(200);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $out['file'] . '"');
    header('Content-Length: ' . strlen($bin));
    header('Cache-Control: no-store');
    echo $bin;
    exit;
} catch (Throwable $ex) {
    error_log('[transaksi-export] ' . $ex->getMessage());
    json_out(500, ['message' => 'Ekspor gagal. Coba lagi.']);
}