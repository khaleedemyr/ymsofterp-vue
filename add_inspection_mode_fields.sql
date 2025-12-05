-- Add inspection_mode field to inspections table
ALTER TABLE `inspections` 
ADD COLUMN `inspection_mode` ENUM('product', 'cleanliness') NOT NULL DEFAULT 'product' AFTER `departemen`;

-- Add cleanliness_rating field to inspection_details table  
ALTER TABLE `inspection_details`
ADD COLUMN `cleanliness_rating` ENUM('Yes', 'No', 'NA') NULL AFTER `point`;

-- Update existing records to have product mode
UPDATE `inspections` SET `inspection_mode` = 'product' WHERE `inspection_mode` IS NULL;
