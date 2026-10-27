<?php
// File: helpers/MailHelper.php

require_once __DIR__ . '/../vendor/autoload.php'; // Ini tetep butuh buat PHPMailer-nya ya
require_once __DIR__ . '/../config/env.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailHelper {

    public static function sendOTP($toEmail, $toName, $otpCode) {
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Alamat email penerima tidak valid.');
        }

        $env = [];
        foreach (['SMTP_HOST', 'SMTP_USER', 'SMTP_PASS', 'SMTP_PORT'] as $key) {
            $value = app_env_value($key);
            if ($value === null || trim($value) === '') {
                throw new \RuntimeException("Konfigurasi {$key} belum tersedia.");
            }
            $env[$key] = $value;
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $env['SMTP_HOST']; // Ambil dari $env
            $mail->SMTPAuth   = true;
            $mail->Username   = $env['SMTP_USER']; 
            $mail->Password   = $env['SMTP_PASS']; 
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $env['SMTP_PORT'];

            // Pengirim & Penerima
            // Pengirim & Penerima (Email pengirim WAJIB SAMA dengan akun SMTP_USER)
            $mail->setFrom($env['SMTP_USER'], 'SSO BKK Jateng');
            $mail->addAddress($toEmail, $toName);

            // Konten Email
            $safeName = htmlspecialchars((string)$toName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safeOtp = htmlspecialchars((string)$otpCode, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $mail->isHTML(true);
            $mail->Subject = 'Kode OTP Reset Password - BKK Jateng';
            $mail->Body    = "
                <div style='font-family: Arial, sans-serif; max-width: 500px; margin: auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
                    <h3 style='color: #333;'>Halo, {$safeName}</h3>
                    <p>Anda meminta untuk mereset password. Berikut adalah kode OTP Anda:</p>
                    <div style='text-align: center; margin: 20px 0;'>
                        <h2 style='color: #0056b3; letter-spacing: 5px; font-size: 32px; background: #f4f4f4; padding: 15px; border-radius: 8px; display: inline-block;'>{$safeOtp}</h2>
                    </div>
                    <p>Kode ini hanya berlaku selama <strong>5 menit</strong>. Jangan berikan kode ini kepada siapapun termasuk pihak BKK!</p>
                </div>
            ";

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('[API] OTP email delivery failed: ' . ($mail->ErrorInfo ?: $e->getMessage()));
            throw new \RuntimeException('Gagal mengirim email OTP. Silakan coba lagi nanti.');
        }
    }
}
