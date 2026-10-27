<?php
declare(strict_types=1);
require_once __DIR__.'/config/bootstrap.php';
require_once __DIR__.'/middleware/signature.php';
[$user,$profile,$security]=require_signature_ready($pdo);
$st=$pdo->prepare("SELECT * FROM signature_documents WHERE id_peg=:id ORDER BY id DESC LIMIT 20");
$st->execute(['id'=>$user['employee_id']]);$docs=$st->fetchAll();
$docId='DEMO-'.date('Ymd-His');
$letterPlace=trim((string)($user['branch_name']??''));
if($letterPlace===''||$letterPlace==='-')$letterPlace='Kantor Pusat';
$letterOffice=$letterPlace;
$letterRole=trim((string)($user['job_position']??'-'));
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Coba TTD Surat - TTD IN BKK</title><link rel="stylesheet" href="<?=e(base_url('assets/app.css'))?>"><script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" defer></script></head><body><div class="app"><?php require __DIR__.'/partials/header.php';?><main class="page">
<div class="page-intro"><div><span class="eyebrow">Ruang simulasi signing</span><h1>Coba TTD Surat</h1><p class="muted lead">Uji proses penandatanganan surat dengan PIN pribadi, QR verifikasi, dan output print yang rapi.</p></div><div class="actions"><button class="btn btn-soft" id="printLetter" type="button">Print Surat</button><button class="btn btn-primary" id="openSign" type="button">Tanda Tangani</button></div></div>
<div class="grid"><section class="card hero"><div class="card-header"><div><span class="badge">DOKUMEN SIMULASI</span><h2>Nota Persetujuan Internal</h2></div><span class="status-pill">Belum ditandatangani</span></div>
<div class="document-paper"><div class="document-meta"><div><span>Nomor dokumen</span><b><?=e($docId)?></b></div><div><span>Penandatangan</span><b><?=e($user['full_name']??'-')?></b></div></div><div class="document-callout">Dokumen dummy ini digunakan untuk mencoba otorisasi PIN, pencatatan SHA-256, audit trail, dan QR verifikasi tanpa mengubah dokumen kantor.</div></div>
<div id="result" class="result" style="display:none"><div class="result-box"><div><div class="eyebrow">Hasil signing</div><h3>TTD Pegawai</h3><img id="sigImg" class="signature-img" alt="Hasil tanda tangan"></div></div><div class="result-box qr-result-box"><div><div class="eyebrow">Verifikasi</div><h3>QR TTD Digital</h3><div class="qr-holder"><div id="qr"></div><div class="qr-logo"><img src="<?=e(base_url('img/logo.png'))?>" alt="Logo BKK"></div></div></div><a id="verifyLink" class="qr-verify-link" target="_blank" rel="noopener" aria-hidden="true"></a></div></div><div id="hashBox" class="code" style="display:none;margin-top:12px"></div><p id="signedAt" class="muted" style="display:none;font-size:12px"></p></section>
<aside class="card side"><div class="card-header"><div><span class="eyebrow">Proses aman</span><h3>Alur signing</h3></div><span class="badge">PIN</span></div><ol class="workflow-list"><li>Masukkan PIN TTD 6 digit.</li><li>Server memvalidasi PIN dan status keamanan.</li><li>Sistem membuat SHA-256 serta token verifikasi.</li><li>TTD, QR, dan audit tersimpan otomatis.</li></ol><div class="alert alert-success">TTD aktif dan siap digunakan.</div></aside>
<section class="card full"><div class="card-header"><div><span class="eyebrow">Audit trail</span><h3>Riwayat Dokumen</h3></div><span class="badge"><?=count($docs)?> dokumen</span></div><div class="table-wrap"><table class="table"><thead><tr><th>Dokumen</th><th>Aplikasi</th><th>Waktu</th></tr></thead><tbody><?php if(!$docs):?><tr><td colspan="3" class="muted">Belum ada dokumen.</td></tr><?php endif;?><?php foreach($docs as $d):?><tr><td><?=e($d['document_name'])?><br><small class="muted"><?=e($d['document_id'])?></small></td><td><?=e($d['application'])?></td><td><?=e($d['signed_at'])?></td></tr><?php endforeach;?></tbody></table></div></section></div>
<section class="letter-preview" id="letterPreview" aria-label="Preview surat untuk dicetak"><div class="letter-header"><img src="<?=e(base_url('img/logo.png'))?>" alt="Logo BKK Jawa Tengah" class="letter-logo"><div><h2 id="letterOfficeHeader"><?=e($letterOffice)?></h2><p>NOTA PERSETUJUAN INTERNAL</p><small>Dokumen simulasi TTD Digital IN BKK</small></div></div><div class="letter-rule"></div><div class="letter-meta"><span><b>Nomor</b>: <?=e($docId)?></span><span><b>Tanggal</b>: <?=e(date('d-m-Y'))?></span></div><h3 class="letter-subject">NOTA PERSETUJUAN INTERNAL</h3><p>Dengan hormat,</p><p>Dokumen ini dibuat sebagai contoh bentuk surat internal yang akan ditandatangani melalui aplikasi TTD IN BKK. Isi surat dapat diganti sesuai kebutuhan aplikasi internal.</p><p>Dengan ini kami menyatakan bahwa dokumen telah melewati proses verifikasi PIN dan dapat diverifikasi melalui QR Code di bawah ini.</p><table class="letter-table"><tr><td>Penandatangan</td><td><?=e($user['full_name']??'-')?></td></tr><tr><td>Jabatan</td><td><?=e($user['job_position']??'-')?></td></tr><tr><td>Status</td><td id="letterStatus">Belum ditandatangani</td></tr></table><div class="letter-footer"><div class="letter-signature-area"><p><span id="letterPlace"><?=e($letterPlace)?></span>, <?=e(date('d F Y'))?></p><p><b id="letterOfficeTop"><?=e($letterOffice)?></b></p><div class="letter-qr-area"><div class="letter-qr-holder"><img id="letterQrImg" alt="QR verifikasi dokumen" style="display:none"><span class="letter-qr-logo"><img src="<?=e(base_url('img/logo.png'))?>" alt="Logo BKK"></span></div><small id="letterQrText">QR muncul setelah dokumen ditandatangani</small></div><b><?=e($user['full_name']??'-')?></b><small><?=e($letterRole)?></small><small class="letter-office" id="letterOfficeBottom"><?=e($letterOffice)?></small></div></div><div id="letterHash" class="code letter-hash" style="display:none"></div><p class="letter-note">Dokumen ini merupakan contoh output audit internal, bukan klaim TTE tersertifikasi PSrE. Lokasi hanya digunakan untuk tampilan print.</p></section></main></div>
<div class="modal" id="modal" role="dialog" aria-modal="true" aria-labelledby="pinTitle"><div class="modal-card"><div class="modal-header"><h3 id="pinTitle">Verifikasi PIN TTD</h3><button class="modal-close" id="closeModal" type="button" aria-label="Tutup">×</button></div><p class="muted">Masukkan PIN 6 digit untuk menyetujui dokumen ini.</p><label for="pin">PIN TTD</label><input id="pin" class="input pin" type="password" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="••••••"><div id="msg" aria-live="polite"></div><div class="actions" style="margin-top:14px"><button class="btn btn-soft" id="cancel" type="button">Batal</button><button class="btn btn-primary" id="doSign" type="button">Verifikasi & TTD</button></div></div></div>
<script>
const modal = document.getElementById('modal');
const pin = document.getElementById('pin');
const message = document.getElementById('msg');
const openButton = document.getElementById('openSign');
const printButton = document.getElementById('printLetter');
const cancelButton = document.getElementById('cancel');
const closeButton = document.getElementById('closeModal');
const signButton = document.getElementById('doSign');
const result = document.getElementById('result');
const signatureImage = document.getElementById('sigImg');
const hashBox = document.getElementById('hashBox');
const qrBox = document.getElementById('qr');
const verifyLink = document.getElementById('verifyLink');
const signedAt = document.getElementById('signedAt');
const letterQrImg = document.getElementById('letterQrImg');
const letterQrText = document.getElementById('letterQrText');
const letterHash = document.getElementById('letterHash');
const letterStatus = document.getElementById('letterStatus');
const letterPlace = document.getElementById('letterPlace');

