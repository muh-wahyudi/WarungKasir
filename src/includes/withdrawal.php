<?php
// Rekening bank dan Withdrawal: pengajuan pemilik warung dan proses admin.
// Semua operasi saldo memakai fungsi ledger di wallet.php (satu transaksi database, baris wallet dikunci).
//
// Alur status:  Pending -> Approved -> Processing -> Completed
//               Pending/Approved -> Rejected        (saldo kembali ke tersedia)
//               Processing -> Failed                (saldo kembali ke tersedia)
require_once __DIR__ . '/wallet.php';

const WD_OPEN = ['Pending', 'Approved', 'Processing'];
const WD_PROOF_MAX = 3 * 1024 * 1024;

// Galat yang pesannya aman ditampilkan ke pengguna. $field = nama isian formulir yang bermasalah.
class WdError extends RuntimeException {
    public int $http;
    public ?string $field;
    public function __construct(string $msg, int $http = 400, ?string $field = null) {
        parent::__construct($msg);
        $this->http = $http;
        $this->field = $field;
    }
}

function wd_fail(WdError $e) {
    json_out($e->http, ['message' => $e->getMessage(), 'errors' => $e->field ? [$e->field => $e->getMessage()] : (object) []]);
}

function wd_banks(): array {
    return [
        'BCA' => 'BCA', 'BRI' => 'BRI', 'BNI' => 'BNI', 'MANDIRI' => 'Mandiri', 'BSI' => 'BSI (Bank Syariah Indonesia)',
        'CIMB' => 'CIMB Niaga', 'PERMATA' => 'Permata', 'DANAMON' => 'Danamon', 'BTN' => 'BTN', 'BTPN' => 'BTPN / Jenius',
        'JAGO' => 'Bank Jago', 'SEABANK' => 'SeaBank', 'OCBC' => 'OCBC', 'MAYBANK' => 'Maybank',
        'DANA' => 'DANA', 'OVO' => 'OVO', 'GOPAY' => 'GoPay',
    ];
}

/* ---------- Enkripsi nomor rekening ---------- */

// Kunci dari environment variable WALLET_ENC_KEY (disarankan untuk produksi). Bila belum diatur, MODE UJI:
// kunci acak dibuat sekali dan disimpan di database. Mode uji tidak melindungi bila database bocor.
function wd_key(PDO $pdo): string {
    static $k = null;
    if ($k !== null) return $k;
    $env = getenv('WALLET_ENC_KEY');
    if (is_string($env) && strlen($env) >= 16) return $k = hash('sha256', $env, true);
    $pdo->prepare("INSERT IGNORE INTO wallet_settings (setting_key, setting_value, description) VALUES ('enc_key_dev', ?, 'KUNCI MODE UJI. Atur WALLET_ENC_KEY di environment untuk produksi.')")
        ->execute([bin2hex(random_bytes(32))]);
    $row = db_one($pdo, "SELECT setting_value FROM wallet_settings WHERE setting_key = 'enc_key_dev'");
    return $k = hash('sha256', (string) $row['setting_value'], true);
}

function wd_key_is_dev(): bool {
    $env = getenv('WALLET_ENC_KEY');
    return !(is_string($env) && strlen($env) >= 16);
}

function wd_encrypt(PDO $pdo, string $plain): string {
    $iv = random_bytes(12);
    $c = openssl_encrypt($plain, 'aes-256-gcm', wd_key($pdo), OPENSSL_RAW_DATA, $iv, $tag);
    if ($c === false) throw new RuntimeException('Enkripsi gagal.');
    return base64_encode($iv . $tag . $c);
}

function wd_decrypt(PDO $pdo, string $enc): string {
    $raw = base64_decode($enc, true);
    if ($raw === false || strlen($raw) < 29) throw new RuntimeException('Data rekening rusak.');
    $p = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', wd_key($pdo), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    if ($p === false) throw new RuntimeException('Data rekening tidak dapat dibuka (kunci enkripsi berbeda?).');
    return $p;
}

function wd_acct_hash(PDO $pdo, string $number): string { return hash_hmac('sha256', $number, wd_key($pdo)); }

/* ---------- Pengamanan ---------- */

// Memastikan permintaan memang dari halaman kita (tambahan di luar cookie SameSite dan cek Origin).
function wd_require_xhr(): void {
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'fetch') json_out(403, ['message' => 'Permintaan ditolak.']);
}

