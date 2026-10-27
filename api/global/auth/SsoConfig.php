<?php
declare(strict_types=1);

function sso_config_value(string $key, ?string $fallback = null): ?string
{
    $environmentValue = getenv($key);
    if ($environmentValue !== false && $environmentValue !== '') {
        return (string)$environmentValue;
    }

    static $env = null;
    if ($env === null) {
        $projectRoot = dirname(__DIR__, 3);
        $envFile = $projectRoot . '/.env';
        $loaded = is_file($envFile)
            ? parse_ini_file($envFile, false, INI_SCANNER_RAW)
            : [];
        $env = is_array($loaded) ? $loaded : [];
    }

    $fileValue = $env[$key] ?? null;
    if ($fileValue !== null && $fileValue !== '') {
        return (string)$fileValue;
    }

    return $fallback;
}

function sso_secret_key(): string
{
    return (string)sso_config_value('SSO_JWT_SECRET', 'SSO_BKK_SECRET_2026');
}
