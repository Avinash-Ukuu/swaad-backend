/*
SQLyog Ultimate v13.1.1 (64 bit)
MySQL - 10.4.32-MariaDB : Database - swaad_backend
*********************************************************************
*/

/*!40101 SET NAMES utf8 */;

/*!40101 SET SQL_MODE=''*/;

/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
CREATE DATABASE /*!32312 IF NOT EXISTS*/`swaad_backend` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;

USE `swaad_backend`;

/*Table structure for table `addresses` */

DROP TABLE IF EXISTS `addresses`;

CREATE TABLE `addresses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `recipient_name` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address_line_1` varchar(255) NOT NULL,
  `address_line_2` varchar(255) DEFAULT NULL,
  `landmark` varchar(255) DEFAULT NULL,
  `city` varchar(255) NOT NULL,
  `state` varchar(255) NOT NULL,
  `postal_code` varchar(10) NOT NULL,
  `country` varchar(2) NOT NULL DEFAULT 'IN',
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `addresses_user_id_is_default_index` (`user_id`,`is_default`),
  CONSTRAINT `addresses_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `addresses` */

/*Table structure for table `cache` */

DROP TABLE IF EXISTS `cache`;

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `cache` */

insert  into `cache`(`key`,`value`,`expiration`) values 
('laravel_cache_5c785c036466adea360111aa28563bfd556b5fba','i:1;',1793245677),
('laravel_cache_5c785c036466adea360111aa28563bfd556b5fba:timer','i:1793245677;',1793245677);

/*Table structure for table `cache_locks` */

DROP TABLE IF EXISTS `cache_locks`;

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `cache_locks` */

/*Table structure for table `failed_jobs` */

DROP TABLE IF EXISTS `failed_jobs`;

CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `failed_jobs` */

/*Table structure for table `job_batches` */

DROP TABLE IF EXISTS `job_batches`;

CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `job_batches` */

/*Table structure for table `jobs` */

DROP TABLE IF EXISTS `jobs`;

CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `jobs` */

/*Table structure for table `migrations` */

DROP TABLE IF EXISTS `migrations`;

CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `migrations` */

insert  into `migrations`(`id`,`migration`,`batch`) values 
(1,'0001_01_01_000000_create_users_table',1),
(2,'0001_01_01_000001_create_cache_table',1),
(3,'0001_01_01_000002_create_jobs_table',1),
(4,'2026_10_22_000001_add_swaad_fields_to_users_table',1),
(5,'2026_10_22_000002_create_addresses_table',1),
(6,'2026_10_22_000003_create_orders_table',1),
(7,'2026_10_22_000004_create_order_items_table',1),
(8,'2026_10_22_000005_create_payments_table',1),
(9,'2026_10_22_054136_create_personal_access_tokens_table',1),
(10,'2026_10_23_000001_add_payment_link_fields_and_webhook_events',1),
(11,'2026_10_23_000001_add_order_status_email_timestamps',2);

/*Table structure for table `order_items` */

DROP TABLE IF EXISTS `order_items`;

CREATE TABLE `order_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `product_id` varchar(100) NOT NULL,
  `product_variant_id` varchar(100) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `variant_name` varchar(255) DEFAULT NULL,
  `quantity` int(10) unsigned NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `line_total` decimal(12,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_items_order_id_foreign` (`order_id`),
  CONSTRAINT `order_items_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `order_items` */

insert  into `order_items`(`id`,`order_id`,`product_id`,`product_variant_id`,`product_name`,`variant_name`,`quantity`,`unit_price`,`line_total`,`created_at`,`updated_at`) values 
(5,5,'cow-ghee-500g','cow-ghee-500g','Pure Desi Cow Ghee','500g',1,1100.00,1100.00,'2026-10-23 05:22:27','2026-10-23 05:22:27'),
(6,5,'ajwain-honey-250g','ajwain-honey-250g','Ajwain Honey','250g',1,350.00,350.00,'2026-10-23 05:22:27','2026-10-23 05:22:27'),
(7,6,'cow-ghee-500g','cow-ghee-500g','Pure Desi Cow Ghee','500g',1,1100.00,1100.00,'2026-10-23 06:34:20','2026-10-23 06:34:20'),
(8,6,'ajwain-honey-250g','ajwain-honey-250g','Ajwain Honey','250g',1,350.00,350.00,'2026-10-23 06:34:20','2026-10-23 06:34:20'),
(9,7,'ajwain-honey-250g','ajwain-honey-250g','Ajwain Honey','250g',2,350.00,700.00,'2026-10-29 03:46:57','2026-10-29 03:46:57');

/*Table structure for table `orders` */

DROP TABLE IF EXISTS `orders`;

CREATE TABLE `orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_number` varchar(40) NOT NULL,
  `idempotency_key` varchar(100) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `customer_email` varchar(255) DEFAULT NULL,
  `checkout_token_hash` varchar(64) NOT NULL,
  `recipient_name` varchar(255) NOT NULL,
  `recipient_phone` varchar(20) NOT NULL,
  `delivery_address_line_1` varchar(255) NOT NULL,
  `delivery_address_line_2` varchar(255) DEFAULT NULL,
  `landmark` varchar(255) DEFAULT NULL,
  `city` varchar(255) NOT NULL,
  `state` varchar(255) NOT NULL,
  `postal_code` varchar(10) NOT NULL,
  `country` varchar(2) NOT NULL DEFAULT 'IN',
  `subtotal` decimal(12,2) NOT NULL,
  `shipping_fee` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'INR',
  `payment_status` varchar(30) NOT NULL DEFAULT 'pending',
  `order_status` varchar(30) NOT NULL DEFAULT 'pending_payment',
  `shipping_provider` varchar(255) DEFAULT NULL,
  `shipping_id` varchar(255) DEFAULT NULL,
  `tracking_url` text DEFAULT NULL,
  `customer_notes` text DEFAULT NULL,
  `internal_notes` text DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `shipped_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `confirmed_email_sent_at` timestamp NULL DEFAULT NULL,
  `shipped_email_sent_at` timestamp NULL DEFAULT NULL,
  `delivered_email_sent_at` timestamp NULL DEFAULT NULL,
  `coupon_code` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `orders_order_number_unique` (`order_number`),
  UNIQUE KEY `orders_idempotency_key_unique` (`idempotency_key`),
  KEY `orders_user_id_foreign` (`user_id`),
  KEY `orders_created_at_payment_status_order_status_index` (`created_at`,`payment_status`,`order_status`),
  KEY `orders_payment_status_index` (`payment_status`),
  KEY `orders_order_status_index` (`order_status`),
  KEY `orders_shipping_id_index` (`shipping_id`),
  CONSTRAINT `orders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `orders` */

insert  into `orders`(`id`,`order_number`,`idempotency_key`,`user_id`,`customer_name`,`customer_email`,`checkout_token_hash`,`recipient_name`,`recipient_phone`,`delivery_address_line_1`,`delivery_address_line_2`,`landmark`,`city`,`state`,`postal_code`,`country`,`subtotal`,`shipping_fee`,`discount_amount`,`total_amount`,`currency`,`payment_status`,`order_status`,`shipping_provider`,`shipping_id`,`tracking_url`,`customer_notes`,`internal_notes`,`paid_at`,`confirmed_at`,`shipped_at`,`delivered_at`,`created_at`,`updated_at`,`confirmed_email_sent_at`,`shipped_email_sent_at`,`delivered_email_sent_at`,`coupon_code`) values 
(5,'SWD-20261023-BYOYLP','swaad-test-checkout-002',NULL,'Avinash Test','avinash.test@example.com','f2dbf2890ec5561f1085e7422851dc945e20af60746a81efc8019f403dd71c2f','Avinash Test','9876543210','House No. 25, Model Town','Near Main Market','Near City Park','Jalandhar','Punjab','144001','IN',1450.00,0.00,0.00,1450.00,'INR','paid','returned','test','12121212121212','https://swaadtasteofpunjab.com/','Please call before delivery.',NULL,'2026-10-23 05:25:53','2026-10-23 05:25:53','2026-10-23 09:18:47','2026-10-23 09:18:55','2026-10-23 05:22:27','2026-10-23 09:19:04',NULL,NULL,NULL,NULL),
(6,'SWD-20261023-SKBI5B','swaad-test-checkout-005',NULL,'Shobit Test','shobit.test@example.com','bf32fb19cadc6007e7d5091bd710d2aa68ea2c3b92c6d6581367a155cf478ef0','Shobit Test','9876543210','House No. 25, Model Town','Near Main Market','Near City Park','Jalandhar','Punjab','144001','IN',1450.00,0.00,0.00,1450.00,'INR','paid','delivered','asa','asas','https://swaadtasteofpunjab.com/','Please call before delivery.','aaa','2026-10-23 06:35:38','2026-10-23 06:35:38','2026-10-23 06:49:48','2026-10-23 06:49:56','2026-10-23 06:34:20','2026-10-23 06:49:56',NULL,NULL,NULL,NULL),
(7,'SWD-20261029-HSXQVH','test-nijjarr200-001',NULL,'Test Customer','customer@example.com','8d725e220f425dabdfc1e14687d7535ce16717fad9f3d8c7748de3d051e9a68f','Test Customer','9876543210','1 Test Street',NULL,NULL,'Jalandhar','Punjab','144001','IN',700.00,0.00,200.00,500.00,'INR','pending','pending_payment',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-29 03:46:57','2026-10-29 03:46:57',NULL,NULL,NULL,'NIJJARR200');

/*Table structure for table `password_reset_tokens` */

DROP TABLE IF EXISTS `password_reset_tokens`;

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `password_reset_tokens` */

/*Table structure for table `payments` */

DROP TABLE IF EXISTS `payments`;

CREATE TABLE `payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `provider` varchar(255) NOT NULL DEFAULT 'razorpay',
  `razorpay_order_id` varchar(255) DEFAULT NULL,
  `razorpay_payment_id` varchar(255) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'INR',
  `status` varchar(30) NOT NULL DEFAULT 'created',
  `signature_verified_at` timestamp NULL DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `failure_reason` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `razorpay_payment_link_id` varchar(255) DEFAULT NULL,
  `payment_link_url` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payments_razorpay_order_id_unique` (`razorpay_order_id`),
  UNIQUE KEY `payments_razorpay_payment_id_unique` (`razorpay_payment_id`),
  UNIQUE KEY `payments_razorpay_payment_link_id_unique` (`razorpay_payment_link_id`),
  KEY `payments_order_id_foreign` (`order_id`),
  KEY `payments_status_index` (`status`),
  CONSTRAINT `payments_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `payments` */

insert  into `payments`(`id`,`order_id`,`provider`,`razorpay_order_id`,`razorpay_payment_id`,`amount`,`currency`,`status`,`signature_verified_at`,`paid_at`,`failure_reason`,`created_at`,`updated_at`,`razorpay_payment_link_id`,`payment_link_url`) values 
(5,5,'razorpay',NULL,'pay_TrEXD0FsruT1Yt',1450.00,'INR','paid','2026-10-23 05:25:53','2026-10-23 05:25:53',NULL,'2026-10-23 05:22:27','2026-10-23 05:25:53','plink_TrEU7WhzD2OT2d','https://rzp.io/rzp/Rk2qqkw'),
(6,6,'razorpay',NULL,'pay_TrFiussQkKOB59',1450.00,'INR','paid','2026-10-23 06:35:38','2026-10-23 06:35:38',NULL,'2026-10-23 06:34:20','2026-10-23 06:35:38','plink_TrFi42E3xFKFYM','https://rzp.io/rzp/u5uBwgE'),
(7,7,'razorpay',NULL,NULL,500.00,'INR','failed',NULL,NULL,'Razorpay rejected Payment Link creation.','2026-10-29 03:46:57','2026-10-29 03:46:58',NULL,NULL);

/*Table structure for table `personal_access_tokens` */

DROP TABLE IF EXISTS `personal_access_tokens`;

CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `personal_access_tokens` */

/*Table structure for table `razorpay_webhook_events` */

DROP TABLE IF EXISTS `razorpay_webhook_events`;

CREATE TABLE `razorpay_webhook_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` varchar(255) NOT NULL,
  `event_type` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `razorpay_webhook_events_event_id_unique` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `razorpay_webhook_events` */

/*Table structure for table `sessions` */

DROP TABLE IF EXISTS `sessions`;

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `sessions` */

insert  into `sessions`(`id`,`user_id`,`ip_address`,`user_agent`,`payload`,`last_activity`) values 
('QuvPQFbfz3zCYhAsnr6cWJENRYrdrPdE7NOVHvru',1,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','YTo1OntzOjY6Il90b2tlbiI7czo0MDoiRHZERldsNWRidjlicTVRVk9yVXdCM0p0MzlVd3ljbzAzdjFKcFlPVSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzY6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9hZG1pbi9vcmRlcnMvNiI7czo1OiJyb3V0ZSI7czoxNzoiYWRtaW4ub3JkZXJzLnNob3ciO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX1zOjUwOiJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI7aToxO3M6MTc6InBhc3N3b3JkX2hhc2hfd2ViIjtzOjY0OiIwOGI2ZjExYzhhZDMzMzFkNTA1MDM4ZTNhNTM1MTJmNWI0NzQyNTUxZWQzNmM0NDg0YWE0NTI5ZTgzYzdjMWJlIjt9',1792738204),
('rVTTIH3lu1v0yduoW6lh7aK8nVhT7oDCh37yJwlM',1,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','YTo1OntzOjY6Il90b2tlbiI7czo0MDoidzhIMEx2M2cweVdQQTJKT1lwMjkyaURJVGlpbzBwYVVlbU1wdzJvRCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NDQ6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9hZG1pbi9vcmRlcnMvNi9pbnZvaWNlIjtzOjU6InJvdXRlIjtzOjIwOiJhZG1pbi5vcmRlcnMuaW52b2ljZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fXM6NTA6ImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjtpOjE7czoxNzoicGFzc3dvcmRfaGFzaF93ZWIiO3M6NjQ6IjA4YjZmMTFjOGFkMzMzMWQ1MDUwMzhlM2E1MzUxMmY1YjQ3NDI1NTFlZDM2YzQ0ODRhYTQ1MjllODNjN2MxYmUiO30=',1793245234),
('SBjzOFGFOzMCF9IWLyt5FdzKMvRdMlUuDijdR62b',1,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','YTo1OntzOjY6Il90b2tlbiI7czo0MDoieTFkSlUzSHRURnFFSDl6RTNrdVhSMnhLcldXRjVlYTl1ZlgyMUJLbCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NDQ6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9hZG1pbi9vcmRlcnMvNi9pbnZvaWNlIjtzOjU6InJvdXRlIjtzOjIwOiJhZG1pbi5vcmRlcnMuaW52b2ljZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fXM6NTA6ImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjtpOjE7czoxNzoicGFzc3dvcmRfaGFzaF93ZWIiO3M6NjQ6IjA4YjZmMTFjOGFkMzMzMWQ1MDUwMzhlM2E1MzUxMmY1YjQ3NDI1NTFlZDM2YzQ0ODRhYTQ1MjllODNjN2MxYmUiO30=',1793181708),
('uL7iUvhzKnkbIRcx3njEpfssvEd8wR3Y0Am5i9xO',1,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','YTo1OntzOjY6Il90b2tlbiI7czo0MDoiT2J4blp5NXplSmZYUXpKMk40blVKMDExR2h2bFhWT3ZUMWtCTUpDMyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzY6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9hZG1pbi9vcmRlcnMvNSI7czo1OiJyb3V0ZSI7czoxNzoiYWRtaW4ub3JkZXJzLnNob3ciO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX1zOjUwOiJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI7aToxO3M6MTc6InBhc3N3b3JkX2hhc2hfd2ViIjtzOjY0OiIwOGI2ZjExYzhhZDMzMzFkNTA1MDM4ZTNhNTM1MTJmNWI0NzQyNTUxZWQzNmM0NDg0YWE0NTI5ZTgzYzdjMWJlIjt9',1792747152);

/*Table structure for table `users` */

DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_phone_index` (`phone`),
  KEY `users_is_admin_index` (`is_admin`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `users` */

insert  into `users`(`id`,`name`,`email`,`email_verified_at`,`password`,`remember_token`,`created_at`,`updated_at`,`phone`,`is_admin`) values 
(1,'admin','admin@gmail.com',NULL,'$2y$12$T9H0KZpkrNsY0d8SV4ovM.8IAaebAZq52HIRMLahERobFWJj9kfSm',NULL,'2026-10-23 12:15:20','2026-10-23 12:15:17',NULL,1);

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
