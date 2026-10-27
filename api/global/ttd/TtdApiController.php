<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/ttd_db.php';
require_once dirname(__DIR__, 3) . '/helpers/response.php';
require_once __DIR__ . '/SignatureFunctions.php';

if (!function_exists('client_ip')) {
    function client_ip(): ?string
    {
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }
}

if (!function_exists('user_agent')) {
    function user_agent(): string
    {
        return substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    }
}

final class TtdApiController
{
    public static function status(): void
    {
        global $ttdPdo;

        $input = self::input();
        self::guardEndpoint($input);

        $idPeg = self::requiredString($input, 'id_peg', 50);
        $application = self::requiredString($input, 'nama_aplikasi', 100);
        $profile = get_signature_profile($ttdPdo, $idPeg);
        $security = get_signature_security($ttdPdo, $idPeg);
        $active = $profile !== null
            && $security !== null
            && ($profile['status'] ?? '') === 'ACTIVE';

        sendResponse(200, $active ? 'TTD aktif.' : 'Pegawai belum memiliki TTD aktif.', [
            'code' => $active ? 'SIGNATURE_ACTIVE' : 'SIGNATURE_NOT_REGISTERED',
            'id_peg' => $idPeg,
            'nama_aplikasi' => $application,
            'registered' => $profile !== null,
            'active' => $active,
            'can_sign' => $active,
            'signature_url' => $active ? self::ttdUrl((string)$profile['signature_path']) : null,
            'register_url' => $active ? null : self::ttdUrl('register-signature.php'),
        ]);
    }

