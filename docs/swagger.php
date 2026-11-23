<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
require_once dirname(__DIR__) . '/api/global/admin/AdminSession.php';
require_once dirname(__DIR__) . '/helpers/webPath.php';
AdminSession::start();

$docsBaseUrl = $docsBaseUrl ?? web_script_base();
$docsAssetBaseUrl = $docsAssetBaseUrl ?? web_script_base();
$adminBaseUrl = $adminBaseUrl ?? (rtrim(dirname($docsBaseUrl), '/') . '/admin');
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
    <link rel="stylesheet" href="<?= htmlspecialchars($docsAssetBaseUrl . '/swagger.css?v=20261122-7', ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<aside class="docs-control-sidebar" id="docs-control-sidebar" data-control-sidebar aria-label="Navigasi Control Plane" aria-hidden="true" inert>
    <div class="docs-control-heading">
        <a class="docs-brand" href="<?= htmlspecialchars($adminBaseUrl . '/overview', ENT_QUOTES, 'UTF-8') ?>">
            <span class="docs-brand-mark" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 9 12 4l9 5"/><path d="M5 10v8M9 10v8M15 10v8M19 10v8M3 20h18"/><path d="m10 14-2 2 2 2M14 14l2 2-2 2"/></svg></span>
            <span><strong>Bank API BKK</strong><small>SSO Control Plane</small></span>
        </a>
        <button class="docs-control-close" type="button" data-control-nav-close aria-label="Tutup menu" title="Tutup menu">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
        </button>
    </div>
    <nav class="docs-control-nav" aria-label="Menu Control Plane">
        <p>Workspace</p>
        <?php if (AdminSession::hasPermission('registry.view')): ?>
            <a href="<?= htmlspecialchars($adminBaseUrl . '/overview', ENT_QUOTES, 'UTF-8') ?>">Overview</a>
            <a href="<?= htmlspecialchars($adminBaseUrl . '/registry', ENT_QUOTES, 'UTF-8') ?>">Applications</a>
        <?php endif; ?>
        <?php if (AdminSession::hasPermission('audit.view')): ?><a href="<?= htmlspecialchars($adminBaseUrl . '/audit', ENT_QUOTES, 'UTF-8') ?>">Audit</a><?php endif; ?>
        <?php if (AdminSession::hasPermission('recap.view')): ?><a href="<?= htmlspecialchars($adminBaseUrl . '/recap', ENT_QUOTES, 'UTF-8') ?>">Rekap</a><?php endif; ?>
        <?php if (AdminSession::hasPermission('users.manage')): ?><a href="<?= htmlspecialchars($adminBaseUrl . '/rbac', ENT_QUOTES, 'UTF-8') ?>">RBAC</a><?php endif; ?>
        <p class="docs-nav-section">Developer</p>
        <a class="is-active" href="<?= htmlspecialchars($docsBaseUrl . '/' . $group, ENT_QUOTES, 'UTF-8') ?>">API Docs <span aria-hidden="true">›</span></a>
        <div class="docs-nav-submenu">
            <?php if (AdminSession::hasPermission('docs.global')): ?><a class="<?= $group === 'global' ? 'is-current' : '' ?>" href="<?= htmlspecialchars($docsBaseUrl . '/global', ENT_QUOTES, 'UTF-8') ?>" <?= $group === 'global' ? 'aria-current="page"' : '' ?>>Global</a><?php endif; ?>
            <?php if (AdminSession::hasPermission('docs.global')): ?><a class="<?= $group === 'simpeg' ? 'is-current' : '' ?>" href="<?= htmlspecialchars($docsBaseUrl . '/simpeg', ENT_QUOTES, 'UTF-8') ?>" <?= $group === 'simpeg' ? 'aria-current="page"' : '' ?>>Simpeg</a><?php endif; ?>
            <?php if (AdminSession::hasPermission('docs.simontok')): ?><a class="<?= $group === 'simontok' ? 'is-current' : '' ?>" href="<?= htmlspecialchars($docsBaseUrl . '/simontok', ENT_QUOTES, 'UTF-8') ?>" <?= $group === 'simontok' ? 'aria-current="page"' : '' ?>>Simontok</a><?php endif; ?>
            <?php if (AdminSession::hasPermission('docs.monbis')): ?><a class="<?= $group === 'monbis' ? 'is-current' : '' ?>" href="<?= htmlspecialchars($docsBaseUrl . '/monbis', ENT_QUOTES, 'UTF-8') ?>" <?= $group === 'monbis' ? 'aria-current="page"' : '' ?>>Monbis</a><?php endif; ?>
        </div>
    </nav>
