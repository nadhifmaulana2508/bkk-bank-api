<?php
declare(strict_types=1);

require_once __DIR__ . '/controllers/AoController.php';
require_once __DIR__ . '/controllers/EmployeeController.php';
require_once dirname(__DIR__, 2) . '/global/auth/RequireUserMiddleware.php';

return static function (ApiRouter $router): void {
    $router->get(
        '/api/simpeg/ao/list',
        [AoController::class, 'getAoByOffice'],
        [[RequireUserMiddleware::class, 'handle']]
    );
    $router->get('/api/simpeg/pegawai/search', [EmployeeController::class, 'search']);
};
