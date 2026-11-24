<?php
declare(strict_types=1);

require_once __DIR__ . '/controllers/AoController.php';
require_once __DIR__ . '/controllers/EmployeeController.php';
require_once __DIR__ . '/controllers/DirectoryController.php';
require_once dirname(__DIR__, 2) . '/global/auth/RequireUserMiddleware.php';
require_once dirname(__DIR__, 2) . '/global/auth/RequireAppMiddleware.php';

return static function (ApiRouter $router): void {
    $router->get(
        '/api/simpeg/ao/list',
        [AoController::class, 'getAoByOffice'],
        [[RequireUserMiddleware::class, 'handle']]
    );
    $router->get('/api/simpeg/pegawai/search', [EmployeeController::class, 'search']);
    $router->post(
        '/api/simpeg/pegawai/status',
        [EmployeeController::class, 'updateLoginStatus'],
        [[RequireAppMiddleware::class, 'handleRegisteredApplication']]
    );
    $router->get(
        '/api/simpeg/jabatan',
        [DirectoryController::class, 'positions'],
        [[RequireAppMiddleware::class, 'handleRegisteredApplication']]
    );
    $router->get(
        '/api/simpeg/pegawai',
        [DirectoryController::class, 'employees'],
        [[RequireAppMiddleware::class, 'handleRegisteredApplication']]
    );
    $router->get(
        '/api/simpeg/penempatan',
        [DirectoryController::class, 'assignments'],
        [[RequireAppMiddleware::class, 'handleRegisteredApplication']]
    );
    $router->get(
        '/api/simpeg/unit-kerja',
        [DirectoryController::class, 'units'],
        [[RequireAppMiddleware::class, 'handleRegisteredApplication']]
    );
};
