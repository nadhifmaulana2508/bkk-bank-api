<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
require_once dirname(__DIR__) . '/config/env.php';
require_once dirname(__DIR__) . '/helpers/request.php';
require_once dirname(__DIR__) . '/helpers/response.php';
require_once dirname(__DIR__) . '/helpers/webPath.php';
require_once dirname(__DIR__) . '/api/global/admin/AdminSession.php';
require_once dirname(__DIR__) . '/api/global/admin/AdminController.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'");

AdminSession::start();
$actionQuery = $_GET['action'] ?? '';
$action = is_string($actionQuery) ? strtolower(trim($actionQuery)) : '';
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$cleanEmployeeQuery = null;
$cleanDashboardPage = null;
$cleanRoute = web_route_after(web_script_base());
$cleanSegments = array_values(array_filter(explode('/', $cleanRoute), static fn(string $segment): bool => $segment !== ''));
if (($cleanSegments[0] ?? '') === 'api') {
    $cleanAction = $cleanSegments[1] ?? '';
    if ($cleanAction === 'login' || $cleanAction === 'logout') {
        $action = $cleanAction;
    } elseif ($cleanAction === 'dashboard' && isset($cleanSegments[2])) {
        $cleanDashboardPage = strtolower(rawurldecode($cleanSegments[2]));
        $action = 'dashboard';
    } elseif ($cleanAction === 'users') {
        $action = isset($cleanSegments[2])
            ? 'user-' . strtolower(rawurldecode($cleanSegments[2]))
            : 'users';
    } elseif ($cleanAction === 'employee-search') {
        $action = 'employee-search';
        $cleanEmployeeQuery = isset($cleanSegments[2]) ? rawurldecode($cleanSegments[2]) : '';
    } elseif ($cleanAction === 'applications') {
        $action = isset($cleanSegments[2])
            ? strtolower(rawurldecode($cleanSegments[2]))
            : 'create';
    }
}

if ($action === 'login' && $method === 'POST') {
    if (!AdminSession::canAttemptLogin()) {
        header('Retry-After: 900');
        sendResponse(429, 'Terlalu banyak percobaan login dari alamat ini. Coba lagi 15 menit kemudian.');
    }

    $payload = jsonRequestBody();
    if (!is_string($payload['username'] ?? null) || !is_string($payload['password'] ?? null)) {
        AdminSession::recordFailedLogin();
        sendResponse(401, 'Username atau password tidak valid.');
    }
    if (!AdminSession::login(
        $payload['username'],
        $payload['password']
    )) {
        sendResponse(401, 'Username atau password tidak valid.');
    }

    sendResponse(200, 'Login admin berhasil.', [
        'csrf_token' => AdminSession::csrfToken(),
        'user' => AdminSession::user(),
    ]);
}

if ($action === 'logout' && $method === 'POST') {
    AdminSession::requireCsrf();
    AdminSession::logout();
    sendResponse(200, 'Logout berhasil.');
}

AdminSession::requireAuthenticated();

if ($method === 'GET' && $action === 'employee-search') {
    AdminSession::requirePermission('users.manage');
    $query = $cleanEmployeeQuery ?? ($_GET['q'] ?? '');
    AdminController::searchEmployees(is_string($query) ? $query : '');
}

if ($method === 'GET' && $action === 'dashboard') {
    $pageQuery = $cleanDashboardPage ?? ($_GET['page'] ?? 'registry');
    $page = is_string($pageQuery) ? strtolower(trim($pageQuery)) : 'registry';
    $permission = match ($page) {
        'audit' => 'audit.view',
        'recap' => 'recap.view',
        default => 'registry.view',
    };
    AdminSession::requirePermission($permission);
    AdminController::dashboard($page);
}

if ($method === 'GET' && $action === 'users') {
    AdminSession::requirePermission('users.manage');
    AdminController::users();
}

if ($method !== 'POST') {
    sendResponse(405, 'Method tidak diizinkan.');
}

AdminSession::requireCsrf();

switch ($action) {
    case 'create':
        AdminSession::requirePermission('registry.manage');
        AdminController::create();
        break;
    case 'update':
        AdminSession::requirePermission('registry.manage');
        AdminController::update();
        break;
    case 'rotate':
        AdminSession::requirePermission('registry.manage');
        AdminController::rotate();
        break;
    case 'user-create':
        AdminSession::requirePermission('users.manage');
        AdminController::createUser();
        break;
    case 'user-update':
        AdminSession::requirePermission('users.manage');
        AdminController::updateUser();
        break;
    default:
        sendResponse(404, 'Aksi admin tidak ditemukan.');
}
