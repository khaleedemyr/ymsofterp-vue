-- ============================================
-- QUERY ALTER TABLE untuk menambahkan kolom yang hilang
-- Berdasarkan hasil perbandingan: Kolom di DB1 tapi tidak di DB2
-- ============================================
-- Ganti 'database2' dengan nama database target Anda
-- BACKUP DATABASE TERLEBIH DAHULU sebelum menjalankan query ini!

USE database2;

-- ============================================
-- 1. Tabel: investor_outlet
-- ============================================
-- Jika tabel investor_outlet belum ada sama sekali, buat tabelnya:
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

-- Jika tabel sudah ada, tambahkan kolom yang belum ada (uncomment jika perlu):
-- ALTER TABLE `investor_outlet` ADD COLUMN `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST;
-- ALTER TABLE `investor_outlet` ADD COLUMN `investor_id` int NOT NULL AFTER `id`;
-- ALTER TABLE `investor_outlet` ADD COLUMN `outlet_id` int NOT NULL AFTER `investor_id`;
-- ALTER TABLE `investor_outlet` ADD COLUMN `created_at` timestamp NULL DEFAULT NULL AFTER `outlet_id`;
-- ALTER TABLE `investor_outlet` ADD COLUMN `updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`;

-- ============================================
-- 2. Tabel: order_items
-- ============================================
-- Cek dulu struktur tabel dengan: DESCRIBE order_items;
-- Atau: SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'database2' AND TABLE_NAME = 'order_items' ORDER BY ORDINAL_POSITION;

-- Tambahkan kolom b1g1_promo_id
-- Jika kolom 'id' ada, gunakan AFTER 'id', jika tidak gunakan tanpa AFTER (akan ditambahkan di akhir)
ALTER TABLE `order_items` 
ADD COLUMN `b1g1_promo_id` int NULL DEFAULT NULL;

-- Jika ingin menempatkan setelah kolom tertentu, sesuaikan query di bawah ini:
-- ALTER TABLE `order_items` ADD COLUMN `b1g1_promo_id` int NULL DEFAULT NULL AFTER `id`; -- Ganti 'id' dengan kolom yang benar-benar ada

-- Tambahkan kolom b1g1_status setelah b1g1_promo_id
ALTER TABLE `order_items` 
ADD COLUMN `b1g1_status` varchar(20) NULL DEFAULT NULL 
AFTER `b1g1_promo_id`;

-- Optional: Tambahkan index jika diperlukan
-- ALTER TABLE `order_items` ADD INDEX `idx_b1g1_promo_id` (`b1g1_promo_id`);

-- Optional: Tambahkan foreign key jika diperlukan
-- ALTER TABLE `order_items` 
-- ADD CONSTRAINT `fk_order_items_b1g1_promo` 
-- FOREIGN KEY (`b1g1_promo_id`) REFERENCES `promos` (`id`) ON DELETE SET NULL;

-- ============================================
-- 3. Tabel: orders
-- ============================================
-- Cek dulu struktur tabel dengan: DESCRIBE orders;
-- Atau: SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'database2' AND TABLE_NAME = 'orders' ORDER BY ORDINAL_POSITION;

-- Tambahkan kolom voucher_info
-- Jika kolom 'id' ada, gunakan AFTER 'id', jika tidak gunakan tanpa AFTER (akan ditambahkan di akhir)
ALTER TABLE `orders` 
ADD COLUMN `voucher_info` text NULL DEFAULT NULL;

-- Jika ingin menempatkan setelah kolom tertentu, sesuaikan query di bawah ini:
-- ALTER TABLE `orders` ADD COLUMN `voucher_info` text NULL DEFAULT NULL AFTER `id`; -- Ganti 'id' dengan kolom yang benar-benar ada

-- Tambahkan kolom inactive_promo_items setelah voucher_info
ALTER TABLE `orders` 
ADD COLUMN `inactive_promo_items` text NULL DEFAULT NULL 
AFTER `voucher_info`;

-- Tambahkan kolom promo_discount_info setelah inactive_promo_items
ALTER TABLE `orders` 
ADD COLUMN `promo_discount_info` text NULL DEFAULT NULL 
AFTER `inactive_promo_items`;

-- ============================================
-- 4. Tabel: promos
-- ============================================
-- Cek dulu struktur tabel dengan: DESCRIBE promos;
-- Atau: SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'database2' AND TABLE_NAME = 'promos' ORDER BY ORDINAL_POSITION;

-- Tambahkan kolom all_tiers
-- Jika kolom 'id' ada, gunakan AFTER 'id', jika tidak gunakan tanpa AFTER (akan ditambahkan di akhir)
ALTER TABLE `promos` 
ADD COLUMN `all_tiers` tinyint(1) NULL DEFAULT 0;

-- Jika ingin menempatkan setelah kolom tertentu, sesuaikan query di bawah ini:
-- ALTER TABLE `promos` ADD COLUMN `all_tiers` tinyint(1) NULL DEFAULT 0 AFTER `id`; -- Ganti 'id' dengan kolom yang benar-benar ada

-- Tambahkan kolom tiers setelah all_tiers
ALTER TABLE `promos` 
ADD COLUMN `tiers` json NULL DEFAULT NULL 
AFTER `all_tiers`;

-- Tambahkan kolom days
-- Jika kolom 'id' ada, gunakan AFTER 'id', jika tidak gunakan tanpa AFTER (akan ditambahkan di akhir)
ALTER TABLE `promos` 
ADD COLUMN `days` json NULL DEFAULT NULL;

-- Jika ingin menempatkan setelah kolom tertentu, sesuaikan query di bawah ini:
-- ALTER TABLE `promos` ADD COLUMN `days` json NULL DEFAULT NULL AFTER `end_date`; -- Ganti 'end_date' dengan kolom yang benar-benar ada

-- ============================================
-- QUERY UNTUK MENCARI KOLOM YANG ADA (Helper Query)
-- ============================================
-- Jalankan query di bawah ini untuk melihat kolom yang ada di setiap tabel:

-- Untuk order_items:
-- SELECT COLUMN_NAME, ORDINAL_POSITION 
-- FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_SCHEMA = 'database2' AND TABLE_NAME = 'order_items'
-- ORDER BY ORDINAL_POSITION;

-- Untuk orders:
-- SELECT COLUMN_NAME, ORDINAL_POSITION 
-- FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_SCHEMA = 'database2' AND TABLE_NAME = 'orders'
-- ORDER BY ORDINAL_POSITION;

-- Untuk promos:
-- SELECT COLUMN_NAME, ORDINAL_POSITION 
-- FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_SCHEMA = 'database2' AND TABLE_NAME = 'promos'
-- ORDER BY ORDINAL_POSITION;

-- ============================================
-- CATATAN PENTING:
-- ============================================
-- 1. Query di atas menggunakan pendekatan AMAN tanpa AFTER clause yang merujuk ke kolom yang mungkin tidak ada
-- 2. Kolom akan ditambahkan di akhir tabel jika tidak ada AFTER clause
-- 3. Jika ingin menempatkan kolom di posisi tertentu:
--    a. Jalankan query helper di atas untuk melihat kolom yang ada
--    b. Edit query ALTER dan tambahkan AFTER clause dengan kolom yang benar-benar ada
-- 4. BACKUP database terlebih dahulu sebelum menjalankan query ini!
-- 5. Test di development/staging terlebih dahulu sebelum production!
