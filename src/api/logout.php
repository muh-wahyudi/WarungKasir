<?php
require __DIR__ . '/../includes/helpers.php';
read_post_json();
$_SESSION = [];
setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
session_destroy();
json_out(200, ['message' => 'Anda sudah keluar.', 'redirect' => 'login.html']);
