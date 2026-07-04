-- NPD Plan Report items: PIC users (JSON array per item)
-- Eksekusi manual jika tabel npd_plan_report_items sudah ada.

ALTER TABLE `npd_plan_report_items`
    ADD COLUMN `pics` TEXT NULL COMMENT 'JSON array [{id,name,jabatan}] PIC users' AFTER `proposed_launch_area_outlet`;
