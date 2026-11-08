-- =====================================================================
-- WarungKasir: Wallet, Ledger, Biaya QRIS, Rekening Bank, Withdrawal
-- RANCANGAN (tahap 3). Belum divalidasi pada MySQL; jalankan dulu di SALINAN
-- database (bukan produksi) dan cadangkan database sebelum migrasi.
-- Target: MySQL 8.0 (CHECK constraint ditegakkan mulai 8.0.16).
-- Semua nominal dalam Rupiah bulat (BIGINT), tanpa desimal. Tarif dalam
-- basis points (bps): 100 bps = 1%, jadi 0,7% = 70 bps.
-- Tipe kunci asing mengikuti skema yang ada: users.id dan transactions.id = INT (signed).
-- Semua relasi baru memakai ON DELETE RESTRICT (bukan CASCADE) agar catatan
-- keuangan tidak ikut terhapus bila pengguna dihapus.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 1. Kolom tambahan pada tabel transactions (pembayaran QRIS Midtrans)
-- ---------------------------------------------------------------------
ALTER TABLE `transactions`
  ADD COLUMN `midtrans_transaction_id` varchar(64) DEFAULT NULL AFTER `midtrans_order_id`,
  ADD COLUMN `paid_at` datetime DEFAULT NULL AFTER `midtrans_transaction_id`,
  ADD UNIQUE KEY `uq_trx_midtrans_order` (`midtrans_order_id`),
  ADD UNIQUE KEY `uq_trx_midtrans_txid` (`midtrans_transaction_id`);

-- ---------------------------------------------------------------------
-- 2. Konfigurasi wallet (diubah admin; setiap perubahan dicatat di admin_audit_log)
-- ---------------------------------------------------------------------
CREATE TABLE `wallet_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `description` varchar(255) NOT NULL DEFAULT '',
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`),
  CONSTRAINT `fk_wset_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- NILAI AWAL INI HANYA PENGISI. Admin wajib menyesuaikannya dengan kontrak/laporan Midtrans.
INSERT INTO `wallet_settings` (`setting_key`, `setting_value`, `description`) VALUES
('mdr_bps',               '70',    'Tarif MDR QRIS dalam bps (70 = 0,70%). WAJIB disesuaikan dengan ketentuan Midtrans untuk akun ini.'),
('mdr_tax_bps',           '1100',  'Pajak atas MDR dalam bps (1100 = 11%). Sesuaikan dengan ketentuan pajak yang berlaku.'),
('settlement_delay_days', '1',     'Jumlah hari kerja setelah pembayaran sebelum saldo pending menjadi tersedia. Konfirmasi dengan Midtrans.'),
('min_withdrawal',        '10000', 'Nominal minimum withdrawal (Rp).'),
('withdrawal_fee_flat',   '0',     'Biaya transfer per withdrawal (Rp). Isi sesuai biaya transfer aktual.'),
('payout_mode',           'manual','manual = admin mentransfer sendiri. Jangan ubah ke otomatis sebelum disetujui Midtrans.'),
('wallet_start_at',       '',      'KOSONG = wallet belum aktif (tidak ada pembayaran yang dicatat). Isi saat go-live (Y-m-d H:i:s): hanya pembayaran QRIS setelah waktu ini yang masuk wallet.');

-- ---------------------------------------------------------------------
-- 3. Wallet: satu baris per pemilik warung (saldo terpisah per warung)
-- ---------------------------------------------------------------------
CREATE TABLE `wallets` (
  `owner_id` int NOT NULL,
  `pending_balance` bigint NOT NULL DEFAULT 0,
  `available_balance` bigint NOT NULL DEFAULT 0,
  `processing_balance` bigint NOT NULL DEFAULT 0,
  `total_withdrawn` bigint NOT NULL DEFAULT 0,
  `total_fees` bigint NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`owner_id`),
  CONSTRAINT `fk_wallet_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_wallet_pending` CHECK (`pending_balance` >= 0),
  CONSTRAINT `ck_wallet_available` CHECK (`available_balance` >= 0),
  CONSTRAINT `ck_wallet_processing` CHECK (`processing_balance` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Wallet untuk pemilik warung yang sudah ada (saldo awal 0)
INSERT INTO `wallets` (`owner_id`) SELECT `id` FROM `users` WHERE `role` = 'owner';

-- ---------------------------------------------------------------------
-- 4. Rekening bank pemilik warung
--    Nomor rekening disimpan terenkripsi di level aplikasi (AES-256-GCM, kunci di
--    luar database). Hanya 4 digit terakhir yang disimpan terbuka untuk tampilan.
-- ---------------------------------------------------------------------
CREATE TABLE `bank_accounts` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `owner_id` int NOT NULL,
  `bank_code` varchar(20) NOT NULL,
  `bank_name` varchar(80) NOT NULL,
  `account_number_enc` varchar(255) NOT NULL,
  `account_number_hash` char(64) NOT NULL COMMENT 'HMAC-SHA256 untuk deteksi duplikat',
  `account_last4` char(4) NOT NULL,
  `account_holder` varchar(100) NOT NULL,
  `status` enum('pending','verified','rejected','disabled') NOT NULL DEFAULT 'pending',
  `verified_by` int DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `reject_reason` varchar(255) DEFAULT NULL,
  `usable_after` datetime DEFAULT NULL COMMENT 'masa tunggu keamanan setelah rekening baru/diubah',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bank_owner_acct` (`owner_id`, `bank_code`, `account_number_hash`),
  KEY `idx_bank_status` (`status`),
  CONSTRAINT `fk_bank_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_bank_verifier` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