// Konfirmasi kata sandi untuk tindakan sensitif, dengan pembatasan percobaan (tabel login_attempts yang sama).
function wd_check_password(PDO $pdo, int $uid, $pw, string $field = 'password'): void {
    $pw = is_string($pw) ? $pw : '';
    if ($pw === '' || strlen($pw) > 200) throw new WdError('Masukkan kata sandi Anda untuk konfirmasi.', 400, $field);
    $me = db_one($pdo, 'SELECT email, password_hash FROM users WHERE id = ?', [$uid]);
    $fails = (int) db_one($pdo, 'SELECT COUNT(*) AS n FROM login_attempts WHERE email = ? AND attempted_at > NOW() - INTERVAL 15 MINUTE', [$me['email']])['n'];
    if ($fails >= 5) throw new WdError('Terlalu banyak percobaan. Coba lagi dalam 15 menit.', 429);
    if (!password_verify($pw, $me['password_hash'])) {
        $pdo->prepare('INSERT INTO login_attempts (ip, email) VALUES (?, ?)')->execute([$_SERVER['REMOTE_ADDR'] ?? '', substr($me['email'], 0, 190)]);
        throw new WdError('Kata sandi salah.', 400, $field);
    }
    $pdo->prepare('DELETE FROM login_attempts WHERE email = ?')->execute([$me['email']]);
}

function admin_api_guard(PDO $pdo, bool $masterOnly = false): array {
    if (empty($_SESSION['user_id'])) json_out(401, ['message' => 'Sesi berakhir. Silakan masuk kembali.']);
    $me = db_one($pdo, 'SELECT id, full_name, role, is_master FROM users WHERE id = ? AND is_active = 1 LIMIT 1', [$_SESSION['user_id']]);
    if (!$me) json_out(401, ['message' => 'Sesi tidak valid. Silakan masuk kembali.']);
    if ($me['role'] !== 'admin') json_out(403, ['message' => 'Akses ditolak.']);
    if ($masterOnly && (int) $me['is_master'] !== 1) json_out(403, ['message' => 'Hanya master admin yang dapat melakukan tindakan ini.']);
    return ['id' => (int) $me['id'], 'name' => $me['full_name'], 'master' => (int) $me['is_master'] === 1];
}

/* ---------- Rekening bank (pemilik) ---------- */

function wd_add_bank(PDO $pdo, int $uid, array $b): array {
    $banks = wd_banks();
    $code = is_string($b['bank_code'] ?? null) ? $b['bank_code'] : '';
    if (!isset($banks[$code])) throw new WdError('Pilih bank atau dompet digital.', 400, 'bank_code');
    $num = preg_replace('/[\s\-\.]/', '', is_string($b['account_number'] ?? null) ? $b['account_number'] : '');
    if (!preg_match('/^\d{5,20}$/', $num)) throw new WdError('Nomor rekening harus 5–20 angka.', 400, 'account_number');
    $holder = clean($b['account_holder'] ?? '');
    if (mb_strlen($holder) < 2 || mb_strlen($holder) > 100 || !preg_match("/^[\p{L}\p{M}\s.'\-]+$/u", $holder)) {
        throw new WdError('Nama pemilik rekening harus 2–100 huruf, sesuai buku tabungan.', 400, 'account_holder');
    }
    wd_check_password($pdo, $uid, $b['password'] ?? '');

    $active = (int) db_one($pdo, "SELECT COUNT(*) AS n FROM bank_accounts WHERE owner_id = ? AND status <> 'disabled'", [$uid])['n'];
    if ($active >= 5) throw new WdError('Maksimal 5 rekening. Nonaktifkan rekening yang tidak dipakai terlebih dahulu.', 409);

    try {
        $pdo->prepare('INSERT INTO bank_accounts (owner_id, bank_code, bank_name, account_number_enc, account_number_hash, account_last4, account_holder)
                       VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$uid, $code, $banks[$code], wd_encrypt($pdo, $num), wd_acct_hash($pdo, $num), substr($num, -4), $holder]);
    } catch (PDOException $e) {
        if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
            // Rekening yang sama yang sudah dinonaktifkan boleh diaktifkan kembali dengan verifikasi ulang
            $old = db_one($pdo, 'SELECT id, status FROM bank_accounts WHERE owner_id = ? AND bank_code = ? AND account_number_hash = ?', [$uid, $code, wd_acct_hash($pdo, $num)]);
            if ($old && $old['status'] === 'disabled') {
                $pdo->prepare("UPDATE bank_accounts SET status = 'pending', account_holder = ?, verified_by = NULL, verified_at = NULL, reject_reason = NULL WHERE id = ? AND status = 'disabled'")
                    ->execute([$holder, (int) $old['id']]);
                return ['id' => (int) $old['id']];
            }
            throw new WdError('Rekening ini sudah terdaftar.', 409, 'account_number');
        }
        throw $e;
    }
    return ['id' => (int) $pdo->lastInsertId()];
}

