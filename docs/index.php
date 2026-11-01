<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/global/admin/AdminSession.php';

AdminSession::start();
$group = strtolower(trim((string) ($_GET['group'] ?? 'global')));
if (!in_array($group, ['global', 'simpeg', 'simontok', 'monbis'], true)) {
    $group = 'global';
}
$docsPermissionGroup = $group === 'simpeg' ? 'global' : $group;

if (!AdminSession::isAuthenticated()) {
    header('Location: ../admin/?page=registry');
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
