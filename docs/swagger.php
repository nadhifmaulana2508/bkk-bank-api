<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
require_once dirname(__DIR__) . '/api/global/admin/AdminSession.php';
require_once dirname(__DIR__) . '/helpers/webPath.php';
AdminSession::start();

$docsBaseUrl = web_script_base();
$adminBaseUrl = rtrim(dirname($docsBaseUrl), '/') . '/admin';
$docsRoute = web_route_after($docsBaseUrl);
$routeGroup = strtolower(trim(explode('/', $docsRoute, 2)[0] ?? ''));
$groupQuery = $_GET['group'] ?? $_SESSION['docs_group'] ?? 'global';
$group = in_array($routeGroup, ['global', 'simpeg', 'simontok', 'monbis'], true)
    ? $routeGroup
    : (is_string($groupQuery) ? strtolower(trim($groupQuery)) : 'global');
if (!in_array($group, ['global', 'simpeg', 'simontok', 'monbis'], true)) {
    $group = 'global';
}
$docsPermissionGroup = $group === 'simpeg' ? 'global' : $group;

if (!AdminSession::isAuthenticated()) {
    header('Location: ' . $adminBaseUrl . '/registry');
    exit;
}

if (!AdminSession::hasPermission('docs.' . $docsPermissionGroup)) {
    http_response_code(403);
    exit('Akses dokumentasi ditolak.');
}

