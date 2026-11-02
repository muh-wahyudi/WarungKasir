<?php
require_once __DIR__ . '/../koneksi.php';

ini_set('session.gc_maxlifetime', '2592000'); // 30 hari (untuk "Ingat Saya")
ini_set('session.use_strict_mode', '1');
session_name('wk_sid');
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'httponly' => true,
    'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']),
]);
session_start();

function json_out(int $code, array $data): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Hanya menerima POST JSON dari situs yang sama
function read_post_json(): array {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_out(405, ['message' => 'Metode tidak diizinkan.']);
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]) {
        json_out(403, ['message' => 'Permintaan ditolak.']);
    }
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) json_out(400, ['message' => 'Permintaan tidak valid.']);
    return $data;
}

function clean($v): string {
    return is_string($v) ? trim(preg_replace('/\s+/', ' ', $v)) : '';
}
