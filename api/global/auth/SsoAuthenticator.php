<?php
declare(strict_types=1);

require_once __DIR__ . '/SsoConfig.php';
require_once dirname(__DIR__, 3) . '/helpers/response.php';
require_once dirname(__DIR__, 3) . '/helpers/jwt.php';

final class SsoAuthenticator
{
    public static function bearerToken(): ?string
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $authorization = $headers['Authorization'] ?? $headers['authorization'] ?? null;

        if (!is_string($authorization)) {
            return null;
        }

        if (preg_match('/Bearer\s(\S+)/', $authorization, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public static function user(): array
    {
        $token = self::bearerToken();
        if (!$token) {
            sendResponse(401, 'Unauthorized');
        }

        $decoded = jwt_decode($token, sso_secret_key());
        if (!$decoded) {
            sendResponse(401, 'Invalid token');
        }

        return $decoded;
    }
}
