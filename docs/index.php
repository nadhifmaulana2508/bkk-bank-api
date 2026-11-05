<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
require_once dirname(__DIR__) . '/api/global/admin/AdminSession.php';
require_once dirname(__DIR__) . '/helpers/webPath.php';

AdminSession::start();
$docsBaseUrl = web_script_base();
$docsRoute = web_route_after($docsBaseUrl);
$routeGroup = strtolower(trim(explode('/', $docsRoute, 2)[0] ?? ''));
$groupQuery = $_GET['group'] ?? 'global';
$group = in_array($routeGroup, ['global', 'simpeg', 'simontok', 'monbis'], true)
    ? $routeGroup
    : (is_string($groupQuery) ? strtolower(trim($groupQuery)) : 'global');
if (!in_array($group, ['global', 'simpeg', 'simontok', 'monbis'], true)) {
    $group = 'global';
}
$docsPermissionGroup = $group === 'simpeg' ? 'global' : $group;

if (!AdminSession::isAuthenticated()) {
    header('Location: ' . rtrim(dirname($docsBaseUrl), '/') . '/admin/registry');
    exit;
}

if (!AdminSession::hasPermission('docs.' . $docsPermissionGroup)) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Akses ditolak</title><p>Akun ini belum memiliki permission untuk dokumentasi API ini.</p>';
    exit;
}

$_SESSION['docs_group'] = $group;
require __DIR__ . '/swagger.php';