function wd_disable_bank(PDO $pdo, int $uid, int $bankId): void {
    wallet_tx($pdo, function () use ($pdo, $uid, $bankId) {
        $b = db_one($pdo, 'SELECT id, status FROM bank_accounts WHERE id = ? AND owner_id = ? FOR UPDATE', [$bankId, $uid]);
        if (!$b) throw new WdError('Rekening tidak ditemukan.', 404);
        $open = db_one($pdo, "SELECT id FROM withdrawals WHERE bank_account_id = ? AND status IN ('Pending','Approved','Processing') LIMIT 1", [$bankId]);
        if ($open) throw new WdError('Rekening sedang dipakai oleh withdrawal yang belum selesai.', 409);
        $pdo->prepare("UPDATE bank_accounts SET status = 'disabled' WHERE id = ?")->execute([$bankId]);
    });
}

/* ---------- Pengajuan withdrawal (pemilik) ---------- */

function wd_lock_wallet(PDO $pdo, int $uid): array {
    $sql = 'SELECT pending_balance, available_balance, processing_balance FROM wallets WHERE owner_id = ? FOR UPDATE';
    $w = db_one($pdo, $sql, [$uid]);
    if (!$w) { wallet_ensure($pdo, $uid); $w = db_one($pdo, $sql, [$uid]); }
    return $w;
}

