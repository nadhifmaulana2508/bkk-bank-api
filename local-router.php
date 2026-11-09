<?php
declare(strict_types=1);

// Development router for PHP's built-in server; .htaccess is not processed.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/') ?: '/';

if ($path === '/admin') {
    $_GET['page'] = 'overview';
    require __DIR__ . '/admin/index.php';
    return true;
}

if (preg_match('#^/admin/(overview|registry|audit|recap|rbac|users)$#', $path, $m)) {
    $_GET['page'] = $m[1];
    require __DIR__ . '/admin/index.php';
    return true;
}

if (preg_match('#^/admin/api/(login|logout)$#', $path, $m)) {
    $_GET['action'] = $m[1];
    require __DIR__ . '/admin/api.php';
    return true;
}

if (preg_match('#^/admin/api/dashboard/(overview|registry|audit|recap)$#', $path, $m)) {
    $_GET['action'] = 'dashboard';
    $_GET['page'] = $m[1];
    require __DIR__ . '/admin/api.php';
    return true;
}

if ($path === '/admin/api/users') {
    $_GET['action'] = 'users';
    require __DIR__ . '/admin/api.php';
    return true;
}

if (preg_match('#^/admin/api/users/(create|update)$#', $path, $m)) {
    $_GET['action'] = 'user-' . $m[1];
    require __DIR__ . '/admin/api.php';
    return true;
}

if (preg_match('#^/admin/api/applications(?:/(update|rotate))?$#', $path, $m)) {
    $_GET['action'] = $m[1] ?? 'create';
    require __DIR__ . '/admin/api.php';
    return true;
}

if (preg_match('#^/docs/(global|simpeg|simontok|monbis)$#', $path, $m)) {
    $_GET['group'] = $m[1];
    require __DIR__ . '/docs/index.php';
    return true;
}

if (preg_match('#^/docs/openapi/(global|simpeg|simontok|monbis)$#', $path, $m)) {
    $_GET['group'] = $m[1];
    require __DIR__ . '/docs/openapi.php';
    return true;
}

$file = __DIR__ . $path;
if (is_file($file)) {
    return false;
}

require __DIR__ . '/index.php';
return true;
