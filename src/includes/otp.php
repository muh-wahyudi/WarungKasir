<?php
// Pendukung OTP email. Memakai helpers.php (json_out, read_post_json, clean) dan koneksi.php ($pdo).
require_once __DIR__ . '/helpers.php';
require_once '/opt/mail/vendor/autoload.php'; // PHPMailer, dipasang oleh Dockerfile

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

const OTP_TTL_MINUTES    = 5;   // masa berlaku kode
const OTP_MAX_ATTEMPTS   = 5;   // maksimal salah input
const OTP_RESEND_SECONDS = 60;  // jeda kirim ulang
const RESET_TTL_MINUTES  = 10;  // masa berlaku kode reset kata sandi

function otp_generate(): string {
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function otp_send_email(string $to, string $name, string $otp, string $purpose = 'register'): bool {
    $user = getenv('SMTP_USER') ?: '';
    $m = new PHPMailer(true);
    try {
        $m->isSMTP();
        $m->Host       = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
        $m->SMTPAuth   = true;
        $m->Username   = $user;
        $m->Password   = getenv('SMTP_PASS') ?: '';
        $m->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $m->Port       = (int) (getenv('SMTP_PORT') ?: 587);
        $m->CharSet    = 'UTF-8';
        $m->setFrom(getenv('MAIL_FROM') ?: $user, getenv('MAIL_FROM_NAME') ?: 'WarungKasir');
        $m->addAddress($to, $name);
        $m->isHTML(true);
        $reset = $purpose === 'reset';
        $m->Subject = ($reset ? 'Kode reset kata sandi WarungKasir: ' : 'Kode verifikasi WarungKasir: ') . $otp;
        $intro = $reset ? 'Gunakan kode berikut untuk mengatur ulang kata sandi akun WarungKasir Anda:'
                        : 'Gunakan kode berikut untuk menyelesaikan pendaftaran akun WarungKasir Anda:';
        $ignore = $reset ? 'Jika Anda tidak meminta reset kata sandi, abaikan email ini. Kata sandi Anda tidak akan berubah.'
                         : 'Jika Anda tidak merasa mendaftar, abaikan email ini.';
        $n = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $ttl = $reset ? RESET_TTL_MINUTES : OTP_TTL_MINUTES;
        $m->Body = "
          <div style=\"font-family:Arial,sans-serif;max-width:420px;margin:auto;padding:24px\">
            <h2 style=\"margin:0 0 12px\">Halo, {$n}</h2>
            <p>{$intro}</p>
            <p style=\"font-size:32px;font-weight:700;letter-spacing:8px;background:#f3f4f6;padding:16px;text-align:center;border-radius:12px\">{$otp}</p>
            <p>Kode berlaku {$ttl} menit. Jangan bagikan kode ini kepada siapa pun.</p>
            <p style=\"color:#6b7280;font-size:13px\">{$ignore}</p>
          </div>";
        $m->AltBody = "Kode verifikasi WarungKasir Anda: {$otp} (berlaku {$ttl} menit).";
        $m->send();
        return true;
    } catch (MailException $e) {
        error_log('[otp-mail] ' . $m->ErrorInfo);
        return false;
    }
}