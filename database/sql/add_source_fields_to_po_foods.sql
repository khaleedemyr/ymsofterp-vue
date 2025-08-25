-- Add source fields to purchase_order_foods table
ALTER TABLE purchase_order_foods 
ADD COLUMN source_type ENUM('pr_foods', 'ro_supplier') NULL COMMENT 'Source type: PR Foods or RO Supplier',
ADD COLUMN source_id BIGINT UNSIGNED NULL COMMENT 'ID of the source document (PR ID or RO ID)';

-- Add source fields to purchase_order_food_items table
ALTER TABLE purchase_order_food_items 
ADD COLUMN source_type ENUM('pr_foods', 'ro_supplier') NULL COMMENT 'Source type: PR Foods or RO Supplier',
ADD COLUMN source_id BIGINT UNSIGNED NULL COMMENT 'ID of the source document (PR ID or RO ID)',
ADD COLUMN ro_id BIGINT UNSIGNED NULL COMMENT 'RO ID if source is RO Supplier',
ADD COLUMN ro_number VARCHAR(255) NULL COMMENT 'RO Number if source is RO Supplier';

-- Add indexes for better performance
ALTER TABLE purchase_order_foods ADD INDEX idx_source_type (source_type);
ALTER TABLE purchase_order_foods ADD INDEX idx_source_id (source_id);
ALTER TABLE purchase_order_food_items ADD INDEX idx_source_type (source_type);
ALTER TABLE purchase_order_food_items ADD INDEX idx_source_id (source_id);
ALTER TABLE purchase_order_food_items ADD INDEX idx_ro_id (ro_id);
