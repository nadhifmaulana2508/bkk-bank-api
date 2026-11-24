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
    'nora' => [
        'label' => 'API NORA',
        'description' => 'Endpoint NORA untuk pencarian rekening, detail kredit, agunan, dan master cabang.',
    ],
    'monbis' => [
        'label' => 'API Monbis',
        'description' => 'Belum ada endpoint khusus MONBIS yang didaftarkan pada Control Plane.',
    ],
    'dummy' => [
        'label' => 'API Dummy',
        'description' => 'Endpoint login dan profil dummy untuk pengujian jabatan di local/development.',
    ],
][$group];
$hasDocs = in_array($group, ['global', 'simpeg', 'nora', 'dummy'], true);
$docsDefaultApp = in_array($group, ['global', 'dummy'], true) ? 'monbis' : $group;
$docsNavIcon = static fn(string $name): string => match ($name) {
    'overview' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/></svg>',
    'applications' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="6" rx="1"/><rect x="4" y="14" width="16" height="6" rx="1"/></svg>',
    'audit' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5h12M8 12h12M8 19h12M3 5h.01M3 12h.01M3 19h.01"/></svg>',
    'recap' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 19V9M12 19V5M19 19v-7"/></svg>',
    'rbac' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3.5 19a5.5 5.5 0 0 1 11 0M16 11a3 3 0 1 0 0-6M16 14a5 5 0 0 1 4.5 5"/></svg>',
    'docs' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m8 8-4 4 4 4M16 8l4 4-4 4M14 5l-4 14"/></svg>',
    'global' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg>',
    'simpeg' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3"/><path d="M5 20a7 7 0 0 1 14 0"/></svg>',
    'nora' => '<svg viewBox="0 0 24 24" aria-hidden="true"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/></svg>',
    'monbis' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20V8l8-4 8 4v12M2 20h20M9 10v2M15 10v2M9 15v2M15 15v2"/></svg>',
    'dummy' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 3h6M10 3v6l-5.5 9.2A2 2 0 0 0 6.2 21h11.6a2 2 0 0 0 1.7-2.8L14 9V3M8 15h8"/></svg>',
    default => '',
};

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
    <meta name="docs-app-default" content="<?= htmlspecialchars($docsDefaultApp, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="docs-base" content="<?= htmlspecialchars($docsBaseUrl, ENT_QUOTES, 'UTF-8') ?>">
    <?php if ($hasDocs): ?><link rel="stylesheet" href="<?= htmlspecialchars($docsAssetBaseUrl . '/vendor/lenis/lenis.css?v=1.3.27', ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($docsAssetBaseUrl . '/swagger.css?v=20261124-1', ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<aside class="docs-control-sidebar" id="docs-control-sidebar" data-control-sidebar aria-label="Navigasi Control Plane" aria-hidden="true" inert>
    <div class="docs-control-heading">
        <a class="docs-brand" href="<?= htmlspecialchars($adminBaseUrl . '/overview', ENT_QUOTES, 'UTF-8') ?>" aria-label="Bank API BKK, Overview" title="Bank API BKK">
            <span class="docs-brand-mark" aria-hidden="true" title="Bank API BKK"><svg viewBox="0 0 24 24"><path d="M3 9 12 4l9 5"/><path d="M5 10v8M9 10v8M15 10v8M19 10v8M3 20h18"/><path d="m10 14-2 2 2 2M14 14l2 2-2 2"/></svg></span>
            <span><strong>Bank API BKK</strong><small>SSO Control Plane</small></span>
        </a>
        <button class="docs-control-close" type="button" data-control-nav-close aria-label="Tutup menu" title="Tutup menu">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
        </button>
    </div>
    <nav class="docs-control-nav" aria-label="Menu Control Plane">
        <p>Workspace</p>
        <?php if (AdminSession::hasPermission('registry.view')): ?>
            <a href="<?= htmlspecialchars($adminBaseUrl . '/overview', ENT_QUOTES, 'UTF-8') ?>" aria-label="Overview" title="Overview"><span class="docs-nav-icon" aria-hidden="true"><?= $docsNavIcon('overview') ?></span><span class="docs-nav-label">Overview</span></a>
            <a href="<?= htmlspecialchars($adminBaseUrl . '/registry', ENT_QUOTES, 'UTF-8') ?>" aria-label="Applications" title="Applications"><span class="docs-nav-icon" aria-hidden="true"><?= $docsNavIcon('applications') ?></span><span class="docs-nav-label">Applications</span></a>
        <?php endif; ?>
        <?php if (AdminSession::hasPermission('audit.view')): ?><a href="<?= htmlspecialchars($adminBaseUrl . '/audit', ENT_QUOTES, 'UTF-8') ?>" aria-label="Audit" title="Audit"><span class="docs-nav-icon" aria-hidden="true"><?= $docsNavIcon('audit') ?></span><span class="docs-nav-label">Audit</span></a><?php endif; ?>
        <?php if (AdminSession::hasPermission('recap.view')): ?><a href="<?= htmlspecialchars($adminBaseUrl . '/recap', ENT_QUOTES, 'UTF-8') ?>" aria-label="Rekap" title="Rekap"><span class="docs-nav-icon" aria-hidden="true"><?= $docsNavIcon('recap') ?></span><span class="docs-nav-label">Rekap</span></a><?php endif; ?>
        <?php if (AdminSession::hasPermission('users.manage')): ?><a href="<?= htmlspecialchars($adminBaseUrl . '/rbac', ENT_QUOTES, 'UTF-8') ?>" aria-label="RBAC" title="RBAC"><span class="docs-nav-icon" aria-hidden="true"><?= $docsNavIcon('rbac') ?></span><span class="docs-nav-label">RBAC</span></a><?php endif; ?>
        <p class="docs-nav-section">Developer</p>
        <a class="is-active" aria-label="API Docs" title="API Docs" href="<?= htmlspecialchars($docsBaseUrl . '/' . $group, ENT_QUOTES, 'UTF-8') ?>"><span class="docs-nav-icon" aria-hidden="true"><?= $docsNavIcon('docs') ?></span><span class="docs-nav-label">API Docs</span><span class="docs-nav-arrow" aria-hidden="true">›</span></a>
        <div class="docs-nav-submenu">
            <?php if (AdminSession::hasPermission('docs.global')): ?><a class="<?= $group === 'global' ? 'is-current' : '' ?>" aria-label="Global" title="Global" href="<?= htmlspecialchars($docsBaseUrl . '/global', ENT_QUOTES, 'UTF-8') ?>" <?= $group === 'global' ? 'aria-current="page"' : '' ?>><span class="docs-nav-icon" aria-hidden="true"><?= $docsNavIcon('global') ?></span><span class="docs-nav-label">Global</span></a><?php endif; ?>
            <?php if (AdminSession::hasPermission('docs.global')): ?><a class="<?= $group === 'simpeg' ? 'is-current' : '' ?>" aria-label="Simpeg" title="Simpeg" href="<?= htmlspecialchars($docsBaseUrl . '/simpeg', ENT_QUOTES, 'UTF-8') ?>" <?= $group === 'simpeg' ? 'aria-current="page"' : '' ?>><span class="docs-nav-icon" aria-hidden="true"><?= $docsNavIcon('simpeg') ?></span><span class="docs-nav-label">Simpeg</span></a><?php endif; ?>
            <?php if (AdminSession::hasPermission('docs.simontok')): ?><a class="<?= $group === 'nora' ? 'is-current' : '' ?>" aria-label="NORA" title="NORA" href="<?= htmlspecialchars($docsBaseUrl . '/nora', ENT_QUOTES, 'UTF-8') ?>" <?= $group === 'nora' ? 'aria-current="page"' : '' ?>><span class="docs-nav-icon" aria-hidden="true"><?= $docsNavIcon('nora') ?></span><span class="docs-nav-label">NORA</span></a><?php endif; ?>
            <?php if (AdminSession::hasPermission('docs.monbis')): ?><a class="<?= $group === 'monbis' ? 'is-current' : '' ?>" aria-label="Monbis" title="Monbis" href="<?= htmlspecialchars($docsBaseUrl . '/monbis', ENT_QUOTES, 'UTF-8') ?>" <?= $group === 'monbis' ? 'aria-current="page"' : '' ?>><span class="docs-nav-icon" aria-hidden="true"><?= $docsNavIcon('monbis') ?></span><span class="docs-nav-label">Monbis</span></a><?php endif; ?>
            <?php if (AdminSession::hasPermission('docs.global')): ?><a class="<?= $group === 'dummy' ? 'is-current' : '' ?>" aria-label="Dummy" title="Dummy" href="<?= htmlspecialchars($docsBaseUrl . '/dummy', ENT_QUOTES, 'UTF-8') ?>" <?= $group === 'dummy' ? 'aria-current="page"' : '' ?>><span class="docs-nav-icon" aria-hidden="true"><?= $docsNavIcon('dummy') ?></span><span class="docs-nav-label">Dummy</span></a><?php endif; ?>
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
                <?php if (AdminSession::hasPermission('docs.simontok')): ?><a class="<?= $group === 'nora' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($docsBaseUrl . '/nora', ENT_QUOTES, 'UTF-8') ?>" <?= $group === 'nora' ? 'aria-current="page"' : '' ?>>NORA</a><?php endif; ?>
                <?php if (AdminSession::hasPermission('docs.monbis')): ?><a class="<?= $group === 'monbis' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($docsBaseUrl . '/monbis', ENT_QUOTES, 'UTF-8') ?>" <?= $group === 'monbis' ? 'aria-current="page"' : '' ?>>Monbis</a><?php endif; ?>
                <?php if (AdminSession::hasPermission('docs.global')): ?><a class="<?= $group === 'dummy' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($docsBaseUrl . '/dummy', ENT_QUOTES, 'UTF-8') ?>" <?= $group === 'dummy' ? 'aria-current="page"' : '' ?>>Dummy</a><?php endif; ?>
            </nav>
        </div>
        <?php if (in_array($group, ['global', 'dummy'], true)): ?><div class="docs-target-card">
            <span class="docs-card-label">Target aplikasi</span>
            <strong><?= htmlspecialchars($group === 'global' ? 'Pilih saat testing' : 'Aplikasi terdaftar', ENT_QUOTES, 'UTF-8') ?></strong>
            <small><?= $group === 'global' ? 'Header <code>App</code> mengikuti aplikasi yang dipilih.' : 'Gunakan App dan X-App-Token aplikasi aktif; default App adalah monbis.' ?></small>
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
                    <div class="auth-field"><label for="auth-app">App</label><input id="auth-app" name="app" data-auth-app value="<?= htmlspecialchars($docsDefaultApp, ENT_QUOTES, 'UTF-8') ?>" autocomplete="off"><small>Kode aplikasi terdaftar.</small></div>
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
<?php if ($hasDocs): ?><script src="<?= htmlspecialchars($docsAssetBaseUrl . '/vendor/lenis/lenis.min.js?v=1.3.27', ENT_QUOTES, 'UTF-8') ?>" defer></script><script src="<?= htmlspecialchars($docsAssetBaseUrl . '/swagger-init.js?v=20261124-1', ENT_QUOTES, 'UTF-8') ?>" defer></script><?php endif; ?>
</body>
</html>
