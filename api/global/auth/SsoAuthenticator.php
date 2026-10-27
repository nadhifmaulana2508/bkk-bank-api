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
        $headers = is_array($headers) ? array_change_key_case($headers, CASE_LOWER) : [];
        $authorization = $headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? null);

        if (!is_string($authorization)) {
            return null;
        }

        if (preg_match('/^Bearer\s+(\S+)$/i', trim($authorization), $matches)) {
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
        if (
            !is_array($decoded)
            || empty($decoded['id_peg'])
            || empty($decoded['app'])
            || isset($decoded['purpose'])
            || !isset($decoded['exp'])
            || !is_numeric($decoded['exp'])
            || (int) $decoded['exp'] <= time()
        ) {
            sendResponse(401, 'Invalid token');
        }

        return $decoded;
    }
}
