-- Query untuk menambahkan kolom bank_id ke tabel non_food_payments
-- Jalankan query ini langsung di database

-- 1. Tambahkan kolom bank_id
ALTER TABLE `non_food_payments` 
ADD COLUMN `bank_id` BIGINT UNSIGNED NULL 
AFTER `payment_method`;

-- 2. Tambahkan foreign key constraint
ALTER TABLE `non_food_payments` 
ADD CONSTRAINT `non_food_payments_bank_id_foreign` 
FOREIGN KEY (`bank_id`) 
REFERENCES `bank_accounts` (`id`) 
ON DELETE SET NULL;

-- 3. Tambahkan index untuk performa (opsional tapi direkomendasikan)
ALTER TABLE `non_food_payments` 
ADD INDEX `idx_bank_id` (`bank_id`);
