<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/Router.php';

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$requestPath = is_string($requestPath) ? $requestPath : '/';
$basePath = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');

if ($basePath !== '' && $basePath !== '/' && ($requestPath === $basePath || strpos($requestPath, $basePath . '/') === 0)) {
    $requestPath = substr($requestPath, strlen($basePath));
}

$uri = '/' . trim($requestPath, '/');

// Setiap domain mendaftarkan endpoint-nya sendiri. File ini hanya menjadi
// front controller dan tidak menyimpan detail endpoint bisnis.
$router = new ApiRouter();
require __DIR__ . '/global/routes.php';
require __DIR__ . '/modules/simpeg/routes.php';

if (!$router->dispatch($method, $uri)) {
    sendResponse(404, 'Route tidak ditemukan', ['uri' => $uri, 'method' => $method]);
}
