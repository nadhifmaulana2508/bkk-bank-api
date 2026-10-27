<?php
declare(strict_types=1);

require_once __DIR__ . '/SsoAuthenticator.php';

final class RequireUserMiddleware
{
    public static function handle(callable $next): void
    {
        SsoAuthenticator::user();
        $next();
    }
}
