<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
require_once dirname(__DIR__) . '/api/global/admin/AdminSession.php';
require_once dirname(__DIR__) . '/helpers/webPath.php';

AdminSession::start();
$authenticated = AdminSession::isAuthenticated();
$allowedPages = ['overview', 'registry', 'audit', 'recap', 'rbac', 'users', 'docs-global', 'docs-simpeg', 'docs-simontok', 'docs-monbis'];
$adminBaseUrl = web_script_base();
$adminRoute = web_route_after($adminBaseUrl);
if ($adminRoute === 'index.php') {
    $adminRoute = '';
}
$cleanPage = strtolower(trim(explode('/', $adminRoute, 2)[0] ?? ''));
$pageQuery = $_GET['page'] ?? 'overview';
$page = $cleanPage !== '' ? $cleanPage : (is_string($pageQuery) ? strtolower(trim($pageQuery)) : 'registry');
if (!in_array($page, $allowedPages, true)) {
    $page = 'registry';
}
// `users` tetap diterima sebagai alias lama agar link yang sudah tersimpan tidak rusak.
if ($page === 'users') {
    $page = 'rbac';
}
$permissions = $authenticated ? AdminSession::permissions() : [];
$can = static fn(string $permission): bool => in_array($permission, $permissions, true);
$pagePermissions = [
    'overview' => 'registry.view',
    'registry' => 'registry.view',
    'audit' => 'audit.view',
    'recap' => 'recap.view',
    'rbac' => 'users.manage',
];
if ($authenticated && isset($pagePermissions[$page]) && !$can($pagePermissions[$page])) {
    $page = 'forbidden';
    foreach ($pagePermissions as $candidate => $permission) {
        if ($can($permission)) {
            $page = $candidate;
            break;
        }
    }
}
$pageMeta = [
    'overview' => [
        'eyebrow' => 'Control plane / Overview',
        'title' => 'Ringkasan Bank API',
        'description' => 'Lihat aplikasi yang terdaftar, aktivitas API, dan area yang perlu perhatian.',
    ],
    'registry' => [
        'eyebrow' => 'Applications / Registry',
        'title' => 'Aplikasi yang memakai SSO',
        'description' => 'Kelola kredensial aplikasi, status akses, dan rotasi token dari satu tempat.',
    ],
    'audit' => [
        'eyebrow' => 'Audit',
        'title' => 'Jejak pemakaian SSO',
        'description' => 'Lihat endpoint yang dipanggil aplikasi, status response, dan waktu request yang tercatat.',
    ],
    'recap' => [
        'eyebrow' => 'Rekap',
        'title' => 'Rekap penggunaan API',
        'description' => 'Pantau volume hit dan error harian untuk membantu evaluasi integrasi tiap aplikasi.',
    ],
    'rbac' => [
        'eyebrow' => 'RBAC / Access control',
        'title' => 'RBAC user SSO',
        'description' => 'Kelola akun web SSO dan tentukan menu yang boleh dibuka setiap user.',
    ],
    'docs-global' => [
        'eyebrow' => 'API Global',
        'title' => 'Kontrak API Global',
        'description' => 'Endpoint bersama untuk autentikasi, profil, reset password, direktori pegawai, dan AO.',
    ],
    'docs-simpeg' => [
        'eyebrow' => 'API Simpeg',
        'title' => 'Dokumentasi API Simpeg',
        'description' => 'Tempat dokumentasi endpoint Simpeg saat modulnya sudah resmi tersedia.',
    ],
    'docs-simontok' => [
        'eyebrow' => 'API Simontok',
        'title' => 'Panduan integrasi SIMONTOK',
        'description' => 'Konfigurasi aplikasi SIMONTOK agar dapat memakai autentikasi dan endpoint global SSO.',
    ],
    'docs-monbis' => [
        'eyebrow' => 'API Monbis',
        'title' => 'Panduan integrasi MONBIS',
        'description' => 'Konfigurasi MONBIS dengan app token terdaftar dan JWT pegawai sesuai kebutuhan endpoint.',
    ],
    'forbidden' => [
        'eyebrow' => 'Access denied',
        'title' => 'Menu tidak tersedia',
        'description' => 'Akun ini belum memiliki permission untuk membuka menu tersebut.',
    ],
][$page];
$pageUrl = static fn(string $target): string => $adminBaseUrl . '/' . rawurlencode($target === 'users' ? 'rbac' : $target);
$docsBaseUrl = rtrim(dirname($adminBaseUrl), '/') . '/docs';
$navIcon = static fn(string $name): string => match ($name) {
    'bank' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 9 12 4l9 5"/><path d="M5 10v8M9 10v8M15 10v8M19 10v8M3 20h18"/><path d="m10 14-2 2 2 2M14 14l2 2-2 2"/></svg>',
    'registry' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/></svg>',
    'audit' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 5h12M6 12h12M6 19h8"/><circle cx="4" cy="5" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="19" r="1"/></svg>',
    'recap' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 19V9M12 19V5M19 19v-7"/></svg>',
    'rbac' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3.5 19a5.5 5.5 0 0 1 11 0M16 11a3 3 0 1 0 0-6M16 14a5 5 0 0 1 4.5 5"/></svg>',
    'docs' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m8 8-4 4 4 4M16 8l4 4-4 4M14 5l-4 14"/></svg>',
    default => '',
};
if (str_starts_with($page, 'docs-')) {
    $docsGroup = substr($page, 5);
    header('Location: ' . $docsBaseUrl . '/' . rawurlencode($docsGroup));
    exit;
}
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
header('X-Permitted-Cross-Domain-Policies: none');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; connect-src 'self'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Control plane internal untuk registry aplikasi dan audit penggunaan SSO BKK">
    <title>SSO Control Plane</title>
    <link rel="stylesheet" href="styles.css?v=20261102-3">