    public static function sign(): void
    {
        global $ttdPdo;

        $input = self::input();
        self::guardEndpoint($input);

        $idPeg = self::requiredString($input, 'id_peg', 50);
        $pin = (string)($input['input_pin'] ?? '');
        $application = self::requiredString($input, 'nama_aplikasi', 100);

        if (!preg_match('/^\d{6}$/', $pin)) {
            sendResponse(422, 'input_pin wajib berupa 6 digit angka.', [
                'code' => 'INVALID_PIN_FORMAT',
            ]);
        }

        $profile = get_signature_profile($ttdPdo, $idPeg);
        $security = get_signature_security($ttdPdo, $idPeg);
        if (!$profile || !$security || ($profile['status'] ?? '') !== 'ACTIVE') {
            sendResponse(409, 'Pegawai belum memiliki TTD aktif. Registrasikan TTD terlebih dahulu.', [
                'code' => 'SIGNATURE_NOT_REGISTERED',
                'id_peg' => $idPeg,
                'nama_aplikasi' => $application,
                'registered' => false,
                'active' => false,
                'can_sign' => false,
                'register_url' => self::ttdUrl('register-signature.php'),
            ]);
        }

        if (!empty($security['locked_until']) && strtotime((string)$security['locked_until']) > time()) {
            sendResponse(423, 'PIN TTD terkunci sementara.', [
                'code' => 'SIGNATURE_PIN_LOCKED',
                'locked_until' => $security['locked_until'],
            ]);
        }

        $docId = substr(trim((string)($input['document_id'] ?? '')), 0, 100);
        if ($docId === '') {
            $docId = 'API-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
        }
        $docName = substr(trim((string)($input['document_name'] ?? 'Dokumen dari ' . $application)), 0, 255);
        if ($docName === '') {
            $docName = 'Dokumen dari ' . $application;
        }

        if (!password_verify($pin, (string)$security['pin_hash'])) {
            $failed = (int)$security['failed_attempt'] + 1;
            $lockedUntil = $failed >= 5 ? date('Y-m-d H:i:s', time() + 900) : null;
            $ttdPdo->prepare('UPDATE signature_security SET failed_attempt=:failed,locked_until=:locked WHERE id_peg=:id')
                ->execute(['failed' => $failed, 'locked' => $lockedUntil, 'id' => $idPeg]);
            log_signature($ttdPdo, $idPeg, 'SIGN', 'FAILED', $docId, $application);

            sendResponse(401, $lockedUntil
                ? 'PIN salah 5 kali. TTD dikunci selama 15 menit.'
                : 'PIN TTD salah.', [
                    'code' => $lockedUntil ? 'SIGNATURE_PIN_LOCKED' : 'INVALID_SIGNATURE_PIN',
                    'failed_attempt' => $failed,
                    'locked_until' => $lockedUntil,
                ]);
        }

        $signedAt = date(DATE_ATOM);
        $providedHash = strtolower(trim((string)($input['document_hash'] ?? '')));
        if ($providedHash !== '' && !preg_match('/^[a-f0-9]{64}$/', $providedHash)) {
            sendResponse(422, 'document_hash harus berupa SHA-256 64 karakter hexadecimal.', [
                'code' => 'INVALID_DOCUMENT_HASH',
            ]);
        }

        $documentHash = $providedHash !== ''
            ? $providedHash
            : hash('sha256', json_encode([
                'document_id' => $docId,
                'document_name' => $docName,
                'id_peg' => $idPeg,
                'application' => $application,
                'signed_at' => $signedAt,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $token = bin2hex(random_bytes(32));
        $ttdPdo->beginTransaction();
        try {
            $ttdPdo->prepare('UPDATE signature_security SET failed_attempt=0,locked_until=NULL WHERE id_peg=:id')
                ->execute(['id' => $idPeg]);
            $ttdPdo->prepare('INSERT INTO signature_documents(document_id,id_peg,signature_path,application,document_name,document_hash,verification_token)
                              VALUES(:doc,:id,:signature_path,:app,:name,:hash,:token)')
                ->execute([
                    'doc' => $docId,
                    'id' => $idPeg,
                    'signature_path' => $profile['signature_path'],
                    'app' => $application,
                    'name' => $docName,
                    'hash' => $documentHash,
                    'token' => $token,
                ]);
            log_signature($ttdPdo, $idPeg, 'SIGN', 'SUCCESS', $docId, $application);
            $ttdPdo->commit();
        } catch (Throwable $e) {
            if ($ttdPdo->inTransaction()) {
                $ttdPdo->rollBack();
            }
            error_log('[SSO API] TTD signing error: ' . $e->getMessage());
            sendResponse(500, 'Proses signing gagal.', [
                'code' => 'SIGNATURE_PROCESS_FAILED',
            ]);
        }

        $verificationUrl = self::ttdUrl('verify.php?token=' . $token);
        sendResponse(200, 'Dokumen berhasil ditandatangani.', [
            'code' => 'SIGNATURE_SUCCESS',
            'id_peg' => $idPeg,
            'nama_aplikasi' => $application,
            'document_id' => $docId,
            'document_name' => $docName,
            'signature_url' => self::ttdUrl((string)$profile['signature_path']),
            'verification_url' => $verificationUrl,
            'qr_content' => $verificationUrl,
            'document_hash' => $documentHash,
            'signed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private static function input(): array
    {
        $raw = file_get_contents('php://input');
        $input = json_decode($raw ?: '', true);
        return is_array($input) ? $input : $_POST;
    }

    private static function guardEndpoint(array $input): void
    {
        $expected = self::setting('TTD_API_PASSWORD', '');
        if ($expected === '') {
            error_log('[SSO API] TTD_API_PASSWORD belum dikonfigurasi.');
            sendResponse(503, 'Endpoint TTD belum dikonfigurasi.');
        }

        $provided = (string)($input['pass_endpoint'] ?? '');
        if ($provided === '' || !hash_equals((string)$expected, $provided)) {
            sendResponse(401, 'pass_endpoint tidak valid.', [
                'code' => 'INVALID_ENDPOINT_PASSWORD',
            ]);
        }
    }

    private static function requiredString(array $input, string $key, int $maxLength): string
    {
        $value = trim((string)($input[$key] ?? ''));
        if ($value === '') {
            sendResponse(422, $key . ' wajib diisi.', [
                'code' => 'REQUIRED_FIELD',
                'field' => $key,
            ]);
        }
        return substr($value, 0, $maxLength);
    }

    private static function ttdUrl(string $path = ''): string
    {
        $default = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/rest_api_sso/ttd_in_bkk';
        $base = self::setting('TTD_APP_URL', $default);
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }

    private static function setting(string $key, string $default): string
    {
        return (string) app_env_value($key, $default);
    }
}
