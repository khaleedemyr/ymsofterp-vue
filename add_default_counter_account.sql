-- Query untuk menambahkan kolom default_counter_account_id ke tabel chart_of_accounts
-- Jalankan query ini langsung di database

-- 1. Tambahkan kolom default_counter_account_id
ALTER TABLE `chart_of_accounts` 
ADD COLUMN `default_counter_account_id` BIGINT UNSIGNED NULL 
AFTER `budget_limit`;

-- 2. Tambahkan foreign key constraint
ALTER TABLE `chart_of_accounts` 
ADD CONSTRAINT `chart_of_accounts_default_counter_account_id_foreign` 
FOREIGN KEY (`default_counter_account_id`) 
REFERENCES `chart_of_accounts` (`id`) 
ON DELETE SET NULL;

-- 3. Tambahkan index untuk performa (opsional tapi direkomendasikan)
ALTER TABLE `chart_of_accounts` 
ADD INDEX `idx_default_counter_account_id` (`default_counter_account_id`);
