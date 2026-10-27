<?php
declare(strict_types=1);
require_once __DIR__.'/config/bootstrap.php';

$token=(string)($_GET['token']??'');
$row=null;
if(preg_match('/^[a-f0-9]{64}$/',$token)) {
    $st=$pdo->prepare("SELECT d.*,COALESCE(d.signature_path,p.signature_path) AS signature_path FROM signature_documents d
                       LEFT JOIN signature_profiles p ON p.id_peg=d.id_peg
                       WHERE d.verification_token=:t LIMIT 1");
    $st->execute(['t'=>$token]);$row=$st->fetch();
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Verifikasi TTD</title><link rel="stylesheet" href="<?=e(base_url('assets/app.css'))?>"></head><body><main class="public-shell"><section class="card public-card"><div class="brand"><span class="brandmark"><img src="<?=e(base_url('img/logo.png'))?>" alt="Logo BKK"></span><span class="brand-name">VERIFIKASI TTD IN BKK<small>Audit dokumen internal</small></span></div>
<?php if(!$row):?><div class="alert alert-error"><b>Token tidak valid / dokumen tidak ditemukan.</b></div><?php else:?><div class="alert alert-success"><b>Dokumen tercatat pada TTD IN BKK</b></div><div class="page-intro" style="margin-top:22px"><div><span class="eyebrow">Dokumen terverifikasi</span><h1><?=e($row['document_name'])?></h1></div><span class="status-pill">Valid</span></div><div class="verified-signature"><div class="muted">Tanda tangan digital pegawai</div><?php if(!empty($row['signature_path'])):?><img class="signature-img" src="<?=e(base_url($row['signature_path']))?>" alt="Tanda tangan digital pegawai"><?php else:?><p class="muted">Gambar TTD tidak tersedia.</p><?php endif;?></div><dl class="profile-list"><div><dt>ID Dokumen</dt><dd><?=e($row['document_id'])?></dd></div><div><dt>ID Pegawai</dt><dd><?=e($row['id_peg'])?></dd></div><div><dt>Aplikasi</dt><dd><?=e($row['application'])?></dd></div><div><dt>Waktu TTD</dt><dd><?=e($row['signed_at'])?></dd></div></dl><p style="margin-top:20px"><b>SHA-256</b></p><div class="code"><?=e($row['document_hash'])?></div><p class="field-help">QR ini menampilkan hasil verifikasi audit internal, bukan klaim TTE tersertifikasi PSrE.</p><?php endif;?></section></main></body></html>
