<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_name('BKKAPIDOCSSESSID');
  session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'path' => '/',
  ]);
  session_start();
}

if (($_SESSION['docs_access'] ?? false) !== true) {
  header('Location: index.php');
  exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dokumentasi API BKK</title>
  <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist/swagger-ui.css" />
  <style>
    :root { color-scheme: light; }
    body { margin: 0; background: #f4f7fb; font-family: Inter, "Segoe UI", Arial, sans-serif; }
    .docs-header { padding: 22px clamp(16px, 4vw, 48px); color: #fff; background: linear-gradient(135deg, #062d55, #0d69ad 65%, #20b7c7); }
    .docs-header h1 { margin: 0 0 6px; font-size: clamp(22px, 4vw, 34px); }
    .docs-header p { max-width: 780px; margin: 0; color: rgba(255,255,255,.84); }
    .docs-header code { color: #fff; background: rgba(255,255,255,.15); border-radius: 6px; padding: 2px 6px; }
    .docs-note { width: min(1100px, calc(100% - 32px)); margin: 20px auto 0; padding: 16px 18px; color: #25445d; background: #eaf7fb; border: 1px solid #c8eaf0; border-radius: 12px; }
    .docs-note strong { color: #073b6b; }
    #swagger-ui { width: min(1200px, 100%); margin: 0 auto; }
    .swagger-ui .topbar { display: none; }
    .swagger-ui .wrapper { padding: 0 16px; }
  </style>
</head>
<body>

<header class="docs-header">
  <h1>Dokumentasi REST API BKK</h1>
  <p>Dokumentasi endpoint SSO dan Global TTD. Endpoint <code>/api/v1/ttd/status</code> dan <code>/api/v1/ttd/sign</code> tidak menggunakan Bearer token; kirim <code>pass_endpoint</code> melalui JSON body. Endpoint lama <code>/api/ttd/*</code> tetap tersedia untuk kompatibilitas.</p>
</header>

<div class="docs-note">
  <strong>Integrasi TTD:</strong> gunakan tag <b>Global TTD</b> di bawah untuk mencoba cek status TTD dan signing dokumen. Jika pegawai belum memiliki TTD, API akan mengembalikan kode <code>SIGNATURE_NOT_REGISTERED</code>.
</div>

<div id="swagger-ui"></div>

<script src="https://unpkg.com/swagger-ui-dist/swagger-ui-bundle.js"></script>

<script>
SwaggerUIBundle({
  url: "../swagger.json?v=<?=rawurlencode((string)@filemtime(__DIR__ . '/../swagger.json'))?>",
  dom_id: "#swagger-ui",
  deepLinking: true,
  displayRequestDuration: true,
  filter: true,
  persistAuthorization: true,
  tryItOutEnabled: true
});
</script>

</body>
</html>
