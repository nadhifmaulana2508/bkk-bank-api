<?php
declare(strict_types=1);
require_once __DIR__.'/config/bootstrap.php';
require_once __DIR__.'/middleware/signature.php';

[$user,$profile,$security]=require_signature_ready($pdo);
$flash=pull_flash();
$st=$pdo->prepare("SELECT COUNT(*) total,MAX(signed_at) last_signed FROM signature_documents WHERE id_peg=:id");
$st->execute(['id'=>$user['employee_id']]);$stat=$st->fetch()?:['total'=>0,'last_signed'=>null];
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dashboard - TTD IN BKK</title><link rel="stylesheet" href="<?=e(base_url('assets/app.css'))?>"></head><body><div class="app"><?php require __DIR__.'/partials/header.php';?>
<main class="page"><?php if($flash):?><div class="alert alert-<?=e($flash['type'])?>"><?=e($flash['message'])?></div><?php endif;?>
<div class="page-intro"><div><span class="eyebrow">Ruang kerja TTD digital</span><h1>Halo, <?=e($user['full_name']??'Pegawai')?>.</h1><p class="muted lead">Profil TTD Anda siap digunakan untuk menandatangani surat internal dengan PIN pribadi.</p></div><span class="status-pill">TTD aktif</span></div>
<div class="grid"><section class="card hero hero--welcome"><div class="card-header"><div><span class="badge">SIAP DIGUNAKAN</span><h2>Mulai tanda tangani surat</h2></div></div><p class="muted">Gunakan ruang coba untuk melihat alur signing, hasil QR verifikasi, SHA-256, dan catatan audit sebelum diterapkan pada surat internal.</p>
<div class="kpis"><div class="kpi"><span class="muted">Status TTD</span><strong>ACTIVE</strong></div><div class="kpi"><span class="muted">Dokumen Ditandatangani</span><strong><?= (int)$stat['total'] ?></strong></div><div class="kpi"><span class="muted">Terakhir TTD</span><strong style="font-size:14px"><?=e($stat['last_signed']?:'Belum ada')?></strong></div></div>
<div class="actions" style="margin-top:22px"><a class="btn btn-primary" href="<?=e(base_url('documents.php'))?>">Coba TTD Surat</a><a class="btn btn-soft" href="<?=e(base_url('signature.php'))?>">Lihat TTD Saya</a></div></section>
<aside class="card side"><div class="card-header"><div><span class="eyebrow">Identitas SSO</span><h3>Profil Pegawai</h3></div><span class="badge">TERHUBUNG</span></div><dl class="profile-list"><div><dt>Nama lengkap</dt><dd><?=e($user['full_name']??'-')?></dd></div><div><dt>ID Pegawai</dt><dd><?=e($user['employee_id']??'-')?></dd></div><div><dt>Jabatan</dt><dd><?=e($user['job_position']??'-')?></dd></div><div><dt>Unit kerja</dt><dd><?=e($user['unit_kerja']??'-')?></dd></div><div><dt>Kantor / Cabang</dt><dd><?=e($user['branch_name']??'Kantor Pusat')?></dd></div></dl></aside>
<section class="card full card--soft"><div class="card-header"><div><span class="eyebrow">Panduan singkat</span><h3>Alur penggunaan</h3></div></div><p class="muted">TTD Anda tersimpan di database dan setiap proses signing membutuhkan PIN. QR Code dapat dipindai untuk melihat informasi verifikasi dokumen.</p><div class="actions"><a class="btn btn-primary" href="<?=e(base_url('documents.php'))?>">Buka ruang coba TTD</a><span class="badge">PIN diminta saat signing</span><span class="badge">QR verifikasi otomatis</span></div></section>
</div></main></div></body></html>
