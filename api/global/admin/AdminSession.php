<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/env.php';
require_once dirname(__DIR__, 3) . '/config/db.php';
require_once dirname(__DIR__, 3) . '/helpers/response.php';
require_once __DIR__ . '/AdminRbac.php';

final class AdminSession
{
    private const SESSION_NAME = 'BKKAPIADMINSESSID';
    private const SESSION_TIMEOUT = 43200;
    private const LOGIN_WINDOW = 900;
    private const LOGIN_LIMIT = 5;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string) self::SESSION_TIMEOUT);
        $sessionPaths = [
            sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bank-api-admin-sessions',
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'admin-sessions',
        ];
        $sessionPathSet = false;
        foreach ($sessionPaths as $sessionPath) {
            if (!is_dir($sessionPath) && !@mkdir($sessionPath, 0700, true) && !is_dir($sessionPath)) {
                continue;
            }
            if (is_dir($sessionPath) && is_writable($sessionPath)) {
                session_save_path($sessionPath);
                $sessionPathSet = true;
                break;
            }
        }
        if (!$sessionPathSet) {
            error_log('[API] Admin session storage is not writable.');
        }
        session_name(self::SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => self::SESSION_TIMEOUT,
            'httponly' => true,
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'samesite' => 'Strict',
            'path' => '/',
        ]);
        session_start();
    }

    public static function isAuthenticated(): bool
    {
        self::start();

        if (($_SESSION['admin_authenticated'] ?? false) !== true || (int) ($_SESSION['admin_user_id'] ?? 0) < 1) {
            return false;
        }

        $lastActivity = (int) ($_SESSION['admin_last_activity'] ?? 0);
        if ($lastActivity <= 0 || $lastActivity + self::SESSION_TIMEOUT < time()) {
            self::logout();
            return false;
        }

        $_SESSION['admin_last_activity'] = time();
        self::refreshCookie();
        return true;
    }

    public static function login(string $username, string $password): bool
    {
        self::start();
        $username = strtolower(trim($username));

        if (!self::canAttemptLogin($username)) {
            return false;
        }

        if (!preg_match('/^[a-z0-9][a-z0-9._-]{2,79}$/', $username) || $password === '') {
            self::recordFailedLogin($username);
            return false;
        }

        $statement = self::pdo()->prepare(
            'SELECT id, username, full_name, email, password_hash, role_code, permissions_json, is_active
             FROM user_sso
             WHERE username = :username
             LIMIT 1'
        );
        $statement->execute(['username' => $username]);
        $user = $statement->fetch();
        $valid = is_array($user)
            && (int) ($user['is_active'] ?? 0) === 1
            && password_verify($password, (string) ($user['password_hash'] ?? ''));

        if (!$valid) {
            usleep(150000);
            self::recordFailedLogin($username);
            return false;
        }

        $permissions = AdminRbac::normalize($user['permissions_json'] ?? []);
        if ($permissions === []) {
            self::recordFailedLogin($username);
            return false;
        }

        self::pdo()->prepare('UPDATE user_sso SET last_login_at = NOW() WHERE id = :id')
            ->execute(['id' => (int) $user['id']]);
        self::clearLoginAttempts($username);

        session_regenerate_id(true);
        $_SESSION['admin_authenticated'] = true;
        $_SESSION['admin_user_id'] = (int) $user['id'];
        $_SESSION['admin_username'] = (string) $user['username'];
        $_SESSION['admin_full_name'] = (string) $user['full_name'];
        $_SESSION['admin_email'] = (string) ($user['email'] ?? '');
        $_SESSION['admin_role_code'] = (string) $user['role_code'];
        $_SESSION['admin_permissions'] = $permissions;
        $_SESSION['admin_last_activity'] = time();
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));

        return true;
    }

    public static function canAttemptLogin(string $username = ''): bool
    {
        $username = self::loginAttemptUsername($username);
        $statement = self::pdo()->prepare(
            'SELECT blocked_until FROM sso_login_attempts
             WHERE ip_address = :ip AND username = :username
             LIMIT 1'
        );
        $statement->execute(['ip' => self::clientIp(), 'username' => $username]);
        $blockedUntil = $statement->fetchColumn();

        return $blockedUntil === false
            || $blockedUntil === null
            || strtotime((string) $blockedUntil) <= time();
    }

    public static function recordFailedLogin(string $username = ''): void
    {
        $pdo = self::pdo();
        $ip = self::clientIp();
        $username = self::loginAttemptUsername($username);
        $statement = $pdo->prepare(
            'SELECT failed_attempts, window_started_at
             FROM sso_login_attempts
             WHERE ip_address = :ip AND username = :username
             LIMIT 1'
        );
        $statement->execute(['ip' => $ip, 'username' => $username]);
        $row = $statement->fetch();
        $expired = !is_array($row) || strtotime((string) ($row['window_started_at'] ?? '')) <= time() - self::LOGIN_WINDOW;

        if ($expired) {
            $query = $pdo->prepare(
                'INSERT INTO sso_login_attempts
                    (ip_address, username, failed_attempts, window_started_at, blocked_until)
                 VALUES (:ip, :username, 1, NOW(), NULL)
                 ON DUPLICATE KEY UPDATE
                    failed_attempts = 1,
                    window_started_at = NOW(), blocked_until = NULL'
            );
            $query->execute(['ip' => $ip, 'username' => $username]);
            return;
        }

        $attempts = min(self::LOGIN_LIMIT, (int) $row['failed_attempts'] + 1);
        $query = $pdo->prepare(
            'UPDATE sso_login_attempts
             SET username = :username, failed_attempts = :attempts,
                 blocked_until = :blocked_until, updated_at = NOW()
             WHERE ip_address = :ip AND username = :username'
        );
        $query->execute([
            'username' => $username,
            'attempts' => $attempts,
            'blocked_until' => $attempts >= self::LOGIN_LIMIT ? date('Y-m-d H:i:s', time() + self::LOGIN_WINDOW) : null,
            'ip' => $ip,
        ]);
    }

    public static function clearLoginAttempts(string $username): void
    {
        self::pdo()->prepare(
            'DELETE FROM sso_login_attempts WHERE ip_address = :ip AND username = :username'
        )->execute([
            'ip' => self::clientIp(),
            'username' => self::loginAttemptUsername($username),
        ]);
    }

    /** @return array<string, mixed> */
    public static function user(): array
    {
        self::start();
        return [
            'id' => (int) ($_SESSION['admin_user_id'] ?? 0),
            'username' => (string) ($_SESSION['admin_username'] ?? ''),
            'full_name' => (string) ($_SESSION['admin_full_name'] ?? ''),
            'email' => (string) ($_SESSION['admin_email'] ?? ''),
            'role_code' => (string) ($_SESSION['admin_role_code'] ?? ''),
            'permissions' => AdminRbac::normalize($_SESSION['admin_permissions'] ?? []),
        ];
    }

    /** @return array<int, string> */
    public static function permissions(): array
    {
        return self::user()['permissions'];
    }

    public static function hasPermission(string $permission): bool
    {
        return self::isAuthenticated() && AdminRbac::has(self::permissions(), $permission);
    }

    public static function requirePermission(string $permission): void
    {
        self::requireAuthenticated();
        if (!self::hasPermission($permission)) {
            sendResponse(403, 'Anda tidak memiliki akses ke menu ini.');
        }
    }

    public static function csrfToken(): string
    {
        self::start();
        if (!isset($_SESSION['admin_csrf']) || !is_string($_SESSION['admin_csrf'])) {
            $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['admin_csrf'];
    }

    public static function requireAuthenticated(): void
    {
        if (!self::isAuthenticated()) {
            sendResponse(401, 'Sesi admin tidak valid atau sudah berakhir.');
        }
    }

    public static function requireCsrf(): void
    {
        self::requireAuthenticated();
        $provided = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $expected = self::csrfToken();

        if ($provided === '' || !hash_equals($expected, $provided)) {
            sendResponse(419, 'Token CSRF tidak valid. Muat ulang halaman admin.');
        }
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, (string) $params['path'], (string) $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
        }

        session_destroy();
    }

    private static function pdo(): PDO
    {
        global $pdo;
        if (!$pdo instanceof PDO) {
            throw new RuntimeException('Koneksi database tidak tersedia.');
        }
        return $pdo;
    }

    private static function refreshCookie(): void
    {
        if (!ini_get('session.use_cookies') || headers_sent()) {
            return;
        }

        $params = session_get_cookie_params();
        setcookie(session_name(), session_id(), [
            'expires' => time() + self::SESSION_TIMEOUT,
            'path' => (string) ($params['path'] ?: '/'),
            'domain' => (string) $params['domain'],
            'secure' => (bool) $params['secure'],
            'httponly' => (bool) $params['httponly'],
            'samesite' => (string) ($params['samesite'] ?? 'Strict'),
        ]);
    }

    private static function clientIp(): string
    {
        $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        return filter_var($ip, FILTER_VALIDATE_IP) ? substr($ip, 0, 45) : 'unknown';
    }

    private static function loginAttemptUsername(string $username): string
    {
        $username = strtolower(trim($username));
        return preg_match('/^[a-z0-9][a-z0-9._-]{2,79}$/', $username) === 1
            ? $username
            : 'invalid';
    }
}

