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
}
