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
            $lines = @file($envFile, FILE_IGNORE_NEW_LINES);
            if (is_array($lines)) {
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || str_starts_with($line, '#')) {
                        continue;
                    }

                    if (str_starts_with($line, 'export ')) {
                        $line = trim(substr($line, 7));
                    }

                    $separator = strpos($line, '=');
                    if ($separator === false) {
                        continue;
                    }

                    $parsedKey = trim(substr($line, 0, $separator));
                    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $parsedKey)) {
                        continue;
                    }

                    $value = trim(substr($line, $separator + 1));
                    $length = strlen($value);
                    if ($length >= 2) {
                        $first = $value[0];
                        $last = $value[$length - 1];
                        if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                            $value = substr($value, 1, -1);
                        }
                    }

                    $values[$parsedKey] = $value;
                }
            } else {
                error_log('[API] File environment tidak dapat dibaca.');
            }
        }
    }

    $fileValue = $values[$key] ?? null;
    return ($fileValue !== null && $fileValue !== '') ? (string) $fileValue : $fallback;
}
