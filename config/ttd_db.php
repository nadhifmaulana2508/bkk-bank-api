<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/env.php';

/**
 * Koneksi database TTD IN BKK untuk endpoint REST.
 *
 * Konfigurasi hanya dibaca dari environment server/root .env.
 * Adapter ini tidak bergantung pada aplikasi UI TTD lokal.
 */
$ttdEnvValue = static function (string $key, $default = null) {
    $configuredValue = app_env_value('TTD_' . $key);
    if ($configuredValue !== null) {
        return $configuredValue;
    }
    return $default;
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
