-- F&B Product Calibration — tambah mode kitchen/bar + kolom parameter bar
-- Eksekusi manual sekali di MySQL

ALTER TABLE `fb_product_calibrations`
    ADD COLUMN `mode` ENUM('kitchen', 'bar') NOT NULL DEFAULT 'kitchen' AFTER `conductor_name`,
    ADD KEY `fb_product_calibrations_mode_index` (`mode`);

ALTER TABLE `fb_product_calibration_results`
    ADD COLUMN `beverage_method` ENUM('C', 'NC') NULL AFTER `cooking_method`,
    ADD COLUMN `thickness` ENUM('C', 'NC') NULL AFTER `temperature`,
    ADD COLUMN `freshness` ENUM('C', 'NC') NULL AFTER `thickness`;
