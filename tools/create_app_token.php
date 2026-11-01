<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/config/db.php';

$arguments = [];
foreach (array_slice($argv, 1) as $argument) {
    if (strpos($argument, '=') === false) {
        continue;
    }

    [$key, $value] = explode('=', $argument, 2);
    $arguments[ltrim($key, '-')] = trim($value);
}

$appCode = strtolower((string) ($arguments['code'] ?? ''));
$appName = trim((string) ($arguments['name'] ?? ''));

if (
    !preg_match('/^[a-z0-9][a-z0-9._-]{0,49}$/', $appCode)
    || $appName === ''
    || strlen($appName) > 100
) {
    fwrite(STDERR, "Usage: php tools/create_app_token.php --code=simpeg --name=SIMPEG\n");
    exit(1);
}

$rawToken = 'app_' . bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $rawToken);

try {
    /** @var PDO $pdo */
    $statement = $pdo->prepare(
        'INSERT INTO api_applications (app_code, app_name, token_hash)
         VALUES (:app_code, :app_name, :token_hash)'
    );
    $statement->execute([
        'app_code' => $appCode,
        'app_name' => $appName,
        'token_hash' => $tokenHash,
    ]);
} catch (Throwable $exception) {
    fwrite(STDERR, "Gagal mendaftarkan aplikasi: {$exception->getMessage()}\n");
    exit(1);
}

fwrite(STDOUT, "Aplikasi berhasil didaftarkan. Simpan token ini sekarang; token tidak ditampilkan ulang.\n");
fwrite(STDOUT, "App: {$appCode}\n");
fwrite(STDOUT, "X-App-Token: {$rawToken}\n");
