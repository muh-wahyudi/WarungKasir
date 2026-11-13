-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: db
-- Waktu pembuatan: 08 Nov 2026 pada 13.33
-- Versi server: 8.0.46
-- Versi PHP: 8.3.35

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Basis data: `warungkasir_db`
--
CREATE DATABASE IF NOT EXISTS `warungkasir_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE `warungkasir_db`;

-- --------------------------------------------------------

--
-- Struktur dari tabel `admin_audit_log`
--

CREATE TABLE `admin_audit_log` (
  `id` bigint UNSIGNED NOT NULL,
  `admin_id` int NOT NULL,
  `action` varchar(50) NOT NULL,
  `entity_type` varchar(30) NOT NULL,
  `entity_id` bigint UNSIGNED DEFAULT NULL,
  `detail` json DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `admin_audit_log`
--

INSERT INTO `admin_audit_log` (`id`, `admin_id`, `action`, `entity_type`, `entity_id`, `detail`, `reason`, `ip`, `created_at`) VALUES
(1, 3, 'bank_verify', 'bank_account', 1, '{\"owner_id\": 2}', NULL, '172.18.0.1', '2026-11-07 10:18:34.830'),
(2, 3, 'wallet_adjust', 'wallet', 2, '{\"delta\": 10000, \"bucket\": \"available\", \"ledger_id\": 2}', 'PTTTTT', '172.18.0.1', '2026-11-07 10:24:23.885'),
(3, 3, 'reveal_account', 'withdrawal', 1, '{\"owner_id\": 2}', NULL, '172.18.0.1', '2026-11-07 10:24:57.639'),
(4, 3, 'withdrawal_approve', 'withdrawal', 1, '{\"to\": \"Approved\", \"from\": \"Pending\", \"amount\": 10000, \"owner_id\": 2, \"transfer_reference\": null}', NULL, '172.18.0.1', '2026-11-07 10:25:02.927'),
(5, 3, 'withdrawal_process', 'withdrawal', 1, '{\"to\": \"Processing\", \"from\": \"Approved\", \"amount\": 10000, \"owner_id\": 2, \"transfer_reference\": null}', NULL, '172.18.0.1', '2026-11-07 11:00:18.082'),
(6, 3, 'reveal_account', 'withdrawal', 1, '{\"owner_id\": 2}', NULL, '172.18.0.1', '2026-11-07 11:00:48.800'),
(7, 3, 'upload_proof', 'withdrawal', 1, '{\"file\": \"1-a7c0429d1ae22fd90b6dcc6d.png\"}', NULL, '172.18.0.1', '2026-11-07 11:01:04.863'),
(8, 3, 'reveal_account', 'withdrawal', 1, '{\"owner_id\": 2}', NULL, '172.18.0.1', '2026-11-07 11:01:14.497'),
(9, 3, 'withdrawal_complete', 'withdrawal', 1, '{\"to\": \"Completed\", \"from\": \"Processing\", \"amount\": 10000, \"owner_id\": 2, \"transfer_reference\": \"0888888888\"}', NULL, '172.18.0.1', '2026-11-07 11:01:20.131'),
(10, 3, 'reveal_account', 'withdrawal', 1, '{\"owner_id\": 2}', NULL, '172.18.0.1', '2026-11-07 11:09:00.066'),
(11, 3, 'release_payment', 'payment_fee', 1, '{\"net\": 49611, \"owner_id\": 2, \"ledger_id\": 5}', 'UJI COBA', '172.18.0.1', '2026-11-07 15:07:20.424'),
(12, 3, 'reveal_account', 'withdrawal', 2, '{\"owner_id\": 2}', NULL, '172.18.0.1', '2026-11-07 15:08:02.112'),
(13, 3, 'withdrawal_approve', 'withdrawal', 2, '{\"to\": \"Approved\", \"from\": \"Pending\", \"amount\": 30000, \"owner_id\": 2, \"transfer_reference\": null}', NULL, '172.18.0.1', '2026-11-07 15:08:05.351'),
(14, 3, 'reveal_account', 'withdrawal', 2, '{\"owner_id\": 2}', NULL, '172.18.0.1', '2026-11-07 15:08:13.894'),
(15, 3, 'withdrawal_process', 'withdrawal', 2, '{\"to\": \"Processing\", \"from\": \"Approved\", \"amount\": 30000, \"owner_id\": 2, \"transfer_reference\": null}', NULL, '172.18.0.1', '2026-11-07 15:08:16.783'),
(16, 3, 'reveal_account', 'withdrawal', 2, '{\"owner_id\": 2}', NULL, '172.18.0.1', '2026-11-07 15:08:21.261'),
(17, 3, 'upload_proof', 'withdrawal', 2, '{\"file\": \"2-e2b10321aec1a95a8a65c422.png\"}', NULL, '172.18.0.1', '2026-11-07 15:08:37.918'),
(18, 3, 'withdrawal_complete', 'withdrawal', 2, '{\"to\": \"Completed\", \"from\": \"Processing\", \"amount\": 30000, \"owner_id\": 2, \"transfer_reference\": \"0888888888\"}', NULL, '172.18.0.1', '2026-11-07 15:08:45.843'),
(19, 3, 'reveal_account', 'withdrawal', 3, '{\"owner_id\": 2}', NULL, '172.18.0.1', '2026-11-08 14:45:50.818'),
(20, 3, 'withdrawal_approve', 'withdrawal', 3, '{\"to\": \"Approved\", \"from\": \"Pending\", \"amount\": 19611, \"owner_id\": 2, \"transfer_reference\": null}', NULL, '172.18.0.1', '2026-11-08 14:46:07.039'),
(21, 3, 'withdrawal_process', 'withdrawal', 3, '{\"to\": \"Processing\", \"from\": \"Approved\", \"amount\": 19611, \"owner_id\": 2, \"transfer_reference\": null}', NULL, '172.18.0.1', '2026-11-08 14:46:38.528'),
(22, 3, 'upload_proof', 'withdrawal', 3, '{\"file\": \"3-4200bff88f66f9ea10b91232.png\"}', NULL, '172.18.0.1', '2026-11-08 14:47:10.672'),
(23, 3, 'withdrawal_complete', 'withdrawal', 3, '{\"to\": \"Completed\", \"from\": \"Processing\", \"amount\": 19611, \"owner_id\": 2, \"transfer_reference\": \"99999\"}', NULL, '172.18.0.1', '2026-11-08 14:47:18.521'),
(24, 3, 'wallet_adjust', 'wallet', 2, '{\"delta\": 100000, \"bucket\": \"available\", \"ledger_id\": 15}', 'HHHUUU', '172.18.0.1', '2026-11-08 14:49:55.215'),
(25, 3, 'release_payment', 'payment_fee', 2, '{\"net\": 69456, \"owner_id\": 2, \"ledger_id\": 16}', 'UUUUUUUUUU', '172.18.0.1', '2026-11-08 14:50:29.512'),
(26, 3, 'release_payment', 'payment_fee', 7, '{\"net\": 5953, \"owner_id\": 2, \"ledger_id\": 18}', 'UJI COBA', '172.18.0.1', '2026-11-08 14:54:53.543'),
(27, 3, 'release_payment', 'payment_fee', 8, '{\"net\": 9922, \"owner_id\": 2, \"ledger_id\": 20}', 'LAGIII', '172.18.0.1', '2026-11-08 14:56:53.247'),
(28, 3, 'withdrawal_approve', 'withdrawal', 4, '{\"to\": \"Approved\", \"from\": \"Pending\", \"amount\": 100000, \"owner_id\": 2, \"transfer_reference\": null}', NULL, '172.18.0.1', '2026-11-08 14:57:38.422'),
(29, 3, 'withdrawal_process', 'withdrawal', 4, '{\"to\": \"Processing\", \"from\": \"Approved\", \"amount\": 100000, \"owner_id\": 2, \"transfer_reference\": null}', NULL, '172.18.0.1', '2026-11-08 14:57:44.254'),
(30, 3, 'upload_proof', 'withdrawal', 4, '{\"file\": \"4-3a705d7f5893632edc1e1b90.png\"}', NULL, '172.18.0.1', '2026-11-08 14:57:59.533'),
(31, 3, 'withdrawal_complete', 'withdrawal', 4, '{\"to\": \"Completed\", \"from\": \"Processing\", \"amount\": 100000, \"owner_id\": 2, \"transfer_reference\": \"111111111111111\"}', NULL, '172.18.0.1', '2026-11-08 14:58:07.230'),
(32, 3, 'wallet_adjust', 'wallet', 2, '{\"delta\": -10000, \"bucket\": \"pending\", \"ledger_id\": 23}', 'UJI COBA', '172.18.0.1', '2026-11-08 15:01:43.110');

-- --------------------------------------------------------

--
-- Struktur dari tabel `bank_accounts`
--

CREATE TABLE `bank_accounts` (
  `id` int UNSIGNED NOT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `bank_accounts`
--

INSERT INTO `bank_accounts` (`id`, `owner_id`, `bank_code`, `bank_name`, `account_number_enc`, `account_number_hash`, `account_last4`, `account_holder`, `status`, `verified_by`, `verified_at`, `reject_reason`, `usable_after`, `created_at`, `updated_at`) VALUES
(1, 2, 'GOPAY', 'GoPay', 'cGqqNX0O1RVzQO39yfIU9OOi/12aDO/ZW9hOumRcY98d4fAbZao=', '64377e00d933316db44dbf40725257c810912fafe31d555434e733cdfa54dff4', '8888', 'USER', 'verified', 3, '2026-11-07 10:18:34', NULL, NULL, '2026-11-07 03:17:34', '2026-11-07 03:18:34');

-- --------------------------------------------------------

--
-- Struktur dari tabel `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int NOT NULL,
  `ip` varchar(45) NOT NULL,
  `email` varchar(190) DEFAULT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `midtrans_events`
