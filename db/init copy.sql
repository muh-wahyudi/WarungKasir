CREATE DATABASE IF NOT EXISTS warungkasir_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE warungkasir_db;

-- =====================================================================
-- 1. TABEL USERS
-- =====================================================================
CREATE TABLE IF NOT EXISTS users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  full_name     VARCHAR(100) NOT NULL,
  business_name VARCHAR(120) NOT NULL,
  email         VARCHAR(190) NOT NULL,
  phone         VARCHAR(20) NOT NULL,
  role          ENUM('owner','admin') NOT NULL DEFAULT 'owner',
  is_master     TINYINT(1) NOT NULL DEFAULT 0,
  password_hash VARCHAR(255) NOT NULL,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 2. TABEL PLANS (Paket Langganan)
-- =====================================================================
CREATE TABLE IF NOT EXISTS plans (
  id            TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code          VARCHAR(20) NOT NULL,
  name          VARCHAR(50) NOT NULL,
  price_monthly INT UNSIGNED NOT NULL DEFAULT 0,
  max_products  INT UNSIGNED NULL,
  history_days  INT UNSIGNED NULL,
  max_accounts  INT UNSIGNED NULL,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_plans_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO plans (code, name, price_monthly, max_products, history_days, max_accounts, is_active) VALUES
  ('free',   'Free',    0,     30,   30,   1,    1),
  ('pro',    'Pro',     29000, 200,  365,  2,    0),
  ('promax', 'Pro Max', 59000, NULL, NULL, 5,    0)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- =====================================================================
-- 3. TABEL SUBSCRIPTIONS
-- =====================================================================
CREATE TABLE IF NOT EXISTS subscriptions (
  id          INT NOT NULL AUTO_INCREMENT,
  user_id     INT NOT NULL,
  plan_id     TINYINT UNSIGNED NOT NULL,
  status      ENUM('active','pending_payment','expired','canceled') NOT NULL,
  started_at  DATETIME NOT NULL,
  ends_at     DATETIME NULL,
  payment_ref VARCHAR(100) NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_sub_user_status (user_id, status),
  CONSTRAINT fk_sub_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_sub_plan FOREIGN KEY (plan_id) REFERENCES plans(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 4. TABEL LOGIN ATTEMPTS
-- =====================================================================
CREATE TABLE IF NOT EXISTS login_attempts (
  id           INT NOT NULL AUTO_INCREMENT,
  ip           VARCHAR(45) NOT NULL,
  email        VARCHAR(190) NULL,
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_attempts_ip_time (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 5. TABEL PRODUCTS
-- =====================================================================
CREATE TABLE IF NOT EXISTS products (
  id         INT NOT NULL AUTO_INCREMENT,
  owner_id   INT NOT NULL,
  name       VARCHAR(120) NOT NULL,
  sku        VARCHAR(40) NOT NULL,
  image      VARCHAR(255) NULL,
  price      INT UNSIGNED NOT NULL,
  stock      INT NOT NULL DEFAULT 0,
  min_stock  INT NOT NULL DEFAULT 5,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_products_owner_name (owner_id, name),
  UNIQUE KEY uq_products_owner_sku (owner_id, sku),
  KEY idx_products_owner (owner_id, is_active),
  CONSTRAINT fk_products_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 6. TABEL TRANSACTIONS
-- =====================================================================
CREATE TABLE IF NOT EXISTS transactions (
  id             INT NOT NULL AUTO_INCREMENT,
  owner_id       INT NOT NULL,
  code           VARCHAR(20) NOT NULL,
  customer_name  VARCHAR(100) NOT NULL DEFAULT 'Pelanggan Umum',
  total          INT UNSIGNED NOT NULL,
  payment_method ENUM('Tunai','QRIS','Transfer') NOT NULL,
  status         ENUM('Selesai','Menunggu','Dibatalkan') NOT NULL DEFAULT 'Selesai',
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_trx_code (owner_id, code),
  KEY idx_trx_owner_date (owner_id, created_at),
  KEY idx_trx_owner_status (owner_id, status),
  CONSTRAINT fk_trx_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 7. TABEL TRANSACTION ITEMS
-- =====================================================================
CREATE TABLE IF NOT EXISTS transaction_items (
  id             INT NOT NULL AUTO_INCREMENT,
  transaction_id INT NOT NULL,
  product_id     INT NULL,
  product_name   VARCHAR(120) NOT NULL,
  qty            INT UNSIGNED NOT NULL,
  unit_price     INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY idx_items_trx (transaction_id),
  KEY idx_items_product (product_id),
  CONSTRAINT fk_items_trx FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
  CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 8. TABEL SCAN REQUESTS
-- =====================================================================
CREATE TABLE IF NOT EXISTS scan_requests (
  id           INT NOT NULL AUTO_INCREMENT,
  user_id      INT NOT NULL,
  status       ENUM('pending','approved','completed','rejected','expired','canceled') NOT NULL DEFAULT 'pending',
  barcode      VARCHAR(100) NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at   DATETIME NOT NULL,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_scan_user_status (user_id, status),
  KEY idx_scan_expires (expires_at),
  CONSTRAINT fk_scan_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 9. AKUN MASTER ADMIN
-- =====================================================================
-- Akun master: admin@warungkasir.local
-- Kata sandi default: (Hubungi pemilik proyek jika lupa)
-- Hash di bawah ini adalah untuk kata sandi: Password123!
INSERT INTO users (full_name, business_name, email, phone, password_hash, role, is_master)
VALUES ('Administrator', 'WarungKasir', 'admin@warungkasir.local', '-', '$2y$12$1CJ/AiRPQf3G.VKfynT6zeCgFYJrESEEaf4N2r484VLW2oqw0RVtG', 'admin', 1)
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);