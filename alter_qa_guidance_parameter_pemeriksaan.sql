-- Alter table qa_guidance_category_parameters to change parameter_pemeriksaan from varchar(255) to TEXT
-- This removes the 255 character limit

ALTER TABLE `qa_guidance_category_parameters` 
MODIFY COLUMN `parameter_pemeriksaan` TEXT NOT NULL;

