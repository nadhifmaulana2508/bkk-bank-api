<?php
declare(strict_types=1);

require_once __DIR__ . '/config/env.php';

// =========================================
// 🔥 PENGAMAN CORS (WAJIB DI PALING ATAS)
// =========================================
// API tidak boleh membocorkan warning/path/stack trace ke client.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'");
header('X-Permitted-Cross-Domain-Policies: none');
header('Cache-Control: no-store');

$maxRequestBytes = 1024 * 1024;
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $maxRequestBytes) {
    http_response_code(413);
    echo json_encode([
        'status' => 413,
        'message' => 'Ukuran request terlalu besar.',
        'data' => null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// CORS harus memakai allowlist. Isi CORS_ALLOWED_ORIGINS di environment
// server, dipisahkan koma. Origin yang tidak terdaftar tidak diberi header CORS.
$configuredOrigins = app_env_value('CORS_ALLOWED_ORIGINS');
$allowedOrigins = $configuredOrigins === null
    ? []
    : array_values(array_filter(array_map('trim', explode(',', $configuredOrigins))));
$origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));

if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, App, X-App-Name, X-App-Token, X-Employee-Id');
    header('Vary: Origin');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}
// =========================================

set_exception_handler(static function (Throwable $exception): void {
    error_log('[API] Unhandled exception: ' . $exception->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo json_encode([
        'status' => 500,
        'message' => 'Terjadi kesalahan pada server.',
        'data' => null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
});

require_once __DIR__ . '/api/routes.php';
