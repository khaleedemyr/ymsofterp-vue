-- Add PAID status to purchase_requisitions status enum
-- This allows PR status to be updated to PAID when payment is completed

ALTER TABLE purchase_requisitions 
MODIFY COLUMN `status` ENUM('DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED', 'PROCESSED', 'COMPLETED', 'PAID') DEFAULT 'DRAFT';

