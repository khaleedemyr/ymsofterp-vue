ALTER TABLE `member_apps_vouchers`
ADD COLUMN `is_birthday_voucher` TINYINT(1) NOT NULL DEFAULT 0 AFTER `one_time_purchase`;

