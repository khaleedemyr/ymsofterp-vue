-- Query untuk menambahkan kolom payment_method dan bank_id ke tabel outlet_payments
-- Jalankan query ini langsung di database

-- 1. Tambahkan kolom payment_method
ALTER TABLE `outlet_payments`
ADD COLUMN `payment_method` VARCHAR(50) NULL DEFAULT 'cash'
AFTER `notes`;

-- 2. Tambahkan kolom bank_id
ALTER TABLE `outlet_payments`
ADD COLUMN `bank_id` BIGINT UNSIGNED NULL
AFTER `payment_method`;

-- 3. Tambahkan foreign key constraint untuk bank_id
ALTER TABLE `outlet_payments`
ADD CONSTRAINT `outlet_payments_bank_id_foreign`
FOREIGN KEY (`bank_id`)
REFERENCES `bank_accounts` (`id`)
ON DELETE SET NULL;

-- 4. Tambahkan kolom receiver_bank_id (bank penerima di head office)
ALTER TABLE `outlet_payments`
ADD COLUMN `receiver_bank_id` BIGINT UNSIGNED NULL
AFTER `bank_id`;

-- 5. Tambahkan foreign key constraint untuk receiver_bank_id
ALTER TABLE `outlet_payments`
ADD CONSTRAINT `outlet_payments_receiver_bank_id_foreign`
FOREIGN KEY (`receiver_bank_id`)
REFERENCES `bank_accounts` (`id`)
ON DELETE SET NULL;

-- 6. Tambahkan index untuk performa (opsional tapi direkomendasikan)
ALTER TABLE `outlet_payments`
ADD INDEX `idx_outlet_payments_bank_id` (`bank_id`);
ALTER TABLE `outlet_payments`
ADD INDEX `idx_outlet_payments_receiver_bank_id` (`receiver_bank_id`);