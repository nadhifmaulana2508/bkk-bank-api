<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/global/auth/SsoAuthenticator.php';

if (!function_exists('getBearerToken')) {
    function getBearerToken(): ?string
    {
        return SsoAuthenticator::bearerToken();
    }
}

if (!function_exists('auth')) {
    function auth(): array
    {
        return SsoAuthenticator::user();
    }
}