</head>
<body class="page-mode-<?= htmlspecialchars($page, ENT_QUOTES, 'UTF-8') ?>" data-authenticated="<?= $authenticated ? 'true' : 'false' ?>" data-page="<?= htmlspecialchars($page, ENT_QUOTES, 'UTF-8') ?>" data-can-manage="<?= $can('registry.manage') ? 'true' : 'false' ?>">
<a class="skip-link" href="#main-content">Lewati ke konten</a>

<?php if (!$authenticated): ?>
    <main class="auth-layout" id="main-content">
        <section class="auth-card" aria-labelledby="login-title">
            <p class="eyebrow">BKK / SSO control plane</p>
            <h1 id="login-title">Masuk ke SSO Control Plane</h1>
            <p class="lede">Gunakan akun user SSO untuk membuka menu sesuai permission yang diberikan.</p>
            <form id="login-form" class="stack-form">
                <label for="admin-username">Username</label>
                <input id="admin-username" name="username" autocomplete="username" required>
                <label for="admin-password">Password</label>
                <div class="password-field">
                    <input id="admin-password" name="password" type="password" autocomplete="current-password" required>
                    <button class="password-toggle" id="password-toggle" type="button" aria-label="Tampilkan password" aria-pressed="false">
                        <svg class="password-icon password-icon-show" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-5 9.5-5 9.5 5 9.5 5-3.5 5-9.5 5-9.5-5-9.5-5Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                        <svg class="password-icon password-icon-hide" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.6 6.2A10.8 10.8 0 0 1 12 6c6 0 9.5 6 9.5 6a16.8 16.8 0 0 1-3 3.5M6.2 6.2C3.8 7.7 2.5 12 2.5 12s3.5 6 9.5 6c1.1 0 2.1-.2 3-.5"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
                    </button>
                </div>
                <p class="form-error" id="login-error" role="alert" hidden></p>
                <button class="button button-primary button-block" type="submit">Masuk</button>
            </form>
            <p class="auth-note">Percobaan login dibatasi dan akun memakai permission per menu.</p>
        </section>
    </main>
