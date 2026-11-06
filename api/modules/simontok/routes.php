<?php
declare(strict_types=1);

require_once __DIR__ . '/controllers/CreditAccountController.php';
require_once dirname(__DIR__, 2) . '/global/auth/RequireUserMiddleware.php';

return static function (ApiRouter $router): void {
    $router->get(
        '/api/v1/simontok/credit-accounts',
        [CreditAccountController::class, 'search'],
        [[RequireUserMiddleware::class, 'handle']]
    );
    $router->get(
        '/api/v1/simontok/credit-accounts/{account_id}',
        [CreditAccountController::class, 'detail'],
        [[RequireUserMiddleware::class, 'handle']]
    );
    $router->get(
        '/api/v1/simontok/credit-accounts/{account_id}/collaterals',
        [CreditAccountController::class, 'collaterals'],
        [[RequireUserMiddleware::class, 'handle']]
    );
    $router->get(
        '/api/v1/simontok/branches',
        [CreditAccountController::class, 'branches']
    );
};
