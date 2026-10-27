<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
require_once __DIR__.'/../middleware/signature.php';

[$user,$profile,$security]=require_signature_ready($pdo);
$csrf=$_SERVER['HTTP_X_CSRF_TOKEN']??null;
if(!csrf_validate($csrf)) json_out(419,['success'=>false,'message'=>'CSRF token tidak valid.']);

$input=json_decode(file_get_contents('php://input'),true)?:[];
$pin=(string)($input['pin']??'');
$docId=substr(trim((string)($input['document_id']??'')),0,100);
$docName=substr(trim((string)($input['document_name']??'Dokumen')),0,255);
$app=substr(trim((string)($input['application']??'TTD IN BKK')),0,100);
$idPeg=(string)$user['employee_id'];

if(!preg_match('/^\d{6}$/',$pin)||$docId==='') json_out(422,['success'=>false,'message'=>'PIN 6 digit dan document_id wajib diisi.']);

if(!empty($security['locked_until'])&&strtotime($security['locked_until'])>time()) {
    json_out(423,['success'=>false,'message'=>'PIN TTD terkunci sementara.']);
}

if(!password_verify($pin,$security['pin_hash'])) {
    $failed=(int)$security['failed_attempt']+1;
    $locked=$failed>=5?date('Y-m-d H:i:s',time()+900):null;
    $pdo->prepare("UPDATE signature_security SET failed_attempt=:f,locked_until=:l WHERE id_peg=:id")
        ->execute(['f'=>$failed,'l'=>$locked,'id'=>$idPeg]);
    log_signature($pdo,$idPeg,'SIGN','FAILED',$docId,$app);
    json_out(401,['success'=>false,'message'=>$locked?'PIN salah 5 kali. TTD dikunci 15 menit.':'PIN TTD salah.']);
}

$pdo->beginTransaction();
try {
    $pdo->prepare("UPDATE signature_security SET failed_attempt=0,locked_until=NULL WHERE id_peg=:id")->execute(['id'=>$idPeg]);
    $payload=[
        'document_id'=>$docId,
        'document_name'=>$docName,
        'id_peg'=>$idPeg,
        'application'=>$app,
        'signed_at'=>date(DATE_ATOM)
    ];
    $hash=hash('sha256',json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    $token=bin2hex(random_bytes(32));
    $pdo->prepare("INSERT INTO signature_documents(document_id,id_peg,signature_path,application,document_name,document_hash,verification_token)
                   VALUES(:doc,:id,:signature_path,:app,:name,:hash,:token)")
        ->execute(['doc'=>$docId,'id'=>$idPeg,'signature_path'=>$profile['signature_path'],'app'=>$app,'name'=>$docName,'hash'=>$hash,'token'=>$token]);
    log_signature($pdo,$idPeg,'SIGN','SUCCESS',$docId,$app);
    $pdo->commit();

    $verify=base_url('verify.php?token='.$token);
    json_out(200,['success'=>true,'message'=>'Dokumen berhasil ditandatangani.','data'=>[
        'signature_url'=>base_url($profile['signature_path']),
        'verification_url'=>$verify,
        'document_hash'=>$hash,
        'signed_at'=>date('Y-m-d H:i:s'),
    ]]);
} catch(Throwable $e) {
    if($pdo->inTransaction())$pdo->rollBack();
    json_out(500,['success'=>false,'message'=>'Proses signing gagal.']);
}
