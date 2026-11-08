<?php
// Menampilkan bukti transfer withdrawal. Hanya admin, atau pemilik warung yang bersangkutan.
require __DIR__ . '/../includes/withdrawal.php';

try {
    if (empty($_SESSION['user_id'])) { http_response_code(401); exit; }
    $me = db_one($pdo, 'SELECT id, role FROM users WHERE id = ? AND is_active = 1', [$_SESSION['user_id']]);
    if (!$me) { http_response_code(401); exit; }
    $w = db_one($pdo, 'SELECT owner_id, proof_file FROM withdrawals WHERE id = ?', [(int) ($_GET['id'] ?? 0)]);
    // Pemilik lain mendapat 404 yang sama dengan bukti yang tidak ada (tidak membocorkan keberadaan data)
    if (!$w || !$w['proof_file'] || ($me['role'] !== 'admin' && (int) $w['owner_id'] !== (int) $me['id'])) { http_response_code(404); exit; }
    if (!preg_match('/^[0-9]+-[a-f0-9]{24}\.(jpg|png|pdf)$/', $w['proof_file'], $m)) { http_response_code(404); exit; }
    $path = wd_proof_dir() . '/' . $w['proof_file'];
    if (!is_file($path)) { http_response_code(404); exit; }
    $type = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf'][$m[1]];
    header('Content-Type: ' . $type);
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    header('Content-Disposition: inline; filename="bukti-transfer-' . (int) $_GET['id'] . '.' . $m[1] . '"');
    readfile($path);
} catch (Throwable $e) {
    error_log('[bukti] ' . $e->getMessage());
    http_response_code(500);
}