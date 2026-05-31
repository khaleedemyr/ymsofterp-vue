-- Payroll generate 2 fase: status terpisah Gajian 1 & Gajian 2
-- Jalankan sekali di MySQL

ALTER TABLE `payroll_generated`
    ADD COLUMN IF NOT EXISTS `gajian1_status` ENUM('draft', 'generated', 'locked') NOT NULL DEFAULT 'draft' AFTER `status`,
    ADD COLUMN IF NOT EXISTS `gajian2_status` ENUM('draft', 'generated', 'locked') NOT NULL DEFAULT 'draft' AFTER `gajian1_status`,
    ADD COLUMN IF NOT EXISTS `gajian1_generated_at` DATETIME NULL DEFAULT NULL AFTER `gajian2_status`,
    ADD COLUMN IF NOT EXISTS `gajian2_generated_at` DATETIME NULL DEFAULT NULL AFTER `gajian1_generated_at`;

-- Backfill: payroll lama yang sudah generated dianggap kedua fase sudah generate
UPDATE `payroll_generated`
SET
    `gajian1_status` = CASE WHEN `status` IN ('generated', 'locked') THEN `status` ELSE 'draft' END,
    `gajian2_status` = CASE WHEN `status` IN ('generated', 'locked') THEN `status` ELSE 'draft' END,
    `gajian1_generated_at` = CASE WHEN `status` IN ('generated', 'locked') THEN COALESCE(`updated_at`, `created_at`) ELSE NULL END,
    `gajian2_generated_at` = CASE WHEN `status` IN ('generated', 'locked') THEN COALESCE(`updated_at`, `created_at`) ELSE NULL END
WHERE `gajian1_status` = 'draft'
  AND `gajian2_status` = 'draft'
  AND `status` IN ('generated', 'locked');
