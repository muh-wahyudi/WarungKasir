<?php
if (!defined('APP_TZ')) define('APP_TZ', 'Asia/Jakarta');
date_default_timezone_set(APP_TZ);

$pdo = new PDO(
    'mysql:host=' . (getenv('DB_HOST') ?: 'db') . ';dbname=' . (getenv('DB_NAME') ?: 'warungkasir_db') . ';charset=utf8mb4',
    getenv('DB_USER') ?: 'user',
    getenv('DB_PASS') ?: 'userpass',
    [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]
);

$offset = (new DateTime('now', new DateTimeZone(APP_TZ)))->format('P');
$pdo->exec("SET time_zone = '$offset'");