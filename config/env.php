<?php
declare(strict_types=1);

/**
 * Loader environment sederhana untuk API.
 *
 * Prioritas konfigurasi:
 * 1. environment variable dari web server;
 * 2. file yang ditunjuk APP_ENV_FILE;
 * 3. .env lokal saat development.
 */
function app_env_value(string $key, ?string $fallback = null): ?string
{
    $systemValue = getenv($key);
    if ($systemValue !== false && $systemValue !== '') {
        return (string) $systemValue;
    }

    static $loaded = false;
    static $values = [];

    if (!$loaded) {
        $loaded = true;
        $configuredFile = getenv('APP_ENV_FILE');
        $envFile = ($configuredFile !== false && $configuredFile !== '')
            ? $configuredFile
            : dirname(__DIR__) . '/.env';

        if (is_file($envFile)) {
            $parsed = @parse_ini_file($envFile, false, INI_SCANNER_RAW);
            if (is_array($parsed)) {
                $values = $parsed;
            } else {
                error_log('[API] File environment tidak valid.');
            }
        }
    }

    $fileValue = $values[$key] ?? null;
    return ($fileValue !== null && $fileValue !== '') ? (string) $fileValue : $fallback;
}
