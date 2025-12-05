-- Alter existing purchase_requisitions table to add required columns
-- This script adds the missing columns to match the new structure

-- Add division_id column
ALTER TABLE purchase_requisitions 
ADD COLUMN division_id BIGINT UNSIGNED NULL AFTER department,
ADD FOREIGN KEY (division_id) REFERENCES divisis(id) ON DELETE SET NULL;

-- Add category_id column
ALTER TABLE purchase_requisitions 
ADD COLUMN category_id BIGINT UNSIGNED NULL AFTER division_id,
ADD FOREIGN KEY (category_id) REFERENCES purchase_requisition_categories(id) ON DELETE SET NULL;

-- Add outlet_id column
ALTER TABLE purchase_requisitions 
ADD COLUMN outlet_id BIGINT UNSIGNED NULL AFTER category_id,
ADD FOREIGN KEY (outlet_id) REFERENCES outlets(id) ON DELETE SET NULL;

-- Add ticket_id column
ALTER TABLE purchase_requisitions 
ADD COLUMN ticket_id BIGINT UNSIGNED NULL AFTER outlet_id,
ADD FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE SET NULL;

-- Add title column
ALTER TABLE purchase_requisitions 
ADD COLUMN title VARCHAR(255) NULL AFTER ticket_id;

-- Add description column
ALTER TABLE purchase_requisitions 
ADD COLUMN description TEXT NULL AFTER title;

-- Add amount column
ALTER TABLE purchase_requisitions 
ADD COLUMN amount DECIMAL(15,2) NULL AFTER description;

-- Add currency column
ALTER TABLE purchase_requisitions 
ADD COLUMN currency VARCHAR(3) DEFAULT 'IDR' AFTER amount;

-- Add priority column
ALTER TABLE purchase_requisitions 
ADD COLUMN priority ENUM('LOW', 'MEDIUM', 'HIGH', 'URGENT') DEFAULT 'MEDIUM' AFTER status;
