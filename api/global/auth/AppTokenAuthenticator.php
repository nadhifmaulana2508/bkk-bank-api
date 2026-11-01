<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/env.php';
require_once dirname(__DIR__, 3) . '/helpers/response.php';
require_once dirname(__DIR__) . '/audit/AppRequestLogger.php';

final class AppTokenAuthenticator
{
    /** @var array<string, mixed>|null */
    private static ?array $currentApplication = null;

    /**
     * App-token enforcement is opt-in during rollout so existing clients do
     * not break before their application token has been registered.
     */
    public static function isRequired(): bool
    {
        $value = strtolower(trim((string) app_env_value('APP_TOKEN_REQUIRED', 'false')));

        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Validate the calling application and return its public context.
     *
     * The raw token is never stored. Only a SHA-256 digest is compared with
     * the digest stored in api_applications.
     *
     * @return array<string, mixed>|null
     */
    public static function application(): ?array
    {
        if (!self::isRequired()) {
            return null;
        }

        $appCode = self::header('App') ?? self::header('X-App-Name');
        $rawToken = self::header('X-App-Token');

        if ($appCode === null || $rawToken === null) {
            header('WWW-Authenticate: AppToken');
            sendResponse(401, 'App dan X-App-Token wajib diisi.');
        }

        $appCode = strtolower(trim($appCode));
        $rawToken = trim($rawToken);

        if (
            !preg_match('/^[a-z0-9][a-z0-9._-]{0,49}$/', $appCode)
            || strlen($rawToken) < 8
            || strlen($rawToken) > 255
        ) {
            sendResponse(401, 'Kredensial aplikasi tidak valid.');
        }

        try {
            global $pdo;
            require_once dirname(__DIR__, 3) . '/config/db.php';

            if (!$pdo instanceof PDO) {
                throw new RuntimeException('Koneksi database tidak tersedia.');
            }

            $statement = $pdo->prepare(
                'SELECT id, app_code, app_name, token_hash, is_active, expires_at
                 FROM api_applications
                 WHERE app_code = :app_code
                 LIMIT 1'
            );
            $statement->execute(['app_code' => $appCode]);
            $application = $statement->fetch();

            $expectedHash = is_array($application)
                ? (string) ($application['token_hash'] ?? '')
                : '';
            $providedHash = hash('sha256', $rawToken);

            if (
                !is_array($application)
                || (int) ($application['is_active'] ?? 0) !== 1
                || $expectedHash === ''
                || !hash_equals($expectedHash, $providedHash)
                || self::isExpired($application['expires_at'] ?? null)
            ) {
                sendResponse(403, 'Token aplikasi tidak valid atau tidak aktif.');
            }

            $update = $pdo->prepare(
                'UPDATE api_applications SET last_used_at = NOW() WHERE id = :id'
            );
            $update->execute(['id' => (int) $application['id']]);

            AppRequestLogger::record($pdo, (int) $application['id']);

            self::$currentApplication = [
                'id' => (int) $application['id'],
                'app_code' => (string) $application['app_code'],
                'app_name' => (string) $application['app_name'],
            ];

            return self::$currentApplication;
        } catch (PDOException $exception) {
            error_log('[API] App-token validation failed: ' . $exception->getMessage());
            sendResponse(503, 'Validasi aplikasi sedang tidak tersedia.');
        } catch (Throwable $exception) {
            error_log('[API] App-token validation failed: ' . $exception->getMessage());
            sendResponse(503, 'Validasi aplikasi sedang tidak tersedia.');
        }

        return null;
    }

    /**
     * Return the application authenticated by the current request.
     *
     * @return array<string, mixed>|null
     */
    public static function current(): ?array
    {
        return self::$currentApplication;
    }

    private static function isExpired($expiresAt): bool
    {
        if ($expiresAt === null || $expiresAt === '') {
            return false;
        }

        $timestamp = strtotime((string) $expiresAt);

        return $timestamp === false || $timestamp <= time();
    }

    private static function header(string $name): ?string
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $headers = is_array($headers) ? array_change_key_case($headers, CASE_LOWER) : [];
        $key = strtolower($name);
        $value = $headers[$key] ?? ($_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $name))] ?? null);

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
