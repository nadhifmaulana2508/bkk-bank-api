<?php
declare(strict_types=1);

/** Return the current URL path without its query string. */
function web_request_path(): string
{
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

    return is_string($path) && $path !== '' ? $path : '/';
}

/** Return the directory URL for the current PHP script. */
function web_script_base(): string
{
    return rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
}

/** Return the clean route below a script directory. */
function web_route_after(string $base): string
{
    $path = web_request_path();
    $base = rtrim(str_replace('\\', '/', $base), '/');

    if ($base !== '' && ($path === $base || str_starts_with($path, $base . '/'))) {
        return trim(substr($path, strlen($base)), '/');
    }

    return '';
}
