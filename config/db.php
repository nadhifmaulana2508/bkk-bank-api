<?php
declare(strict_types=1);

// response helper (tetap relatif dari api/config/)
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/env.php';

/** Ambil env: prioritaskan environment variable sistem, lalu file environment. */
$ENV = static function (string $key, $default = null) {
  return app_env_value($key, $default);
};

// ----- Konfigurasi DB (PORT kompatibel dengan DB_ROOT lama) -----
$DB_HOST = (string)$ENV('DB_HOST', 'localhost');
$DB_USER = (string)$ENV('DB_USER', 'root');
$DB_PASS = (string)$ENV('DB_PASS', '');
$DB_NAME = (string)$ENV('DB_NAME', '');
$DB_PORT = (int)$ENV('DB_PORT', (string)$ENV('DB_ROOT', '3306')); // fallback DB_ROOT

if ($DB_NAME === '') {
  error_log('[API] DB_NAME belum dikonfigurasi.');
  sendResponse(500, 'Konfigurasi database belum lengkap.');
}

try {
  $dsn = "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4";
  $options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
  ];
  $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
  // sukses: $pdo siap dipakai
} catch (PDOException $e) {
  error_log('[API] Database connection failed: ' . $e->getMessage());
  sendResponse(500, 'Layanan database sedang tidak tersedia.');
  exit;
}
