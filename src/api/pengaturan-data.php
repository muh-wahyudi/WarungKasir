<?php
require __DIR__ . '/../includes/owner.php';
require __DIR__ . '/../includes/midtrans.php';

const MON = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

function fmt_date(?string $dt): ?string {
    if (!$dt) return null;
    $d = new DateTime($dt, new DateTimeZone(APP_TZ));
    return (int) $d->format('j') . ' ' . MON[(int) $d->format('n')] . ' ' . $d->format('Y');
}

function rate_limit_or_fail(string $key, int $seconds, string $message): void {
    $now = time();
    $last = (int) ($_SESSION['rl_' . $key] ?? 0);
    if ($now - $last < $seconds) {
        json_out(429, ['message' => $message]);
    }
    $_SESSION['rl_' . $key] = $now;
}

function account_data(PDO $pdo, int $uid): array {
    $u = db_one($pdo, 'SELECT full_name, business_name, email, phone, created_at FROM users WHERE id = ?', [$uid]);
    $pr = db_one($pdo, 'SELECT alamat FROM user_profiles WHERE user_id = ?', [$uid]);
    $cur = db_one($pdo, "SELECT s.started_at, s.ends_at, p.id, p.code, p.name, p.price_monthly, p.max_products, p.history_days, p.max_accounts
                         FROM subscriptions s JOIN plans p ON p.id = s.plan_id
                         WHERE s.user_id = ? AND s.status = 'active' ORDER BY s.started_at DESC, s.id DESC LIMIT 1", [$uid])
        ?: db_one($pdo, "SELECT NULL AS started_at, NULL AS ends_at, id, code, name, price_monthly, max_products, history_days, max_accounts
                         FROM plans WHERE code = 'free' LIMIT 1");
    $used = (int) db_one($pdo, 'SELECT COUNT(*) AS n FROM products WHERE owner_id = ? AND is_active = 1', [$uid])['n'];
    $ni = fn($v) => $v === null ? null : (int) $v;
    $shape = fn(array $r) => [
        'id' => (int) $r['id'],
        'code' => $r['code'], 'name' => $r['name'], 'price' => (int) $r['price_monthly'],
        'max_products' => $ni($r['max_products']), 'history_days' => $ni($r['history_days']), 'max_accounts' => $ni($r['max_accounts']),
    ];
    return [
        'profile' => ['full_name' => $u['full_name'], 'email' => $u['email'], 'phone' => $u['phone'], 'member_since' => fmt_date($u['created_at'])],
        'business' => ['business_name' => $u['business_name'], 'alamat' => $pr['alamat'] ?? ''],
        'plan' => $shape($cur) + ['started_at' => fmt_date($cur['started_at']), 'ends_at' => fmt_date($cur['ends_at']), 'used_products' => $used],
        'plans' => array_map($shape, db_all($pdo, 'SELECT id, code, name, price_monthly, max_products, history_days, max_accounts FROM plans ORDER BY price_monthly, id')),
    ];
}

function normalize_phone($raw): ?string {
    $s = preg_replace('/[\s\-\.\(\)]/', '', is_string($raw) ? $raw : '');
    if (!preg_match('/^\+?\d{9,15}$/', $s)) return null;
    $s = ltrim($s, '+');
    return str_starts_with($s, '62') ? '0' . substr($s, 2) : $s;
}

function save_profile(PDO $pdo, int $uid, array $b): void {
    rate_limit_or_fail('profile', 5, 'Tunggu 5 detik sebelum menyimpan lagi.');
    $e = [];
    $name = clean($b['full_name'] ?? '');
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) $e['full_name'] = 'Nama lengkap harus 2–100 karakter.';
    $phone = normalize_phone($b['phone'] ?? '');
    if ($phone === null) $e['phone'] = 'Nomor HP tidak valid. Gunakan 9–15 angka, contoh 081234567890.';
    if ($e) json_out(400, ['message' => 'Periksa kembali data Anda.', 'errors' => $e]);
    try {
        $pdo->prepare('UPDATE users SET full_name = ?, phone = ? WHERE id = ?')->execute([$name, $phone, $uid]);
    } catch (PDOException $ex) {
        if (($ex->errorInfo[1] ?? 0) === 1062) {
            json_out(409, ['message' => 'Nomor HP ini sudah dipakai akun lain.', 'errors' => ['phone' => 'Nomor HP ini sudah dipakai akun lain.']]);
        }
        throw $ex;
    }
    json_out(200, ['message' => 'Profil disimpan.', 'profile' => ['full_name' => $name, 'phone' => $phone]]);
}

function save_business(PDO $pdo, int $uid, array $b): void {
    rate_limit_or_fail('business', 5, 'Tunggu 5 detik sebelum menyimpan lagi.');
    $e = [];
    $biz = clean($b['business_name'] ?? '');
    $alamat = clean($b['alamat'] ?? '');
    if (mb_strlen($biz) < 2 || mb_strlen($biz) > 120) $e['business_name'] = 'Nama usaha harus 2–120 karakter.';
    if (mb_strlen($alamat) > 255) $e['alamat'] = 'Alamat maksimal 255 karakter.';
    if ($e) json_out(400, ['message' => 'Periksa kembali data usaha.', 'errors' => $e]);
    try {
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE users SET business_name = ? WHERE id = ?')->execute([$biz, $uid]);
        $pdo->prepare("INSERT INTO user_profiles (user_id, user_type, alamat) VALUES (?, 'seller', ?)
                       ON DUPLICATE KEY UPDATE alamat = VALUES(alamat)")->execute([$uid, $alamat === '' ? null : $alamat]);
        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $ex;
    }
    json_out(200, ['message' => 'Data usaha disimpan.', 'business' => ['business_name' => $biz, 'alamat' => $alamat]]);
}

function change_password(PDO $pdo, int $uid, array $b): void {
    $cur = is_string($b['current'] ?? null) ? $b['current'] : '';
    $new = is_string($b['password'] ?? null) ? $b['password'] : '';
    $conf = is_string($b['confirm'] ?? null) ? $b['confirm'] : '';
    $e = [];
    if ($cur === '' || strlen($cur) > 200) $e['current'] = 'Kata sandi saat ini wajib diisi.';
    if (strlen($new) < 8) $e['password'] = 'Kata sandi baru minimal 8 karakter.';
    elseif (strlen($new) > 200) $e['password'] = 'Kata sandi baru maksimal 200 karakter.';
    elseif ($new === $cur) $e['password'] = 'Kata sandi baru harus berbeda dari yang lama.';
    if ($conf !== $new) $e['confirm'] = 'Konfirmasi kata sandi tidak sama.';
    if ($e) json_out(400, ['message' => 'Periksa kembali isian Anda.', 'errors' => $e]);

    $me = db_one($pdo, 'SELECT email, password_hash FROM users WHERE id = ?', [$uid]);
    $fails = (int) db_one($pdo, 'SELECT COUNT(*) AS n FROM login_attempts WHERE email = ? AND attempted_at > NOW() - INTERVAL 15 MINUTE', [$me['email']])['n'];
    if ($fails >= 5) json_out(429, ['message' => 'Terlalu banyak percobaan. Coba lagi dalam 15 menit.']);

    if (!password_verify($cur, $me['password_hash'])) {
        $pdo->prepare('INSERT INTO login_attempts (ip, email) VALUES (?, ?)')->execute([$_SERVER['REMOTE_ADDR'] ?? '', substr($me['email'], 0, 190)]);
        json_out(400, ['message' => 'Kata sandi saat ini salah.', 'errors' => ['current' => 'Kata sandi saat ini salah.']]);
    }
    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $uid]);
    $pdo->prepare('DELETE FROM login_attempts WHERE email = ?')->execute([$me['email']]);
    session_regenerate_id(true);
    json_out(200, ['message' => 'Kata sandi berhasil diubah.']);
}