function setMessage(text, type = 'error') {
  message.textContent = text;
  message.className = text ? `alert alert-${type}` : '';
}
function closeModal() {
  modal.classList.remove('show');
  pin.value = '';
  setMessage('');
}
function openModal() {
  modal.classList.add('show');
  setMessage('');
  window.setTimeout(() => pin.focus(), 50);
}

openButton.addEventListener('click', openModal);
async function detectMapCity() {
  if (!navigator.geolocation) return;
  const position = await new Promise((resolve) => {
    navigator.geolocation.getCurrentPosition(resolve, () => resolve(null), { enableHighAccuracy: false, timeout: 3500, maximumAge: 600000 });
  });
  if (!position) return;
  try {
    const params = new URLSearchParams({ format: 'jsonv2', lat: position.coords.latitude.toString(), lon: position.coords.longitude.toString(), 'accept-language': 'id' });
    const response = await fetch(`https://nominatim.openstreetmap.org/reverse?${params.toString()}`);
    const data = await response.json();
    const address = data.address || {};
    const city = address.city || address.town || address.municipality || address.village || address.county;
    if (city) letterPlace.textContent = city;
  } catch (error) {
    // Fallback to the office/branch value already rendered from SSO.
  }
}
printButton.addEventListener('click', async () => {
  const label = printButton.textContent;
  printButton.disabled = true;
  printButton.textContent = 'Mendeteksi lokasi...';
  await detectMapCity();
  printButton.disabled = false;
  printButton.textContent = label;
  window.setTimeout(() => window.print(), 80);
});
cancelButton.addEventListener('click', closeModal);
closeButton.addEventListener('click', closeModal);
modal.addEventListener('click', (event) => { if (event.target === modal) closeModal(); });
document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeModal(); });
pin.addEventListener('input', () => { pin.value = pin.value.replace(/\D/g, '').slice(0, 6); });

