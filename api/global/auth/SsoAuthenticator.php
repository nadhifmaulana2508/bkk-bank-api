<?php
declare(strict_types=1);

require_once __DIR__ . '/SsoConfig.php';
require_once dirname(__DIR__, 3) . '/config/db.php';
require_once dirname(__DIR__, 3) . '/helpers/response.php';
require_once dirname(__DIR__, 3) . '/helpers/jwt.php';

final class SsoAuthenticator
{
    /** @param mixed $status */
    public static function isLoginBlockedStatus($status): bool
    {
        $status = strtolower(trim((string) ($status ?? '')));

        return in_array($status, ['0', 'non job'], true);
    }

    public static function bearerToken(): ?string
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $headers = is_array($headers) ? array_change_key_case($headers, CASE_LOWER) : [];
        $authorization = $headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? null);

        if (!is_string($authorization)) {
            return null;
        }

        if (preg_match('/^Bearer\s+(\S+)$/i', trim($authorization), $matches)) {
            return strlen($matches[1]) <= 4096 ? $matches[1] : null;
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
            || !is_string($decoded['id_peg'] ?? null)
            || !preg_match('/^[A-Za-z0-9._-]{1,80}$/', $decoded['id_peg'])
            || !is_string($decoded['app'] ?? null)
            || !preg_match('/^[a-z0-9][a-z0-9._-]{0,49}$/', $decoded['app'])
            || isset($decoded['purpose'])
            || !isset($decoded['exp'])
            || !is_numeric($decoded['exp'])
            || (int) $decoded['exp'] <= time()
        ) {
            sendResponse(401, 'Invalid token');
        }

        global $pdo;
        $statement = $pdo->prepare('SELECT status FROM tb_apk WHERE id_peg = :id_peg LIMIT 1');
        $statement->execute(['id_peg' => $decoded['id_peg']]);
        $status = $statement->fetchColumn();
        if ($status === false) {
            sendResponse(401, 'Invalid token');
        }
        if (self::isLoginBlockedStatus($status)) {
            sendResponse(403, 'Akun pegawai berstatus nonaktif atau non job.');
        }

        return $decoded;
    }
}
