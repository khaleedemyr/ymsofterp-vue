-- NPD Plan Report items: category_id + JSON launch outlets
-- Eksekusi manual jika tabel npd_plan_report_items sudah ada.

ALTER TABLE `npd_plan_report_items`
    ADD COLUMN `category_id` INT UNSIGNED NULL AFTER `category`;

ALTER TABLE `npd_plan_report_items`
    MODIFY COLUMN `proposed_launch_area_outlet` TEXT NULL COMMENT 'JSON array [{id,name}] launch outlets';
