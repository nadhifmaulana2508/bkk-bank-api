<?php
declare(strict_types=1);

require_once __DIR__ . '/../legacy/AuthController.php';

return static function (ApiRouter $router): void {
    $router->post('/api/auth/login', [AuthController::class, 'login']);
    $router->post(
        '/api/auth/login-dummy',
        [AuthController::class, 'loginDummy'],
        [[RequireAppMiddleware::class, 'handleRegisteredApplication']]
    );
    $router->add(
        ['GET', 'POST'],
        '/api/auth/auth-dummy',
        [AuthController::class, 'authDummy'],
        [[RequireAppMiddleware::class, 'handleRegisteredApplication']]
    );
    $router->post(
        '/api/auth/admin-reset-password',
        [AuthController::class, 'adminResetPassword'],
        [[RequireAppMiddleware::class, 'handleRegisteredApplication']]
    );
    $router->add(['GET', 'POST'], '/api/auth/whoami', [AuthController::class, 'whoami']);
    $router->post('/api/auth/change-password', [AuthController::class, 'changePassword']);
    $router->post('/api/auth/update-profile', [AuthController::class, 'updateProfile']);
    $router->post('/api/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    $router->post('/api/auth/resend-otp', [AuthController::class, 'resendOtp']);
    $router->post('/api/auth/verify-otp', [AuthController::class, 'verifyOtp']);
    $router->post('/api/auth/reset-password', [AuthController::class, 'resetPassword']);
};
