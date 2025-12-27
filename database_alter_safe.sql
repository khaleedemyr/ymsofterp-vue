-- ============================================
-- QUERY ALTER TABLE AMAN (Tanpa AFTER clause yang berisiko)
-- Versi ini menambahkan kolom di akhir tabel untuk menghindari error
-- ============================================
-- Ganti 'database2' dengan nama database target Anda
-- BACKUP DATABASE TERLEBIH DAHULU sebelum menjalankan query ini!

USE database2;

-- ============================================
-- 1. Tabel: investor_outlet
-- ============================================
CREATE TABLE IF NOT EXISTS `investor_outlet` (
  `id` int NOT NULL AUTO_INCREMENT,
  `investor_id` int NOT NULL,
  `outlet_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `investor_outlet_investor_id_foreign` (`investor_id`),
  KEY `investor_outlet_outlet_id_foreign` (`outlet_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. Tabel: order_items
-- ============================================
-- Tambahkan kolom tanpa AFTER clause (akan ditambahkan di akhir)
ALTER TABLE `order_items` 
ADD COLUMN `b1g1_promo_id` int NULL DEFAULT NULL;

ALTER TABLE `order_items` 
ADD COLUMN `b1g1_status` varchar(20) NULL DEFAULT NULL;

-- ============================================
-- 3. Tabel: orders
-- ============================================
ALTER TABLE `orders` 
ADD COLUMN `voucher_info` text NULL DEFAULT NULL;

ALTER TABLE `orders` 
ADD COLUMN `inactive_promo_items` text NULL DEFAULT NULL;

ALTER TABLE `orders` 
ADD COLUMN `promo_discount_info` text NULL DEFAULT NULL;

-- ============================================
-- 4. Tabel: promos
-- ============================================
ALTER TABLE `promos` 
ADD COLUMN `all_tiers` tinyint(1) NULL DEFAULT 0;

ALTER TABLE `promos` 
ADD COLUMN `tiers` json NULL DEFAULT NULL;

ALTER TABLE `promos` 
ADD COLUMN `days` json NULL DEFAULT NULL;

-- ============================================
-- CATATAN:
-- ============================================
-- Query ini menambahkan kolom di akhir tabel untuk menghindari error
-- Jika perlu mengatur posisi kolom, jalankan query helper terlebih dahulu
-- untuk melihat struktur tabel, lalu edit query dengan AFTER clause yang benar

