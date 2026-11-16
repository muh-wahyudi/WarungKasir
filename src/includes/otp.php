<?php
require_once __DIR__ . '/helpers.php';
require_once '/opt/mail/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

const OTP_TTL_MINUTES    = 5;
const OTP_MAX_ATTEMPTS   = 5;
const OTP_RESEND_SECONDS = 60;
const RESET_TTL_MINUTES  = 10;

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
        $n = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

        if ($purpose === 'email_changed') {
            $m->Subject = 'Email akun WarungKasir Anda telah diubah';
            $m->Body = "
              <div style=\"font-family:Arial,sans-serif;max-width:420px;margin:auto;padding:24px\">
                <h2 style=\"margin:0 0 12px\">Halo, {$n}</h2>
                <p>Email akun WarungKasir Anda baru saja diubah ke alamat baru.</p>
                <p>Jika Anda tidak melakukan perubahan ini, segera hubungi admin WarungKasir.</p>
                <p style=\"color:#6b7280;font-size:13px\">Email ini dikirim ke alamat lama sebagai notifikasi keamanan.</p>
              </div>";
            $m->AltBody = "Email akun WarungKasir Anda telah diubah. Jika bukan Anda, hubungi admin.";
            $m->send();
            return true;
        }

        $isEmailChange = $purpose === 'email_change';
        $reset = $purpose === 'reset';
        $ttl = $reset ? RESET_TTL_MINUTES : ($isEmailChange ? 10 : OTP_TTL_MINUTES);

        $m->Subject = ($isEmailChange ? 'Kode verifikasi email baru WarungKasir: ' : ($reset ? 'Kode reset kata sandi WarungKasir: ' : 'Kode verifikasi WarungKasir: ')) . $otp;

        if ($isEmailChange) {
            $intro = 'Gunakan kode berikut untuk memverifikasi alamat email baru akun WarungKasir Anda:';
            $ignore = 'Jika Anda tidak meminta perubahan email, abaikan email ini. Email akun Anda tidak akan berubah.';
        } elseif ($reset) {
            $intro = 'Gunakan kode berikut untuk mengatur ulang kata sandi akun WarungKasir Anda:';
            $ignore = 'Jika Anda tidak meminta reset kata sandi, abaikan email ini. Kata sandi Anda tidak akan berubah.';
        } else {
            $intro = 'Gunakan kode berikut untuk menyelesaikan pendaftaran akun WarungKasir Anda:';
            $ignore = 'Jika Anda tidak merasa mendaftar, abaikan email ini.';
        }

        $m->Body = "
          <div style=\"font-family:Arial,sans-serif;max-width:420px;margin:auto;padding:24px\">
            <h2 style=\"margin:0 0 12px\">Halo, {$n}</h2>
            <p>{$intro}</p>
            <p style=\"font-size:32px;font-weight:700;letter-spacing:8px;background:#f3f4f6;padding:16px;text-align:center;border-radius:12px\">{$otp}</p>
            <p>Kode berlaku {$ttl} menit. Jangan bagikan kode ini kepada siapa pun.</p>
            <p style=\"color:#6b7280;font-size:13px\">{$ignore}</p>
          </div>";
        $m->AltBody = "Kode WarungKasir Anda: {$otp} (berlaku {$ttl} menit).";
        $m->send();
        return true;
    } catch (MailException $e) {
        error_log('[otp-mail] ' . $m->ErrorInfo);
        return false;
    }
}

function otp_send_team_invite(string $to, string $businessName, string $role, string $link): bool {
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
        $m->addAddress($to);
        $m->isHTML(true);
        $roleLabel = $role === 'admin' ? 'Admin' : 'Kasir';
        $biz = htmlspecialchars($businessName, ENT_QUOTES, 'UTF-8');
        $l = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
        $m->Subject = 'Undangan bergabung ke ' . $businessName . ' di WarungKasir';
        $m->Body = "
          <div style=\"font-family:Arial,sans-serif;max-width:480px;margin:auto;padding:24px\">
            <h2 style=\"margin:0 0 12px\">Undangan Staf</h2>
            <p>Anda diundang untuk bergabung sebagai <b>{$roleLabel}</b> di warung <b>{$biz}</b> pada platform WarungKasir.</p>
            <p>Klik tombol di bawah untuk membuat kata sandi dan mengaktifkan akun Anda:</p>
            <p style=\"text-align:center;margin:24px 0\"><a href=\"{$l}\" style=\"display:inline-block;padding:14px 28px;background:#16A34A;color:#fff;text-decoration:none;border-radius:10px;font-weight:700\">Terima Undangan</a></p>
            <p style=\"color:#6b7280;font-size:13px\">Atau salin link ini: {$l}</p>
            <p style=\"color:#6b7280;font-size:13px\">Link berlaku 72 jam. Jika Anda tidak merasa diundang, abaikan email ini.</p>
          </div>";
        $m->AltBody = "Undangan bergabung sebagai {$roleLabel} di {$businessName}. Buka: {$link}";
        $m->send();
        return true;
    } catch (MailException $e) {
        error_log('[otp-mail] ' . $m->ErrorInfo);
        return false;
    }
}