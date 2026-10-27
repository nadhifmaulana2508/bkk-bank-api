<?php
declare(strict_types=1);

require_once __DIR__ . '/../legacy/AuthController.php';

$router->post('/api/auth/login', [AuthController::class, 'login']);
$router->add(['GET', 'POST'], '/api/auth/whoami', [AuthController::class, 'whoami']);
$router->post('/api/auth/change-password', [AuthController::class, 'changePassword']);
$router->post('/api/auth/update-profile', [AuthController::class, 'updateProfile']);
$router->post('/api/auth/forgot-password', [AuthController::class, 'forgotPassword']);
$router->post('/api/auth/resend-otp', [AuthController::class, 'resendOtp']);
$router->post('/api/auth/verify-otp', [AuthController::class, 'verifyOtp']);
$router->post('/api/auth/reset-password', [AuthController::class, 'resetPassword']);

// TTD tetap lazy-load karena database TTD masih lokal dan belum menjadi
// dependency setiap request API.
$router->post('/api/ttd/status', static function (): void {
    require_once __DIR__ . '/ttd/TtdApiController.php';
    TtdApiController::status();
});

$router->post('/api/ttd/sign', static function (): void {
    require_once __DIR__ . '/ttd/TtdApiController.php';
    TtdApiController::sign();
});