// Mengajukan withdrawal. Aman diulang dengan idempotency key yang sama (mengembalikan pengajuan yang sama).
function wd_request(PDO $pdo, int $uid, $bankId, $amountRaw, $key): array {
    $key = is_string($key) ? $key : '';
    if (!preg_match('/^[A-Za-z0-9_-]{16,64}$/', $key)) throw new WdError('Permintaan tidak valid. Muat ulang halaman lalu coba lagi.', 400);
    $bankId = is_int($bankId) ? $bankId : (is_string($bankId) && ctype_digit($bankId) ? (int) $bankId : 0);
    if ($bankId <= 0) throw new WdError('Pilih rekening tujuan.', 400, 'bank_id');
    $amount = is_int($amountRaw) ? $amountRaw : (is_string($amountRaw) && preg_match('/^\d{1,12}$/', $amountRaw) ? (int) $amountRaw : 0);
    if ($amount <= 0) throw new WdError('Masukkan nominal withdrawal.', 400, 'amount');

    return wallet_tx($pdo, function () use ($pdo, $uid, $bankId, $amount, $key) {
        $w = wd_lock_wallet($pdo, $uid);   // serialkan semua pengajuan satu warung

        $dup = db_one($pdo, 'SELECT * FROM withdrawals WHERE owner_id = ? AND idempotency_key = ?', [$uid, $key]);
        if ($dup) return ['withdrawal' => $dup, 'duplicate' => true];

        $bank = db_one($pdo, 'SELECT * FROM bank_accounts WHERE id = ? AND owner_id = ?', [$bankId, $uid]);
        if (!$bank) throw new WdError('Rekening tidak ditemukan.', 404, 'bank_id');
        if ($bank['status'] !== 'verified') throw new WdError('Rekening belum diverifikasi admin, sehingga belum dapat dipakai.', 409, 'bank_id');

        $maxOpen = max(1, (int) wallet_cfg($pdo, 'max_open_withdrawals', '1'));
        $open = (int) db_one($pdo, "SELECT COUNT(*) AS n FROM withdrawals WHERE owner_id = ? AND status IN ('Pending','Approved','Processing')", [$uid])['n'];
        if ($open >= $maxOpen) throw new WdError('Masih ada pengajuan withdrawal yang belum selesai. Tunggu sampai selesai sebelum mengajukan lagi.', 409);

        $min = max(1, (int) wallet_cfg($pdo, 'min_withdrawal', '10000'));
        if ($amount < $min) throw new WdError('Nominal minimum withdrawal Rp' . number_format($min, 0, ',', '.') . '.', 400, 'amount');
        if ($amount > (int) $w['available_balance']) throw new WdError('Nominal melebihi saldo tersedia (Rp' . number_format((int) $w['available_balance'], 0, ',', '.') . ').', 400, 'amount');

        $fee = max(0, (int) wallet_cfg($pdo, 'withdrawal_fee_flat', '0'));
        $net = $amount - $fee;
        if ($net <= 0) throw new WdError('Nominal harus lebih besar dari biaya transfer (Rp' . number_format($fee, 0, ',', '.') . ').', 400, 'amount');

        $pdo->prepare('INSERT INTO withdrawals (owner_id, bank_account_id, idempotency_key, amount, transfer_fee, net_amount, bank_name, account_number_enc, account_last4, account_holder)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$uid, $bankId, $key, $amount, $fee, $net, $bank['bank_name'], $bank['account_number_enc'], $bank['account_last4'], $bank['account_holder']]);
        $id = (int) $pdo->lastInsertId();
        try {
            wallet_withdrawal_hold($pdo, $uid, $id, $amount);
        } catch (WalletException $e) {
            throw new WdError('Saldo tersedia tidak mencukupi.', 409, 'amount');
        }
        return ['withdrawal' => db_one($pdo, 'SELECT * FROM withdrawals WHERE id = ?', [$id]), 'duplicate' => false];
    });
}

/* ---------- Proses admin ---------- */

function wd_admin_transition(PDO $pdo, int $adminId, int $wdId, string $action, array $in): array {
    $rules = [   // aksi => [status asal, status tujuan]
        'approve'  => [['Pending'], 'Approved'],
        'reject'   => [['Pending', 'Approved'], 'Rejected'],
        'process'  => [['Approved'], 'Processing'],
        'complete' => [['Processing'], 'Completed'],
        'fail'     => [['Processing'], 'Failed'],
    ];
    if (!isset($rules[$action])) throw new WdError('Aksi tidak dikenal.', 400);
    [$from, $to] = $rules[$action];
    $reason = clean($in['reason'] ?? '');
    $ref = clean($in['transfer_reference'] ?? '');
    if (in_array($action, ['reject', 'fail'], true) && mb_strlen($reason) < 3) throw new WdError('Alasan wajib diisi (minimal 3 karakter).', 400, 'reason');
    if (mb_strlen($reason) > 255) throw new WdError('Alasan maksimal 255 karakter.', 400, 'reason');
    if ($action === 'complete' && (mb_strlen($ref) < 3 || mb_strlen($ref) > 100)) throw new WdError('Nomor referensi transfer wajib diisi (3–100 karakter).', 400, 'transfer_reference');

    return wallet_tx($pdo, function () use ($pdo, $adminId, $wdId, $action, $from, $to, $reason, $ref) {
        $w = db_one($pdo, 'SELECT * FROM withdrawals WHERE id = ? FOR UPDATE', [$wdId]);
        if (!$w) throw new WdError('Withdrawal tidak ditemukan.', 404);
        if (!in_array($w['status'], $from, true)) throw new WdError('Status saat ini (' . $w['status'] . ') tidak dapat diubah dengan aksi ini. Muat ulang halaman.', 409);
        $uid = (int) $w['owner_id'];
        $amount = (int) $w['amount'];

        switch ($action) {
            case 'approve':
                $sql = "UPDATE withdrawals SET status = 'Approved', approved_by = ?, approved_at = NOW() WHERE id = ? AND status = ?"; $args = [$adminId];
                break;
            case 'process':
                $sql = "UPDATE withdrawals SET status = 'Processing', processed_by = ?, processed_at = NOW() WHERE id = ? AND status = ?"; $args = [$adminId];
                break;
            case 'complete':
                $sql = "UPDATE withdrawals SET status = 'Completed', transfer_reference = ?, completed_at = NOW() WHERE id = ? AND status = ?"; $args = [$ref];
                break;
            case 'reject':
                $sql = "UPDATE withdrawals SET status = 'Rejected', reject_reason = ?, approved_by = ?, completed_at = NOW() WHERE id = ? AND status = ?"; $args = [$reason, $adminId];
                break;
            default: // fail
                $sql = "UPDATE withdrawals SET status = 'Failed', failure_reason = ?, completed_at = NOW() WHERE id = ? AND status = ?"; $args = [$reason];
        }
        $u = $pdo->prepare($sql);
        $u->execute(array_merge($args, [$wdId, $w['status']]));
        if ($u->rowCount() !== 1) throw new WdError('Status sudah berubah. Muat ulang halaman.', 409);

        if ($action === 'complete') wallet_withdrawal_complete($pdo, $uid, $wdId, $amount);
        if ($action === 'reject') wallet_withdrawal_release($pdo, $uid, $wdId, $amount, 'ditolak admin');
        if ($action === 'fail') wallet_withdrawal_release($pdo, $uid, $wdId, $amount, 'gagal ditransfer');

        audit_log($pdo, $adminId, 'withdrawal_' . $action, 'withdrawal', $wdId,
            ['from' => $w['status'], 'to' => $to, 'owner_id' => $uid, 'amount' => $amount, 'transfer_reference' => $ref ?: null], $reason ?: null);
        return db_one($pdo, 'SELECT * FROM withdrawals WHERE id = ?', [$wdId]);
    });
}

// Admin melihat nomor rekening lengkap untuk keperluan transfer. Setiap pembukaan dicatat di audit log.
function wd_reveal_account(PDO $pdo, int $adminId, int $wdId): array {
    $w = db_one($pdo, 'SELECT id, owner_id, bank_name, account_number_enc, account_holder, status FROM withdrawals WHERE id = ?', [$wdId]);
    if (!$w) throw new WdError('Withdrawal tidak ditemukan.', 404);
    $num = wd_decrypt($pdo, $w['account_number_enc']);
    audit_log($pdo, $adminId, 'reveal_account', 'withdrawal', $wdId, ['owner_id' => (int) $w['owner_id']]);
    return ['bank_name' => $w['bank_name'], 'account_number' => $num, 'account_holder' => $w['account_holder']];
}

function wd_review_bank(PDO $pdo, int $adminId, int $bankId, string $decision, string $reason): void {
    if (!in_array($decision, ['verify', 'reject'], true)) throw new WdError('Aksi tidak dikenal.', 400);
    $reason = clean($reason);
    if ($decision === 'reject' && mb_strlen($reason) < 3) throw new WdError('Alasan penolakan wajib diisi.', 400, 'reason');
    wallet_tx($pdo, function () use ($pdo, $adminId, $bankId, $decision, $reason) {
        $b = db_one($pdo, 'SELECT id, owner_id, status FROM bank_accounts WHERE id = ? FOR UPDATE', [$bankId]);
        if (!$b) throw new WdError('Rekening tidak ditemukan.', 404);
        if ($b['status'] !== 'pending') throw new WdError('Rekening ini sudah diproses (status: ' . $b['status'] . ').', 409);
        if ($decision === 'verify') {
            $pdo->prepare("UPDATE bank_accounts SET status = 'verified', verified_by = ?, verified_at = NOW(), reject_reason = NULL WHERE id = ?")->execute([$adminId, $bankId]);
        } else {
            $pdo->prepare("UPDATE bank_accounts SET status = 'rejected', verified_by = ?, verified_at = NOW(), reject_reason = ? WHERE id = ?")->execute([$adminId, mb_substr($reason, 0, 255), $bankId]);
        }
        audit_log($pdo, $adminId, 'bank_' . $decision, 'bank_account', $bankId, ['owner_id' => (int) $b['owner_id']], $reason ?: null);
    });
}

/* ---------- Bukti transfer ---------- */

function wd_proof_dir(): string { return realpath(__DIR__ . '/..') . '/uploads/bukti'; }

function wd_save_proof(PDO $pdo, int $adminId, int $wdId, ?array $f): string {
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'] ?? '')) throw new WdError('Pilih file bukti transfer.', 400, 'proof');
    if ($f['size'] > WD_PROOF_MAX) throw new WdError('Ukuran file maksimal 3 MB.', 400, 'proof');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'][$mime] ?? null;
    if (!$ext) throw new WdError('Format file harus JPG, PNG, atau PDF.', 400, 'proof');

    $w = db_one($pdo, 'SELECT id, status, proof_file FROM withdrawals WHERE id = ?', [$wdId]);
    if (!$w) throw new WdError('Withdrawal tidak ditemukan.', 404);
    if (!in_array($w['status'], ['Processing', 'Completed'], true)) throw new WdError('Bukti hanya dapat diunggah saat withdrawal Processing atau Completed.', 409, 'proof');

    $dir = wd_proof_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) throw new RuntimeException('Folder bukti tidak dapat dibuat.');
    if (!is_file($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
    $name = $wdId . '-' . bin2hex(random_bytes(12)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) throw new RuntimeException('File bukti gagal disimpan.');

    $pdo->prepare('UPDATE withdrawals SET proof_file = ? WHERE id = ?')->execute([$name, $wdId]);
    if ($w['proof_file'] && preg_match('/^[0-9]+-[a-f0-9]{24}\.(jpg|png|pdf)$/', $w['proof_file'])) @unlink($dir . '/' . $w['proof_file']);
    audit_log($pdo, $adminId, 'upload_proof', 'withdrawal', $wdId, ['file' => $name]);
    return $name;
}