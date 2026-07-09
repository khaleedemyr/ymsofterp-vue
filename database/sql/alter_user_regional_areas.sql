-- Regional Management: multi-area per user (Bar/Kitchen/Service)
-- Jalankan sekali di MySQL setelah backup user_regional.

ALTER TABLE `user_regional`
  ADD COLUMN `areas` JSON NULL DEFAULT NULL
    COMMENT 'Regional areas: Bar, Kitchen, Service (multi-select)'
    AFTER `area`;

UPDATE `user_regional`
SET `areas` = JSON_ARRAY(`area`)
WHERE `areas` IS NULL AND `area` IS NOT NULL;
