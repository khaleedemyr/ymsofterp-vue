-- Add Assistant SSD Manager approval columns to food_inventory_adjustments table
-- This allows warehouse stock adjustment approval to be separated by warehouse (MK vs non-MK)

ALTER TABLE `food_inventory_adjustments` 
ADD COLUMN `approved_by_assistant_ssd_manager` INT(11) NULL AFTER `approved_at_ssd_manager`,
ADD COLUMN `approved_at_assistant_ssd_manager` DATETIME NULL AFTER `approved_by_assistant_ssd_manager`,
ADD COLUMN `assistant_ssd_manager_note` TEXT NULL AFTER `approved_at_assistant_ssd_manager`;

-- Update status enum to include 'waiting_ssd_manager' status for non-MK warehouses
ALTER TABLE `food_inventory_adjustments` 
MODIFY COLUMN `status` ENUM('waiting_approval', 'waiting_ssd_manager', 'waiting_cost_control', 'approved', 'rejected') NOT NULL DEFAULT 'waiting_approval';

