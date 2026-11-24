<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
require_once dirname(__DIR__) . '/api/global/admin/AdminSession.php';
require_once dirname(__DIR__) . '/helpers/webPath.php';

AdminSession::start();
$requestPath = web_request_path();
$adminDocsMatch = [];
$isAdminDocsRoute = preg_match(
    '#^(.*)/admin/docs/(global|simpeg|nora|simontok|monbis|dummy)/?$#',
    $requestPath,
    $adminDocsMatch
) === 1;
if ($isAdminDocsRoute) {
    $projectBase = rtrim((string) ($adminDocsMatch[1] ?? ''), '/');
    $adminBaseUrl = $projectBase . '/admin';
    $docsBaseUrl = $adminBaseUrl . '/docs';
    $docsAssetBaseUrl = $projectBase . '/docs';
    $routeGroup = strtolower($adminDocsMatch[2]);
} else {
    $docsBaseUrl = web_script_base();
    $docsAssetBaseUrl = $docsBaseUrl;
    $adminBaseUrl = rtrim(dirname($docsBaseUrl), '/') . '/admin';
    $docsRoute = web_route_after($docsBaseUrl);
    $routeGroup = strtolower(trim(explode('/', $docsRoute, 2)[0] ?? ''));
}
$groupQuery = $_GET['group'] ?? 'global';
$group = in_array($routeGroup, ['global', 'simpeg', 'nora', 'simontok', 'monbis', 'dummy'], true)
    ? $routeGroup
    : (is_string($groupQuery) ? strtolower(trim($groupQuery)) : 'global');
if (!in_array($group, ['global', 'simpeg', 'nora', 'simontok', 'monbis', 'dummy'], true)) {
    $group = 'global';
}
if ($group === 'simontok') {
    $group = 'nora';
}
$docsPermissionGroup = in_array($group, ['simpeg', 'dummy'], true) ? 'global' : ($group === 'nora' ? 'simontok' : $group);

if (!AdminSession::isAuthenticated()) {
    header('Location: ' . $adminBaseUrl . '/overview');
    exit;
}

if (!AdminSession::hasPermission('docs.' . $docsPermissionGroup)) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Akses ditolak</title><p>Akun ini belum memiliki permission untuk dokumentasi API ini.</p>';
    exit;
}

$_SESSION['docs_group'] = $group;
if (!$isAdminDocsRoute) {
    header('Location: ' . $adminBaseUrl . '/docs/' . rawurlencode($group), true, 302);
    exit;
}
require __DIR__ . '/swagger.php';