function handle_subscription(PDO $pdo, int $uid, array $b): void {
    $action = $b['action'] ?? '';

    if ($action === 'checkout') {
        $planCode = clean($b['plan_code'] ?? '');
        $plan = db_one($pdo, 'SELECT id, name, price_monthly FROM plans WHERE code = ? AND price_monthly > 0 LIMIT 1', [$planCode]);
        if (!$plan) json_out(400, ['message' => 'Paket tidak valid atau gratis.']);
        $planId = (int) $plan['id'];

        $pdo->prepare("UPDATE subscriptions SET status = 'canceled' WHERE user_id = ? AND status = 'pending_payment'")->execute([$uid]);

        $orderId = 'SUB-' . $uid . '-' . time();
        $amount = (int) $plan['price_monthly'];

        try {
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO subscriptions (user_id, plan_id, status, started_at, payment_ref) VALUES (?, ?, 'pending_payment', NOW(), ?)")
                ->execute([$uid, $planId, $orderId]);

            $r = midtrans_charge_qris($orderId, $amount);
            $qrUrl = $r['actions'][0]['url'] ?? null;
            if (!$qrUrl) throw new Exception('QRIS URL tidak ditemukan.');

            $pdo->commit();
            json_out(200, ['order_id' => $orderId, 'qr_url' => $qrUrl, 'total' => $amount]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            json_out(502, ['message' => 'Gagal membuat pembayaran QRIS. Coba lagi.']);
        }
    }

    if ($action === 'check_status') {
        $orderId = clean($b['order_id'] ?? '');
        $sub = db_one($pdo, "SELECT s.id, s.plan_id, s.status, p.price_monthly
                             FROM subscriptions s
                             JOIN plans p ON p.id = s.plan_id
                             WHERE s.user_id = ? AND s.payment_ref = ? LIMIT 1", [$uid, $orderId]);
        if (!$sub) json_out(404, ['message' => 'Transaksi langganan tidak ditemukan.']);

        if ($sub['status'] === 'active') {
            json_out(200, ['status' => 'active', 'message' => 'Paket sudah aktif.']);
        }

        if ($sub['status'] !== 'pending_payment') {
            json_out(409, ['message' => 'Status langganan tidak dapat diproses: ' . $sub['status'] . '.']);
        }

        $r = midtrans_get_status($orderId);
        $ts = $r['transaction_status'] ?? 'not_found';

        if ($ts !== 'settlement' && $ts !== 'capture') {
            $resp = [
                'status' => 'pending_payment',
                'message' => 'Menunggu pembayaran.',
                'midtrans_status' => $ts,
            ];
            if (getenv('APP_ENV') === 'development') {
                $resp['debug'] = [
                    'order_id_sent' => $orderId,
                    'status_code' => $r['status_code'] ?? '-',
                    'status_message' => $r['status_message'] ?? '-',
                ];
            }
            json_out(200, $resp);
        }

        $gross = (int) round((float) ($r['gross_amount'] ?? 0));
        if ($gross !== (int) $sub['price_monthly']) {
            error_log("[pengaturan] nominal Midtrans ($gross) beda dengan harga paket ({$sub['price_monthly']}) untuk order $orderId");
            json_out(409, ['message' => 'Nominal pembayaran tidak sesuai dengan harga paket. Hubungi admin.']);
        }

        $pdo->beginTransaction();
        try {
            $locked = db_one($pdo, 'SELECT status FROM subscriptions WHERE id = ? FOR UPDATE', [(int) $sub['id']]);
            if (!$locked || $locked['status'] !== 'pending_payment') {
                $pdo->rollBack();
                json_out(200, ['status' => 'active', 'message' => 'Paket sudah aktif.']);
            }

            $pdo->prepare("UPDATE subscriptions SET status = 'expired' WHERE user_id = ? AND status = 'active'")->execute([$uid]);
            $pdo->prepare("UPDATE subscriptions SET status = 'active', started_at = NOW(), ends_at = DATE_ADD(NOW(), INTERVAL 30 DAY)
                           WHERE id = ? AND status = 'pending_payment'")->execute([(int) $sub['id']]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        json_out(200, ['status' => 'active', 'message' => 'Pembayaran diterima. Paket berhasil diaktifkan.']);
    }
}

try {
    $uid = owner_api_guard($pdo);
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if ($method === 'GET') json_out(200, account_data($pdo, $uid));
    if ($method !== 'POST') json_out(405, ['message' => 'Metode tidak diizinkan.']);

    $b = read_post_json();
    $action = is_string($b['action'] ?? null) ? $b['action'] : '';

    if ($action === 'save_profile') save_profile($pdo, $uid, $b);
    if ($action === 'save_business') save_business($pdo, $uid, $b);
    if ($action === 'change_password') change_password($pdo, $uid, $b);
    if (in_array($action, ['checkout', 'check_status'])) handle_subscription($pdo, $uid, $b);

    json_out(400, ['message' => 'Aksi tidak dikenal.']);
} catch (Throwable $ex) {
    error_log('[pengaturan] ' . $ex->getMessage());
    json_out(500, ['message' => 'Terjadi kesalahan pada server.']);
}