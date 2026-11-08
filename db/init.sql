-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: localhost    Database: warungkasir_db
-- ------------------------------------------------------
-- Server version	8.0.46

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_attempts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) NOT NULL,
  `email` varchar(190) DEFAULT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_attempts_ip_time` (`ip`,`attempted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_attempts`
--

LOCK TABLES `login_attempts` WRITE;
/*!40000 ALTER TABLE `login_attempts` DISABLE KEYS */;
/*!40000 ALTER TABLE `login_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `plans`
--

DROP TABLE IF EXISTS `plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `plans` (
  `id` tinyint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(50) NOT NULL,
  `price_monthly` int unsigned NOT NULL DEFAULT '0',
  `max_products` int unsigned DEFAULT NULL,
  `history_days` int unsigned DEFAULT NULL,
  `max_accounts` int unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_plans_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `plans`
--

LOCK TABLES `plans` WRITE;
/*!40000 ALTER TABLE `plans` DISABLE KEYS */;
INSERT INTO `plans` VALUES (1,'free','Free',0,30,30,1,1),(2,'pro','Pro',29000,200,365,2,0),(3,'promax','Pro Max',59000,NULL,NULL,5,0);
/*!40000 ALTER TABLE `plans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` int NOT NULL AUTO_INCREMENT,
  `owner_id` int NOT NULL,
  `name` varchar(120) NOT NULL,
  `sku` varchar(40) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `price` int unsigned NOT NULL,
  `stock` int NOT NULL DEFAULT '0',
  `min_stock` int NOT NULL DEFAULT '5',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_products_owner_name` (`owner_id`,`name`),
  UNIQUE KEY `uq_products_owner_sku` (`owner_id`,`sku`),
  KEY `idx_products_owner` (`owner_id`,`is_active`),
  CONSTRAINT `fk_products_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (7,2,'indomie','76677662','produk/2/c82a95a60452256794e73949059e5036.jpg',3000,95,5,1,'2026-11-02 05:40:24','2026-11-03 11:24:47'),(8,2,'Mie Sedap selection','8998866202343','produk/2/942e6241a364c8e9245fb2980c10a88f.jpg',3500,29,5,1,'2026-11-03 14:03:13','2026-11-04 01:08:47');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `scan_requests`
--

DROP TABLE IF EXISTS `scan_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `scan_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `status` enum('pending','approved','completed','rejected','expired','canceled') NOT NULL DEFAULT 'pending',
  `barcode` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_scan_user_status` (`user_id`,`status`),
  KEY `idx_scan_expires` (`expires_at`),
  CONSTRAINT `fk_scan_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `scan_requests`
--

LOCK TABLES `scan_requests` WRITE;
/*!40000 ALTER TABLE `scan_requests` DISABLE KEYS */;
INSERT INTO `scan_requests` VALUES (27,2,'canceled',NULL,'2026-11-03 14:40:32','2026-11-03 21:45:32','2026-11-03 14:40:53'),(28,2,'expired',NULL,'2026-11-03 14:41:03','2026-11-03 21:46:03','2026-11-03 14:46:52'),(29,2,'canceled','8998866202343,8998866202343,557146202343,8998866202343,8998866202343,8998866202343,8998866202343','2026-11-03 14:49:48','2026-11-03 21:54:48','2026-11-03 14:53:02'),(32,2,'canceled','8998866202343,8998866202343','2026-11-03 14:54:01','2026-11-03 21:59:01','2026-11-03 14:54:59'),(33,2,'canceled',NULL,'2026-11-03 14:54:29','2026-11-03 21:59:29','2026-11-03 14:55:24'),(34,2,'canceled','8998866202343,8998866202343,8998866202343,8998866202343','2026-11-03 14:58:27','2026-11-03 22:03:27','2026-11-03 14:59:12'),(36,2,'canceled',NULL,'2026-11-03 15:01:58','2026-11-03 22:06:58','2026-11-03 15:02:25'),(38,2,'canceled',NULL,'2026-11-03 15:05:04','2026-11-03 22:10:04','2026-11-03 15:05:37'),(39,2,'expired',NULL,'2026-11-03 15:05:40','2026-11-03 22:10:40','2026-11-04 00:56:28'),(40,2,'canceled',NULL,'2026-11-04 01:02:53','2026-11-04 08:07:53','2026-11-04 01:03:11'),(41,2,'canceled',NULL,'2026-11-04 01:08:08','2026-11-04 08:13:08','2026-11-04 01:08:47');
/*!40000 ALTER TABLE `scan_requests` ENABLE KEYS */;
UNLOCK TABLES;

-- Jenis sesi scan: 'kasir' hanya menerima produk terdaftar, 'produk' menerima kode baru.
ALTER TABLE `scan_requests` ADD COLUMN `scan_mode` varchar(10) NOT NULL DEFAULT 'kasir' AFTER `status`;

--
-- Table structure for table `subscriptions`
--

DROP TABLE IF EXISTS `subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscriptions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `plan_id` tinyint unsigned NOT NULL,
  `status` enum('active','pending_payment','expired','canceled') NOT NULL,
  `started_at` datetime NOT NULL,
  `ends_at` datetime DEFAULT NULL,
  `payment_ref` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sub_user_status` (`user_id`,`status`),
  KEY `fk_sub_plan` (`plan_id`),
  CONSTRAINT `fk_sub_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`),
  CONSTRAINT `fk_sub_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subscriptions`
--

LOCK TABLES `subscriptions` WRITE;
/*!40000 ALTER TABLE `subscriptions` DISABLE KEYS */;
INSERT INTO `subscriptions` VALUES (1,1,1,'active','2026-10-19 06:56:15',NULL,NULL,'2026-10-19 06:56:15'),(2,2,1,'active','2026-10-25 06:50:21',NULL,NULL,'2026-10-25 06:50:21'),(3,4,1,'active','2026-10-26 06:16:05',NULL,NULL,'2026-10-26 06:16:05'),(4,6,1,'active','2026-10-31 09:15:05',NULL,NULL,'2026-10-31 02:15:05');
/*!40000 ALTER TABLE `subscriptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transaction_items`
--

DROP TABLE IF EXISTS `transaction_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transaction_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `transaction_id` int NOT NULL,
  `product_id` int DEFAULT NULL,
  `product_name` varchar(120) NOT NULL,
  `qty` int unsigned NOT NULL,
  `unit_price` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_items_trx` (`transaction_id`),
  KEY `idx_items_product` (`product_id`),
  CONSTRAINT `fk_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_items_trx` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transaction_items`
