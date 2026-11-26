-- Sales Lead: Room Requirement + Attachment
-- Sudah dijalankan via migrate di environment yang pakai artisan.
-- Untuk server yang manual SQL saja:

ALTER TABLE `sales_leads`
  ADD COLUMN `room_requirement` VARCHAR(50) NULL AFTER `outlet_id`,
  ADD COLUMN `attachment` VARCHAR(255) NULL AFTER `closing_remarks`,
  ADD COLUMN `attachment_original_name` VARCHAR(255) NULL AFTER `attachment`;
