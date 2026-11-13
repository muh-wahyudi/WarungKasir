-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: db
-- Waktu pembuatan: 06 Nov 2026 pada 09.03
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
(7, 2, 'indomie', '76677662', 'produk/2/c82a95a60452256794e73949059e5036.jpg', 3000, 96, 5, 1, '2026-11-02 05:40:24', '2026-11-06 04:14:17'),
(8, 2, 'Mie Sedap selection', '8998866202343', 'produk/2/942e6241a364c8e9245fb2980c10a88f.jpg', 3500, 959, 5, 1, '2026-11-03 14:03:13', '2026-11-06 03:58:55'),
(9, 2, 'botol vit', '896867700326', NULL, 3000, 9999, 5, 1, '2026-11-04 11:44:18', '2026-11-06 04:14:17');

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
(67, 2, 'completed', 'kasir', '896867700326:1', '2026-11-06 04:02:43', '2026-11-06 11:07:43', '2026-11-06 04:02:56');

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
(14, 2, 2, 'active', '2026-11-06 11:06:30', '2026-12-06 11:06:30', 'SUB-2-1793937977', '2026-11-06 04:06:17');

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
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `transactions`
--

INSERT INTO `transactions` (`id`, `owner_id`, `code`, `customer_name`, `total`, `payment_method`, `status`, `midtrans_order_id`, `created_at`) VALUES
(1, 2, 'TRX-00001', 'Pelanggan Umum', 50000, 'Tunai', 'Selesai', NULL, '2026-11-01 08:47:33'),
(2, 2, 'TRX-00002', 'Pelanggan Umum', 50000, 'QRIS', 'Dibatalkan', NULL, '2026-11-01 08:47:45'),
(3, 2, 'TRX-00003', 'Pelanggan Umum', 50000, 'Tunai', 'Selesai', NULL, '2026-11-01 12:17:57'),
(4, 2, 'TRX-00004', 'Pelanggan Umum', 50000, 'Tunai', 'Selesai', NULL, '2026-11-01 12:18:28'),
(5, 2, 'TRX-00005', 'Pelanggan Umum', 3000, 'Tunai', 'Selesai', NULL, '2026-11-02 13:32:54'),
(6, 2, 'TRX-00006', 'Pelanggan Umum', 3000, 'Tunai', 'Selesai', NULL, '2026-11-02 14:18:59'),
(7, 2, 'TRX-00007', 'Pelanggan Umum', 3000, 'QRIS', 'Dibatalkan', 'WK-2-1793672160', '2026-11-03 09:16:00'),
(8, 2, 'TRX-00008', 'Pelanggan Umum', 3000, 'QRIS', 'Selesai', 'WK-2-1793672217', '2026-11-03 09:16:57'),
(9, 2, 'TRX-00009', 'Pelanggan Umum', 3000, 'Transfer', 'Dibatalkan', NULL, '2026-11-03 09:20:58'),
(10, 2, 'TRX-00010', 'Pelanggan Umum', 6000, 'Tunai', 'Selesai', NULL, '2026-11-03 18:16:50'),
(11, 2, 'TRX-00011', 'Pelanggan Umum', 9000, 'QRIS', 'Selesai', 'WK-2-1793705087', '2026-11-03 18:24:47'),
(12, 2, 'TRX-00012', 'Pelanggan Umum', 17500, 'Tunai', 'Selesai', NULL, '2026-11-03 21:04:37'),
(13, 2, 'TRX-00013', 'Pelanggan Umum', 38500, 'Tunai', 'Selesai', NULL, '2026-11-03 21:07:26'),
(14, 2, 'TRX-00014', 'Pelanggan Umum', 38500, 'Tunai', 'Selesai', NULL, '2026-11-03 21:09:41'),
(15, 2, 'TRX-00015', 'Pelanggan Umum', 7000, 'Tunai', 'Selesai', NULL, '2026-11-03 21:11:57'),
(16, 2, 'TRX-00016', 'Pelanggan Umum', 10500, 'Tunai', 'Selesai', NULL, '2026-11-03 21:13:39'),
(17, 2, 'TRX-00017', 'Pelanggan Umum', 3500, 'Tunai', 'Selesai', NULL, '2026-11-03 21:15:30'),
(18, 2, 'TRX-00018', 'Pelanggan Umum', 7000, 'Tunai', 'Selesai', NULL, '2026-11-03 21:24:42'),
(19, 2, 'TRX-00019', 'Pelanggan Umum', 7000, 'Tunai', 'Selesai', NULL, '2026-11-03 21:29:08'),
(20, 2, 'TRX-00020', 'Pelanggan Umum', 7000, 'Tunai', 'Selesai', NULL, '2026-11-03 21:29:59'),
(21, 2, 'TRX-00021', 'Pelanggan Umum', 10500, 'Tunai', 'Selesai', NULL, '2026-11-03 21:33:05'),
(22, 2, 'TRX-00022', 'Pelanggan Umum', 3500, 'Tunai', 'Selesai', NULL, '2026-11-03 21:34:13'),
(23, 2, 'TRX-00023', 'Pelanggan Umum', 3500, 'Tunai', 'Selesai', NULL, '2026-11-03 21:36:37'),
(24, 2, 'TRX-00024', 'Pelanggan Umum', 10500, 'Tunai', 'Selesai', NULL, '2026-11-03 21:38:30'),
(25, 2, 'TRX-00025', 'Pelanggan Umum', 7000, 'Tunai', 'Selesai', NULL, '2026-11-03 21:40:53'),
(26, 2, 'TRX-00026', 'Pelanggan Umum', 7000, 'Tunai', 'Selesai', NULL, '2026-11-03 21:50:10'),
(27, 2, 'TRX-00027', 'Pelanggan Umum', 10500, 'Tunai', 'Selesai', NULL, '2026-11-03 21:54:21'),
(28, 2, 'TRX-00028', 'Pelanggan Umum', 7000, 'Tunai', 'Selesai', NULL, '2026-11-03 21:55:24'),
(29, 2, 'TRX-00029', 'Pelanggan Umum', 7000, 'Tunai', 'Selesai', NULL, '2026-11-03 21:58:46'),
(30, 2, 'TRX-00030', 'Pelanggan Umum', 7000, 'Tunai', 'Selesai', NULL, '2026-11-03 22:02:25'),
(31, 2, 'TRX-00031', 'Pelanggan Umum', 10500, 'Tunai', 'Selesai', NULL, '2026-11-03 22:05:37'),
(32, 2, 'TRX-00032', 'Pelanggan Umum', 3500, 'Tunai', 'Selesai', NULL, '2026-11-04 08:03:11'),
(33, 2, 'TRX-00033', 'Pelanggan Umum', 24500, 'QRIS', 'Selesai', 'WK-2-1793754527', '2026-11-04 08:08:47'),
(34, 2, 'TRX-00034', 'Pelanggan Umum', 17500, 'QRIS', 'Selesai', 'WK-2-1793760392', '2026-11-04 09:46:32'),
(35, 2, 'TRX-00035', 'Pelanggan Umum', 21000, 'Tunai', 'Selesai', NULL, '2026-11-04 10:23:30'),
(36, 2, 'TRX-00036', 'Pelanggan Umum', 7000, 'Tunai', 'Selesai', NULL, '2026-11-04 10:32:16'),
(37, 2, 'TRX-00037', 'Pelanggan Umum', 17500, 'Tunai', 'Selesai', NULL, '2026-11-04 13:00:23'),
(38, 2, 'TRX-00038', 'Pelanggan Umum', 38500, 'Tunai', 'Selesai', NULL, '2026-11-04 13:08:17'),
(39, 2, 'TRX-00039', 'Pelanggan Umum', 38500, 'Tunai', 'Selesai', NULL, '2026-11-04 13:10:21'),
(40, 2, 'TRX-00040', 'Pelanggan Umum', 3500, 'Tunai', 'Selesai', NULL, '2026-11-04 13:15:56'),
(41, 2, 'TRX-00041', 'Pelanggan Umum', 3500, 'Tunai', 'Selesai', NULL, '2026-11-04 13:17:00'),
(42, 2, 'TRX-00042', 'Pelanggan Umum', 3500, 'Tunai', 'Selesai', NULL, '2026-11-04 13:24:10'),
(43, 2, 'TRX-00043', 'Pelanggan Umum', 17500, 'Tunai', 'Selesai', NULL, '2026-11-04 13:27:30'),
(44, 2, 'TRX-00044', 'Pelanggan Umum', 73500, 'Tunai', 'Selesai', NULL, '2026-11-04 13:38:42'),
(45, 2, 'TRX-00045', 'Pelanggan Umum', 9500, 'QRIS', 'Selesai', 'WK-2-1793792738', '2026-11-04 18:45:38'),
(46, 2, 'TRX-00046', 'Pelanggan Umum', 3000, 'QRIS', 'Dibatalkan', 'WK-2-1793937510', '2026-11-06 10:58:30'),
(47, 2, 'TRX-00047', 'Pelanggan Umum', 3000, 'QRIS', 'Dibatalkan', 'WK-2-1793937523', '2026-11-06 10:58:43'),
(48, 2, 'TRX-00048', 'Pelanggan Umum', 6500, 'QRIS', 'Dibatalkan', 'WK-2-1793937531', '2026-11-06 10:58:51'),
(49, 2, 'TRX-00049', 'Pelanggan Umum', 3000, 'QRIS', 'Dibatalkan', 'WK-2-1793937649', '2026-11-06 11:00:49'),
(50, 2, 'TRX-00050', 'Pelanggan Umum', 3000, 'QRIS', 'Dibatalkan', 'WK-2-1793937689', '2026-11-06 11:01:29'),
(51, 2, 'TRX-00051', 'Pelanggan Umum', 3000, 'QRIS', 'Dibatalkan', 'WK-2-1793937711', '2026-11-06 11:01:51'),
(52, 2, 'TRX-00052', 'Pelanggan Umum', 3000, 'QRIS', 'Dibatalkan', 'WK-2-1793937775', '2026-11-06 11:02:55'),
(53, 2, 'TRX-00053', 'Pelanggan Umum', 3000, 'QRIS', 'Dibatalkan', 'WK-2-1793938041', '2026-11-06 11:07:21'),
(54, 2, 'TRX-00054', 'Pelanggan Umum', 3000, 'QRIS', 'Dibatalkan', 'WK-2-1793938055', '2026-11-06 11:07:35'),
(55, 2, 'TRX-00055', 'Pelanggan Umum', 3000, 'QRIS', 'Dibatalkan', 'WK-2-55-1793938413', '2026-11-06 11:13:33'),
(56, 2, 'TRX-00056', 'Pelanggan Umum', 6000, 'QRIS', 'Selesai', 'WK-2-56-1793938457', '2026-11-06 11:14:17');

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
(59, 56, 9, 'botol vit', 1, 3000);

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
(6, 'ssssss', 'sssss', '$2y$10$Zclg1ltG3u.udbIajJYAuutsa7lEXLmDN4nfYmkV1Fk9aHlNwseIO', '123111@gmail.com', '000000000006', 'owner', 0, '2026-10-31 02:15:05', 1, '2026-11-01 04:41:19');

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

--
-- Indeks untuk tabel yang dibuang
--

--
-- Indeks untuk tabel `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_attempts_ip_time` (`ip`,`attempted_at`);

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
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `plans`
--
ALTER TABLE `plans`
  MODIFY `id` tinyint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `products`
--
ALTER TABLE `products`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT untuk tabel `scan_requests`
--
ALTER TABLE `scan_requests`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT untuk tabel `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT untuk tabel `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT untuk tabel `transaction_items`
--
ALTER TABLE `transaction_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
