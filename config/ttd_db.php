<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/response.php';

/**
 * Koneksi database TTD IN BKK untuk endpoint REST.
 *
 * Prioritas konfigurasi:
 * 1. TTD_DB_* dari environment server/root .env;
 * 2. DB_* dari ttd_in_bkk/.env;
 * 3. default lokal.
 */
$ttdEnvFile = dirname(__DIR__) . '/ttd_in_bkk/.env';
$ttdEnv = is_file($ttdEnvFile)
    ? (parse_ini_file($ttdEnvFile, false, INI_SCANNER_RAW) ?: [])
    : [];
$rootEnvFile = dirname(__DIR__) . '/.env';
$rootEnv = is_file($rootEnvFile)
    ? (parse_ini_file($rootEnvFile, false, INI_SCANNER_RAW) ?: [])
    : [];

$ttdEnvValue = static function (string $key, $default = null) use ($ttdEnv, $rootEnv) {
    $serverValue = getenv('TTD_' . $key);
    if ($serverValue !== false && $serverValue !== '') {
        return $serverValue;
    }
    if (isset($rootEnv['TTD_' . $key]) && $rootEnv['TTD_' . $key] !== '') {
        return $rootEnv['TTD_' . $key];
    }
    return $ttdEnv[$key] ?? $default;
};

$ttdDbHost = (string)$ttdEnvValue('DB_HOST', '127.0.0.1');
$ttdDbPort = (int)$ttdEnvValue('DB_PORT', 3306);
$ttdDbName = (string)$ttdEnvValue('DB_NAME', 'db_ttd_in_bkk');
$ttdDbUser = (string)$ttdEnvValue('DB_USER', 'root');
$ttdDbPass = (string)$ttdEnvValue('DB_PASS', '');

try {
    $ttdDsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $ttdDbHost,
        $ttdDbPort,
        $ttdDbName
    );
    $ttdPdo = new PDO($ttdDsn, $ttdDbUser, $ttdDbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log('[SSO API] TTD database connection error: ' . $e->getMessage());
    sendResponse(503, 'Database TTD sedang tidak tersedia.', [
        'code' => 'TTD_DATABASE_UNAVAILABLE',
    ]);
}
