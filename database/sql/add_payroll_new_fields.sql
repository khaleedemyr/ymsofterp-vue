-- Add new columns to payroll_generated_details table for alpha, potongan alpha, unpaid leave, and leave data
-- Run this SQL if columns don't exist yet

ALTER TABLE `payroll_generated_details`
ADD COLUMN IF NOT EXISTS `total_alpha` int(11) DEFAULT 0 COMMENT 'Total hari alpha' AFTER `potongan_telat`,
ADD COLUMN IF NOT EXISTS `potongan_alpha` decimal(15,2) DEFAULT 0.00 COMMENT 'Potongan alpha (20% dari gaji pokok + tunjangan × total hari alpha)' AFTER `total_alpha`,
ADD COLUMN IF NOT EXISTS `potongan_unpaid_leave` decimal(15,2) DEFAULT 0.00 COMMENT 'Potongan unpaid leave (pro rate dari gaji pokok + tunjangan / 26 × jumlah unpaid leave)' AFTER `potongan_alpha`,
ADD COLUMN IF NOT EXISTS `leave_data` longtext DEFAULT NULL COMMENT 'JSON leave data breakdown per leave type' AFTER `custom_items`;
