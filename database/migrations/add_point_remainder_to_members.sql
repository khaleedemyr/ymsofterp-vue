-- Add point_remainder field to member_apps_members table
-- This field stores fractional points (decimal) that will be accumulated
-- and converted to integer points when >= 1.0

ALTER TABLE `member_apps_members`
ADD COLUMN `point_remainder` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 
AFTER `just_points`;

-- Add comment
ALTER TABLE `member_apps_members`
MODIFY COLUMN `point_remainder` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 
COMMENT 'Fractional points remainder for earning rate 1.5 (Loyal tier). Accumulated and converted to integer when >= 1.0';

