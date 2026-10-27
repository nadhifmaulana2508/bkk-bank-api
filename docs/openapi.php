<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('BKKAPIDOCSSESSID');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Strict',
        'path' => '/',
    ]);
    session_start();
}

if (($_SESSION['docs_access'] ?? false) !== true) {
    http_response_code(404);
    exit;
}

$specFile = dirname(__DIR__) . '/swagger.json';
if (!is_file($specFile)) {
    http_response_code(404);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
readfile($specFile);
