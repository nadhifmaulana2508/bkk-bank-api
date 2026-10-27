<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/response.php';
require_once __DIR__ . '/helpers/jwt.php';

// 🔥 Panggil MailHelper yang baru saja kita buat
require_once __DIR__ . '/helpers/mailHelper.php';

$SECRET_KEY = "SSO_BKK_SECRET_2026";

class AuthController {

    // ==========================================
    // 1. LOGIN
    // ==========================================
    public static function login() {
        global $pdo, $SECRET_KEY;
        $input = json_decode(file_get_contents("php://input"), true);

        $id_peg   = $input['id_peg'] ?? '';
        $password = $input['password'] ?? '';
        $app      = $input['app'] ?? ''; 

        if (!$app) {
            sendResponse(400, "App wajib diisi (monbis, simpeg, dll)"); return;
        }

        $stmt = $pdo->prepare("
            SELECT a.*, p.nama FROM tb_apk a
            JOIN tb_pegawai p ON a.id_peg = p.id_peg
            WHERE a.id_peg = :id_peg LIMIT 1
        ");
        $stmt->execute(['id_peg' => $id_peg]);
        $user = $stmt->fetch();

        if (!$user) {
            sendResponse(401, "User tidak ditemukan"); return;
        }
        if (!password_verify($password, $user['pass'])) {
            sendResponse(401, "Password salah"); return;
        }

        $allowedApps = ['monbis', 'ims', 'rekrutmen' ,'simontok', 'simstock', 'simpeg', 'vitised_ao', 'portal_bkk', 'sipatuh'];
        if (!in_array($app, $allowedApps)) {
            sendResponse(400, "App tidak valid"); return;
        }
        if ((int)$user[$app] !== 1) {
            sendResponse(403, "Tidak punya akses ke aplikasi {$app}"); return;
        }

        $payload = [
            "id_peg" => $user['id_peg'],
            "nama"   => $user['nama'],
            "app"    => $app,
            "exp"    => time() + (60 * 60 * 8)
        ];
        $token = jwt_encode($payload, $SECRET_KEY);

        sendResponse(200, "Login berhasil", ["token" => $token]);
    }

    // ==========================================
    // 2. WHOAMI
    // ==========================================
    public static function whoami() {
        global $pdo;
        require_once __DIR__ . '/middlewares/authMiddleware.php';
        $user = auth();

        $stmt = $pdo->prepare("
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
            WHERE j.status_jab = 'Aktif' AND p.id_peg = :id_peg
            LIMIT 1
        ");
        $stmt->execute(['id_peg' => $user['id_peg']]);
        $data = $stmt->fetch();

        sendResponse(200, "OK", $data);
    }

    // ==========================================
    // 3. GANTI PASSWORD (Harus Login)
    // ==========================================
    public static function changePassword() {
        global $pdo;
        require_once __DIR__ . '/middlewares/authMiddleware.php';
        $user = auth();

        $input = json_decode(file_get_contents("php://input"), true);
        $old_password = $input['old_password'] ?? '';
        $new_password = $input['new_password'] ?? '';

        if (!$old_password || !$new_password) {
            sendResponse(400, "Password lama dan baru wajib diisi"); return;
        }

        $stmt = $pdo->prepare("SELECT pass FROM tb_apk WHERE id_peg = :id_peg");
        $stmt->execute(['id_peg' => $user['id_peg']]);
        $dbUser = $stmt->fetch();

        if (!password_verify($old_password, $dbUser['pass'])) {
            sendResponse(400, "Password lama yang Anda masukkan salah!"); return;
        }

        $hashedPassword = password_hash($new_password, PASSWORD_BCRYPT);
        $update = $pdo->prepare("UPDATE tb_apk SET pass = :pass WHERE id_peg = :id_peg");
        $update->execute(['pass' => $hashedPassword, 'id_peg' => $user['id_peg']]);

        sendResponse(200, "Password berhasil diubah!");
    }

    // ==========================================
    // 4. UPDATE PROFIL (Email & Telp)
    // ==========================================
    public static function updateProfile() {
        global $pdo;
        require_once __DIR__ . '/middlewares/authMiddleware.php';
        $user = auth();

        $input = json_decode(file_get_contents("php://input"), true);
        $email = $input['email'] ?? null;
        $telp = $input['telp'] ?? null;

        if (!$email && !$telp) {
            sendResponse(400, "Minimal kirim email atau telp yang ingin diubah"); return;
        }

        $fields = [];
        $params = ['id_peg' => $user['id_peg']];

        if ($email !== null) { $fields[] = "email = :email"; $params['email'] = $email; }
        if ($telp !== null)  { $fields[] = "telp = :telp"; $params['telp'] = $telp; }

        $queryStr = implode(", ", $fields);
        $update = $pdo->prepare("UPDATE tb_pegawai SET {$queryStr} WHERE id_peg = :id_peg");
        $update->execute($params);

        sendResponse(200, "Data Profil berhasil diperbarui!");
    }

    // ==========================================
    // 5. LUPA PASSWORD (GENERATE OTP & PANGGIL HELPER)
    // ==========================================
    public static function forgotPassword() {
        global $pdo, $SECRET_KEY;
        $input = json_decode(file_get_contents("php://input"), true);
        $email = $input['email'] ?? '';

        if (!$email) {
            sendResponse(400, "Email wajib diisi"); return;
        }

        $stmt = $pdo->prepare("SELECT id_peg, nama FROM tb_pegawai WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $pegawai = $stmt->fetch();

        if (!$pegawai) {
            sendResponse(404, "Email tidak terdaftar di sistem!"); return;
        }

        // Generate OTP & Token
        $otpCode = sprintf("%06d", mt_rand(1, 999999));
        $otpHash = hash('sha256', $otpCode);

        $payload = [
            "id_peg"   => $pegawai['id_peg'],
            "otp_hash" => $otpHash,
            "purpose"  => "verify_otp",
            "exp"      => time() + (60 * 5)
        ];
        $otpToken = jwt_encode($payload, $SECRET_KEY);

        // 🔥 PANGGIL MAIL HELPER (Controller jadi super bersih!) 🔥
        try {
            MailHelper::sendOTP($email, $pegawai['nama'], $otpCode);
        } catch (Exception $e) {
            sendResponse(500, $e->getMessage()); 
            return;
        }

        sendResponse(200, "Kode OTP berhasil dikirim ke email Anda.", [
            "otp_token" => $otpToken
        ]);
    }

    // ==========================================
    // 6. VERIFIKASI KODE OTP
    // ==========================================
    public static function verifyOtp() {
        global $SECRET_KEY;
        $input = json_decode(file_get_contents("php://input"), true);
        
        $otpToken = $input['otp_token'] ?? '';
        $otpInput = $input['otp_code'] ?? ''; 

        if (!$otpToken || !$otpInput) {
            sendResponse(400, "Token OTP dan Kode OTP wajib diisi!"); return;
        }

        $decoded = jwt_decode($otpToken, $SECRET_KEY);
        if (!$decoded || !isset($decoded['purpose']) || $decoded['purpose'] !== 'verify_otp') {
            sendResponse(401, "Sesi OTP tidak valid atau sudah kadaluarsa."); return;
        }

        if (hash('sha256', $otpInput) !== $decoded['otp_hash']) {
            sendResponse(400, "Kode OTP salah!"); return;
        }

        $resetPayload = [
            "id_peg"  => $decoded['id_peg'],
            "purpose" => "reset_password",
            "exp"     => time() + (60 * 15) // Berlaku 15 menit
        ];
        $resetToken = jwt_encode($resetPayload, $SECRET_KEY);

        sendResponse(200, "OTP Valid! Silakan masukkan password baru.", [
            "reset_token" => $resetToken
        ]);
    }

    // ==========================================
    // 7. RESET PASSWORD (UBAH PASSWORD)
    // ==========================================
    public static function resetPassword() {
        global $pdo, $SECRET_KEY;
        $input = json_decode(file_get_contents("php://input"), true);
        
        $token = $input['reset_token'] ?? ''; 
        $new_password = $input['new_password'] ?? '';

        if (!$token || !$new_password) {
            sendResponse(400, "Token reset dan password baru wajib diisi!"); return;
        }

        $decoded = jwt_decode($token, $SECRET_KEY);
        if (!$decoded || !isset($decoded['purpose']) || $decoded['purpose'] !== 'reset_password') {
            sendResponse(401, "Sesi reset password tidak valid atau sudah kadaluarsa!"); return;
        }

        $hashedPassword = password_hash($new_password, PASSWORD_BCRYPT);
        $update = $pdo->prepare("UPDATE tb_apk SET pass = :pass WHERE id_peg = :id_peg");
        $update->execute(['pass' => $hashedPassword, 'id_peg' => $decoded['id_peg']]);

        sendResponse(200, "Password berhasil di-reset. Silakan login kembali dengan password baru Anda!");
    }
}