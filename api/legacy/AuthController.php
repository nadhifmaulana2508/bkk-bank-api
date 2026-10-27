<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/global/auth/SsoService.php';

/**
 * Legacy adapter for the existing /api/auth/* routes.
 * The public contract stays unchanged while the implementation lives in the
 * global SSO service.
 */
final class AuthController
{
    public static function login(): void
    {
        SsoService::login();
    }

    public static function whoami(): void
    {
        SsoService::whoami();
    }

    public static function changePassword(): void
    {
        SsoService::changePassword();
    }

    public static function updateProfile(): void
    {
        SsoService::updateProfile();
    }

    public static function forgotPassword(): void
    {
        SsoService::forgotPassword();
    }

    public static function resendOtp(): void
    {
        SsoService::resendOtp();
    }

    public static function verifyOtp(): void
    {
        SsoService::verifyOtp();
    }

    public static function resetPassword(): void
    {
        SsoService::resetPassword();
    }
}