signButton.addEventListener('click', async () => {
  if (!/^\d{6}$/.test(pin.value)) {
    setMessage('PIN harus terdiri dari 6 digit angka.');
    pin.focus();
    return;
  }
  signButton.disabled = true;
  signButton.textContent = 'Memproses...';
  setMessage('Memvalidasi PIN dan menyimpan tanda tangan...', 'warning');
  try {
    const response = await fetch('<?=e(base_url('api/sign.php'))?>', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?=e(csrf_token())?>' },
      body: JSON.stringify({ pin: pin.value, document_id: '<?=e($docId)?>', document_name: 'Nota Persetujuan Internal', application: 'TTD IN BKK' })
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok || !payload.success) throw new Error(payload.message || 'Proses signing gagal.');

    closeModal();
    result.style.display = 'grid';
    signatureImage.src = payload.data.signature_url;
    letterStatus.textContent = 'SUDAH DITANDATANGANI';
    hashBox.style.display = 'block';
    hashBox.textContent = `SHA-256: ${payload.data.document_hash}`;
    signedAt.style.display = 'block';
    signedAt.textContent = `Ditandatangani pada ${payload.data.signed_at}`;
    if (verifyLink) verifyLink.href = payload.data.verification_url;
    qrBox.innerHTML = '';
    if (typeof QRCode === 'function') {
      const qrOptions = { text: payload.data.verification_url, width: 168, height: 168 };
      if (QRCode.CorrectLevel?.H) qrOptions.correctLevel = QRCode.CorrectLevel.H;
      new QRCode(qrBox, qrOptions);
      window.setTimeout(() => {
        const generatedQr = qrBox.querySelector('canvas, img');
        if (generatedQr) {
          letterQrImg.src = generatedQr.tagName.toLowerCase() === 'canvas' ? generatedQr.toDataURL('image/png') : generatedQr.src;
          letterQrImg.style.display = 'block';
          letterQrText.textContent = 'Scan untuk verifikasi TTD';
        }
      }, 50);
    } else {
      const fallback = document.createElement('a');
      fallback.href = payload.data.verification_url;
      fallback.target = '_blank';
      fallback.rel = 'noopener';
      fallback.textContent = 'Buka link verifikasi';
      qrBox.appendChild(fallback);
      letterQrText.textContent = 'Link verifikasi: ' + payload.data.verification_url;
    }
    letterHash.style.display = 'block';
    letterHash.textContent = 'SHA-256: ' + payload.data.document_hash;
  } catch (error) {
    setMessage(error.message || 'Proses signing gagal.');
  } finally {
    signButton.disabled = false;
    signButton.textContent = 'Verifikasi & TTD';
  }
});
</script></body></html>
