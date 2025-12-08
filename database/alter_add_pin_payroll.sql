-- ALTER query untuk menambahkan kolom pin_payroll ke tabel users
-- Jalankan query ini di database untuk menambahkan field pin_payroll

ALTER TABLE `users` 
ADD COLUMN `pin_payroll` VARCHAR(10) NULL AFTER `pin_pos`;

-- Optional: Tambahkan index jika diperlukan untuk performa query
-- CREATE INDEX `idx_users_pin_payroll` ON `users` (`pin_payroll`);

