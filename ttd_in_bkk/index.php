<?php
declare(strict_types=1);
require_once __DIR__.'/config/bootstrap.php';

if (current_user()) redirect_to('dashboard.php');

$error=null;
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!csrf_validate($_POST['_token']??null)) {
        $error='Sesi formulir tidak valid.';
    } else {
        $idPeg=trim((string)($_POST['id_peg']??''));
        $password=(string)($_POST['password']??'');
        if ($idPeg===''||$password==='') {
            $error='ID Pegawai dan password wajib diisi.';
        } else {
            try {
                $login=sso_login($idPeg,$password);
                $body=$login['body'];
                $token=sso_extract_token($body);
                if (!$login['ok']||!$token) {
                    $error=$body['message']??'Login SSO gagal.';
                } else {
                    $who=sso_whoami($token);
                    $profile=sso_extract_profile($who['body']);
                    if (!$who['ok']||$profile===null) {
                        $error=$who['body']['message']??'Profil SSO tidak dapat diambil.';
                    } else {
                        session_regenerate_id(true);
                        csrf_regenerate();
                        $_SESSION['sso_token']=$token;
                        $_SESSION['user']=$profile;
                        $employeeId=(string)$profile['employee_id'];
                        log_signature($pdo,$employeeId,'LOGIN','SUCCESS',null,'TTD IN BKK');
                        $sig=get_signature_profile($pdo,$employeeId);
                        $sec=get_signature_security($pdo,$employeeId);
                        redirect_to(($sig&&$sec&&$sig['status']==='ACTIVE')?'dashboard.php':'register-signature.php');
                    }
                }
            } catch(Throwable $e) {
                error_log('[TTD IN BKK] Login error: ' . $e->getMessage());
                $error='Login belum dapat diproses. Periksa koneksi SSO dan database.';
            }
        }
    }
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>TTD IN BKK</title><link rel="stylesheet" href="<?=e(base_url('assets/app.css'))?>"></head><body>
<div class="login">
  <section class="login-left">
    <div class="brand login-brand">
      <span class="brandmark"><img src="<?=e(base_url('img/logo.png'))?>" alt="Logo BKK"></span>
      <span>PT BPR BKK JATENG</span>
    </div>
    <div class="login-copy">
      <span class="badge" style="background:rgba(255,255,255,.14);color:#fff;border-color:rgba(255,255,255,.25)">TTD DIGITAL TERPUSAT</span>
      <h1>TTD IN BKK</h1>
      <p>Satu identitas SSO, satu profil tanda tangan, dan satu PIN untuk penggunaan TTD lintas aplikasi internal.</p>
      <ul class="login-features">
        <li>Registrasi TTD dengan gambar atau upload file</li>
        <li>PIN pribadi untuk setiap proses signing</li>
        <li>QR verifikasi dan audit trail otomatis</li>
      </ul>
    </div>
  </section>
  <section class="login-right">
    <form class="card login-form-card" method="post">
      <div class="brand">
        <span class="brandmark"><img src="<?=e(base_url('img/logo.png'))?>" alt="Logo BKK"></span>
        <span class="brand-name">TTD IN BKK<small>Login menggunakan BKK SSO 2026</small></span>
      </div>
      <span class="eyebrow">Akses pegawai</span>
      <h2>Login Pegawai</h2>
      <p class="muted">Masuk menggunakan akun BKK SSO Anda untuk mengelola tanda tangan digital.</p>
      <?php if($error):?><div class="alert alert-error"><?=e($error)?></div><?php endif;?>
      <?=csrf_input()?>
      <label for="id_peg">ID Pegawai</label><input class="input" id="id_peg" name="id_peg" autocomplete="username" required autofocus placeholder="Masukkan ID pegawai">
      <label for="password">Password</label><input class="input" id="password" type="password" name="password" autocomplete="current-password" required placeholder="Masukkan password">
      <button class="btn btn-primary w100" style="margin-top:22px">Masuk melalui SSO</button>
      <p class="field-help">Password hanya diteruskan ke BKK SSO dan tidak disimpan di database TTD IN BKK.</p>
    </form>
  </section>
</div></body></html>
