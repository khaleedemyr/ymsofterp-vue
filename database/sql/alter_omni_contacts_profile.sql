-- Profil kontak omnichannel (status marital, outlet pilihan, area)
-- Jalankan sekali; abaikan error "Duplicate column name" jika sudah ada

ALTER TABLE `omni_contacts`
    ADD COLUMN `marital_status` VARCHAR(32) NULL DEFAULT NULL AFTER `member_apps_member_id`,
    ADD COLUMN `preferred_outlet_id` INT UNSIGNED NULL DEFAULT NULL AFTER `marital_status`,
    ADD COLUMN `preferred_area` VARCHAR(255) NULL DEFAULT NULL AFTER `preferred_outlet_id`;

ALTER TABLE `omni_contacts`
    ADD KEY `omni_contacts_preferred_outlet_id_index` (`preferred_outlet_id`);
