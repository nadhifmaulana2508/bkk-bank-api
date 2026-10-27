<?php
declare(strict_types=1);
require_once __DIR__.'/config/bootstrap.php';
require_once __DIR__.'/middleware/signature.php';
[$user,$profile,$security]=require_signature_ready($pdo);
$flash=pull_flash();
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>TTD Saya</title><link rel="stylesheet" href="<?=e(base_url('assets/app.css'))?>"></head><body><div class="app"><?php require __DIR__.'/partials/header.php';?><main class="page">
<?php if($flash):?><div class="alert alert-<?=e($flash['type'])?>"><?=e($flash['message'])?></div><?php endif;?>
<div class="page-intro"><div><span class="eyebrow">Profil keamanan</span><h1>TTD Saya</h1><p class="muted lead">Kelola gambar tanda tangan dan PIN yang dipakai untuk proses signing.</p></div><span class="status-pill">Aktif</span></div>
<div class="grid"><section class="card hero"><div class="card-header"><div><span class="badge">PREVIEW TTD</span><h2>Tanda tangan tersimpan</h2></div></div><div class="signature-preview"><img class="signature-img" src="<?=e(base_url($profile['signature_path']))?>" alt="Tanda tangan digital <?=e($user['full_name']??'pegawai')?>"></div><div class="actions" style="margin-top:16px"><a class="btn btn-primary" href="<?=e(base_url('documents.php'))?>">Coba TTD Surat</a><a class="btn btn-soft" href="<?=e(base_url('register-signature.php'))?>">Ganti TTD / PIN</a></div></section>
<aside class="card side"><div class="card-header"><div><span class="eyebrow">Status profil</span><h3>TTD aktif</h3></div><span class="status-pill">ACTIVE</span></div><dl class="profile-list"><div><dt>Terdaftar</dt><dd><?=e($profile['registered_at'])?></dd></div><div><dt>Pembaruan terakhir</dt><dd><?=e($profile['updated_at'])?></dd></div><div><dt>Proteksi</dt><dd>PIN 6 digit</dd></div></dl><div class="alert alert-success">TTD siap digunakan. PIN tetap diperlukan pada setiap proses signing.</div></aside></div></main></div></body></html>
