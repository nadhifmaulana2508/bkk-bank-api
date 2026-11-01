<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/global/admin/AdminSession.php';
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

$group = strtolower(trim((string) ($_GET['group'] ?? $_SESSION['docs_group'] ?? 'global')));
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
$spec['info']['description'] = 'Dokumentasi interaktif untuk testing kontrak API. App code aktif: ' . $group . '.';

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
echo json_encode($spec, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
