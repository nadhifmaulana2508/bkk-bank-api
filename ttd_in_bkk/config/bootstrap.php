<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');

$root = dirname(__DIR__);
$envFile = $root . '/.env';
if (!is_file($envFile)) {
    http_response_code(500);
    exit('File .env belum tersedia. Salin .env.example menjadi .env.');
}

$env = parse_ini_file($envFile, false, INI_SCANNER_RAW) ?: [];
function envv(string $key, $default = null) {
    global $env;
    $sys = getenv($key);
    return $sys !== false ? $sys : ($env[$key] ?? $default);
}

date_default_timezone_set('Asia/Jakarta');

session_name((string)envv('SESSION_NAME', 'TTDINBKKSESSID'));
session_set_cookie_params([
    'httponly' => true,
    'secure' => (bool)(int)envv('SESSION_SECURE', 0),
    'samesite' => 'Lax',
    'path' => '/',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
    envv('DB_HOST', '127.0.0.1'),
    (int)envv('DB_PORT', 3306),
    envv('DB_NAME', 'db_ttd_in_bkk')
);

try {
    $pdo = new PDO($dsn, (string)envv('DB_USER', 'root'), (string)envv('DB_PASS', ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Koneksi database TTD IN BKK gagal.');
}

require_once $root . '/lib/helpers.php';
require_once $root . '/lib/csrf.php';
require_once $root . '/lib/sso.php';
require_once $root . '/lib/signature.php';
