<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/legacy/AuthController.php';
require_once __DIR__ . '/legacy/AoController.php';
require_once __DIR__ . '/modules/simpeg/EmployeeController.php';

$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

$uri = explode('?', $uri)[0];
$basePath = dirname($_SERVER['SCRIPT_NAME']);

if ($basePath !== '/' && strpos($uri, $basePath) === 0) {
    $uri = substr($uri, strlen($basePath));
}
$uri = '/' . trim($uri, '/');

if ($uri === '/api/auth/login' && $method === 'POST') {
    AuthController::login();
} elseif ($uri === '/api/auth/whoami' && ($method === 'GET' || $method === 'POST')) {
    AuthController::whoami();
} elseif ($uri === '/api/auth/change-password' && $method === 'POST') {
    AuthController::changePassword();
} elseif ($uri === '/api/auth/update-profile' && $method === 'POST') {
    AuthController::updateProfile();
} elseif ($uri === '/api/auth/forgot-password' && $method === 'POST') {
    AuthController::forgotPassword();
} elseif ($uri === '/api/auth/verify-otp' && $method === 'POST') {
    AuthController::verifyOtp();
} elseif ($uri === '/api/auth/reset-password' && $method === 'POST') {
    AuthController::resetPassword();
} elseif ($uri === '/api/ao/list' && $method === 'GET') {
    AoController::getAoByCabang();
} elseif ($uri === '/api/simpeg/pegawai/search' && $method === 'GET') {
    EmployeeController::search();
} elseif ($uri === '/api/ttd/status' && $method === 'POST') {
    require_once __DIR__ . '/global/ttd/TtdApiController.php';
    TtdApiController::status();
} elseif ($uri === '/api/ttd/sign' && $method === 'POST') {
    require_once __DIR__ . '/global/ttd/TtdApiController.php';
    TtdApiController::sign();
} else {
    sendResponse(404, 'Route tidak ditemukan', ['uri' => $uri, 'method' => $method]);
}
