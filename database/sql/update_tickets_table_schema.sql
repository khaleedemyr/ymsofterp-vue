-- Update tickets table schema to use divisi_id instead of department_id

-- 1. Add divisi_id column
ALTER TABLE `tickets` ADD COLUMN `divisi_id` bigint(20) unsigned NULL AFTER `status_id`;

-- 2. Add foreign key constraint for divisi_id
ALTER TABLE `tickets` ADD CONSTRAINT `tickets_divisi_id_foreign` FOREIGN KEY (`divisi_id`) REFERENCES `tbl_data_divisi` (`id`) ON DELETE RESTRICT;

-- 3. Add index for divisi_id
ALTER TABLE `tickets` ADD KEY `tickets_divisi_id_foreign` (`divisi_id`);

-- 4. Remove old department_id column and its constraints (if exists)
-- ALTER TABLE `tickets` DROP FOREIGN KEY `tickets_department_id_foreign`;
-- ALTER TABLE `tickets` DROP KEY `tickets_department_id_foreign`;
-- ALTER TABLE `tickets` DROP COLUMN `department_id`;

-- 5. Remove assigned_to column and its constraints (if exists)
-- ALTER TABLE `tickets` DROP FOREIGN KEY `tickets_assigned_to_foreign`;
-- ALTER TABLE `tickets` DROP KEY `tickets_assigned_to_foreign`;
-- ALTER TABLE `tickets` DROP COLUMN `assigned_to`;
