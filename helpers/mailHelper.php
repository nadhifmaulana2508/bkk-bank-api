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

        $host = trim((string) $env['SMTP_HOST']);
        $port = filter_var($env['SMTP_PORT'], FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 65535],
        ]);
        if (
            (!filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) && !filter_var($host, FILTER_VALIDATE_IP))
            || $port === false
        ) {
            throw new \RuntimeException('Konfigurasi SMTP_HOST atau SMTP_PORT tidak valid.');
        }

        $encryption = strtolower(trim((string) app_env_value(
            'SMTP_ENCRYPTION',
            (int) $port === 465 ? 'ssl' : 'tls'
        )));
        if (!in_array($encryption, ['ssl', 'tls', 'none'], true)) {
            throw new \RuntimeException('Konfigurasi SMTP_ENCRYPTION tidak valid.');
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $env['SMTP_USER']; 
            $mail->Password   = $env['SMTP_PASS']; 
            $mail->SMTPSecure = $encryption === 'ssl'
                ? PHPMailer::ENCRYPTION_SMTPS
                : ($encryption === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : '');
            $mail->Port       = (int) $port;
            $mail->Timeout    = 15;

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
