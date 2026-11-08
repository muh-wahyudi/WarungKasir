<?php
// Tugas berkala wallet. Jalankan dari command line setiap 5-10 menit, misalnya:
//   */10 * * * * php /var/www/html/cron/wallet-cron.php >> /var/log/wallet-cron.log 2>&1
// 1) melengkapi pembayaran QRIS yang sudah Selesai tapi belum tercatat di wallet
// 2) memindahkan saldo pending yang sudah melewati jeda settlement menjadi tersedia
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../includes/wallet.php';

$a = wallet_backfill_missing($pdo);
$b = wallet_release_due($pdo);
echo date('Y-m-d H:i:s'), ' backfill=', json_encode($a), ' release=', json_encode($b), PHP_EOL;