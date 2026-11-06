<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

final class BankApiDatabase
{
    private static ?PDO $bank = null;
    private static ?PDO $branch = null;

    public static function bank(): PDO
    {
        if (!self::$bank instanceof PDO) {
            self::$bank = self::connect('BANK_API_DB', 'db_bank_api');
        }

        return self::$bank;
    }

    public static function branch(): PDO
    {
        if (!self::$branch instanceof PDO) {
            self::$branch = self::connect('BANK_API_BRANCH_DB', 'dpk');
        }

        return self::$branch;
    }

    private static function connect(string $prefix, string $defaultDatabase): PDO
    {
        $host = (string) app_env_value($prefix . '_HOST', app_env_value('DB_HOST', 'localhost'));
        $port = (int) app_env_value($prefix . '_PORT', app_env_value('DB_PORT', app_env_value('DB_ROOT', '3306')));
        $database = (string) app_env_value($prefix . '_NAME', $defaultDatabase);
        $user = (string) app_env_value($prefix . '_USER', app_env_value('DB_USER', 'root'));
        $password = (string) app_env_value($prefix . '_PASS', app_env_value('DB_PASS', ''));

        if (!preg_match('/^[A-Za-z0-9_]+$/', $database)) {
            throw new RuntimeException('Nama database Bank API tidak valid.');
        }

        try {
            return new PDO(
                "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
                $user,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_TIMEOUT => 5,
                ]
            );
        } catch (PDOException $exception) {
            error_log('[API] Bank API database connection failed: ' . $exception->getMessage());
            throw new RuntimeException('Database Bank API sedang tidak tersedia.', 0, $exception);
        }
    }
}
