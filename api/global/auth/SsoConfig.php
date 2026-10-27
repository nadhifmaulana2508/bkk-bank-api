<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/env.php';

function sso_config_value(string $key, ?string $fallback = null): ?string
{
    return app_env_value($key, $fallback);
}

function sso_secret_key(): string
{
    $secret = sso_config_value('SSO_JWT_SECRET');
    if ($secret !== null && strlen($secret) >= 32) {
        return $secret;
    }

    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
    $isLocal = in_array(explode(':', $host)[0], ['localhost', '127.0.0.1', '::1'], true)
        || sso_config_value('APP_ENV') === 'local';

    if ($isLocal) {
        // Kompatibilitas development saja. Wajib diganti di server.
        return 'SSO_BKK_SECRET_2026';
    }

    throw new RuntimeException('SSO_JWT_SECRET belum dikonfigurasi dengan aman.');
}
