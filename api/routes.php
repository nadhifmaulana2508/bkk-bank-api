<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/Core/Routing/ApiRouter.php';
require_once __DIR__ . '/Core/Http/ApiRequest.php';
require_once __DIR__ . '/global/auth/RequireAppMiddleware.php';

// Composition root: daftar route berada di masing-masing global/module.
$router = new ApiRouter();
$router->addGlobalMiddleware([RequireAppMiddleware::class, 'handle']);

$routeFiles = [
    __DIR__ . '/global/routes.php',
    __DIR__ . '/modules/simpeg/routes.php',
    __DIR__ . '/modules/simontok/routes.php',
];

foreach ($routeFiles as $routeFile) {
    $registerRoutes = require $routeFile;
    if (is_callable($registerRoutes)) {
        $registerRoutes($router);
    }
}

$method = ApiRequest::method();
$path = ApiRequest::path();

if (!$router->dispatch($method, $path)) {
    sendResponse(404, 'Route tidak ditemukan', ['uri' => $path, 'method' => $method]);
}