--

LOCK TABLES `transaction_items` WRITE;
/*!40000 ALTER TABLE `transaction_items` DISABLE KEYS */;
INSERT INTO `transaction_items` VALUES (5,5,7,'indomie',1,3000),(6,6,7,'indomie',1,3000),(7,7,7,'indomie',1,3000),(8,8,7,'indomie',1,3000),(9,9,7,'indomie',1,3000),(10,10,7,'indomie',2,3000),(11,11,7,'indomie',3,3000),(12,12,8,'Mie Sedap selection',5,3500),(13,13,8,'Mie Sedap selection',11,3500),(14,14,8,'Mie Sedap selection',11,3500),(15,15,8,'Mie Sedap selection',2,3500),(16,16,8,'Mie Sedap selection',3,3500),(17,17,8,'Mie Sedap selection',1,3500),(18,18,8,'Mie Sedap selection',2,3500),(19,19,8,'Mie Sedap selection',2,3500),(20,20,8,'Mie Sedap selection',2,3500),(21,21,8,'Mie Sedap selection',3,3500),(22,22,8,'Mie Sedap selection',1,3500),(23,23,8,'Mie Sedap selection',1,3500),(24,24,8,'Mie Sedap selection',3,3500),(25,25,8,'Mie Sedap selection',2,3500),(26,26,8,'Mie Sedap selection',2,3500),(27,27,8,'Mie Sedap selection',3,3500),(28,28,8,'Mie Sedap selection',2,3500),(29,29,8,'Mie Sedap selection',2,3500),(30,30,8,'Mie Sedap selection',2,3500),(31,31,8,'Mie Sedap selection',3,3500),(32,32,8,'Mie Sedap selection',1,3500),(33,33,8,'Mie Sedap selection',7,3500);
/*!40000 ALTER TABLE `transaction_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transactions`
--

DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transactions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `owner_id` int NOT NULL,
  `code` varchar(20) NOT NULL,
  `customer_name` varchar(100) NOT NULL DEFAULT 'Pelanggan Umum',
  `total` int unsigned NOT NULL,
  `payment_method` enum('Tunai','QRIS','Transfer') NOT NULL,
  `status` enum('Selesai','Menunggu','Dibatalkan') NOT NULL DEFAULT 'Selesai',
  `midtrans_order_id` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_trx_code` (`owner_id`,`code`),
  KEY `idx_trx_owner_date` (`owner_id`,`created_at`),
  KEY `idx_trx_owner_status` (`owner_id`,`status`),
  CONSTRAINT `fk_trx_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transactions`
--

LOCK TABLES `transactions` WRITE;
/*!40000 ALTER TABLE `transactions` DISABLE KEYS */;
INSERT INTO `transactions` VALUES (1,2,'TRX-00001','Pelanggan Umum',50000,'Tunai','Selesai',NULL,'2026-11-01 08:47:33'),(2,2,'TRX-00002','Pelanggan Umum',50000,'QRIS','Menunggu',NULL,'2026-11-01 08:47:45'),(3,2,'TRX-00003','Pelanggan Umum',50000,'Tunai','Selesai',NULL,'2026-11-01 12:17:57'),(4,2,'TRX-00004','Pelanggan Umum',50000,'Tunai','Selesai',NULL,'2026-11-01 12:18:28'),(5,2,'TRX-00005','Pelanggan Umum',3000,'Tunai','Selesai',NULL,'2026-11-02 13:32:54'),(6,2,'TRX-00006','Pelanggan Umum',3000,'Tunai','Selesai',NULL,'2026-11-02 14:18:59'),(7,2,'TRX-00007','Pelanggan Umum',3000,'QRIS','Menunggu','WK-2-1793672160','2026-11-03 09:16:00'),(8,2,'TRX-00008','Pelanggan Umum',3000,'QRIS','Selesai','WK-2-1793672217','2026-11-03 09:16:57'),(9,2,'TRX-00009','Pelanggan Umum',3000,'Transfer','Menunggu',NULL,'2026-11-03 09:20:58'),(10,2,'TRX-00010','Pelanggan Umum',6000,'Tunai','Selesai',NULL,'2026-11-03 18:16:50'),(11,2,'TRX-00011','Pelanggan Umum',9000,'QRIS','Selesai','WK-2-1793705087','2026-11-03 18:24:47'),(12,2,'TRX-00012','Pelanggan Umum',17500,'Tunai','Selesai',NULL,'2026-11-03 21:04:37'),(13,2,'TRX-00013','Pelanggan Umum',38500,'Tunai','Selesai',NULL,'2026-11-03 21:07:26'),(14,2,'TRX-00014','Pelanggan Umum',38500,'Tunai','Selesai',NULL,'2026-11-03 21:09:41'),(15,2,'TRX-00015','Pelanggan Umum',7000,'Tunai','Selesai',NULL,'2026-11-03 21:11:57'),(16,2,'TRX-00016','Pelanggan Umum',10500,'Tunai','Selesai',NULL,'2026-11-03 21:13:39'),(17,2,'TRX-00017','Pelanggan Umum',3500,'Tunai','Selesai',NULL,'2026-11-03 21:15:30'),(18,2,'TRX-00018','Pelanggan Umum',7000,'Tunai','Selesai',NULL,'2026-11-03 21:24:42'),(19,2,'TRX-00019','Pelanggan Umum',7000,'Tunai','Selesai',NULL,'2026-11-03 21:29:08'),(20,2,'TRX-00020','Pelanggan Umum',7000,'Tunai','Selesai',NULL,'2026-11-03 21:29:59'),(21,2,'TRX-00021','Pelanggan Umum',10500,'Tunai','Selesai',NULL,'2026-11-03 21:33:05'),(22,2,'TRX-00022','Pelanggan Umum',3500,'Tunai','Selesai',NULL,'2026-11-03 21:34:13'),(23,2,'TRX-00023','Pelanggan Umum',3500,'Tunai','Selesai',NULL,'2026-11-03 21:36:37'),(24,2,'TRX-00024','Pelanggan Umum',10500,'Tunai','Selesai',NULL,'2026-11-03 21:38:30'),(25,2,'TRX-00025','Pelanggan Umum',7000,'Tunai','Selesai',NULL,'2026-11-03 21:40:53'),(26,2,'TRX-00026','Pelanggan Umum',7000,'Tunai','Selesai',NULL,'2026-11-03 21:50:10'),(27,2,'TRX-00027','Pelanggan Umum',10500,'Tunai','Selesai',NULL,'2026-11-03 21:54:21'),(28,2,'TRX-00028','Pelanggan Umum',7000,'Tunai','Selesai',NULL,'2026-11-03 21:55:24'),(29,2,'TRX-00029','Pelanggan Umum',7000,'Tunai','Selesai',NULL,'2026-11-03 21:58:46'),(30,2,'TRX-00030','Pelanggan Umum',7000,'Tunai','Selesai',NULL,'2026-11-03 22:02:25'),(31,2,'TRX-00031','Pelanggan Umum',10500,'Tunai','Selesai',NULL,'2026-11-03 22:05:37'),(32,2,'TRX-00032','Pelanggan Umum',3500,'Tunai','Selesai',NULL,'2026-11-04 08:03:11'),(33,2,'TRX-00033','Pelanggan Umum',24500,'QRIS','Selesai','WK-2-1793754527','2026-11-04 08:08:47');
/*!40000 ALTER TABLE `transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_profiles`
--

DROP TABLE IF EXISTS `user_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_profiles` (
  `user_id` int NOT NULL,
  `user_type` enum('admin','customer','seller') NOT NULL,
  `alamat` text,
  `no_hp` varchar(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `user_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_profiles`
--

LOCK TABLES `user_profiles` WRITE;
/*!40000 ALTER TABLE `user_profiles` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_profiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `business_name` varchar(120) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(190) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `role` enum('owner','admin') NOT NULL DEFAULT 'owner',
  `is_master` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_phone` (`phone`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'wahyu','warung saya','$2y$10$gwOgGPJjtkMKIl99NvjGcukt/v28L8MKzkGZFxRQXx8qgr5wWFL4W','wahyu@gmail.com','081234567890','owner',0,'2026-10-19 06:56:15',1,'2026-10-19 06:56:15'),(2,'user','warungku','$2y$10$y5UNYseXpCaC7V7tQg3GDuh.maIWAekRo6z4Hy4XDboHs7bYVXwHC','123@gmail.com','088705576934','owner',0,'2026-10-25 06:50:21',1,'2026-10-25 06:50:21'),(3,'Administrator','WarungKasir','$2y$12$1CJ/AiRPQf3G.VKfynT6zeCgFYJrESEEaf4N2r484VLW2oqw0RVtG','admin@warungkasir.local','-','admin',1,'2026-10-25 11:43:58',1,'2026-10-25 11:43:58'),(4,'nama saya','saya','$2y$10$W6hLn5s6hCKf0M9AqjkGCOA8gCHTcHAdl103t1zBvGQFyUu2XwsNC','12345@gmail.com','000000000004','owner',0,'2026-10-26 06:16:05',1,'2026-11-01 04:41:19'),(6,'ssssss','sssss','$2y$10$Zclg1ltG3u.udbIajJYAuutsa7lEXLmDN4nfYmkV1Fk9aHlNwseIO','123111@gmail.com','000000000006','owner',0,'2026-10-31 02:15:05',1,'2026-11-01 04:41:19');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-04  1:13:25