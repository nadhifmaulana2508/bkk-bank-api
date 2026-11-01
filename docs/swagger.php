<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/global/admin/AdminSession.php';
AdminSession::start();

$group = strtolower(trim((string) ($_GET['group'] ?? $_SESSION['docs_group'] ?? 'global')));
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
    exit('Akses dokumentasi ditolak.');
}

$_SESSION['docs_group'] = $group;
$groupMeta = [
    'global' => [
        'label' => 'API Global',
        'description' => 'Kontrak endpoint bersama untuk autentikasi, profil, reset password, SIMPEG, dan AO.',
    ],
    'simpeg' => [
        'label' => 'API Simpeg',
        'description' => 'Belum ada endpoint khusus SIMPEG yang didaftarkan pada Control Plane.',
    ],
    'simontok' => [
        'label' => 'API Simontok',
        'description' => 'Belum ada endpoint khusus SIMONTOK yang didaftarkan pada Control Plane.',
    ],
    'monbis' => [
        'label' => 'API Monbis',
        'description' => 'Belum ada endpoint khusus MONBIS yang didaftarkan pada Control Plane.',
    ],
][$group];

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
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
    <link rel="stylesheet" href="swagger.css?v=20261102-2">
</head>
<body>
<div class="docs-shell">
    <header class="docs-topbar">
        <a class="docs-logo" href="../admin/?page=registry" aria-label="Kembali ke Control Plane">
            <span class="docs-logo-mark" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M4 10h16M6 10v8m4-8v8m4-8v8m4-8v8M3 20h18M5 10l7-6 7 6"/></svg>
            </span>
            <span><strong>Bank API BKK</strong><small>API Documentation</small></span>
        </a>
        <div class="docs-top-actions">
            <span class="docs-session"><i></i> Admin session aktif</span>
            <a class="docs-back" href="../admin/?page=registry" data-back-link><span aria-hidden="true">&larr;</span> Kembali</a>
        </div>
    </header>

    <section class="docs-hero <?= $group === 'global' ? '' : 'docs-hero-empty' ?>">
        <div>
            <p class="docs-kicker">SSO / <?= strtoupper(htmlspecialchars($group, ENT_QUOTES, 'UTF-8')) ?></p>
            <h1><?= htmlspecialchars($groupMeta['label'], ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="docs-lede"><?= htmlspecialchars($groupMeta['description'], ENT_QUOTES, 'UTF-8') ?> Dokumentasi ini dibuat khusus untuk testing kontrak endpoint tanpa menampilkan modul TTD.</p>
            <div class="docs-hero-meta">
                <span class="docs-chip" data-endpoint-count>Memuat endpoint...</span>
                <span class="docs-meta-text">Session admin dipakai bersama Control Plane</span>
            </div>
            <nav class="docs-group-nav" aria-label="Kelompok dokumentasi">
                <?php if (AdminSession::hasPermission('docs.global')): ?><a class="<?= $group === 'global' ? 'is-active' : '' ?>" href="?group=global">Global</a><?php endif; ?>
                <?php if (AdminSession::hasPermission('docs.global')): ?><a class="<?= $group === 'simpeg' ? 'is-active' : '' ?>" href="?group=simpeg">Simpeg</a><?php endif; ?>
                <?php if (AdminSession::hasPermission('docs.simontok')): ?><a class="<?= $group === 'simontok' ? 'is-active' : '' ?>" href="?group=simontok">Simontok</a><?php endif; ?>
                <?php if (AdminSession::hasPermission('docs.monbis')): ?><a class="<?= $group === 'monbis' ? 'is-active' : '' ?>" href="?group=monbis">Monbis</a><?php endif; ?>
            </nav>
        </div>
        <?php if ($group === 'global'): ?><div class="docs-target-card">
            <span class="docs-card-label">TARGET APLIKASI</span>
            <strong><?= htmlspecialchars($group === 'global' ? 'Pilih saat testing' : strtoupper($group), ENT_QUOTES, 'UTF-8') ?></strong>
            <small>Header <code>App</code> mengikuti aplikasi yang dipilih.</small>
        </div><?php endif; ?>
    </section>

    <?php if ($group !== 'global'): ?>
    <main class="docs-empty-page">
        <div class="empty-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M7 3h7l4 4v14H7z"/><path d="M14 3v5h5M10 13h5M10 17h5"/></svg>
        </div>
        <p class="section-kicker">BELUM TERSEDIA</p>
        <h2>Dokumentasi <?= htmlspecialchars($groupMeta['label'], ENT_QUOTES, 'UTF-8') ?> belum dibuat</h2>
        <p>Tab ini sengaja dikosongkan sampai endpoint khusus <?= htmlspecialchars(strtoupper($group), ENT_QUOTES, 'UTF-8') ?> resmi tersedia. API Global tetap dapat dibuka dari tab Global.</p>
        <a class="empty-link" href="?group=global">Buka API Global <span aria-hidden="true">&rarr;</span></a>
    </main>
    <?php else: ?>
    <div class="docs-layout">
        <aside class="docs-sidebar" aria-label="Daftar endpoint">
            <div class="sidebar-heading">
                <div><strong>Endpoint</strong><span data-endpoint-count>0 endpoint</span></div>
            </div>
            <label class="docs-search">
                <span aria-hidden="true">&#8981;</span>
                <input type="search" placeholder="Cari endpoint..." data-endpoint-search>
            </label>
            <nav class="endpoint-nav" data-endpoint-nav></nav>
        </aside>

        <main class="docs-main">
            <section class="auth-card" aria-labelledby="auth-title">
                <div class="auth-card-heading">
                    <div>
                        <p class="section-kicker">REQUEST SETUP</p>
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
                <div><p class="section-kicker">API REFERENCE</p><h2>Endpoint yang tersedia</h2></div>
                <label class="server-picker"><span>Server</span><select data-server-select aria-label="Pilih server API"></select></label>
            </div>

            <section class="docs-content" data-docs-content>
                <div class="docs-loading"><span class="loading-dot"></span> Memuat kontrak API...</div>
            </section>
        </main>
    </div>
    <?php endif; ?>
</div>

<?php if ($group === 'global'): ?><script src="swagger-init.js?v=20261102-2" defer></script><?php endif; ?>
</body>
</html>
