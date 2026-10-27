<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/bootstrap.php';

$rows = $pdo->query('SELECT id_peg, signature_path FROM signature_profiles')->fetchAll();
$updated = 0;
foreach ($rows as $row) {
    $path = dirname(__DIR__) . '/' . ltrim((string)$row['signature_path'], '/');
    if (normalize_signature_file($path)) {
        $updated++;
        echo 'NORMALIZED ' . $row['id_peg'] . PHP_EOL;
    }
}

echo 'UPDATED=' . $updated . PHP_EOL;
