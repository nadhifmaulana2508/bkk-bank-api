<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/db.php';
require_once dirname(__DIR__, 3) . '/helpers/response.php';
require_once dirname(__DIR__, 3) . '/helpers/request.php';
require_once dirname(__DIR__, 3) . '/helpers/jwt.php';
require_once dirname(__DIR__, 3) . '/helpers/mailHelper.php';
require_once dirname(__DIR__, 2) . '/modules/simpeg/EmployeeService.php';
require_once __DIR__ . '/SsoConfig.php';
require_once __DIR__ . '/SsoAuthenticator.php';
require_once __DIR__ . '/AppTokenAuthenticator.php';

final class SsoService
{
    public static function login(): void
    {
        global $pdo;

        $input = jsonRequestBody();
        $idPeg = $input['id_peg'] ?? null;
        $password = $input['password'] ?? null;

        if (is_string($idPeg)) {
            $idPeg = trim($idPeg);
        }

        if (
            !is_string($idPeg)
            || $idPeg === ''
            || strlen($idPeg) > 80
            || !preg_match('/^[A-Za-z0-9._-]+$/', $idPeg)
            || !is_string($password)
            || $password === ''
            || strlen($password) < 6
            || strlen($password) > 255
        ) {
            sendResponse(400, 'ID pegawai dan password wajib valid, password minimal 6 karakter.');
        }

        $application = AppTokenAuthenticator::current();
        if (AppTokenAuthenticator::isRequired()) {
            if (!is_array($application)) {
                sendResponse(403, 'Aplikasi belum terdaftar atau token aplikasi belum valid.');
            }
        }

        $app = is_array($application)
            ? strtolower((string) ($application['app_code'] ?? 'sso'))
            : 'sso';

        $stmt = $pdo->prepare('
            SELECT a.*, p.nama FROM tb_apk a
            JOIN tb_pegawai p ON a.id_peg = p.id_peg
            WHERE a.id_peg = :id_peg
            LIMIT 1
        ');
        $stmt->execute(['id_peg' => $idPeg]);
        $user = $stmt->fetch();

        if (!is_array($user)) {
            sendResponse(401, 'ID pegawai atau password salah.');
        }

        if (!is_string($user['pass'] ?? null) || !password_verify($password, $user['pass'])) {
            sendResponse(401, 'ID pegawai atau password salah.');
        }

        if (SsoAuthenticator::isLoginBlockedStatus($user['status'] ?? null)) {
            $employeeName = trim((string) ($user['nama'] ?? ''));
            $subject = $employeeName !== '' ? 'Pegawai ' . $employeeName : 'Akun pegawai';
            sendResponse(403, $subject . ' berstatus nonaktif atau non job dan tidak dapat login ke aplikasi yang menggunakan SSO.');
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

    public static function loginDummy(): void
    {
        self::assertDummyAuthEnabled();
        $input = jsonRequestBody();
        $idPeg = $input['id_peg'] ?? null;
        $password = $input['password'] ?? null;
        if (
            !is_string($idPeg)
            || ($idPeg = trim($idPeg)) === ''
            || strlen($idPeg) > 12
            || !preg_match('/^[A-Za-z0-9._-]+$/', $idPeg)
            || !is_string($password)
            || strlen($password) > 255
        ) {
            sendResponse(400, 'ID pegawai dan password dummy wajib valid.');
        }

        $environment = strtolower(trim((string) sso_config_value('APP_ENV', '')));
        $passwordFallback = $environment === 'local' ? 'BKKJTG123' : null;
        $expectedPassword = sso_config_value('SSO_DUMMY_PASSWORD', $passwordFallback);
        if (!is_string($expectedPassword) || $expectedPassword === '') {
            sendResponse(503, 'Password dummy belum dikonfigurasi di environment development.');
        }
        if (!hash_equals($expectedPassword, $password)) {
            sendResponse(401, 'ID pegawai atau password dummy salah.');
        }

        global $pdo;
        $statement = $pdo->prepare(
            'SELECT a.status, p.nama
             FROM tb_apk a
             INNER JOIN tb_pegawai p ON p.id_peg = a.id_peg
             WHERE a.id_peg = :id_peg
             LIMIT 1'
        );
        $statement->execute(['id_peg' => $idPeg]);
        $account = $statement->fetch(PDO::FETCH_ASSOC);
        $employee = EmployeeService::findActiveByIdPeg($idPeg);
        if (
            !is_array($account)
            || SsoAuthenticator::isLoginBlockedStatus($account['status'] ?? null)
            || !is_array($employee)
        ) {
            sendResponse(401, 'ID pegawai atau password dummy salah.');
        }

        $application = AppTokenAuthenticator::current();
        if (!is_array($application)) {
            sendResponse(401, 'App dan X-App-Token wajib diisi.');
        }
        $token = jwt_encode([
            'id_peg' => $idPeg,
            'nama' => (string) ($account['nama'] ?? ''),
            'app' => (string) $application['app_code'],
            'auth_mode' => 'dummy',
            'exp' => time() + (60 * 60 * 8),
        ], sso_secret_key());

        sendResponse(200, 'Login berhasil', ['token' => $token]);
    }

    public static function authDummy(): void
    {
        self::assertDummyAuthEnabled();
        $token = SsoAuthenticator::bearerToken();
        if ($token === null && strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            $input = jsonRequestBody();
            $token = is_string($input['token'] ?? null) ? $input['token'] : null;
        }
        $decoded = $token !== null ? jwt_decode($token, sso_secret_key()) : false;
        if (
            !is_array($decoded)
            || ($decoded['auth_mode'] ?? null) !== 'dummy'
            || !is_string($decoded['id_peg'] ?? null)
            || !preg_match('/^[A-Za-z0-9._-]{1,12}$/', $decoded['id_peg'])
            || !is_string($decoded['app'] ?? null)
            || !isset($decoded['exp'])
        ) {
            sendResponse(401, 'Token dummy tidak valid atau sudah kadaluarsa.');
        }

        $application = AppTokenAuthenticator::current();
        if (!is_array($application) || strtolower($decoded['app']) !== strtolower((string) $application['app_code'])) {
            sendResponse(403, 'Token pegawai tidak sesuai dengan aplikasi.');
        }

        global $pdo;
        $statement = $pdo->prepare('SELECT status FROM tb_apk WHERE id_peg = :id_peg LIMIT 1');
        $statement->execute(['id_peg' => $decoded['id_peg']]);
        $status = $statement->fetchColumn();
        if ($status === false || SsoAuthenticator::isLoginBlockedStatus($status)) {
            sendResponse(403, 'Pegawai tidak aktif untuk menggunakan login dummy.');
        }

        $employee = EmployeeService::findActiveByIdPeg($decoded['id_peg']);
        if (!is_array($employee)) {
            sendResponse(403, 'Pegawai tidak memiliki jabatan aktif untuk menggunakan login dummy.');
        }

        sendResponse(200, 'OK', $employee);
    }

    public static function whoami(): void
    {
        $user = SsoAuthenticator::user();
        $data = EmployeeService::findActiveByIdPeg((string)$user['id_peg']);

        sendResponse(200, 'OK', $data);
    }

    public static function changePassword(): void
    {
        global $pdo;

        $user = SsoAuthenticator::user();
        $input = jsonRequestBody();
        $oldPassword = $input['old_password'] ?? null;
        $newPassword = $input['new_password'] ?? null;

        if (
            !is_string($oldPassword)
            || $oldPassword === ''
            || strlen($oldPassword) < 6
            || strlen($oldPassword) > 255
            || !is_string($newPassword)
            || strlen($newPassword) < 6
            || strlen($newPassword) > 255
        ) {
            sendResponse(400, 'Password lama dan password baru minimal 6 karakter.');
        }

        $stmt = $pdo->prepare('SELECT pass FROM tb_apk WHERE id_peg = :id_peg');
        $stmt->execute(['id_peg' => $user['id_peg']]);
        $dbUser = $stmt->fetch();

        if (!is_array($dbUser) || !is_string($dbUser['pass'] ?? null) || !password_verify($oldPassword, $dbUser['pass'])) {
            sendResponse(400, 'Password lama yang Anda masukkan salah!');
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        if (!is_string($hashedPassword)) {
            sendResponse(500, 'Password baru tidak dapat diproses.');
        }
        $update = $pdo->prepare('UPDATE tb_apk SET pass = :pass WHERE id_peg = :id_peg');
        $update->execute(['pass' => $hashedPassword, 'id_peg' => $user['id_peg']]);

        sendResponse(200, 'Password berhasil diubah!');
    }

    public static function updateProfile(): void
    {
        global $pdo;

        $user = SsoAuthenticator::user();
        $input = jsonRequestBody();
        $email = $input['email'] ?? null;
        $telp = $input['telp'] ?? null;

        if (!$email && !$telp) {
            sendResponse(400, 'Minimal kirim email atau telp yang ingin diubah');
        }

        if ($email !== null && (!is_string($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            sendResponse(400, 'Format email tidak valid.');
        }

        if ($telp !== null && (!is_string($telp) || !preg_match('/^[0-9+()\-\s]{6,20}$/', $telp))) {
            sendResponse(400, 'Format nomor telepon tidak valid.');
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
        self::sendPasswordOtp();
    }

    public static function resendOtp(): void
    {
        self::sendPasswordOtp();
    }

    private static function sendPasswordOtp(): void
    {
        global $pdo;

        $input = jsonRequestBody();
        $email = $input['email'] ?? '';
        if (is_string($email)) {
            $email = trim($email);
        }
        if (!is_string($email) || strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
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

        $otpCode = sprintf('%06d', random_int(0, 999999));
        $payload = [
            'id_peg' => $pegawai['id_peg'],
            'otp_hash' => hash('sha256', $otpCode),
            'purpose' => 'verify_otp',
            'exp' => time() + (60 * 5),
        ];
        $otpToken = jwt_encode($payload, sso_secret_key());

        try {
            MailHelper::sendOTP($email, $pegawai['nama'], $otpCode);
        } catch (\Throwable $e) {
            error_log('[API] OTP request failed: ' . $e->getMessage());
            sendResponse(500, 'Gagal mengirim email OTP. Silakan coba lagi nanti.');
        }

        sendResponse(200, 'Kode OTP berhasil dikirim ke email Anda.', [
            'otp_token' => $otpToken,
        ]);
    }

    public static function verifyOtp(): void
    {
        $input = jsonRequestBody();
        $otpToken = $input['otp_token'] ?? '';
        $otpInput = $input['otp_code'] ?? '';
        // JSON client sering mengirim OTP sebagai angka. Normalisasi hanya
        // tipe integer agar format string seperti 012345 tetap didukung.
        if (is_int($otpInput)) {
            $otpInput = (string) $otpInput;
        }
        if (
            !is_string($otpToken)
            || $otpToken === ''
            || strlen($otpToken) > 4096
            || !is_string($otpInput)
            || !preg_match('/^\d{6}$/', $otpInput)
        ) {
            sendResponse(400, 'Token OTP dan Kode OTP wajib diisi!');
        }

        $decoded = jwt_decode($otpToken, sso_secret_key());
        if (!$decoded || !isset($decoded['purpose']) || $decoded['purpose'] !== 'verify_otp') {
            sendResponse(401, 'Sesi OTP tidak valid atau sudah kadaluarsa.');
        }
        if (!is_string($decoded['otp_hash'] ?? null) || !hash_equals(
            $decoded['otp_hash'],
            hash('sha256', $otpInput)
        )) {
            sendResponse(400, 'Kode OTP salah!');
        }

        if (!is_string($decoded['id_peg'] ?? null) || !preg_match('/^[A-Za-z0-9._-]{1,80}$/', $decoded['id_peg'])) {
            sendResponse(401, 'Sesi OTP tidak valid atau sudah kadaluarsa.');
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

        $input = jsonRequestBody();
        $token = $input['reset_token'] ?? '';
        $newPassword = $input['new_password'] ?? '';
        if (
            !is_string($token)
            || $token === ''
            || strlen($token) > 4096
            || !is_string($newPassword)
            || strlen($newPassword) < 6
            || strlen($newPassword) > 255
        ) {
            sendResponse(400, 'Token reset wajib diisi dan password baru minimal 6 karakter.');
        }

        $decoded = jwt_decode($token, sso_secret_key());
        if (!$decoded || !isset($decoded['purpose']) || $decoded['purpose'] !== 'reset_password') {
            sendResponse(401, 'Sesi reset password tidak valid atau sudah kadaluarsa!');
        }

        if (!is_string($decoded['id_peg'] ?? null) || !preg_match('/^[A-Za-z0-9._-]{1,80}$/', $decoded['id_peg'])) {
            sendResponse(401, 'Sesi reset password tidak valid atau sudah kadaluarsa!');
        }

        $account = $pdo->prepare('SELECT id_peg FROM tb_apk WHERE id_peg = :id_peg LIMIT 1');
        $account->execute(['id_peg' => $decoded['id_peg']]);
        if (!$account->fetchColumn()) {
            sendResponse(404, 'Akun login pegawai tidak ditemukan.');
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        if (!is_string($hashedPassword)) {
            sendResponse(500, 'Password baru tidak dapat diproses.');
        }
        $update = $pdo->prepare('UPDATE tb_apk SET pass = :pass WHERE id_peg = :id_peg');
        $update->execute(['pass' => $hashedPassword, 'id_peg' => $decoded['id_peg']]);

        sendResponse(200, 'Password berhasil di-reset. Silakan login kembali dengan password baru Anda!');
    }

    public static function adminResetPassword(): void
    {
        global $pdo;

        $input = jsonRequestBody();
        $idPeg = $input['id_peg'] ?? null;
        if (
            !is_string($idPeg)
            || ($idPeg = trim($idPeg)) === ''
            || strlen($idPeg) > 12
            || !preg_match('/^[A-Za-z0-9._-]+$/', $idPeg)
        ) {
            sendResponse(400, 'ID pegawai wajib diisi dengan format yang valid.');
        }

        $statement = $pdo->prepare(
            'SELECT a.id_peg, a.status, p.nama, p.tgl_lahir
             FROM tb_apk a
             INNER JOIN tb_pegawai p ON p.id_peg = a.id_peg
             WHERE a.id_peg = :id_peg
             LIMIT 1'
        );
        $statement->execute(['id_peg' => $idPeg]);
        $account = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($account)) {
            sendResponse(404, 'Akun login atau data pegawai tidak ditemukan.');
        }

        $dateOfBirthString = (string) ($account['tgl_lahir'] ?? '');
        $dateOfBirth = DateTimeImmutable::createFromFormat('!Y-m-d', $dateOfBirthString);
        $dateErrors = DateTimeImmutable::getLastErrors();
        if (
            $dateOfBirth === false
            || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
            || $dateOfBirth->format('Y-m-d') !== $dateOfBirthString
        ) {
            sendResponse(422, 'Tanggal lahir pegawai belum tersedia atau tidak valid untuk reset password.');
        }

        $temporaryPassword = $dateOfBirth->format('dmy');
        $hashedPassword = password_hash($temporaryPassword, PASSWORD_DEFAULT);
        if (!is_string($hashedPassword)) {
            sendResponse(500, 'Password sementara tidak dapat diproses.');
        }

        $update = $pdo->prepare('UPDATE tb_apk SET pass = :pass WHERE id_peg = :id_peg');
        $update->execute(['pass' => $hashedPassword, 'id_peg' => $idPeg]);

        $canLogin = !SsoAuthenticator::isLoginBlockedStatus($account['status'] ?? null);
        $employeeName = trim((string) ($account['nama'] ?? ''));
        $subject = $employeeName !== '' ? 'pegawai ' . $employeeName : 'pegawai ' . $idPeg;
        $message = 'Password sementara ' . $subject . ' berhasil direset ke tanggal lahir format ddmmyy. Minta pegawai segera menggantinya setelah login.';
        if (!$canLogin) {
            $message .= ' Akun tetap tidak dapat login karena status nonaktif atau non job.';
        }

        sendResponse(200, $message, [
            'id_peg' => $idPeg,
            'nama' => $employeeName,
            'temporary_password' => $temporaryPassword,
            'password_format' => 'ddmmyy',
            'can_login' => $canLogin,
        ]);
    }

    private static function assertDummyAuthEnabled(): void
    {
        $environment = strtolower(trim((string) sso_config_value('APP_ENV', '')));
        $enabledFallback = $environment === 'local' ? 'true' : 'false';
        $enabled = strtolower(trim((string) sso_config_value('SSO_DUMMY_AUTH_ENABLED', $enabledFallback)));
        $remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if (
            !in_array($environment, ['local', 'development'], true)
            || !in_array($enabled, ['1', 'true', 'yes', 'on'], true)
        ) {
            sendResponse(404, 'Endpoint dummy hanya tersedia pada environment local/development saat diaktifkan.');
        }

        if ($environment === 'local') {
            if (!in_array($remoteAddress, ['127.0.0.1', '::1'], true)) {
                sendResponse(404, 'Endpoint dummy lokal hanya tersedia dari localhost.');
            }
            return;
        }

        $allowedIps = array_filter(array_map(
            'trim',
            explode(',', (string) sso_config_value('SSO_DUMMY_ALLOWED_IPS', ''))
        ));
        if ($allowedIps === [] || !in_array($remoteAddress, $allowedIps, true)) {
            sendResponse(404, 'Endpoint dummy tidak tersedia dari alamat IP ini.');
        }

        if ((string) sso_config_value('SSO_DUMMY_PASSWORD', '') === '') {
            sendResponse(503, 'Password dummy belum dikonfigurasi di environment development.');
        }
    }
}