--

CREATE TABLE `midtrans_events` (
  `id` bigint UNSIGNED NOT NULL,
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
  `processed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `payment_fees`
--

CREATE TABLE `payment_fees` (
  `id` bigint UNSIGNED NOT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ;

--
-- Dumping data untuk tabel `payment_fees`
--

INSERT INTO `payment_fees` (`id`, `transaction_id`, `owner_id`, `midtrans_order_id`, `midtrans_transaction_id`, `gross_amount`, `mdr_bps`, `tax_bps`, `mdr_amount`, `tax_amount`, `total_fee`, `net_amount`, `fee_source`, `payment_status`, `paid_at`, `release_at`, `released_at`, `reversed_at`, `reconciled_at`, `created_at`) VALUES
(1, 59, 2, 'WK-2-59-1794021547', '8269b11b-8000-4575-9a4e-d32f5c771821', 50000, 70, 1100, 350, 39, 389, 49611, 'estimated', 'settlement', '2026-11-07 10:19:14', '2026-11-09 12:00:00', '2026-11-07 15:07:20', NULL, NULL, '2026-11-07 03:19:17'),
(2, 60, 2, 'WK-2-60-1794055384', '575e5980-ca71-425d-a3b9-1dce01d88690', 70000, 70, 1100, 490, 54, 544, 69456, 'estimated', 'settlement', '2026-11-07 19:43:32', '2026-11-09 12:00:00', '2026-11-08 14:50:29', NULL, NULL, '2026-11-07 12:43:34'),
(3, 61, 2, 'WK-2-61-1794055599', 'a75d87e8-54e1-493d-af31-a97916988835', 10000, 70, 1100, 70, 8, 78, 9922, 'estimated', 'settlement', '2026-11-07 19:46:50', '2026-11-09 12:00:00', NULL, NULL, NULL, '2026-11-07 12:46:53'),
(4, 63, 2, 'WK-2-63-1794120018', 'a61d4619-e35b-48c0-a941-bd9362f4f6f7', 6000, 70, 1100, 42, 5, 47, 5953, 'estimated', 'settlement', '2026-11-08 13:40:34', '2026-11-09 12:00:00', NULL, NULL, NULL, '2026-11-08 06:40:35'),
(5, 64, 2, 'WK-2-64-1794120949', '5a449b58-9209-467a-8f5a-4d8059b7cd74', 12000, 70, 1100, 84, 9, 93, 11907, 'estimated', 'settlement', '2026-11-08 13:57:06', '2026-11-09 12:00:00', NULL, NULL, NULL, '2026-11-08 06:57:08'),
(6, 65, 2, 'WK-2-65-1794123494', 'ab5676f5-4700-433e-8c44-155d65995008', 3000, 70, 1100, 21, 2, 23, 2977, 'estimated', 'settlement', '2026-11-08 14:38:46', '2026-11-09 12:00:00', NULL, NULL, NULL, '2026-11-08 07:38:48'),
(7, 66, 2, 'WK-2-66-1794124300', '612837b0-3a9b-4469-b482-0fc6f1e11f50', 6000, 70, 1100, 42, 5, 47, 5953, 'estimated', 'settlement', '2026-11-08 14:52:48', '2026-11-09 12:00:00', '2026-11-08 14:54:53', NULL, NULL, '2026-11-08 07:52:49'),
(8, 67, 2, 'WK-2-67-1794124542', '226fa068-ccdf-4f43-972c-683b061fa1a7', 10000, 70, 1100, 70, 8, 78, 9922, 'estimated', 'settlement', '2026-11-08 14:56:12', '2026-11-09 12:00:00', '2026-11-08 14:56:53', NULL, NULL, '2026-11-08 07:56:12');

-- --------------------------------------------------------

--
-- Struktur dari tabel `plans`
--

CREATE TABLE `plans` (
  `id` tinyint UNSIGNED NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(50) NOT NULL,
  `price_monthly` int UNSIGNED NOT NULL DEFAULT '0',
  `max_products` int UNSIGNED DEFAULT NULL,
  `history_days` int UNSIGNED DEFAULT NULL,
  `max_accounts` int UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `plans`
--

INSERT INTO `plans` (`id`, `code`, `name`, `price_monthly`, `max_products`, `history_days`, `max_accounts`, `is_active`) VALUES
(1, 'free', 'Free', 0, 30, 30, 1, 1),
(2, 'pro', 'Pro', 29000, 200, 365, 2, 0),
(3, 'promax', 'Pro Max', 59000, NULL, NULL, 5, 0);

-- --------------------------------------------------------

--
-- Struktur dari tabel `products`
--

CREATE TABLE `products` (
  `id` int NOT NULL,
  `owner_id` int NOT NULL,
  `name` varchar(120) NOT NULL,
  `sku` varchar(40) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `price` int UNSIGNED NOT NULL,
  `stock` int NOT NULL DEFAULT '0',
  `min_stock` int NOT NULL DEFAULT '5',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `products`
--

INSERT INTO `products` (`id`, `owner_id`, `name`, `sku`, `image`, `price`, `stock`, `min_stock`, `is_active`, `created_at`, `updated_at`) VALUES
(7, 2, 'indomie', '76677662', 'produk/2/c82a95a60452256794e73949059e5036.jpg', 3000, 91, 5, 1, '2026-11-02 05:40:24', '2026-11-08 06:40:18'),
(8, 2, 'Mie Sedap selection', '8998866202343', 'produk/2/942e6241a364c8e9245fb2980c10a88f.jpg', 3500, 959, 5, 1, '2026-11-03 14:03:13', '2026-11-06 03:58:55'),
(9, 2, 'botol CLU', '896867700326', NULL, 10000, 9998, 5, 1, '2026-11-04 11:44:18', '2026-11-08 07:55:42'),
(10, 2, 'AIR', '8996129800626', NULL, 3000, 189, 5, 1, '2026-11-08 06:39:07', '2026-11-08 07:51:40');

-- --------------------------------------------------------

--
-- Struktur dari tabel `scan_requests`
--

CREATE TABLE `scan_requests` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `status` enum('pending','approved','completed','rejected','expired','canceled') NOT NULL DEFAULT 'pending',
  `scan_mode` varchar(10) NOT NULL DEFAULT 'kasir',
  `barcode` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `scan_requests`
--

INSERT INTO `scan_requests` (`id`, `user_id`, `status`, `scan_mode`, `barcode`, `created_at`, `expires_at`, `updated_at`) VALUES
(51, 2, 'completed', 'kasir', '8998866202343', '2026-11-04 06:16:26', '2026-11-04 13:21:26', '2026-11-04 06:17:00'),
(52, 2, 'completed', 'kasir', '8998866202343:4', '2026-11-04 06:23:40', '2026-11-04 13:28:40', '2026-11-04 06:24:10'),
(53, 2, 'completed', 'kasir', '8998866202343:54,3331866202343:1,8518866202343:1,8945303202343:1,075596202343:1,3975763202343:1,075466202343:1,3092866202343:1,975506202343:1', '2026-11-04 06:24:19', '2026-11-04 13:29:19', '2026-11-04 06:27:30'),
(54, 2, 'completed', 'kasir', '8998866202343:21', '2026-11-04 06:36:33', '2026-11-04 13:41:33', '2026-11-04 06:38:42'),
(55, 2, 'completed', 'produk', '054442200061:1,896867700326:1', '2026-11-04 11:42:42', '2026-11-04 18:47:42', '2026-11-04 11:42:56'),
(56, 2, 'completed', 'produk', '896867700326:1', '2026-11-04 11:43:21', '2026-11-04 18:48:20', '2026-11-04 11:43:39'),
(57, 2, 'completed', 'kasir', '896867700326:2,8998866202343:1', '2026-11-04 11:44:59', '2026-11-04 18:49:59', '2026-11-04 11:45:38'),
(58, 2, 'completed', 'produk', '896867700326:1', '2026-11-06 03:06:31', '2026-11-06 10:11:31', '2026-11-06 03:06:41'),
(59, 2, 'completed', 'produk', '896867700326:1', '2026-11-06 03:13:33', '2026-11-06 10:18:33', '2026-11-06 03:13:43'),
(60, 2, 'completed', 'produk', '896867700326:2,3800007700326:1', '2026-11-06 03:14:28', '2026-11-06 10:19:28', '2026-11-06 03:14:40'),
(61, 2, 'completed', 'produk', '896867700326:1', '2026-11-06 03:16:48', '2026-11-06 10:21:48', '2026-11-06 03:16:58'),
(62, 2, 'completed', 'produk', '896867700326:1', '2026-11-06 03:17:27', '2026-11-06 10:22:27', '2026-11-06 03:17:43'),
(63, 2, 'completed', 'produk', '896867700326:1', '2026-11-06 03:19:38', '2026-11-06 10:24:38', '2026-11-06 03:19:49'),
(64, 2, 'completed', 'produk', '896867700326:1', '2026-11-06 03:21:41', '2026-11-06 10:26:41', '2026-11-06 03:21:49'),
(65, 2, 'expired', 'kasir', NULL, '2026-11-06 04:02:18', '2026-11-06 11:07:18', '2026-11-06 04:07:19'),
(66, 2, 'canceled', 'kasir', NULL, '2026-11-06 04:02:39', '2026-11-06 11:07:39', '2026-11-06 04:02:41'),
(67, 2, 'completed', 'kasir', '896867700326:1', '2026-11-06 04:02:43', '2026-11-06 11:07:43', '2026-11-06 04:02:56'),
(68, 2, 'completed', 'produk', '896867700326:2,603861700326:1', '2026-11-07 12:41:19', '2026-11-07 19:46:19', '2026-11-07 12:41:29'),
(69, 2, 'completed', 'kasir', '896867700326:7', '2026-11-07 12:42:15', '2026-11-07 19:47:15', '2026-11-07 12:43:05'),
(70, 9, 'canceled', 'produk', NULL, '2026-11-08 06:25:39', '2026-11-08 13:30:39', '2026-11-08 06:27:17'),
(71, 9, 'canceled', 'produk', NULL, '2026-11-08 06:27:25', '2026-11-08 13:32:25', '2026-11-08 06:27:56'),
(72, 2, 'completed', 'produk', '8996129800626:1', '2026-11-08 06:28:33', '2026-11-08 13:33:33', '2026-11-08 06:28:41'),
(73, 9, 'canceled', 'produk', NULL, '2026-11-08 06:28:48', '2026-11-08 13:33:48', '2026-11-08 06:29:42'),
(74, 9, 'completed', 'produk', '8996129800626:1', '2026-11-08 06:32:09', '2026-11-08 13:37:09', '2026-11-08 06:32:19'),
(76, 2, 'completed', 'produk', '8996129800626:1', '2026-11-08 06:38:11', '2026-11-08 13:43:11', '2026-11-08 06:38:21'),
(77, 2, 'completed', 'kasir', '8996129800626:3', '2026-11-08 06:39:22', '2026-11-08 13:44:22', '2026-11-08 06:40:05'),
(78, 2, 'completed', 'produk', '7996106800626:1,4953126800626:1,8996129800626:1', '2026-11-08 06:51:59', '2026-11-08 13:56:59', '2026-11-08 06:52:57'),
(79, 2, 'completed', 'produk', '933190000058:1', '2026-11-08 06:53:10', '2026-11-08 13:58:10', '2026-11-08 06:53:16'),
(80, 2, 'completed', 'produk', '7996106800626:1,8996129800626:1', '2026-11-08 06:53:36', '2026-11-08 13:58:36', '2026-11-08 06:53:42'),
(81, 2, 'completed', 'produk', '8996129800626:1', '2026-11-08 06:53:50', '2026-11-08 13:58:50', '2026-11-08 06:53:54'),
(82, 2, 'completed', 'kasir', '8996129800626:4', '2026-11-08 06:54:51', '2026-11-08 13:59:51', '2026-11-08 06:55:49');

-- --------------------------------------------------------

--
-- Struktur dari tabel `subscriptions`
--

CREATE TABLE `subscriptions` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `plan_id` tinyint UNSIGNED NOT NULL,
  `status` enum('active','pending_payment','expired','canceled') NOT NULL,
  `started_at` datetime NOT NULL,
  `ends_at` datetime DEFAULT NULL,
  `payment_ref` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `subscriptions`
--

INSERT INTO `subscriptions` (`id`, `user_id`, `plan_id`, `status`, `started_at`, `ends_at`, `payment_ref`, `created_at`) VALUES
(1, 1, 1, 'active', '2026-10-19 06:56:15', NULL, NULL, '2026-10-19 06:56:15'),
(2, 2, 1, 'expired', '2026-10-25 06:50:21', NULL, NULL, '2026-10-25 06:50:21'),
(3, 4, 1, 'active', '2026-10-26 06:16:05', NULL, NULL, '2026-10-26 06:16:05'),
(4, 6, 1, 'active', '2026-10-31 09:15:05', NULL, NULL, '2026-10-31 02:15:05'),
(10, 2, 2, 'canceled', '2026-11-06 10:49:20', NULL, 'SUB-2-1793936960', '2026-11-06 03:49:20'),
(11, 2, 2, 'canceled', '2026-11-06 11:03:06', NULL, 'SUB-2-1793937786', '2026-11-06 04:03:06'),
(14, 2, 2, 'active', '2026-11-06 11:06:30', '2026-12-06 11:06:30', 'SUB-2-1793937977', '2026-11-06 04:06:17'),
(15, 8, 1, 'active', '2026-11-08 13:20:31', NULL, NULL, '2026-11-08 06:20:31'),
(16, 9, 1, 'expired', '2026-11-08 13:21:38', NULL, NULL, '2026-11-08 06:21:38'),
(17, 9, 2, 'active', '2026-11-08 13:36:33', '2026-12-08 13:36:33', 'SUB-9-1794119736', '2026-11-08 06:35:36');

-- --------------------------------------------------------

--
-- Struktur dari tabel `transactions`
--

CREATE TABLE `transactions` (
  `id` int NOT NULL,
  `owner_id` int NOT NULL,
  `code` varchar(20) NOT NULL,
  `customer_name` varchar(100) NOT NULL DEFAULT 'Pelanggan Umum',
  `total` int UNSIGNED NOT NULL,
  `payment_method` enum('Tunai','QRIS','Transfer') NOT NULL,
  `status` enum('Selesai','Menunggu','Dibatalkan') NOT NULL DEFAULT 'Selesai',
  `midtrans_order_id` varchar(100) DEFAULT NULL,
  `midtrans_transaction_id` varchar(64) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `transactions`
--

INSERT INTO `transactions` (`id`, `owner_id`, `code`, `customer_name`, `total`, `payment_method`, `status`, `midtrans_order_id`, `midtrans_transaction_id`, `paid_at`, `created_at`) VALUES
(1, 2, 'TRX-00001', 'Pelanggan Umum', 50000, 'Tunai', 'Selesai', NULL, NULL, NULL, '2026-11-01 08:47:33'),
(2, 2, 'TRX-00002', 'Pelanggan Umum', 50000, 'QRIS', 'Dibatalkan', NULL, NULL, NULL, '2026-11-01 08:47:45'),
(3, 2, 'TRX-00003', 'Pelanggan Umum', 50000, 'Tunai', 'Selesai', NULL, NULL, NULL, '2026-11-01 12:17:57'),
(4, 2, 'TRX-00004', 'Pelanggan Umum', 50000, 'Tunai', 'Selesai', NULL, NULL, NULL, '2026-11-01 12:18:28'),
(5, 2, 'TRX-00005', 'Pelanggan Umum', 3000, 'Tunai', 'Selesai', NULL, NULL, NULL, '2026-11-02 13:32:54'),
(6, 2, 'TRX-00006', 'Pelanggan Umum', 3000, 'Tunai', 'Selesai', NULL, NULL, NULL, '2026-11-02 14:18:59'),
(7, 2, 'TRX-00007', 'Pelanggan Umum', 3000, 'QRIS', 'Dibatalkan', 'WK-2-1793672160', NULL, NULL, '2026-11-03 09:16:00'),
(8, 2, 'TRX-00008', 'Pelanggan Umum', 3000, 'QRIS', 'Selesai', 'WK-2-1793672217', NULL, NULL, '2026-11-03 09:16:57'),
(9, 2, 'TRX-00009', 'Pelanggan Umum', 3000, 'Transfer', 'Dibatalkan', NULL, NULL, NULL, '2026-11-03 09:20:58'),
(10, 2, 'TRX-00010', 'Pelanggan Umum', 6000, 'Tunai', 'Selesai', NULL, NULL, NULL, '2026-11-03 18:16:50'),
(11, 2, 'TRX-00011', 'Pelanggan Umum', 9000, 'QRIS', 'Selesai', 'WK-2-1793705087', NULL, NULL, '2026-11-03 18:24:47'),
(12, 2, 'TRX-00012', 'Pelanggan Umum', 17500, 'Tunai', 'Selesai', NULL, NULL, NULL, '2026-11-03 21:04:37'),
(13, 2, 'TRX-00013', 'Pelanggan Umum', 38500, 'Tunai', 'Selesai', NULL, NULL, NULL, '2026-11-03 21:07:26'),
(14, 2, 'TRX-00014', 'Pelanggan Umum', 38500, 'Tunai', 'Selesai', NULL, NULL, NULL, '2026-11-03 21:09:41'),
(15, 2, 'TRX-00015', 'Pelanggan Umum', 7000, 'Tunai', 'Selesai', NULL, NULL, NULL, '2026-11-03 21:11:57'),
(16, 2, 'TRX-00016', 'Pelanggan Umum', 10500, 'Tunai', 'Selesai', NULL, NULL, NULL, '2026-11-03 21:13:39'),
(17, 2, 'TRX-00017', 'Pelanggan Umum', 3500, 'Tunai', 'Selesai', NULL, NULL, NULL, '2026-11-03 21:15:30'),
(18, 2, 'TRX-00018', 'Pelanggan Umum', 7000, 'Tunai', 'Selesai', NULL, NULL, NULL, '2026-11-03 21:24:42'),
(19, 2, 'TRX-00019', 'Pelanggan Umum', 7000, 'Tunai', 'Selesai', NULL, NULL, NULL, '2026-11-03 21:29:08'),
(20, 2, 'TRX-00020', 'Pelanggan Umum', 7000, 'Tunai', 'Selesai', NULL, NULL, NULL, '2026-11-03 21:29:59'),
(21, 2, 'TRX-00021', 'Pelanggan Umum', 10500, 'Tunai', 'Selesai', NULL, NULL, NULL, '2026-11-03 21:33:05'),
(22, 2, 'TRX-00022', 'Pelanggan Umum', 3500, 'Tunai', 'Selesai', NULL, NULL, NULL, '2026-11-03 21:34:13'),
(23, 2, 'TRX-00023', 'Pelanggan Umum', 3500, 'Tunai', 'Selesai', NULL, NULL, NULL, '2026-11-03 21:36:37'),
(24, 2, 'TRX-00024', 'Pelanggan Umum', 10500, 'Tunai', 'Selesai', NULL, NULL, NULL, '2026-11-03 21:38:30'),
(25, 2, 'TRX-00025', 'Pelanggan Umum', 7000, 'Tunai', 'Selesai', NULL, NULL, NULL, '2026-11-03 21:40:53'),
(26, 2, 'TRX-00026', 'Pelanggan Umum', 7000, 'Tunai', 'Selesai', NULL, NULL, NULL, '2026-11-03 21:50:10'),
(27, 2, 'TRX-00027', 'Pelanggan Umum', 10500, 'Tunai', 'Selesai', NULL, NULL, NULL, '2026-11-03 21:54:21'),
(28, 2, 'TRX-00028', 'Pelanggan Umum', 7000, 'Tunai', 'Selesai', NULL, NULL, NULL, '2026-11-03 21:55:24'),
(29, 2, 'TRX-00029', 'Pelanggan Umum', 7000, 'Tunai', 'Selesai', NULL, NULL, NULL, '2026-11-03 21:58:46'),
(30, 2, 'TRX-00030', 'Pelanggan Umum', 7000, 'Tunai', 'Selesai', NULL, NULL, NULL, '2026-11-03 22:02:25'),
(31, 2, 'TRX-00031', 'Pelanggan Umum', 10500, 'Tunai', 'Selesai', NULL, NULL, NULL, '2026-11-03 22:05:37'),
(32, 2, 'TRX-00032', 'Pelanggan Umum', 3500, 'Tunai', 'Selesai', NULL, NULL, NULL, '2026-11-04 08:03:11'),
(33, 2, 'TRX-00033', 'Pelanggan Umum', 24500, 'QRIS', 'Selesai', 'WK-2-1793754527', NULL, NULL, '2026-11-04 08:08:47'),
(34, 2, 'TRX-00034', 'Pelanggan Umum', 17500, 'QRIS', 'Selesai', 'WK-2-1793760392', NULL, NULL, '2026-11-04 09:46:32'),
(35, 2, 'TRX-00035', 'Pelanggan Umum', 21000, 'Tunai', 'Selesai', NULL, NULL, NULL, '2026-11-04 10:23:30'),
(36, 2, 'TRX-00036', 'Pelanggan Umum', 7000, 'Tunai', 'Selesai', NULL, NULL, NULL, '2026-11-04 10:32:16'),
(37, 2, 'TRX-00037', 'Pelanggan Umum', 17500, 'Tunai', 'Selesai', NULL, NULL, NULL, '2026-11-04 13:00:23'),
(38, 2, 'TRX-00038', 'Pelanggan Umum', 38500, 'Tunai', 'Selesai', NULL, NULL, NULL, '2026-11-04 13:08:17'),
(39, 2, 'TRX-00039', 'Pelanggan Umum', 38500, 'Tunai', 'Selesai', NULL, NULL, NULL, '2026-11-04 13:10:21'),
(40, 2, 'TRX-00040', 'Pelanggan Umum', 3500, 'Tunai', 'Selesai', NULL, NULL, NULL, '2026-11-04 13:15:56'),
(41, 2, 'TRX-00041', 'Pelanggan Umum', 3500, 'Tunai', 'Selesai', NULL, NULL, NULL, '2026-11-04 13:17:00'),
(42, 2, 'TRX-00042', 'Pelanggan Umum', 3500, 'Tunai', 'Selesai', NULL, NULL, NULL, '2026-11-04 13:24:10'),
(43, 2, 'TRX-00043', 'Pelanggan Umum', 17500, 'Tunai', 'Selesai', NULL, NULL, NULL, '2026-11-04 13:27:30'),
(44, 2, 'TRX-00044', 'Pelanggan Umum', 73500, 'Tunai', 'Selesai', NULL, NULL, NULL, '2026-11-04 13:38:42'),
(45, 2, 'TRX-00045', 'Pelanggan Umum', 9500, 'QRIS', 'Selesai', 'WK-2-1793792738', NULL, NULL, '2026-11-04 18:45:38'),
(46, 2, 'TRX-00046', 'Pelanggan Umum', 3000, 'QRIS', 'Dibatalkan', 'WK-2-1793937510', NULL, NULL, '2026-11-06 10:58:30'),
(47, 2, 'TRX-00047', 'Pelanggan Umum', 3000, 'QRIS', 'Dibatalkan', 'WK-2-1793937523', NULL, NULL, '2026-11-06 10:58:43'),
(48, 2, 'TRX-00048', 'Pelanggan Umum', 6500, 'QRIS', 'Dibatalkan', 'WK-2-1793937531', NULL, NULL, '2026-11-06 10:58:51'),
(49, 2, 'TRX-00049', 'Pelanggan Umum', 3000, 'QRIS', 'Dibatalkan', 'WK-2-1793937649', NULL, NULL, '2026-11-06 11:00:49'),
(50, 2, 'TRX-00050', 'Pelanggan Umum', 3000, 'QRIS', 'Dibatalkan', 'WK-2-1793937689', NULL, NULL, '2026-11-06 11:01:29'),
(51, 2, 'TRX-00051', 'Pelanggan Umum', 3000, 'QRIS', 'Dibatalkan', 'WK-2-1793937711', NULL, NULL, '2026-11-06 11:01:51'),
(52, 2, 'TRX-00052', 'Pelanggan Umum', 3000, 'QRIS', 'Dibatalkan', 'WK-2-1793937775', NULL, NULL, '2026-11-06 11:02:55'),
(53, 2, 'TRX-00053', 'Pelanggan Umum', 3000, 'QRIS', 'Dibatalkan', 'WK-2-1793938041', NULL, NULL, '2026-11-06 11:07:21'),
(54, 2, 'TRX-00054', 'Pelanggan Umum', 3000, 'QRIS', 'Dibatalkan', 'WK-2-1793938055', NULL, NULL, '2026-11-06 11:07:35'),
(55, 2, 'TRX-00055', 'Pelanggan Umum', 3000, 'QRIS', 'Dibatalkan', 'WK-2-55-1793938413', NULL, NULL, '2026-11-06 11:13:33'),
(56, 2, 'TRX-00056', 'Pelanggan Umum', 6000, 'QRIS', 'Selesai', 'WK-2-56-1793938457', NULL, NULL, '2026-11-06 11:14:17'),
(57, 2, 'TRX-00057', 'Pelanggan Umum', 12000, 'QRIS', 'Selesai', 'WK-2-57-1793957072', NULL, NULL, '2026-11-06 16:24:32'),
(58, 2, 'TRX-00058', 'Pelanggan Umum', 10000, 'QRIS', 'Selesai', 'WK-2-58-1793957279', NULL, NULL, '2026-11-06 16:27:59'),
(59, 2, 'TRX-00059', 'Pelanggan Umum', 50000, 'QRIS', 'Selesai', 'WK-2-59-1794021547', '8269b11b-8000-4575-9a4e-d32f5c771821', '2026-11-07 10:19:14', '2026-11-07 10:19:07'),
(60, 2, 'TRX-00060', 'Pelanggan Umum', 70000, 'QRIS', 'Selesai', 'WK-2-60-1794055384', '575e5980-ca71-425d-a3b9-1dce01d88690', '2026-11-07 19:43:32', '2026-11-07 19:43:04'),
(61, 2, 'TRX-00061', 'Pelanggan Umum', 10000, 'QRIS', 'Selesai', 'WK-2-61-1794055599', 'a75d87e8-54e1-493d-af31-a97916988835', '2026-11-07 19:46:50', '2026-11-07 19:46:39'),
(62, 2, 'TRX-00062', 'Pelanggan Umum', 9000, 'Tunai', 'Selesai', NULL, NULL, NULL, '2026-11-08 13:40:05'),
(63, 2, 'TRX-00063', 'Pelanggan Umum', 6000, 'QRIS', 'Selesai', 'WK-2-63-1794120018', 'a61d4619-e35b-48c0-a941-bd9362f4f6f7', '2026-11-08 13:40:34', '2026-11-08 13:40:18'),
(64, 2, 'TRX-00064', 'Pelanggan Umum', 12000, 'QRIS', 'Selesai', 'WK-2-64-1794120949', '5a449b58-9209-467a-8f5a-4d8059b7cd74', '2026-11-08 13:57:06', '2026-11-08 13:55:49'),
(65, 2, 'TRX-00065', 'Pelanggan Umum', 3000, 'QRIS', 'Selesai', 'WK-2-65-1794123494', 'ab5676f5-4700-433e-8c44-155d65995008', '2026-11-08 14:38:46', '2026-11-08 14:38:14'),
(66, 2, 'TRX-00066', 'Pelanggan Umum', 6000, 'QRIS', 'Selesai', 'WK-2-66-1794124300', '612837b0-3a9b-4469-b482-0fc6f1e11f50', '2026-11-08 14:52:48', '2026-11-08 14:51:40'),
(67, 2, 'TRX-00067', 'Pelanggan Umum', 10000, 'QRIS', 'Selesai', 'WK-2-67-1794124542', '226fa068-ccdf-4f43-972c-683b061fa1a7', '2026-11-08 14:56:12', '2026-11-08 14:55:42');

-- --------------------------------------------------------

--
-- Struktur dari tabel `transaction_items`
--

CREATE TABLE `transaction_items` (
  `id` int NOT NULL,
  `transaction_id` int NOT NULL,
  `product_id` int DEFAULT NULL,
  `product_name` varchar(120) NOT NULL,
  `qty` int UNSIGNED NOT NULL,
  `unit_price` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `transaction_items`
--

INSERT INTO `transaction_items` (`id`, `transaction_id`, `product_id`, `product_name`, `qty`, `unit_price`) VALUES
(5, 5, 7, 'indomie', 1, 3000),
(6, 6, 7, 'indomie', 1, 3000),
(7, 7, 7, 'indomie', 1, 3000),
(8, 8, 7, 'indomie', 1, 3000),
(9, 9, 7, 'indomie', 1, 3000),
(10, 10, 7, 'indomie', 2, 3000),
(11, 11, 7, 'indomie', 3, 3000),
(12, 12, 8, 'Mie Sedap selection', 5, 3500),
(13, 13, 8, 'Mie Sedap selection', 11, 3500),
(14, 14, 8, 'Mie Sedap selection', 11, 3500),
(15, 15, 8, 'Mie Sedap selection', 2, 3500),
(16, 16, 8, 'Mie Sedap selection', 3, 3500),
(17, 17, 8, 'Mie Sedap selection', 1, 3500),
(18, 18, 8, 'Mie Sedap selection', 2, 3500),
(19, 19, 8, 'Mie Sedap selection', 2, 3500),
(20, 20, 8, 'Mie Sedap selection', 2, 3500),
(21, 21, 8, 'Mie Sedap selection', 3, 3500),
(22, 22, 8, 'Mie Sedap selection', 1, 3500),
(23, 23, 8, 'Mie Sedap selection', 1, 3500),
(24, 24, 8, 'Mie Sedap selection', 3, 3500),
(25, 25, 8, 'Mie Sedap selection', 2, 3500),
(26, 26, 8, 'Mie Sedap selection', 2, 3500),
(27, 27, 8, 'Mie Sedap selection', 3, 3500),
(28, 28, 8, 'Mie Sedap selection', 2, 3500),
(29, 29, 8, 'Mie Sedap selection', 2, 3500),
(30, 30, 8, 'Mie Sedap selection', 2, 3500),
(31, 31, 8, 'Mie Sedap selection', 3, 3500),
(32, 32, 8, 'Mie Sedap selection', 1, 3500),
(33, 33, 8, 'Mie Sedap selection', 7, 3500),
(34, 34, 8, 'Mie Sedap selection', 5, 3500),
(35, 35, 8, 'Mie Sedap selection', 6, 3500),
(36, 36, 8, 'Mie Sedap selection', 2, 3500),
(37, 37, 8, 'Mie Sedap selection', 5, 3500),
(38, 38, 8, 'Mie Sedap selection', 11, 3500),
(39, 39, 8, 'Mie Sedap selection', 11, 3500),
(40, 40, 8, 'Mie Sedap selection', 1, 3500),
(41, 41, 8, 'Mie Sedap selection', 1, 3500),
(42, 42, 8, 'Mie Sedap selection', 1, 3500),
(43, 43, 8, 'Mie Sedap selection', 5, 3500),
(44, 44, 8, 'Mie Sedap selection', 21, 3500),
(45, 45, 9, 'botol vit', 2, 3000),
(46, 45, 8, 'Mie Sedap selection', 1, 3500),
(47, 46, 7, 'indomie', 1, 3000),
(48, 47, 7, 'indomie', 1, 3000),
(49, 48, 7, 'indomie', 1, 3000),
(50, 48, 8, 'Mie Sedap selection', 1, 3500),
(51, 49, 9, 'botol vit', 1, 3000),
(52, 50, 9, 'botol vit', 1, 3000),
(53, 51, 9, 'botol vit', 1, 3000),
(54, 52, 9, 'botol vit', 1, 3000),
(55, 53, 7, 'indomie', 1, 3000),
(56, 54, 9, 'botol vit', 1, 3000),
(57, 55, 7, 'indomie', 1, 3000),
(58, 56, 7, 'indomie', 1, 3000),
(59, 56, 9, 'botol vit', 1, 3000),
(60, 57, 7, 'indomie', 4, 3000),
(61, 58, 9, 'botol vit', 1, 10000),
(62, 59, 9, 'botol vit', 5, 10000),
(63, 60, 9, 'botol CLUB', 7, 10000),
(64, 61, 9, 'botol CLUB', 1, 10000),
(65, 62, 10, 'AIR', 3, 3000),
(66, 63, 10, 'AIR', 1, 3000),
(67, 63, 7, 'indomie', 1, 3000),
(68, 64, 10, 'AIR', 4, 3000),
(69, 65, 10, 'AIR', 1, 3000),
(70, 66, 10, 'AIR', 2, 3000),
(71, 67, 9, 'botol CLU', 1, 10000);

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `business_name` varchar(120) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(190) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `role` enum('owner','admin') NOT NULL DEFAULT 'owner',
  `is_master` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `full_name`, `business_name`, `password_hash`, `email`, `phone`, `role`, `is_master`, `created_at`, `is_active`, `updated_at`) VALUES
(1, 'wahyu', 'warung saya', '$2y$10$gwOgGPJjtkMKIl99NvjGcukt/v28L8MKzkGZFxRQXx8qgr5wWFL4W', 'wahyu@gmail.com', '081234567890', 'owner', 0, '2026-10-19 06:56:15', 1, '2026-10-19 06:56:15'),
(2, 'user', 'warung SAYA', '$2y$10$oB7euNo/kxpNf13ZWgKD9.uQrUXfYzQFNquOkY63WcvypsyyBJpIm', '123@gmail.com', '088705576934', 'owner', 0, '2026-10-25 06:50:21', 1, '2026-11-06 04:27:57'),
(3, 'Administrator', 'WarungKasir', '$2y$12$1CJ/AiRPQf3G.VKfynT6zeCgFYJrESEEaf4N2r484VLW2oqw0RVtG', 'admin@warungkasir.local', '-', 'admin', 1, '2026-10-25 11:43:58', 1, '2026-10-25 11:43:58'),
(4, 'nama saya', 'saya', '$2y$10$W6hLn5s6hCKf0M9AqjkGCOA8gCHTcHAdl103t1zBvGQFyUu2XwsNC', '12345@gmail.com', '000000000004', 'owner', 0, '2026-10-26 06:16:05', 1, '2026-11-01 04:41:19'),
(6, 'ssssss', 'sssss', '$2y$10$Zclg1ltG3u.udbIajJYAuutsa7lEXLmDN4nfYmkV1Fk9aHlNwseIO', '123111@gmail.com', '000000000006', 'owner', 0, '2026-10-31 02:15:05', 1, '2026-11-01 04:41:19'),
(8, 'QQ', 'warungku', '$2y$10$J8EpmhYXDWt0JVU1kF.chubGezof5HXhXPFaN1HRIP.14zFYN0p2y', 'saya@gmail.com', '88705576934', 'owner', 0, '2026-11-08 06:20:31', 1, '2026-11-08 06:20:31'),
(9, 'isiesoid', 'sjixnisn', '$2y$10$D2Sy6R3Kwrwj6VDquM22UOSWbehJiG22qhOO4yQd/6iDurtIsyyLu', 'warkas123@gmail.com', '08563283942', 'owner', 0, '2026-11-08 06:21:38', 1, '2026-11-08 06:21:38');

-- --------------------------------------------------------

--
-- Struktur dari tabel `user_profiles`
--

CREATE TABLE `user_profiles` (
  `user_id` int NOT NULL,
  `user_type` enum('admin','customer','seller') NOT NULL,
  `alamat` text,
  `no_hp` varchar(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `user_profiles`
--

INSERT INTO `user_profiles` (`user_id`, `user_type`, `alamat`, `no_hp`, `created_at`, `updated_at`) VALUES
(2, 'seller', 'JL. PENDIDIKAN', NULL, '2026-11-06 04:27:57', '2026-11-06 06:10:23');

-- --------------------------------------------------------

--
-- Struktur dari tabel `wallets`
--

CREATE TABLE `wallets` (
  `owner_id` int NOT NULL,
  `pending_balance` bigint NOT NULL DEFAULT '0',
  `available_balance` bigint NOT NULL DEFAULT '0',
  `processing_balance` bigint NOT NULL DEFAULT '0',
  `total_withdrawn` bigint NOT NULL DEFAULT '0',
  `total_fees` bigint NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

--
-- Dumping data untuk tabel `wallets`
--

INSERT INTO `wallets` (`owner_id`, `pending_balance`, `available_balance`, `processing_balance`, `total_withdrawn`, `total_fees`, `created_at`, `updated_at`) VALUES
(1, 0, 0, 0, 0, 0, '2026-11-06 09:11:45', '2026-11-06 09:11:45'),
(2, 20759, 85331, 0, 159611, 1299, '2026-11-06 09:11:45', '2026-11-08 08:01:43'),
(4, 0, 0, 0, 0, 0, '2026-11-06 09:11:45', '2026-11-06 09:11:45'),
(6, 0, 0, 0, 0, 0, '2026-11-06 09:11:45', '2026-11-06 09:11:45'),
(9, 0, 0, 0, 0, 0, '2026-11-08 06:24:09', '2026-11-08 06:24:09');

-- --------------------------------------------------------

--
-- Struktur dari tabel `wallet_settings`
--

CREATE TABLE `wallet_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `description` varchar(255) NOT NULL DEFAULT '',
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `wallet_settings`
--

INSERT INTO `wallet_settings` (`setting_key`, `setting_value`, `description`, `updated_by`, `updated_at`) VALUES
('enc_key_dev', 'af68bb940ebe81f46d70a33cc0cf28adc0987bea79c79474d822d8101faa2b09', 'KUNCI MODE UJI. Atur WALLET_ENC_KEY di environment untuk produksi.', NULL, '2026-11-07 03:17:34'),
('mdr_bps', '70', 'Tarif MDR QRIS dalam bps (70 = 0,70%). WAJIB disesuaikan dengan ketentuan Midtrans untuk akun ini.', NULL, '2026-11-06 09:11:45'),
('mdr_tax_bps', '1100', 'Pajak atas MDR dalam bps (1100 = 11%). Sesuaikan dengan ketentuan pajak yang berlaku.', NULL, '2026-11-06 09:11:45'),
('min_withdrawal', '10000', 'Nominal minimum withdrawal (Rp).', NULL, '2026-11-06 09:11:45'),
('payout_mode', 'manual', 'manual = admin mentransfer sendiri. Jangan ubah ke otomatis sebelum disetujui Midtrans.', NULL, '2026-11-06 09:11:45'),
('settlement_delay_days', '1', 'Jumlah hari kerja setelah pembayaran sebelum saldo pending menjadi tersedia. Konfirmasi dengan Midtrans.', NULL, '2026-11-06 09:11:45'),
('wallet_start_at', '2026-11-06 17:00:00', 'KOSONG = wallet belum aktif (tidak ada pembayaran yang dicatat). Isi saat go-live (Y-m-d H:i:s): hanya pembayaran QRIS setelah waktu ini yang masuk wallet.', NULL, '2026-11-06 09:22:34'),
('withdrawal_fee_flat', '0', 'Biaya transfer per withdrawal (Rp). Isi sesuai biaya transfer aktual.', NULL, '2026-11-06 09:11:45');

-- --------------------------------------------------------

--
-- Struktur dari tabel `wallet_transactions`
--

CREATE TABLE `wallet_transactions` (
  `id` bigint UNSIGNED NOT NULL,
  `owner_id` int NOT NULL,
  `entry_type` enum('payment_pending','payment_reversal','settlement_release','withdrawal_hold','withdrawal_complete','withdrawal_release','manual_adjustment') NOT NULL,
  `pending_delta` bigint NOT NULL DEFAULT '0',
  `available_delta` bigint NOT NULL DEFAULT '0',
  `processing_delta` bigint NOT NULL DEFAULT '0',
  `pending_after` bigint NOT NULL,
  `available_after` bigint NOT NULL,
  `processing_after` bigint NOT NULL,
  `ref_type` varchar(30) DEFAULT NULL,
  `ref_id` bigint UNSIGNED DEFAULT NULL,
  `idempotency_key` varchar(100) NOT NULL,
  `description` varchar(255) NOT NULL DEFAULT '',
  `reason` varchar(255) DEFAULT NULL,
  `actor_id` int DEFAULT NULL,
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
) ;

--
-- Dumping data untuk tabel `wallet_transactions`
--

INSERT INTO `wallet_transactions` (`id`, `owner_id`, `entry_type`, `pending_delta`, `available_delta`, `processing_delta`, `pending_after`, `available_after`, `processing_after`, `ref_type`, `ref_id`, `idempotency_key`, `description`, `reason`, `actor_id`, `created_at`) VALUES
(1, 2, 'payment_pending', 49611, 0, 0, 49611, 0, 0, 'payment_fee', 1, 'pay:59', 'Pembayaran QRIS TRX-00059', NULL, NULL, '2026-11-07 10:19:17.344'),
(2, 2, 'manual_adjustment', 0, 10000, 0, 49611, 10000, 0, NULL, NULL, 'adj:36a7f94ca20041b9bfa8012031a9f1db', 'Penyesuaian manual admin', 'PTTTTT', 3, '2026-11-07 10:24:23.882'),
(3, 2, 'withdrawal_hold', 0, -10000, 10000, 49611, 0, 10000, 'withdrawal', 1, 'wd:1:hold', 'Saldo dikunci untuk withdrawal #1', NULL, NULL, '2026-11-07 10:24:44.124'),
(4, 2, 'withdrawal_complete', 0, 0, -10000, 49611, 0, 0, 'withdrawal', 1, 'wd:1:complete', 'Withdrawal #1 berhasil', NULL, NULL, '2026-11-07 11:01:20.128'),
(5, 2, 'settlement_release', -49611, 49611, 0, 0, 49611, 0, 'payment_fee', 1, 'rel:1', 'Saldo dilepas manual oleh admin', 'UJI COBA', 3, '2026-11-07 15:07:20.411'),
(6, 2, 'withdrawal_hold', 0, -30000, 30000, 0, 19611, 30000, 'withdrawal', 2, 'wd:2:hold', 'Saldo dikunci untuk withdrawal #2', NULL, NULL, '2026-11-07 15:07:54.649'),
(7, 2, 'withdrawal_complete', 0, 0, -30000, 0, 19611, 0, 'withdrawal', 2, 'wd:2:complete', 'Withdrawal #2 berhasil', NULL, NULL, '2026-11-07 15:08:45.840'),
(8, 2, 'payment_pending', 69456, 0, 0, 69456, 19611, 0, 'payment_fee', 2, 'pay:60', 'Pembayaran QRIS TRX-00060', NULL, NULL, '2026-11-07 19:43:34.145'),
(9, 2, 'payment_pending', 9922, 0, 0, 79378, 19611, 0, 'payment_fee', 3, 'pay:61', 'Pembayaran QRIS TRX-00061', NULL, NULL, '2026-11-07 19:46:53.227'),
(10, 2, 'payment_pending', 5953, 0, 0, 85331, 19611, 0, 'payment_fee', 4, 'pay:63', 'Pembayaran QRIS TRX-00063', NULL, NULL, '2026-11-08 13:40:35.365'),
(11, 2, 'withdrawal_hold', 0, -19611, 19611, 85331, 0, 19611, 'withdrawal', 3, 'wd:3:hold', 'Saldo dikunci untuk withdrawal #3', NULL, NULL, '2026-11-08 13:43:34.339'),
(12, 2, 'payment_pending', 11907, 0, 0, 97238, 0, 19611, 'payment_fee', 5, 'pay:64', 'Pembayaran QRIS TRX-00064', NULL, NULL, '2026-11-08 13:57:08.371'),
(13, 2, 'payment_pending', 2977, 0, 0, 100215, 0, 19611, 'payment_fee', 6, 'pay:65', 'Pembayaran QRIS TRX-00065', NULL, NULL, '2026-11-08 14:38:48.104'),
(14, 2, 'withdrawal_complete', 0, 0, -19611, 100215, 0, 0, 'withdrawal', 3, 'wd:3:complete', 'Withdrawal #3 berhasil', NULL, NULL, '2026-11-08 14:47:18.517'),
(15, 2, 'manual_adjustment', 0, 100000, 0, 100215, 100000, 0, NULL, NULL, 'adj:b5c49979180d45479aa20c15f36a1811', 'Penyesuaian manual admin', 'HHHUUU', 3, '2026-11-08 14:49:55.211'),
(16, 2, 'settlement_release', -69456, 69456, 0, 30759, 169456, 0, 'payment_fee', 2, 'rel:2', 'Saldo dilepas manual oleh admin', 'UUUUUUUUUU', 3, '2026-11-08 14:50:29.507'),
(17, 2, 'payment_pending', 5953, 0, 0, 36712, 169456, 0, 'payment_fee', 7, 'pay:66', 'Pembayaran QRIS TRX-00066', NULL, NULL, '2026-11-08 14:52:49.923'),
(18, 2, 'settlement_release', -5953, 5953, 0, 30759, 175409, 0, 'payment_fee', 7, 'rel:7', 'Saldo dilepas manual oleh admin', 'UJI COBA', 3, '2026-11-08 14:54:53.537'),
(19, 2, 'payment_pending', 9922, 0, 0, 40681, 175409, 0, 'payment_fee', 8, 'pay:67', 'Pembayaran QRIS TRX-00067', NULL, NULL, '2026-11-08 14:56:12.995'),
(20, 2, 'settlement_release', -9922, 9922, 0, 30759, 185331, 0, 'payment_fee', 8, 'rel:8', 'Saldo dilepas manual oleh admin', 'LAGIII', 3, '2026-11-08 14:56:53.243'),
(21, 2, 'withdrawal_hold', 0, -100000, 100000, 30759, 85331, 100000, 'withdrawal', 4, 'wd:4:hold', 'Saldo dikunci untuk withdrawal #4', NULL, NULL, '2026-11-08 14:57:21.187'),
(22, 2, 'withdrawal_complete', 0, 0, -100000, 30759, 85331, 0, 'withdrawal', 4, 'wd:4:complete', 'Withdrawal #4 berhasil', NULL, NULL, '2026-11-08 14:58:07.228'),
(23, 2, 'manual_adjustment', -10000, 0, 0, 20759, 85331, 0, NULL, NULL, 'adj:56b8371709e544ef888b1594d86dca2e', 'Penyesuaian manual admin', 'UJI COBA', 3, '2026-11-08 15:01:43.108');

-- --------------------------------------------------------

--
-- Struktur dari tabel `withdrawals`
--

CREATE TABLE `withdrawals` (
  `id` bigint UNSIGNED NOT NULL,
  `owner_id` int NOT NULL,
  `bank_account_id` int UNSIGNED NOT NULL,
  `idempotency_key` varchar(64) NOT NULL,
  `amount` bigint NOT NULL,
  `transfer_fee` bigint NOT NULL DEFAULT '0',
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
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

--
-- Dumping data untuk tabel `withdrawals`
--

INSERT INTO `withdrawals` (`id`, `owner_id`, `bank_account_id`, `idempotency_key`, `amount`, `transfer_fee`, `net_amount`, `status`, `bank_name`, `account_number_enc`, `account_last4`, `account_holder`, `reject_reason`, `failure_reason`, `transfer_reference`, `proof_file`, `approved_by`, `processed_by`, `requested_at`, `approved_at`, `processed_at`, `completed_at`, `updated_at`) VALUES
(1, 2, 1, 'fbd7a06b697f45c69b510e51962377ec', 10000, 0, 10000, 'Completed', 'GoPay', 'cGqqNX0O1RVzQO39yfIU9OOi/12aDO/ZW9hOumRcY98d4fAbZao=', '8888', 'USER', NULL, NULL, '0888888888', '1-a7c0429d1ae22fd90b6dcc6d.png', 3, 3, '2026-11-07 03:24:44', '2026-11-07 10:25:02', '2026-11-07 11:00:18', '2026-11-07 11:01:20', '2026-11-07 04:01:20'),
(2, 2, 1, 'a6b5f2a00c37443181d47d5a7e2db26f', 30000, 0, 30000, 'Completed', 'GoPay', 'cGqqNX0O1RVzQO39yfIU9OOi/12aDO/ZW9hOumRcY98d4fAbZao=', '8888', 'USER', NULL, NULL, '0888888888', '2-e2b10321aec1a95a8a65c422.png', 3, 3, '2026-11-07 08:07:54', '2026-11-07 15:08:05', '2026-11-07 15:08:16', '2026-11-07 15:08:45', '2026-11-07 08:08:45'),
(3, 2, 1, '9cef5ad034c94b339bead4d15ef955ed', 19611, 0, 19611, 'Completed', 'GoPay', 'cGqqNX0O1RVzQO39yfIU9OOi/12aDO/ZW9hOumRcY98d4fAbZao=', '8888', 'USER', NULL, NULL, '99999', '3-4200bff88f66f9ea10b91232.png', 3, 3, '2026-11-08 06:43:34', '2026-11-08 14:46:07', '2026-11-08 14:46:38', '2026-11-08 14:47:18', '2026-11-08 07:47:18'),
(4, 2, 1, '2843aa536f56459da554182cb8f2f500', 100000, 0, 100000, 'Completed', 'GoPay', 'cGqqNX0O1RVzQO39yfIU9OOi/12aDO/ZW9hOumRcY98d4fAbZao=', '8888', 'USER', NULL, NULL, '111111111111111', '4-3a705d7f5893632edc1e1b90.png', 3, 3, '2026-11-08 07:57:21', '2026-11-08 14:57:38', '2026-11-08 14:57:44', '2026-11-08 14:58:07', '2026-11-08 07:58:07');

--
-- Indeks untuk tabel yang dibuang
--

--
-- Indeks untuk tabel `admin_audit_log`
--
ALTER TABLE `admin_audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_audit_admin` (`admin_id`,`created_at`);

--
-- Indeks untuk tabel `bank_accounts`
--
ALTER TABLE `bank_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_bank_owner_acct` (`owner_id`,`bank_code`,`account_number_hash`),
  ADD KEY `idx_bank_status` (`status`),
  ADD KEY `fk_bank_verifier` (`verified_by`);

--
-- Indeks untuk tabel `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_attempts_ip_time` (`ip`,`attempted_at`);

--
-- Indeks untuk tabel `midtrans_events`
--
ALTER TABLE `midtrans_events`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_mev_key` (`event_key`),
  ADD KEY `idx_mev_order` (`order_id`);

--
-- Indeks untuk tabel `payment_fees`
--
ALTER TABLE `payment_fees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_fee_trx` (`transaction_id`),
  ADD UNIQUE KEY `uq_fee_midtrans_txid` (`midtrans_transaction_id`),
  ADD KEY `idx_fee_owner` (`owner_id`,`created_at`),
  ADD KEY `idx_fee_release` (`released_at`,`reversed_at`,`release_at`);

--
-- Indeks untuk tabel `plans`
--
ALTER TABLE `plans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_plans_code` (`code`);

--
-- Indeks untuk tabel `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_products_owner_name` (`owner_id`,`name`),
  ADD UNIQUE KEY `uq_products_owner_sku` (`owner_id`,`sku`),
  ADD KEY `idx_products_owner` (`owner_id`,`is_active`);

--
-- Indeks untuk tabel `scan_requests`
--
ALTER TABLE `scan_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_scan_user_status` (`user_id`,`status`),
  ADD KEY `idx_scan_expires` (`expires_at`);

--
-- Indeks untuk tabel `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sub_user_status` (`user_id`,`status`),
  ADD KEY `fk_sub_plan` (`plan_id`);

--
-- Indeks untuk tabel `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_trx_code` (`owner_id`,`code`),
  ADD UNIQUE KEY `uq_trx_midtrans_order` (`midtrans_order_id`),
  ADD UNIQUE KEY `uq_trx_midtrans_txid` (`midtrans_transaction_id`),
  ADD KEY `idx_trx_owner_date` (`owner_id`,`created_at`),
  ADD KEY `idx_trx_owner_status` (`owner_id`,`status`);

--
-- Indeks untuk tabel `transaction_items`
--
ALTER TABLE `transaction_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_items_trx` (`transaction_id`),
  ADD KEY `idx_items_product` (`product_id`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD UNIQUE KEY `uq_users_phone` (`phone`);

--
-- Indeks untuk tabel `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD PRIMARY KEY (`user_id`);

--
-- Indeks untuk tabel `wallets`
--
ALTER TABLE `wallets`
  ADD PRIMARY KEY (`owner_id`);

--
-- Indeks untuk tabel `wallet_settings`
--
ALTER TABLE `wallet_settings`
  ADD PRIMARY KEY (`setting_key`),
  ADD KEY `fk_wset_user` (`updated_by`);

--
-- Indeks untuk tabel `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_wtx_idem` (`idempotency_key`),
  ADD KEY `idx_wtx_owner` (`owner_id`,`id`),
  ADD KEY `idx_wtx_ref` (`ref_type`,`ref_id`),
  ADD KEY `fk_wtx_actor` (`actor_id`);

--
-- Indeks untuk tabel `withdrawals`
--
ALTER TABLE `withdrawals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_wd_idem` (`owner_id`,`idempotency_key`),
  ADD KEY `idx_wd_status` (`status`,`requested_at`),
  ADD KEY `idx_wd_owner` (`owner_id`,`requested_at`),
  ADD KEY `fk_wd_bank` (`bank_account_id`),
  ADD KEY `fk_wd_approver` (`approved_by`),
  ADD KEY `fk_wd_processor` (`processed_by`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `admin_audit_log`
--
ALTER TABLE `admin_audit_log`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT untuk tabel `bank_accounts`
--
ALTER TABLE `bank_accounts`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `midtrans_events`
--
ALTER TABLE `midtrans_events`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `payment_fees`
--
ALTER TABLE `payment_fees`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `plans`
--
ALTER TABLE `plans`
  MODIFY `id` tinyint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `products`
--
ALTER TABLE `products`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT untuk tabel `scan_requests`
--
ALTER TABLE `scan_requests`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=83;

--
-- AUTO_INCREMENT untuk tabel `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT untuk tabel `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT untuk tabel `transaction_items`
--
ALTER TABLE `transaction_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT untuk tabel `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `withdrawals`
--
ALTER TABLE `withdrawals`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `admin_audit_log`
--
ALTER TABLE `admin_audit_log`
  ADD CONSTRAINT `fk_audit_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT;

--
-- Ketidakleluasaan untuk tabel `bank_accounts`
--
ALTER TABLE `bank_accounts`
  ADD CONSTRAINT `fk_bank_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_bank_verifier` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT;

--
-- Ketidakleluasaan untuk tabel `payment_fees`
--
ALTER TABLE `payment_fees`
  ADD CONSTRAINT `fk_fee_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_fee_trx` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE RESTRICT;

--
-- Ketidakleluasaan untuk tabel `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `scan_requests`
--
ALTER TABLE `scan_requests`
  ADD CONSTRAINT `fk_scan_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD CONSTRAINT `fk_sub_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`),
  ADD CONSTRAINT `fk_sub_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `fk_trx_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `transaction_items`
--
ALTER TABLE `transaction_items`
  ADD CONSTRAINT `fk_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_items_trx` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD CONSTRAINT `user_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `wallets`
--
ALTER TABLE `wallets`
  ADD CONSTRAINT `fk_wallet_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT;

--
-- Ketidakleluasaan untuk tabel `wallet_settings`
--
ALTER TABLE `wallet_settings`
  ADD CONSTRAINT `fk_wset_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT;

--
-- Ketidakleluasaan untuk tabel `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD CONSTRAINT `fk_wtx_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_wtx_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT;

--
-- Ketidakleluasaan untuk tabel `withdrawals`
--
ALTER TABLE `withdrawals`
  ADD CONSTRAINT `fk_wd_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_wd_bank` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_wd_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_wd_processor` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
