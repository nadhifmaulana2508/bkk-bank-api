<?php
declare(strict_types=1);

use Valet\Drivers\BasicValetDriver;

final class LocalValetDriver extends BasicValetDriver
{
    public function isStaticFile(string $sitePath, string $siteName, string $uri): string|false
    {
        if ($this->isBlockedPath($uri)) {
            return false;
        }

        return parent::isStaticFile($sitePath, $siteName, $uri);
    }

    public function frontControllerPath(string $sitePath, string $siteName, string $uri): ?string
    {
        $path = $this->normalizedPath($uri);
        if ($this->isBlockedPath($uri)) {
            return null;
        }

        if (str_ends_with(strtolower($path), '.php') && is_file($sitePath . $path)) {
            $_SERVER['SCRIPT_FILENAME'] = $sitePath . $path;
            $_SERVER['SCRIPT_NAME'] = $path;
            $_SERVER['DOCUMENT_ROOT'] = $sitePath;

            return $sitePath . $path;
        }

        $router = $sitePath . '/local-router.php';
        $_SERVER['SCRIPT_FILENAME'] = $router;
        $_SERVER['SCRIPT_NAME'] = '/local-router.php';
        $_SERVER['DOCUMENT_ROOT'] = $sitePath;

        return $router;
    }

    private function isBlockedPath(string $uri): bool
    {
        $path = ltrim($this->normalizedPath($uri), '/');
        $segments = explode('/', $path);

        if (in_array('..', $segments, true) || in_array('.', $segments, true)) {
            return true;
        }

        return preg_match('~^(?:\.git|config|helpers|middlewares|vendor|database|storage|tools)(?:/|$)~i', $path) === 1
            || preg_match('~^api/(?:Router\.php|routes\.php|Core|global|legacy|modules)(?:/|$)~i', $path) === 1
            || preg_match('~(?:^|/)(?:\.env(?:\..*)?|\.user\.ini|\.htaccess|composer\.(?:json|lock)|[^/]*\.(?:ini|log|sql|bak|dist))$~i', $path) === 1
            || preg_match('~^(?:admin|docs)/.*\.md$~i', $path) === 1
            || in_array(strtolower($path), ['swagger.json', 'check.php'], true);
    }

    private function normalizedPath(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? rawurldecode($path) : '/';

        return '/' . str_replace('\\', '/', ltrim($path, '/'));
    }
}
