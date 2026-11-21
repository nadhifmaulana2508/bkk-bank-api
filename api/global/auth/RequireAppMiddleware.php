<?php
declare(strict_types=1);

require_once __DIR__ . '/AppTokenAuthenticator.php';

final class RequireAppMiddleware
{
    public static function handle(callable $next): void
    {
        AppTokenAuthenticator::application();
        $next();
    }

    public static function handleStatusManager(callable $next): void
    {
        $application = AppTokenAuthenticator::current() ?? AppTokenAuthenticator::application(true);
        if (!in_array($application['app_code'] ?? '', ['simpeg', 'sso'], true)) {
            sendResponse(403, 'Endpoint perubahan status hanya dapat dipanggil oleh aplikasi SIMPEG atau SSO Control Plane.');
        }

        $next();
    }
}
