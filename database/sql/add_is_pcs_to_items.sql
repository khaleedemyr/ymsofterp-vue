-- Item master: flag Item Pcs? (Yes/No)
-- Jalankan manual jika migration belum dijalankan.

ALTER TABLE `items`
  ADD COLUMN `is_pcs` TINYINT(1) NOT NULL DEFAULT 0 AFTER `exp`;