</aside>
<button class="docs-control-edge" type="button" data-control-nav-edge aria-label="Buka navigasi Control Plane" title="Buka menu"></button>
<button class="docs-control-backdrop" type="button" data-control-nav-backdrop aria-label="Tutup menu" hidden></button>
<div class="docs-shell">
    <div class="docs-page-controls">
        <div class="docs-page-start">
            <button class="docs-menu-toggle" type="button" data-control-nav-toggle aria-expanded="false" aria-controls="docs-control-sidebar">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                <span>Menu</span>
            </button>
            <a class="docs-back-link" href="<?= htmlspecialchars($adminBaseUrl . '/overview', ENT_QUOTES, 'UTF-8') ?>" aria-label="Kembali ke Control Plane">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5l-7 7 7 7M8 12h10"/></svg>
                <span>Kembali ke Control Plane</span>
            </a>
        </div>
        <div class="docs-page-actions">
            <?php if (AdminSession::hasPermission('docs.global')): ?>
                <a class="docs-back-link docs-export-link" href="<?= htmlspecialchars($docsBaseUrl . '/export', ENT_QUOTES, 'UTF-8') ?>" aria-label="Unduh semua dokumentasi Markdown">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m0 0 4-4m-4 4-4-4M5 17v3h14v-3"/></svg>
                    <span>Unduh semua Markdown</span>
                </a>
            <?php endif; ?>
            <button class="docs-theme-toggle" type="button" data-theme-toggle aria-label="Ganti tema" title="Ganti tema">
            <span data-theme-icon aria-hidden="true">☾</span>
            <span data-theme-label>Mode terang</span>
            </button>
        </div>
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
                    <div class="auth-field"><label for="auth-app">App</label><input id="auth-app" name="app" data-auth-app value="<?= htmlspecialchars($group === 'global' ? 'monbis' : $group, ENT_QUOTES, 'UTF-8') ?>" autocomplete="off"><small>Kode aplikasi terdaftar.</small></div>
                    <div class="auth-field"><label for="auth-app-token">X-App-Token</label><div class="secret-control"><input id="auth-app-token" name="app_token" type="password" data-auth-token placeholder="Masukkan token aplikasi" autocomplete="off"><button class="secret-toggle" type="button" data-secret-toggle data-secret-label="X-App-Token" aria-controls="auth-app-token" aria-label="Tampilkan X-App-Token" title="Tampilkan X-App-Token" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg></button></div><small>Tidak disimpan kecuali tombol simpan dipilih.</small></div>
                    <div class="auth-field auth-wide"><label for="auth-jwt">Bearer JWT <em>opsional</em></label><div class="secret-control"><input id="auth-jwt" name="jwt" type="password" data-auth-jwt placeholder="Token hasil /api/auth/login" autocomplete="off"><button class="secret-toggle" type="button" data-secret-toggle data-secret-label="Bearer JWT" aria-controls="auth-jwt" aria-label="Tampilkan Bearer JWT" title="Tampilkan Bearer JWT" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg></button></div><small>Dipakai untuk endpoint yang membutuhkan login pegawai.</small></div>
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

<script src="<?= htmlspecialchars($adminBaseUrl . '/theme.js', ENT_QUOTES, 'UTF-8') ?>" defer></script>
<script src="<?= htmlspecialchars($docsAssetBaseUrl . '/control-sidebar.js?v=20261122-4', ENT_QUOTES, 'UTF-8') ?>" defer></script>
<?php if ($hasDocs): ?><script src="<?= htmlspecialchars($docsAssetBaseUrl . '/swagger-init.js?v=20261122-4', ENT_QUOTES, 'UTF-8') ?>" defer></script><?php endif; ?>
</body>
</html>
