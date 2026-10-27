<?php
declare(strict_types=1);

final class ApiRequest
{
    public static function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public static function path(): string
    {
        $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $requestPath = is_string($requestPath) ? $requestPath : '/';
        $basePath = rtrim(
            str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))),
            '/'
        );

        if ($basePath !== '' && $basePath !== '/' && ($requestPath === $basePath || strpos($requestPath, $basePath . '/') === 0)) {
            $requestPath = substr($requestPath, strlen($basePath));
        }

        return '/' . trim($requestPath, '/');
    }
}