$_SESSION['docs_group'] = $group;
$groupMeta = [
    'global' => [
        'label' => 'API Global',
        'description' => 'Kontrak endpoint bersama untuk autentikasi, profil, dan reset password.',
    ],
    'simpeg' => [
        'label' => 'API Simpeg',
        'description' => 'Endpoint SIMPEG untuk pencarian pegawai dan daftar AO.',
    ],
    'simontok' => [
        'label' => 'API Simontok',
        'description' => 'Endpoint SIMONTOK untuk pencarian rekening, detail kredit, agunan, dan master cabang.',
    ],
    'monbis' => [
        'label' => 'API Monbis',
        'description' => 'Belum ada endpoint khusus MONBIS yang didaftarkan pada Control Plane.',
    ],
][$group];
$hasDocs = in_array($group, ['global', 'simpeg', 'simontok'], true);

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');
header('X-Permitted-Cross-Domain-Policies: none');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; connect-src 'self'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($groupMeta['label'], ENT_QUOTES, 'UTF-8') ?> &mdash; Dokumentasi API BKK</title>
    <meta name="docs-group" content="<?= htmlspecialchars($group, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="docs-spec-version" content="<?= htmlspecialchars((string) @filemtime(__DIR__ . '/../swagger.json'), ENT_QUOTES, 'UTF-8') ?>">
    <meta name="docs-app-default" content="<?= htmlspecialchars($group === 'global' ? 'monbis' : $group, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="docs-base" content="<?= htmlspecialchars($docsBaseUrl, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="swagger.css?v=20261106-3">
</head>
<body>
<div class="docs-shell">
    <div class="docs-page-controls">
        <a class="docs-back-link" href="<?= htmlspecialchars($adminBaseUrl . '/overview', ENT_QUOTES, 'UTF-8') ?>" data-back-link aria-label="Kembali ke Control Plane">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5l-7 7 7 7M8 12h10"/></svg>
            <span>Kembali ke Control Plane</span>
        </a>
    </div>

    <section class="docs-hero <?= $hasDocs ? '' : 'docs-hero-empty' ?>">
        <div>
            <p class="docs-kicker">SSO / <?= htmlspecialchars(ucfirst($group), ENT_QUOTES, 'UTF-8') ?></p>
            <h1><?= htmlspecialchars($groupMeta['label'], ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="docs-lede"><?= htmlspecialchars($groupMeta['description'], ENT_QUOTES, 'UTF-8') ?> Dokumentasi ini dibuat khusus untuk testing kontrak endpoint tanpa menampilkan modul TTD.</p>
            <div class="docs-hero-meta">
                <span class="docs-chip" data-endpoint-count>Memuat endpoint...</span>
                <span class="docs-meta-text">Session admin dipakai bersama Control Plane</span>
            </div>
            <nav class="docs-group-tabs" aria-label="Kelompok dokumentasi">
                <?php if (AdminSession::hasPermission('docs.global')): ?><a class="<?= $group === 'global' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($docsBaseUrl . '/global', ENT_QUOTES, 'UTF-8') ?>" <?= $group === 'global' ? 'aria-current="page"' : '' ?>>Global</a><?php endif; ?>
                <?php if (AdminSession::hasPermission('docs.global')): ?><a class="<?= $group === 'simpeg' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($docsBaseUrl . '/simpeg', ENT_QUOTES, 'UTF-8') ?>" <?= $group === 'simpeg' ? 'aria-current="page"' : '' ?>>Simpeg</a><?php endif; ?>
                <?php if (AdminSession::hasPermission('docs.simontok')): ?><a class="<?= $group === 'simontok' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($docsBaseUrl . '/simontok', ENT_QUOTES, 'UTF-8') ?>" <?= $group === 'simontok' ? 'aria-current="page"' : '' ?>>Simontok</a><?php endif; ?>
                <?php if (AdminSession::hasPermission('docs.monbis')): ?><a class="<?= $group === 'monbis' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($docsBaseUrl . '/monbis', ENT_QUOTES, 'UTF-8') ?>" <?= $group === 'monbis' ? 'aria-current="page"' : '' ?>>Monbis</a><?php endif; ?>
            </nav>
        </div>
        <?php if ($group === 'global'): ?><div class="docs-target-card">
            <span class="docs-card-label">Target aplikasi</span>
            <strong><?= htmlspecialchars($group === 'global' ? 'Pilih saat testing' : ucfirst($group), ENT_QUOTES, 'UTF-8') ?></strong>
            <small>Header <code>App</code> mengikuti aplikasi yang dipilih.</small>
        </div><?php endif; ?>
    </section>

    <?php if (!$hasDocs): ?>
    <main class="docs-empty-page">
        <div class="empty-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M7 3h7l4 4v14H7z"/><path d="M14 3v5h5M10 13h5M10 17h5"/></svg>
        </div>
        <p class="section-kicker">Belum tersedia</p>
        <h2>Dokumentasi <?= htmlspecialchars($groupMeta['label'], ENT_QUOTES, 'UTF-8') ?> belum dibuat</h2>
        <p>Tab ini sengaja dikosongkan sampai endpoint khusus <?= htmlspecialchars(ucfirst($group), ENT_QUOTES, 'UTF-8') ?> resmi tersedia. API Global tetap dapat dibuka dari tab Global.</p>
        <a class="empty-link" href="<?= htmlspecialchars($docsBaseUrl . '/global', ENT_QUOTES, 'UTF-8') ?>">Buka API Global <span aria-hidden="true">&rarr;</span></a>
    </main>
    <?php else: ?>
    <div class="docs-layout">
        <aside class="docs-sidebar" id="docs-sidebar" data-endpoint-sidebar aria-label="Daftar endpoint">
            <div class="sidebar-heading">
                <div><strong>Endpoint</strong><span data-endpoint-count>0 endpoint</span></div>
                <button class="sidebar-close" type="button" data-sidebar-close aria-label="Tutup daftar endpoint" title="Tutup daftar endpoint">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>
            <label class="docs-search">
                <span aria-hidden="true">&#8981;</span>
                <input type="search" placeholder="Cari endpoint..." data-endpoint-search>
            </label>
            <nav class="endpoint-nav" data-endpoint-nav></nav>
        </aside>

        <main class="docs-main">
            <button class="docs-sidebar-toggle" type="button" data-sidebar-toggle aria-expanded="false" aria-controls="docs-sidebar">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                <span>Daftar endpoint</span>
                <span class="docs-sidebar-toggle-count" data-endpoint-count>0 endpoint</span>
            </button>
            <div class="docs-sidebar-backdrop" data-sidebar-backdrop hidden></div>
            <section class="auth-card" aria-labelledby="auth-title">
                <div class="auth-card-heading">
                    <div>
                        <p class="section-kicker">Request setup</p>
                        <h2 id="auth-title">Kredensial testing</h2>
                        <p>Nilai ini otomatis dipakai saat menekan tombol Jalankan pada endpoint.</p>
                    </div>
                    <div class="auth-tools">
                        <button class="button-subtle" type="button" data-save-headers>Simpan header</button>
                        <button class="button-subtle" type="button" data-clear-headers>Bersihkan</button>
                    </div>
                </div>
                <div class="auth-grid">
                    <label><span>App</span><input name="app" data-auth-app value="<?= htmlspecialchars($group === 'global' ? 'monbis' : $group, ENT_QUOTES, 'UTF-8') ?>" autocomplete="off"><small>Kode aplikasi terdaftar.</small></label>
                    <label><span>X-App-Token</span><input name="app_token" type="password" data-auth-token placeholder="Masukkan token aplikasi" autocomplete="off"><small>Tidak disimpan kecuali tombol simpan dipilih.</small></label>
                    <label class="auth-wide"><span>Bearer JWT <em>opsional</em></span><input name="jwt" type="password" data-auth-jwt placeholder="Token hasil /api/auth/login" autocomplete="off"><small>Dipakai untuk endpoint yang membutuhkan login pegawai.</small></label>
                </div>
            </section>

            <div class="docs-toolbar-row">
                <div><p class="section-kicker">API reference</p><h2>Endpoint yang tersedia</h2></div>
                <label class="server-picker"><span>Server</span><select data-server-select aria-label="Pilih server API"></select></label>
            </div>

            <section class="docs-content" data-docs-content>
                <div class="docs-loading"><span class="loading-dot"></span> Memuat kontrak API...</div>
            </section>
        </main>
    </div>
    <?php endif; ?>
</div>

<?php if ($hasDocs): ?><script src="swagger-init.js?v=20261106-2" defer></script><?php endif; ?>
</body>
</html>
