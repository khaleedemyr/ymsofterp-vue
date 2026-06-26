-- Soft delete untuk F&B Product Calibration — eksekusi manual sekali di MySQL

ALTER TABLE `fb_product_calibrations`
    ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL AFTER `updated_at`,
    ADD KEY `fb_product_calibrations_deleted_at_index` (`deleted_at`);
