<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
require_once dirname(__DIR__) . '/api/global/admin/AdminSession.php';
require_once dirname(__DIR__) . '/helpers/webPath.php';
AdminSession::start();

if (!AdminSession::isAuthenticated()) {
    http_response_code(404);
    exit;
}

$specFile = dirname(__DIR__) . '/swagger.json';
if (!is_file($specFile)) {
    http_response_code(404);
    exit;
}

$docsBaseUrl = web_script_base();
$docsRoute = web_route_after($docsBaseUrl);
$routeSegments = explode('/', $docsRoute);
$routeGroup = ($routeSegments[0] ?? '') === 'openapi' ? strtolower(trim($routeSegments[1] ?? '')) : '';
$groupQuery = $_GET['group'] ?? $_SESSION['docs_group'] ?? 'global';
$group = in_array($routeGroup, ['global', 'simpeg', 'simontok', 'monbis'], true)
    ? $routeGroup
    : (is_string($groupQuery) ? strtolower(trim($groupQuery)) : 'global');
if (!in_array($group, ['global', 'simpeg', 'simontok', 'monbis'], true)) {
    $group = 'global';
}
$docsPermissionGroup = $group === 'simpeg' ? 'global' : $group;
if (!AdminSession::hasPermission('docs.' . $docsPermissionGroup)) {
    http_response_code(403);
    exit;
}

$spec = json_decode((string) file_get_contents($specFile), true);
if (!is_array($spec)) {
    http_response_code(500);
    exit;
}

$groupLabels = [
    'global' => 'API Global',
    'simpeg' => 'API Simpeg',
    'simontok' => 'API Simontok',
    'monbis' => 'API Monbis',
];
$activeAppCode = $group === 'global' ? 'monbis' : $group;
$spec['info']['title'] = ($groupLabels[$group] ?? 'API Global') . ' BKK';
$spec['info']['description'] = $group === 'global'
    ? 'Dokumentasi endpoint bersama untuk autentikasi, profil, dan reset password.'
    : ($group === 'simontok'
        ? 'Dokumentasi endpoint SIMONTOK untuk pencarian rekening, detail kredit, agunan, dan master cabang.'
        : 'Dokumentasi endpoint SIMPEG untuk data pegawai dan AO.');

$groupTag = match ($group) {
    'simpeg' => 'SIMPEG',
    'simontok' => 'SIMONTOK',
    default => null,
};
foreach (array_keys($spec['paths']) as $path) {
    $pathItem = $spec['paths'][$path];
    if (!is_array($pathItem)) {
        unset($spec['paths'][$path]);
        continue;
    }

    $operationTags = [];
    foreach ($pathItem as $method => $operation) {
        if ($method === 'parameters' || !is_array($operation)) {
            continue;
        }
        foreach (($operation['tags'] ?? []) as $tag) {
            if (is_string($tag)) {
                $operationTags[] = strtoupper($tag);
            }
        }
    }

    $isModulePath = $operationTags !== [] && (
        in_array('SIMPEG', $operationTags, true)
        || in_array('SIMONTOK', $operationTags, true)
    );
    $keepPath = $group === 'global'
        ? !$isModulePath
        : $groupTag !== null && in_array($groupTag, $operationTags, true);

    if (!$keepPath) {
        unset($spec['paths'][$path]);
    }
}

foreach ($spec['paths'] as &$pathItem) {
    if (!is_array($pathItem)) {
        continue;
    }

    foreach ($pathItem as &$operation) {
        if (!is_array($operation)) {
            continue;
        }

        if (isset($operation['requestBody']['content']['application/json']['schema']['properties']['app']['example'])) {
            $operation['requestBody']['content']['application/json']['schema']['properties']['app']['example'] = $activeAppCode;
        }
    }
    unset($operation);
}
unset($pathItem);

if (isset($spec['components']['securitySchemes']['appTokenAuth']['description'])) {
    $spec['components']['securitySchemes']['appTokenAuth']['description'] = 'Token aplikasi ' . $activeAppCode . '. Gunakan token yang diterbitkan dari Registry.';
}
if (isset($spec['components']['securitySchemes']['appCode']['description'])) {
    $spec['components']['securitySchemes']['appCode']['description'] = 'Kode aplikasi terdaftar: ' . $activeAppCode . '.';
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Permitted-Cross-Domain-Policies: none');
echo json_encode($spec, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
