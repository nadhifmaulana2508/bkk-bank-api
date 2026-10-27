<?php
declare(strict_types=1);

// =========================================
// 🔥 PENGAMAN CORS (WAJIB DI PALING ATAS)
// =========================================
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Employee-Id');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
// =========================================

require_once __DIR__ . '/helpers/response.php';
require_once __DIR__ . '/AuthController.php';
require_once __DIR__ . '/AoController.php';

$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

$uri = explode('?', $uri)[0];
$basePath = dirname($_SERVER['SCRIPT_NAME']); 

if ($basePath !== '/' && strpos($uri, $basePath) === 0) {
    $uri = substr($uri, strlen($basePath));
}
$uri = '/' . trim($uri, '/');

// =========================
// ROUTING API SSO
// =========================
if ($uri === '/api/auth/login' && $method === 'POST') {
    AuthController::login();
}
elseif ($uri === '/api/auth/whoami' && ($method === 'GET' || $method === 'POST')) {
    AuthController::whoami();
}
elseif ($uri === '/api/auth/change-password' && $method === 'POST') {
    AuthController::changePassword();
}
elseif ($uri === '/api/auth/update-profile' && $method === 'POST') {
    AuthController::updateProfile();
}
elseif ($uri === '/api/auth/forgot-password' && $method === 'POST') {
    AuthController::forgotPassword();
}
elseif ($uri === '/api/auth/verify-otp' && $method === 'POST') {
    AuthController::verifyOtp();
}
elseif ($uri === '/api/auth/reset-password' && $method === 'POST') {
    AuthController::resetPassword();
}
// get data ao
elseif ($uri === '/api/ao/list' && $method === 'GET') {
    AoController::getAoByCabang();
}
// REST API TTD IN BKK tanpa Bearer token.
// Endpoint tetap memerlukan pass_endpoint pada JSON body.
elseif ($uri === '/api/ttd/status' && $method === 'POST') {
    require_once __DIR__ . '/TtdApiController.php';
    TtdApiController::status();
}
elseif ($uri === '/api/ttd/sign' && $method === 'POST') {
    require_once __DIR__ . '/TtdApiController.php';
    TtdApiController::sign();
}
else {
    sendResponse(404, "Route tidak ditemukan", ["uri" => $uri, "method" => $method]);
}