-- 5. Biaya pembayaran QRIS per transaksi (rincian transparan untuk pemilik)
--    Tarif disalin (snapshot) saat pembayaran dicatat, jadi perubahan tarif
--    di kemudian hari tidak mengubah transaksi lama.
-- ---------------------------------------------------------------------
CREATE TABLE `payment_fees` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `transaction_id` int NOT NULL,
  `owner_id` int NOT NULL,
  `midtrans_order_id` varchar(100) NOT NULL,
  `midtrans_transaction_id` varchar(64) NOT NULL,
  `gross_amount` bigint NOT NULL,
  `mdr_bps` int NOT NULL,
  `tax_bps` int NOT NULL,
  `mdr_amount` bigint NOT NULL,
  `tax_amount` bigint NOT NULL,
  `total_fee` bigint NOT NULL,
  `net_amount` bigint NOT NULL,
  `fee_source` enum('estimated','actual') NOT NULL DEFAULT 'estimated',
  `payment_status` varchar(20) NOT NULL DEFAULT 'settlement',
  `paid_at` datetime NOT NULL,
  `release_at` datetime NOT NULL COMMENT 'saat saldo pending boleh menjadi tersedia',
  `released_at` datetime DEFAULT NULL,
  `reversed_at` datetime DEFAULT NULL COMMENT 'diisi bila pembayaran direfund/dibalik saat masih pending',
  `reconciled_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fee_trx` (`transaction_id`),
  UNIQUE KEY `uq_fee_midtrans_txid` (`midtrans_transaction_id`),
  KEY `idx_fee_owner` (`owner_id`, `created_at`),
  KEY `idx_fee_release` (`released_at`, `reversed_at`, `release_at`),
  CONSTRAINT `fk_fee_trx` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_fee_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_fee_math` CHECK (`total_fee` = `mdr_amount` + `tax_amount` AND `net_amount` = `gross_amount` - `total_fee` AND `net_amount` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
-- 6. Withdrawal
--    Data rekening disalin saat pengajuan agar perubahan rekening kemudian
--    tidak mengubah tujuan transfer yang sudah diajukan.
-- ---------------------------------------------------------------------
CREATE TABLE `withdrawals` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `owner_id` int NOT NULL,
  `bank_account_id` int UNSIGNED NOT NULL,
  `idempotency_key` varchar(64) NOT NULL,
  `amount` bigint NOT NULL,
  `transfer_fee` bigint NOT NULL DEFAULT 0,
  `net_amount` bigint NOT NULL,
  `status` enum('Pending','Approved','Processing','Completed','Rejected','Failed') NOT NULL DEFAULT 'Pending',
  `bank_name` varchar(80) NOT NULL,
  `account_number_enc` varchar(255) NOT NULL,
  `account_last4` char(4) NOT NULL,
  `account_holder` varchar(100) NOT NULL,
  `reject_reason` varchar(255) DEFAULT NULL,
  `failure_reason` varchar(255) DEFAULT NULL,
  `transfer_reference` varchar(100) DEFAULT NULL,
  `proof_file` varchar(255) DEFAULT NULL,
  `approved_by` int DEFAULT NULL,
  `processed_by` int DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_at` datetime DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wd_idem` (`owner_id`, `idempotency_key`),
  KEY `idx_wd_status` (`status`, `requested_at`),
  KEY `idx_wd_owner` (`owner_id`, `requested_at`),
  CONSTRAINT `fk_wd_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_wd_bank` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_wd_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_wd_processor` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_wd_amount` CHECK (`amount` > 0 AND `transfer_fee` >= 0 AND `net_amount` = `amount` - `transfer_fee` AND `net_amount` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
-- 7. Ledger wallet (hanya bisa ditambah, tidak boleh diubah/dihapus)
--    Setiap baris mencatat perubahan di tiga kategori saldo dan saldo sesudahnya.
--    idempotency_key UNIQUE mencegah pencatatan ganda (webhook ulang, klik ganda).
-- ---------------------------------------------------------------------
CREATE TABLE `wallet_transactions` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `owner_id` int NOT NULL,
  `entry_type` enum('payment_pending','payment_reversal','settlement_release','withdrawal_hold','withdrawal_complete','withdrawal_release','manual_adjustment') NOT NULL,
  `pending_delta` bigint NOT NULL DEFAULT 0,
  `available_delta` bigint NOT NULL DEFAULT 0,
  `processing_delta` bigint NOT NULL DEFAULT 0,
  `pending_after` bigint NOT NULL,
  `available_after` bigint NOT NULL,
  `processing_after` bigint NOT NULL,
  `ref_type` varchar(30) DEFAULT NULL,
  `ref_id` bigint UNSIGNED DEFAULT NULL,
  `idempotency_key` varchar(100) NOT NULL,
  `description` varchar(255) NOT NULL DEFAULT '',
  `reason` varchar(255) DEFAULT NULL,
  `actor_id` int DEFAULT NULL,
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wtx_idem` (`idempotency_key`),
  KEY `idx_wtx_owner` (`owner_id`, `id`),
  KEY `idx_wtx_ref` (`ref_type`, `ref_id`),
  CONSTRAINT `fk_wtx_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_wtx_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_wtx_reason` CHECK (`entry_type` <> 'manual_adjustment' OR (`reason` IS NOT NULL AND `reason` <> '')),
  CONSTRAINT `ck_wtx_after` CHECK (`pending_after` >= 0 AND `available_after` >= 0 AND `processing_after` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
-- 8. Log audit aktivitas admin (juga hanya bisa ditambah)
-- ---------------------------------------------------------------------
CREATE TABLE `admin_audit_log` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id` int NOT NULL,
  `action` varchar(50) NOT NULL,
  `entity_type` varchar(30) NOT NULL,
  `entity_id` bigint UNSIGNED DEFAULT NULL,
  `detail` json DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  KEY `idx_audit_entity` (`entity_type`, `entity_id`),
  KEY `idx_audit_admin` (`admin_id`, `created_at`),
  CONSTRAINT `fk_audit_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
-- 9. Notifikasi Midtrans yang diterima (idempotensi webhook + bahan rekonsiliasi)
--    event_key = SHA-256 dari order_id|transaction_status|status_code|gross_amount|transaction_time
-- ---------------------------------------------------------------------
CREATE TABLE `midtrans_events` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_key` char(64) NOT NULL,
  `order_id` varchar(100) NOT NULL,
  `transaction_id` varchar(64) DEFAULT NULL,
  `transaction_status` varchar(30) NOT NULL,
  `status_code` varchar(10) NOT NULL,
  `gross_amount` varchar(20) NOT NULL,
  `signature_valid` tinyint(1) NOT NULL,
  `payload` json NOT NULL,
  `result` varchar(60) DEFAULT NULL,
  `received_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mev_key` (`event_key`),
  KEY `idx_mev_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
-- 10. Penjaga immutabilitas: ledger dan audit log menolak UPDATE dan DELETE.
--     Jika dijalankan lewat phpMyAdmin, pakai tab SQL (mendukung DELIMITER).
--     Memerlukan hak TRIGGER pada database.
-- ---------------------------------------------------------------------
DELIMITER $$

CREATE TRIGGER `trg_wtx_no_update` BEFORE UPDATE ON `wallet_transactions` FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ledger wallet tidak dapat diubah.';
END$$

CREATE TRIGGER `trg_wtx_no_delete` BEFORE DELETE ON `wallet_transactions` FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ledger wallet tidak dapat dihapus.';
END$$

CREATE TRIGGER `trg_audit_no_update` BEFORE UPDATE ON `admin_audit_log` FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit log tidak dapat diubah.';
END$$

CREATE TRIGGER `trg_audit_no_delete` BEFORE DELETE ON `admin_audit_log` FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit log tidak dapat dihapus.';
END$$

DELIMITER ;

-- =====================================================================
-- Pemeriksaan konsistensi (dijalankan berkala / saat pengujian).
-- Hasil kedua query harus KOSONG.
-- =====================================================================
-- Saldo wallet harus sama dengan jumlah seluruh perubahan di ledger:
--   SELECT w.owner_id
--   FROM wallets w
--   LEFT JOIN (SELECT owner_id, SUM(pending_delta) p, SUM(available_delta) a, SUM(processing_delta) r
--              FROM wallet_transactions GROUP BY owner_id) l ON l.owner_id = w.owner_id
--   WHERE w.pending_balance <> COALESCE(l.p,0) OR w.available_balance <> COALESCE(l.a,0) OR w.processing_balance <> COALESCE(l.r,0);
--
-- Saldo processing harus sama dengan withdrawal yang belum selesai:
--   SELECT w.owner_id
--   FROM wallets w
--   LEFT JOIN (SELECT owner_id, SUM(amount) s FROM withdrawals
--              WHERE status IN ('Pending','Approved','Processing') GROUP BY owner_id) x ON x.owner_id = w.owner_id
--   WHERE w.processing_balance <> COALESCE(x.s,0);