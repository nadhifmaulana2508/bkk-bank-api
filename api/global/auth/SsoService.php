<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/db.php';
require_once dirname(__DIR__, 3) . '/helpers/response.php';
require_once dirname(__DIR__, 3) . '/helpers/jwt.php';
require_once dirname(__DIR__, 3) . '/helpers/mailHelper.php';
require_once __DIR__ . '/SsoConfig.php';
require_once __DIR__ . '/SsoAuthenticator.php';

final class SsoService
{
    public static function login(): void
    {
        global $pdo;

        $input = json_decode(file_get_contents('php://input'), true);
        $idPeg = $input['id_peg'] ?? '';
        $password = $input['password'] ?? '';
        $app = $input['app'] ?? '';

        if (!$app) {
            sendResponse(400, 'App wajib diisi (monbis, simpeg, dll)');
        }

        $stmt = $pdo->prepare('
            SELECT a.*, p.nama FROM tb_apk a
            JOIN tb_pegawai p ON a.id_peg = p.id_peg
            WHERE a.id_peg = :id_peg LIMIT 1
        ');
        $stmt->execute(['id_peg' => $idPeg]);
        $user = $stmt->fetch();

        if (!$user) {
            sendResponse(401, 'User tidak ditemukan');
        }
        if (!password_verify($password, $user['pass'])) {
            sendResponse(401, 'Password salah');
        }

        $allowedApps = [
            'monbis', 'ims', 'rekrutmen', 'simontok', 'simstock',
            'simpeg', 'vitised_ao', 'portal_bkk', 'sipatuh',
        ];
        if (!in_array($app, $allowedApps, true)) {
            sendResponse(400, 'App tidak valid');
        }
        if ((int)$user[$app] !== 1) {
            sendResponse(403, "Tidak punya akses ke aplikasi {$app}");
        }

        $payload = [
            'id_peg' => $user['id_peg'],
            'nama' => $user['nama'],
            'app' => $app,
            'exp' => time() + (60 * 60 * 8),
        ];
        $token = jwt_encode($payload, sso_secret_key());

        sendResponse(200, 'Login berhasil', ['token' => $token]);
    }

    public static function whoami(): void
    {
        global $pdo;

        $user = SsoAuthenticator::user();
        $stmt = $pdo->prepare('
            SELECT
                k.kode_cabang AS kode,
                j.id_peg AS employee_id,
                p.nama AS full_name,
                p.email,
                p.telp,
                k.nama_kantor AS branch_name,
                mj.nama_unit_kerja AS unit_kerja,
                mj.nama_jabatan AS job_position,
                mj.level,
                mj.group_jabatan
            FROM tb_jabatan j
            INNER JOIN tb_pegawai p ON j.id_peg = p.id_peg
            INNER JOIN tb_master_jabatan mj
                ON CAST(j.kode_jabatan AS CHAR) = CAST(mj.kode_jabatan AS CHAR)
            LEFT JOIN tb_kantor k
                ON j.unit_kerja = k.kode_kantor_detail
            WHERE j.status_jab = \'Aktif\' AND p.id_peg = :id_peg
            LIMIT 1
        ');
        $stmt->execute(['id_peg' => $user['id_peg']]);
        $data = $stmt->fetch();

        sendResponse(200, 'OK', $data);
    }

    public static function changePassword(): void
    {
        global $pdo;

        $user = SsoAuthenticator::user();
        $input = json_decode(file_get_contents('php://input'), true);
        $oldPassword = $input['old_password'] ?? '';
        $newPassword = $input['new_password'] ?? '';

        if (!$oldPassword || !$newPassword) {
            sendResponse(400, 'Password lama dan baru wajib diisi');
        }

        $stmt = $pdo->prepare('SELECT pass FROM tb_apk WHERE id_peg = :id_peg');
        $stmt->execute(['id_peg' => $user['id_peg']]);
        $dbUser = $stmt->fetch();

        if (!password_verify($oldPassword, $dbUser['pass'])) {
            sendResponse(400, 'Password lama yang Anda masukkan salah!');
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $update = $pdo->prepare('UPDATE tb_apk SET pass = :pass WHERE id_peg = :id_peg');
        $update->execute(['pass' => $hashedPassword, 'id_peg' => $user['id_peg']]);

        sendResponse(200, 'Password berhasil diubah!');
    }

    public static function updateProfile(): void
    {
        global $pdo;

        $user = SsoAuthenticator::user();
        $input = json_decode(file_get_contents('php://input'), true);
        $email = $input['email'] ?? null;
        $telp = $input['telp'] ?? null;

        if (!$email && !$telp) {
            sendResponse(400, 'Minimal kirim email atau telp yang ingin diubah');
        }

        $fields = [];
        $params = ['id_peg' => $user['id_peg']];
        if ($email !== null) {
            $fields[] = 'email = :email';
            $params['email'] = $email;
        }
        if ($telp !== null) {
            $fields[] = 'telp = :telp';
            $params['telp'] = $telp;
        }

        $query = 'UPDATE tb_pegawai SET ' . implode(', ', $fields)
            . ' WHERE id_peg = :id_peg';
        $update = $pdo->prepare($query);
        $update->execute($params);

        sendResponse(200, 'Data Profil berhasil diperbarui!');
    }

    public static function forgotPassword(): void
    {
        global $pdo;

        $input = json_decode(file_get_contents('php://input'), true);
        $email = $input['email'] ?? '';
        if (!$email) {
            sendResponse(400, 'Email wajib diisi');
        }

        $stmt = $pdo->prepare(
            'SELECT id_peg, nama FROM tb_pegawai WHERE email = :email LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $pegawai = $stmt->fetch();
        if (!$pegawai) {
            sendResponse(404, 'Email tidak terdaftar di sistem!');
        }

        $otpCode = sprintf('%06d', mt_rand(1, 999999));
        $payload = [
            'id_peg' => $pegawai['id_peg'],
            'otp_hash' => hash('sha256', $otpCode),
            'purpose' => 'verify_otp',
            'exp' => time() + (60 * 5),
        ];
        $otpToken = jwt_encode($payload, sso_secret_key());

        try {
            MailHelper::sendOTP($email, $pegawai['nama'], $otpCode);
        } catch (\Exception $e) {
            sendResponse(500, $e->getMessage());
        }

        sendResponse(200, 'Kode OTP berhasil dikirim ke email Anda.', [
            'otp_token' => $otpToken,
        ]);
    }

    public static function verifyOtp(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $otpToken = $input['otp_token'] ?? '';
        $otpInput = $input['otp_code'] ?? '';
        if (!$otpToken || !$otpInput) {
            sendResponse(400, 'Token OTP dan Kode OTP wajib diisi!');
        }

        $decoded = jwt_decode($otpToken, sso_secret_key());
        if (!$decoded || !isset($decoded['purpose']) || $decoded['purpose'] !== 'verify_otp') {
            sendResponse(401, 'Sesi OTP tidak valid atau sudah kadaluarsa.');
        }
        if (hash('sha256', $otpInput) !== $decoded['otp_hash']) {
            sendResponse(400, 'Kode OTP salah!');
        }

        $resetPayload = [
            'id_peg' => $decoded['id_peg'],
            'purpose' => 'reset_password',
            'exp' => time() + (60 * 15),
        ];
        $resetToken = jwt_encode($resetPayload, sso_secret_key());

        sendResponse(200, 'OTP Valid! Silakan masukkan password baru.', [
            'reset_token' => $resetToken,
        ]);
    }

    public static function resetPassword(): void
    {
        global $pdo;

        $input = json_decode(file_get_contents('php://input'), true);
        $token = $input['reset_token'] ?? '';
        $newPassword = $input['new_password'] ?? '';
        if (!$token || !$newPassword) {
            sendResponse(400, 'Token reset dan password baru wajib diisi!');
        }

        $decoded = jwt_decode($token, sso_secret_key());
        if (!$decoded || !isset($decoded['purpose']) || $decoded['purpose'] !== 'reset_password') {
            sendResponse(401, 'Sesi reset password tidak valid atau sudah kadaluarsa!');
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $update = $pdo->prepare('UPDATE tb_apk SET pass = :pass WHERE id_peg = :id_peg');
        $update->execute(['pass' => $hashedPassword, 'id_peg' => $decoded['id_peg']]);

        sendResponse(200, 'Password berhasil di-reset. Silakan login kembali dengan password baru Anda!');
    }
}
