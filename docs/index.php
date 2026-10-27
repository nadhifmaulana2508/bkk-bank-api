<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/env.php';

session_name('BKKAPIDOCSSESSID');
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Strict',
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();

function docsAccessCode(): string
{
    return trim((string) app_env_value('API_DOCS_ACCESS_CODE', ''));
}

$accessCode = docsAccessCode();

if (($_SESSION['docs_access'] ?? false) === true) {
    require __DIR__ . '/swagger.php';
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = (string)($_POST['code'] ?? '');
    if ($accessCode !== '' && hash_equals($accessCode, $code)) {
        session_regenerate_id(true);
        $_SESSION['docs_access'] = true;
        header('Location: index.php');
        exit;
    }
    $error = $accessCode === ''
        ? 'Kode akses dokumentasi belum dikonfigurasi di server.'
        : 'Kode akses tidak valid.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Akses Dokumentasi API BKK</title>
    <style>
        :root { --navy:#062d55; --blue:#0d69ad; --cyan:#20b7c7; --ink:#172537; --muted:#6b7b8f; }
        * { box-sizing: border-box; }
        body { min-height:100vh; margin:0; display:grid; place-items:center; padding:20px; color:var(--ink); background:linear-gradient(145deg,#062d55,#0d69ad 60%,#20b7c7); font-family:Inter,"Segoe UI",Arial,sans-serif; }
        .box { width:min(460px,100%); padding:32px; background:#fff; border:1px solid rgba(255,255,255,.55); border-radius:22px; box-shadow:0 25px 70px rgba(0,0,0,.22); text-align:center; }
        .brand { display:flex; align-items:center; justify-content:center; gap:12px; color:var(--navy); font-weight:900; font-size:18px; }
        .brandmark { width:50px; height:50px; display:grid; place-items:center; color:#fff; background:linear-gradient(135deg,var(--navy),var(--blue)); border-radius:15px; box-shadow:0 8px 18px rgba(7,59,107,.18); font-size:14px; }
        h1 { margin:26px 0 8px; font-size:26px; }
        p { color:var(--muted); line-height:1.55; }
        form { margin-top:22px; }
        input { width:100%; padding:13px 14px; border:1px solid #d5e1ec; border-radius:12px; outline:none; font:inherit; }
        input:focus { border-color:var(--cyan); box-shadow:0 0 0 4px #e3f7fa; }
        button { width:100%; padding:13px 20px; color:#fff; background:linear-gradient(135deg,var(--navy),var(--blue)); border:0; border-radius:12px; cursor:pointer; font:inherit; font-weight:800; }
        .error { padding:10px 12px; color:#922637; background:#ffedf0; border:1px solid #ffd6dc; border-radius:10px; }
        small { display:block; margin-top:18px; color:var(--muted); }
    </style>
</head>
<body>
<div class="box">
    <div class="brand"><span class="brandmark">API</span><span>REST API BKK</span></div>
    <h1>Akses Dokumentasi API</h1>
    <p>Masukkan kode akses untuk membuka dokumentasi backend API BKK.</p>

    <?php if ($error !== null): ?>
        <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="password" name="code" placeholder="Masukkan kode akses" autocomplete="current-password" required>
        <br><br>
        <button type="submit">Masuk</button>
    </form>
    <small>Dokumentasi internal. Jangan bagikan kode akses.</small>
</div>
</body>
</html>
