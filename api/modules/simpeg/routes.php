<?php
declare(strict_types=1);

require_once __DIR__ . '/controllers/AoController.php';
require_once __DIR__ . '/controllers/EmployeeController.php';

$router->get('/api/simpeg/ao/list', [AoController::class, 'getAoByOffice']);
$router->get('/api/simpeg/pegawai/search', [EmployeeController::class, 'search']);
