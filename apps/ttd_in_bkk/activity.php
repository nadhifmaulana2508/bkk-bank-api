<?php
declare(strict_types=1);
require_once __DIR__.'/config/bootstrap.php';
require_once __DIR__.'/middleware/signature.php';
[$user,$profile,$security]=require_signature_ready($pdo);
$st=$pdo->prepare("SELECT * FROM signature_logs WHERE id_peg=:id ORDER BY id DESC LIMIT 100");
$st->execute(['id'=>$user['employee_id']]);$rows=$st->fetchAll();
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Aktivitas - TTD IN BKK</title><link rel="stylesheet" href="<?=e(base_url('assets/app.css'))?>"></head><body><div class="app"><?php require __DIR__.'/partials/header.php';?><main class="page"><div class="page-intro"><div><span class="eyebrow">Keamanan dan kepatuhan</span><h1>Aktivitas TTD</h1><p class="muted lead">Riwayat login, registrasi TTD, percobaan PIN, dan signing yang tercatat di server.</p></div><span class="badge"><?=count($rows)?> aktivitas</span></div><section class="card"><div class="card-header"><div><span class="eyebrow">Audit trail</span><h3>Riwayat aktivitas akun</h3></div></div><div class="table-wrap"><table class="table"><thead><tr><th>Waktu</th><th>Aksi</th><th>Status</th><th>Dokumen</th><th>IP</th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="5" class="muted">Belum ada aktivitas untuk akun ini.</td></tr><?php endif;?><?php foreach($rows as $r):?><tr><td><?=e($r['created_at'])?></td><td><?=e($r['action'])?></td><td><?=e($r['status'])?></td><td><?=e($r['document_id']??'-')?></td><td><?=e($r['ip_address']??'-')?></td></tr><?php endforeach;?></tbody></table></div></section></main></div></body></html>
