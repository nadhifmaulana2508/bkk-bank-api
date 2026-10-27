<?php
declare(strict_types=1);
require_once __DIR__.'/config/bootstrap.php';
require_once __DIR__.'/middleware/auth.php';

$user=require_auth();
$idPeg=(string)$user['employee_id'];
$error=null;

if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!csrf_validate($_POST['_token']??null)) {
        $error='Sesi formulir tidak valid.';
    } else {
        $pin=(string)($_POST['pin']??'');
        $pin2=(string)($_POST['pin_confirmation']??'');
        $data=(string)($_POST['signature_data']??'');
        if (!preg_match('/^\d{6}$/',$pin)) $error='PIN harus 6 digit angka.';
        elseif ($pin!==$pin2) $error='Konfirmasi PIN tidak sama.';
        elseif ($data==='') $error='TTD belum dibuat atau diupload.';
        else {
            try {
                $path=store_signature_image($data,$idPeg);
                $pdo->beginTransaction();
                $pdo->prepare("INSERT INTO signature_profiles(id_peg,signature_path,status) VALUES(:id,:path,'ACTIVE')
                    ON DUPLICATE KEY UPDATE signature_path=VALUES(signature_path),status='ACTIVE',updated_at=CURRENT_TIMESTAMP")
                    ->execute(['id'=>$idPeg,'path'=>$path]);
                $pdo->prepare("INSERT INTO signature_security(id_peg,pin_hash,failed_attempt,locked_until) VALUES(:id,:pin,0,NULL)
                    ON DUPLICATE KEY UPDATE pin_hash=VALUES(pin_hash),failed_attempt=0,locked_until=NULL,updated_at=CURRENT_TIMESTAMP")
                    ->execute(['id'=>$idPeg,'pin'=>password_hash($pin,PASSWORD_DEFAULT)]);
                log_signature($pdo,$idPeg,'REGISTER','SUCCESS');
                $pdo->commit();
                flash('success','TTD dan PIN berhasil diaktifkan.');
                redirect_to('dashboard.php');
            } catch(Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('[TTD IN BKK] Signature registration error: ' . $e->getMessage());
                $error='TTD belum dapat disimpan. Silakan coba lagi.';
            }
        }
    }
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Registrasi TTD</title><link rel="stylesheet" href="<?=e(base_url('assets/app.css'))?>"></head><body><div class="app"><?php require __DIR__.'/partials/header.php';?>
<main class="page"><div class="page-intro"><div><span class="eyebrow">Pengaturan keamanan</span><h1>Registrasi TTD</h1><p class="muted lead">Buat profil tanda tangan dan PIN pribadi sebelum menggunakan fitur signing.</p></div><span class="badge">LANGKAH 1 DARI 1</span></div><div class="grid">
<section class="card hero"><div class="card-header"><div><span class="badge">TTD DIGITAL</span><h2>Buat TTD Digital Anda</h2></div></div><p class="muted">Gambar langsung atau upload TTD, lalu buat PIN 6 digit. Setelah aktif, menu <b>Coba TTD Surat</b> tersedia di dashboard.</p>
<?php if($error):?><div class="alert alert-error"><?=e($error)?></div><?php endif;?>
<form method="post" id="form"><?=csrf_input()?>
<label for="pad">Gambar tanda tangan</label><div class="signature-stage"><canvas id="pad" width="1000" height="400" aria-label="Area gambar tanda tangan"></canvas></div>
<p class="field-help">Gunakan mouse, jari, atau stylus. Tinta akan disimpan sebagai gambar dengan latar putih dan pena hitam.</p>
<div class="actions" style="margin-top:12px"><button type="button" class="btn btn-soft" id="clear">Hapus gambar</button><label class="btn btn-soft" style="margin:0;cursor:pointer">Upload PNG/JPG<input id="upload" type="file" accept="image/png,image/jpeg" hidden></label></div>
<input type="hidden" name="signature_data" id="signatureData">
<div class="two" style="margin-top:18px"><div><label for="pin">PIN TTD 6 digit</label><input class="input pin" id="pin" type="password" inputmode="numeric" maxlength="6" name="pin" autocomplete="new-password" required placeholder="••••••"><p class="field-help">PIN ini wajib dimasukkan setiap kali menandatangani surat.</p></div><div><label for="pin_confirmation">Konfirmasi PIN</label><input class="input pin" id="pin_confirmation" type="password" inputmode="numeric" maxlength="6" name="pin_confirmation" autocomplete="new-password" required placeholder="••••••"></div></div>
<button class="btn btn-primary" style="margin-top:18px" id="saveButton">Simpan & Aktifkan TTD</button></form></section>
<aside class="card side"><div class="card-header"><div><span class="eyebrow">Data terverifikasi</span><h3>Identitas dari SSO</h3></div><span class="badge">SSO</span></div><dl class="profile-list"><div><dt>Nama lengkap</dt><dd><?=e($user['full_name']??'-')?></dd></div><div><dt>ID Pegawai</dt><dd><?=e($user['employee_id']??'-')?></dd></div><div><dt>Jabatan</dt><dd><?=e($user['job_position']??'-')?></dd></div><div><dt>Unit kerja</dt><dd><?=e($user['unit_kerja']??'-')?></dd></div></dl><div class="alert alert-warning">PIN tidak pernah disimpan sebagai angka asli. Sistem hanya menyimpan hash PIN.</div></aside>
</div></main></div>
<script>
const canvas = document.getElementById('pad');
const ctx = canvas.getContext('2d', { alpha: true });
const form = document.getElementById('form');
const clearButton = document.getElementById('clear');
const upload = document.getElementById('upload');
const signatureData = document.getElementById('signatureData');
const saveButton = document.getElementById('saveButton');
const lineColor = '#111111';
let drawing = false;
let hasInk = false;
let last = null;

ctx.lineWidth = 8;
ctx.lineCap = 'round';
ctx.lineJoin = 'round';
ctx.strokeStyle = lineColor;

function point(event) {
  const rect = canvas.getBoundingClientRect();
  return {
    x: (event.clientX - rect.left) * canvas.width / rect.width,
    y: (event.clientY - rect.top) * canvas.height / rect.height
  };
}

canvas.addEventListener('pointerdown', (event) => {
  drawing = true;
  hasInk = true;
  last = point(event);
  canvas.setPointerCapture?.(event.pointerId);
  event.preventDefault();
});
canvas.addEventListener('pointermove', (event) => {
  if (!drawing) return;
  const next = point(event);
  ctx.beginPath();
  ctx.moveTo(last.x, last.y);
  ctx.lineTo(next.x, next.y);
  ctx.stroke();
  last = next;
  event.preventDefault();
});
['pointerup', 'pointercancel', 'pointerleave'].forEach((name) => {
  canvas.addEventListener(name, () => { drawing = false; last = null; });
});

clearButton.addEventListener('click', () => {
  ctx.clearRect(0, 0, canvas.width, canvas.height);
  hasInk = false;
  signatureData.value = '';
  upload.value = '';
});

upload.addEventListener('change', (event) => {
  const file = event.target.files?.[0];
  if (!file) return;
  if (!['image/png', 'image/jpeg'].includes(file.type) || file.size > 3 * 1024 * 1024) {
    alert('File harus PNG/JPG dan maksimal 3 MB.');
    upload.value = '';
    return;
  }
  const reader = new FileReader();
  reader.onload = () => {
    const image = new Image();
    image.onload = () => {
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      const scale = Math.min(canvas.width / image.width, canvas.height / image.height) * .86;
      const width = image.width * scale;
      const height = image.height * scale;
      ctx.drawImage(image, (canvas.width - width) / 2, (canvas.height - height) / 2, width, height);
      hasInk = true;
    };
    image.src = reader.result;
  };
  reader.readAsDataURL(file);
});

form.addEventListener('submit', (event) => {
  if (!hasInk) {
    event.preventDefault();
    alert('Silakan gambar atau upload tanda tangan terlebih dahulu.');
    return;
  }
  signatureData.value = canvas.toDataURL('image/png');
  saveButton.disabled = true;
  saveButton.textContent = 'Menyimpan TTD...';
});
</script></body></html>