<?php else: ?>
    <header class="topbar">
        <a class="brand" href="./" aria-label="Bank API BKK — SSO Control Plane">
            <span class="brand-mark"><?= $navIcon('bank') ?></span>
            <span class="brand-copy"><strong>Bank API BKK</strong><small>SSO Control Plane</small></span>
        </a>
        <nav class="main-nav" aria-label="Navigasi control plane">
            <?php if ($can('registry.view')): ?><a class="nav-icon-link <?= $page === 'overview' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($pageUrl('overview'), ENT_QUOTES, 'UTF-8') ?>" aria-label="Overview" data-tooltip="Overview"><?= $navIcon('registry') ?><span class="visually-hidden">Overview</span></a><?php endif; ?>
            <?php if ($can('registry.view')): ?><a class="nav-icon-link <?= $page === 'registry' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($pageUrl('registry'), ENT_QUOTES, 'UTF-8') ?>" aria-label="Applications" data-tooltip="Applications"><?= $navIcon('registry') ?><span class="visually-hidden">Applications</span></a><?php endif; ?>
            <?php if ($can('audit.view')): ?><a class="nav-icon-link <?= $page === 'audit' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($pageUrl('audit'), ENT_QUOTES, 'UTF-8') ?>" aria-label="Audit" data-tooltip="Audit"><?= $navIcon('audit') ?><span class="visually-hidden">Audit</span></a><?php endif; ?>
            <?php if ($can('recap.view')): ?><a class="nav-icon-link <?= $page === 'recap' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($pageUrl('recap'), ENT_QUOTES, 'UTF-8') ?>" aria-label="Rekap" data-tooltip="Rekap"><?= $navIcon('recap') ?><span class="visually-hidden">Rekap</span></a><?php endif; ?>
            <?php if ($can('users.manage')): ?><a class="nav-icon-link <?= $page === 'rbac' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($pageUrl('rbac'), ENT_QUOTES, 'UTF-8') ?>" aria-label="RBAC" data-tooltip="RBAC"><?= $navIcon('rbac') ?><span class="visually-hidden">RBAC</span></a><?php endif; ?>
            <?php if ($can('docs.global') || $can('docs.simontok') || $can('docs.monbis')): ?><details class="nav-menu">
                <summary class="nav-icon-link" aria-label="API Docs" data-tooltip="API Docs"><?= $navIcon('docs') ?><span class="visually-hidden">API Docs</span><span class="submenu-chevron" aria-hidden="true">›</span></summary>
                <div class="nav-menu-popover">
                    <?php if ($can('docs.global')): ?><a href="<?= htmlspecialchars($docsBaseUrl . '/global', ENT_QUOTES, 'UTF-8') ?>">API Global</a><?php endif; ?>
                    <?php if ($can('docs.global')): ?><a href="<?= htmlspecialchars($docsBaseUrl . '/simpeg', ENT_QUOTES, 'UTF-8') ?>">API Simpeg</a><?php endif; ?>
                    <?php if ($can('docs.simontok')): ?><a href="<?= htmlspecialchars($docsBaseUrl . '/simontok', ENT_QUOTES, 'UTF-8') ?>">API Simontok</a><?php endif; ?>
                    <?php if ($can('docs.monbis')): ?><a href="<?= htmlspecialchars($docsBaseUrl . '/monbis', ENT_QUOTES, 'UTF-8') ?>">API Monbis</a><?php endif; ?>
                </div>
            </details><?php endif; ?>
        </nav>
        <div class="topbar-actions">
            <?php $currentAdmin = AdminSession::user(); ?>
            <details class="profile-menu">
                <summary class="profile-trigger" aria-label="Buka detail akun" data-tooltip="Detail akun">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3.2"/><path d="M5.5 19a6.5 6.5 0 0 1 13 0"/></svg>
                    <span class="visually-hidden">Detail akun</span>
                </summary>
                <div class="profile-popover">
                    <p class="profile-eyebrow">AKUN AKTIF</p>
                    <strong><?= htmlspecialchars((string) ($currentAdmin['full_name'] ?? 'User SSO'), ENT_QUOTES, 'UTF-8') ?></strong>
                    <span class="profile-username">@<?= htmlspecialchars((string) ($currentAdmin['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="profile-detail">Role: <?= htmlspecialchars((string) ($currentAdmin['role_code'] ?? 'operator'), ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if ((string) ($currentAdmin['email'] ?? '') !== ''): ?><span class="profile-detail"><?= htmlspecialchars((string) $currentAdmin['email'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                    <div class="profile-divider"></div>
                    <button class="button button-ghost profile-logout" id="logout-button" type="button">Keluar</button>
                </div>
            </details>
        </div>
    </header>

    <main class="page-shell" id="main-content">
        <section class="page-heading">
            <div>
                <p class="eyebrow"><?= htmlspecialchars($pageMeta['eyebrow'], ENT_QUOTES, 'UTF-8') ?></p>
                <h1><?= htmlspecialchars($pageMeta['title'], ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="lede"><?= htmlspecialchars($pageMeta['description'], ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <?php if ($page === 'registry' && $can('registry.manage')): ?>
                <button class="button button-primary" id="create-button" type="button">Tambah aplikasi</button>
            <?php elseif ($page === 'overview' && $can('registry.view')): ?>
                <a class="button button-primary" href="<?= htmlspecialchars($pageUrl('registry'), ENT_QUOTES, 'UTF-8') ?>">Lihat aplikasi <span aria-hidden="true">↗</span></a>
            <?php endif; ?>
        </section>

        <p class="notice" id="global-notice" role="status" hidden></p>

        <section class="metric-row" id="metrics" aria-label="Ringkasan registry"></section>

        <section class="content-grid" id="registry">
            <article class="panel panel-primary">
                <div class="panel-heading panel-heading-with-toolbar">
                    <div>
                        <h2>Registry aplikasi</h2>
                        <p class="panel-description">Token tidak pernah ditampilkan ulang setelah dibuat atau dirotasi.</p>
                    </div>
                    <div class="panel-toolbar">
                        <label class="search-field" for="application-search">
                            <span class="visually-hidden">Cari aplikasi</span>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6"/><path d="m16 16 4 4"/></svg>
                            <input id="application-search" type="search" placeholder="Cari aplikasi…" autocomplete="off">
                        </label>
                        <div class="filter-field application-filter">
                            <span class="visually-hidden">Filter status</span>
                            <select id="application-status-filter" class="visually-hidden-select" tabindex="-1" aria-hidden="true">
                                <option value="all">Semua status</option>
                                <option value="active">Aktif</option>
                                <option value="inactive">Nonaktif</option>
                                <option value="expired">Kedaluwarsa</option>
                            </select>
                            <button class="filter-trigger" id="application-status-trigger" type="button" aria-haspopup="listbox" aria-expanded="false">
                                <span id="application-status-label">Semua status</span>
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 9 5 5 5-5"/></svg>
                            </button>
                            <div class="filter-menu" id="application-status-menu" role="listbox" hidden>
                                <button type="button" role="option" aria-selected="true" data-status="all">Semua status</button>
                                <button type="button" role="option" aria-selected="false" data-status="active">Aktif</button>
                                <button type="button" role="option" aria-selected="false" data-status="inactive">Nonaktif</button>
                                <button type="button" role="option" aria-selected="false" data-status="expired">Kedaluwarsa</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="table-context"><span id="application-result-count">Memuat aplikasi…</span><span class="data-source">Data dari database</span></div>
                <div class="table-wrap">
                    <table class="data-table">
                        <caption class="visually-hidden">Daftar aplikasi terdaftar dan status token</caption>
                        <thead>
                        <tr>
                            <th scope="col">Aplikasi</th>
                            <th scope="col">Status</th>
                            <th scope="col">Berlaku sampai</th>
                            <th scope="col">Hit 7 hari</th>
                            <th scope="col">Hit terakhir</th>
                            <th scope="col"><span class="visually-hidden">Aksi</span></th>
                        </tr>
                        </thead>
                        <tbody id="applications-body">
                        <tr><td colspan="6" class="table-state">Memuat registry aplikasi…</td></tr>
                        </tbody>
                    </table>
                </div>
            </article>

            <aside class="panel activity-panel" id="audit">
                <div class="panel-heading">
                    <div>
                        <h2>Hit terbaru</h2>
                        <p class="panel-description">Endpoint dan status response terakhir.</p>
                    </div>
                </div>
                <div id="recent-hits" class="activity-list" aria-live="polite">
                    <p class="table-state">Memuat aktivitas…</p>
                </div>
            </aside>
        </section>

        <section class="panel trend-panel" id="recap">
            <div class="panel-heading">
                <div>
                    <h2>Rekap hit per hari</h2>
                    <p class="panel-description">Empat belas hari terakhir, termasuk response error yang tercatat.</p>
                </div>
            </div>
            <div id="daily-hits" class="daily-hits" aria-live="polite">
                <p class="table-state">Memuat rekap…</p>
            </div>
        </section>

        <section class="panel users-panel" id="users-panel">
            <div class="panel-heading">
                <div>
                    <h2>Hak akses user web SSO</h2>
                    <p class="panel-description">Centang menu yang boleh dibuka. Permission juga diverifikasi di server, bukan hanya disembunyikan di tampilan.</p>
                </div>
                <button class="button button-primary" id="create-user-button" type="button">Tambah user</button>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <caption class="visually-hidden">Daftar user web SSO</caption>
                    <thead>
                    <tr>
                        <th scope="col">User</th>
                        <th scope="col">Role</th>
                        <th scope="col">Menu</th>
                        <th scope="col">Status</th>
                        <th scope="col">Login terakhir</th>
                        <th scope="col"><span class="visually-hidden">Aksi</span></th>
                    </tr>
                    </thead>
                    <tbody id="users-body">
                    <tr><td colspan="6" class="table-state">Memuat user…</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <?php if (str_starts_with($page, 'docs-')): ?>
            <section class="panel docs-panel" id="api-docs">
                <?php if ($page === 'docs-global'): ?>
                    <div class="panel-heading">
                        <div>
                            <h2>API Global</h2>
                            <p class="panel-description">Kontrak bersama yang dipakai Monbis, Simontok, Notaris, dan aplikasi lain.</p>
                        </div>
                    </div>
                    <div class="docs-content">
                        <div class="doc-callout"><strong>Header aplikasi wajib</strong><code>App: &lt;app_code&gt;</code><code>X-App-Token: &lt;token-aplikasi&gt;</code></div>
                        <p class="field-help">Endpoint yang memerlukan login pegawai juga mengirim <code>Authorization: Bearer &lt;jwt-user&gt;</code>. Token aplikasi tetap memakai <code>X-App-Token</code>.</p>
                        <div class="endpoint-list">
                            <div class="endpoint-row"><span class="method method-post">POST</span><code>/api/auth/login</code><span>Login pegawai dan mendapatkan JWT.</span></div>
                            <div class="endpoint-row"><span class="method method-get">GET</span><code>/api/auth/whoami</code><span>Mengambil data pegawai dari Bearer JWT.</span></div>
                            <div class="endpoint-row"><span class="method method-post">POST</span><code>/api/auth/change-password</code><span>Mengubah password dengan login aktif.</span></div>
                            <div class="endpoint-row"><span class="method method-post">POST</span><code>/api/auth/forgot-password</code><span>Mengirim OTP reset password melalui email.</span></div>
                            <div class="endpoint-row"><span class="method method-post">POST</span><code>/api/auth/verify-otp</code><span>Memverifikasi OTP dan menghasilkan reset token.</span></div>
                            <div class="endpoint-row"><span class="method method-post">POST</span><code>/api/auth/reset-password</code><span>Mengganti password dengan reset token.</span></div>
                            <div class="endpoint-row"><span class="method method-get">GET</span><code>/api/simpeg/pegawai/search</code><span>Pencarian ringkas pegawai berdasarkan ID atau nama.</span></div>
                            <div class="endpoint-row"><span class="method method-get">GET</span><code>/api/ao/list</code><span>Daftar AO berdasarkan tipe dan kode kantor.</span></div>
                        </div>
                        <p class="docs-note">Control Plane ini sudah terdaftar sebagai aplikasi internal <code>sso</code>. Registry <code>monbis</code> tetap dipertahankan untuk client Monbis dan histori hit lama tidak berubah.</p>
                        <p class="docs-note">Dokumentasi endpoint TTD belum ditampilkan karena database TTD masih lokal.</p>
                    </div>
                <?php elseif ($page === 'docs-simontok'): ?>
                    <div class="panel-heading"><div><h2>API Simontok</h2><p class="panel-description">Halaman integrasi aplikasi SIMONTOK.</p></div></div>
                    <div class="docs-content">
                        <div class="doc-callout"><strong>Daftarkan lebih dulu di Registry</strong><span>Gunakan kode aplikasi <code>simontok</code>, lalu simpan token hasil generate ke secret environment aplikasi.</span></div>
                        <h3>Konfigurasi request global</h3>
                        <pre class="credential-snippet">App: simontok
X-App-Token: &lt;SIMONTOK_SSO_TOKEN&gt;</pre>
                        <h3>Endpoint global yang tersedia</h3>
                        <p>Gunakan halaman <a class="inline-link" href="<?= htmlspecialchars($pageUrl('docs-global'), ENT_QUOTES, 'UTF-8') ?>">API Global</a> sebagai referensi endpoint autentikasi, profil, reset password, direktori pegawai, dan AO.</p>
                        <p class="docs-note">Endpoint bisnis khusus SIMONTOK belum didefinisikan di repository SSO ini. Halaman ini menjadi tempat kontrak integrasinya.</p>
                    </div>
                <?php else: ?>
                    <div class="panel-heading"><div><h2>API Monbis</h2><p class="panel-description">Halaman integrasi aplikasi MONBIS.</p></div></div>
                    <div class="docs-content">
                        <div class="doc-callout"><strong>Kode aplikasi terdaftar: <code>monbis</code></strong><span>Token disimpan di secret environment MONBIS dan tidak ditulis di source code.</span></div>
                        <h3>Konfigurasi request global</h3>
                        <pre class="credential-snippet">App: monbis
X-App-Token: &lt;MONBIS_SSO_TOKEN&gt;</pre>
                        <h3>Endpoint global yang tersedia</h3>
                        <p>Gunakan halaman <a class="inline-link" href="<?= htmlspecialchars($pageUrl('docs-global'), ENT_QUOTES, 'UTF-8') ?>">API Global</a> sebagai referensi endpoint autentikasi, profil, reset password, direktori pegawai, dan AO.</p>
                        <p class="docs-note">Jika token Monbis dirotasi, ganti nilai secret di aplikasi Monbis sebelum request berikutnya dikirim.</p>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>

    <dialog class="dialog" id="application-dialog" aria-labelledby="application-dialog-title">
        <form method="dialog" class="dialog-card" id="application-form">
            <div class="dialog-heading">
                <div>
                    <p class="eyebrow">REGISTRY</p>
                    <h2 id="application-dialog-title">Tambah aplikasi</h2>
                </div>
                <button class="icon-button" type="submit" value="cancel" formnovalidate aria-label="Tutup dialog">×</button>
            </div>
            <input type="hidden" id="application-id">
            <div class="form-grid">
                <div class="field field-full">
                    <label for="application-code">Kode aplikasi</label>
                    <input id="application-code" name="app_code" required pattern="[a-z0-9][a-z0-9._-]{0,49}" placeholder="contoh: monbis">
                    <small class="field-help">Huruf kecil, angka, titik, strip, atau underscore.</small>
                    <p class="field-help field-warning" id="application-duplicate-note" role="status" hidden></p>
                </div>
                <div class="field field-full">
                    <label for="application-name">Nama aplikasi</label>
                    <input id="application-name" name="app_name" required maxlength="100" placeholder="Nama yang tampil di registry">
                </div>
                <div class="field">
                    <label for="application-expiry">Berlaku sampai</label>
                    <input id="application-expiry" name="expires_at" type="datetime-local">
                </div>
                <label class="switch-field" for="application-active">
                    <input id="application-active" name="is_active" type="checkbox" checked>
                    <span>
                        <strong>Token aktif</strong>
                        <small>Matikan tanpa menghapus jejak hit.</small>
                    </span>
                </label>
            </div>
            <p class="form-error" id="application-error" role="alert" hidden></p>
            <div class="dialog-actions">
                <button class="button button-ghost" value="cancel" type="submit" formnovalidate>Batal</button>
                <button class="button button-primary" id="save-application" type="button">Simpan</button>
            </div>
        </form>
    </dialog>

    <dialog class="dialog" id="token-dialog" aria-labelledby="token-dialog-title">
        <div class="dialog-card">
            <div class="dialog-heading">
                <div>
                    <p class="eyebrow">ONE-TIME SECRET</p>
                    <h2 id="token-dialog-title">Simpan token ini sekarang</h2>
                </div>
                <button class="icon-button" id="close-token" type="button" aria-label="Tutup dialog">×</button>
            </div>
            <p class="lede">Satu token ini dipakai untuk seluruh endpoint yang diizinkan bagi aplikasi ini. Token mentah tidak akan bisa dilihat lagi setelah dialog ditutup.</p>
            <div class="credential-meta">
                <div>
                    <span>Aplikasi</span>
                    <strong id="token-app-name">-</strong>
                </div>
                <div>
                    <span>Kode aplikasi</span>
                    <code id="token-app-code">-</code>
                </div>
            </div>
            <p class="field-help">Pasang dua header ini pada request dari aplikasi pemanggil:</p>
            <pre class="credential-snippet" id="credential-snippet"></pre>
            <div class="token-box">
                <code id="new-token"></code>
                <button class="button button-secondary" id="copy-token" type="button">Salin token</button>
            </div>
            <p class="form-error" id="token-error" role="alert" hidden></p>
            <div class="dialog-actions">
                <button class="button button-secondary" id="copy-credentials" type="button">Salin header</button>
                <button class="button button-secondary" id="copy-developer" type="button">Salin untuk developer</button>
                <button class="button button-primary" id="done-token" type="button">Saya sudah menyimpannya</button>
            </div>
        </div>
    </dialog>

    <dialog class="dialog" id="user-dialog" aria-labelledby="user-dialog-title">
        <form method="dialog" class="dialog-card" id="user-form">
            <div class="dialog-heading">
                <div>
                    <p class="eyebrow">ACCESS CONTROL</p>
                    <h2 id="user-dialog-title">Tambah user</h2>
                </div>
                <button class="icon-button" type="submit" value="cancel" formnovalidate aria-label="Tutup dialog">×</button>
            </div>
            <input type="hidden" id="user-id">
            <input type="hidden" id="user-employee-id">
            <div class="form-grid">
                <fieldset class="account-type-fieldset field-full">
                    <legend>Jenis user</legend>
                    <div class="account-type-options">
                        <label class="account-type-option">
                            <input type="radio" name="user-source" value="employee" checked>
                            <span><strong>Pegawai internal</strong><small>Cari dari direktori pegawai berdasarkan nama atau ID pegawai.</small></span>
                        </label>
                        <label class="account-type-option">
                            <input type="radio" name="user-source" value="external">
                            <span><strong>User eksternal</strong><small>Isi identitas user secara manual.</small></span>
                        </label>
                    </div>
                </fieldset>
                <div class="field field-full" id="employee-search-panel">
                    <label for="user-employee-query">Cari pegawai</label>
                    <div class="inline-field">
                        <input id="user-employee-query" placeholder="Nama pegawai atau id_peg, contoh 102-119" autocomplete="off">
                        <button class="button button-secondary" id="search-employee-button" type="button">Cari</button>
                    </div>
                    <small class="field-help">Pencarian memakai endpoint direktori pegawai di server; token aplikasi tidak dikirim ke browser admin.</small>
                    <div class="employee-results" id="employee-results" aria-live="polite"></div>
                    <p class="employee-selected" id="employee-selected" role="status" hidden></p>
                </div>
                <div class="field">
                    <label for="user-username">Username</label>
                    <input id="user-username" required pattern="[a-z0-9][a-z0-9._-]{2,79}" autocomplete="username">
                </div>
                <div class="field">
                    <label for="user-role">Role label</label>
                    <input id="user-role" value="operator" maxlength="40" required>
                </div>
                <div class="field field-full">
                    <label for="user-full-name">Nama lengkap</label>
                    <input id="user-full-name" maxlength="120" required>
                </div>
                <div class="field">
                    <label for="user-email">Email</label>
                    <input id="user-email" type="email" maxlength="190" autocomplete="email">
                </div>
                <div class="field">
                    <label for="user-password">Password <small>(minimal 12 karakter)</small></label>
                    <input id="user-password" type="password" minlength="12" autocomplete="new-password">
                </div>
                <label class="switch-field" for="user-active">
                    <input id="user-active" type="checkbox" checked>
                    <span><strong>User aktif</strong><small>Nonaktifkan untuk mencabut akses tanpa menghapus audit.</small></span>
                </label>
            </div>
            <fieldset class="permission-fieldset">
                <legend>Menu &amp; permission RBAC</legend>
                <div class="permission-grid">
                    <?php foreach (AdminRbac::permissions() as $permissionCode => $permissionLabel): ?>
                        <label class="permission-option">
                            <input type="checkbox" data-permission value="<?= htmlspecialchars($permissionCode, ENT_QUOTES, 'UTF-8') ?>">
                            <span><strong><?= htmlspecialchars($permissionCode, ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($permissionLabel, ENT_QUOTES, 'UTF-8') ?></small></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <p class="form-error" id="user-error" role="alert" hidden></p>
            <div class="dialog-actions">
                <button class="button button-ghost" value="cancel" type="submit" formnovalidate>Batal</button>
                <button class="button button-primary" id="save-user" type="button">Simpan user</button>
            </div>
        </form>
    </dialog>
<?php endif; ?>

<script src="app.js" defer></script>
</body>
</html>
