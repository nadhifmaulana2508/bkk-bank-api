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

    public static function handleRegisteredApplication(callable $next): void
    {
        if (AppTokenAuthenticator::current() === null) {
            AppTokenAuthenticator::application(true);
        }

        $next();
    }
}
