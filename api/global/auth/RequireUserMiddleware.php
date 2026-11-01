<?php
declare(strict_types=1);

require_once __DIR__ . '/SsoAuthenticator.php';
require_once __DIR__ . '/AppTokenAuthenticator.php';

final class RequireUserMiddleware
{
    public static function handle(callable $next): void
    {
        $user = SsoAuthenticator::user();
        $application = AppTokenAuthenticator::current();

        if (
            AppTokenAuthenticator::isRequired()
            && is_array($application)
            && strtolower((string) ($user['app'] ?? '')) !== strtolower((string) ($application['app_code'] ?? ''))
        ) {
            sendResponse(403, 'Token user tidak sesuai dengan aplikasi.');
        }

        $next();
    }
}
