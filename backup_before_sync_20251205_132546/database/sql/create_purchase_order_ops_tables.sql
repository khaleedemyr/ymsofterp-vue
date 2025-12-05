-- Create Purchase Order Ops Tables
-- This script creates tables for the Purchase Order Ops system (without stock management)

-- 1. Purchase Order Ops Table
CREATE TABLE IF NOT EXISTS purchase_order_ops (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    number VARCHAR(50) NOT NULL UNIQUE,
    date DATE NOT NULL,
    supplier_id BIGINT UNSIGNED NOT NULL,
    status ENUM('draft', 'approved', 'received', 'rejected') DEFAULT 'draft',
    created_by BIGINT UNSIGNED NOT NULL,
    notes TEXT NULL,
    arrival_date DATE NULL,
    purchasing_manager_approved_at TIMESTAMP NULL DEFAULT NULL,
    purchasing_manager_approved_by BIGINT UNSIGNED NULL,
    purchasing_manager_note TEXT NULL,
    gm_finance_approved_at TIMESTAMP NULL DEFAULT NULL,
    gm_finance_approved_by BIGINT UNSIGNED NULL,
    gm_finance_note TEXT NULL,
    ppn_enabled BOOLEAN DEFAULT FALSE,
    ppn_amount DECIMAL(15,2) DEFAULT 0.00,
    subtotal DECIMAL(15,2) DEFAULT 0.00,
    grand_total DECIMAL(15,2) DEFAULT 0.00,
    source_type ENUM('purchase_requisition_ops') DEFAULT 'purchase_requisition_ops',
    source_id BIGINT UNSIGNED NULL COMMENT 'ID of the source Purchase Requisition Ops',
    printed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (purchasing_manager_approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (gm_finance_approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (source_id) REFERENCES purchase_requisitions(id) ON DELETE SET NULL
);

-- 2. Purchase Order Ops Items Table
CREATE TABLE IF NOT EXISTS purchase_order_ops_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    purchase_order_ops_id BIGINT UNSIGNED NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    quantity DECIMAL(15,2) NOT NULL,
    unit VARCHAR(50) NOT NULL,
    price DECIMAL(15,2) NOT NULL,
    total DECIMAL(15,2) NOT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    arrival_date DATE NULL,
    pr_ops_item_id BIGINT UNSIGNED NULL COMMENT 'Reference to purchase_requisition_items.id',
    source_type ENUM('purchase_requisition_ops') DEFAULT 'purchase_requisition_ops',
    source_id BIGINT UNSIGNED NULL COMMENT 'ID of the source Purchase Requisition Ops',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (purchase_order_ops_id) REFERENCES purchase_order_ops(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (pr_ops_item_id) REFERENCES purchase_requisition_items(id) ON DELETE SET NULL,
    FOREIGN KEY (source_id) REFERENCES purchase_requisitions(id) ON DELETE SET NULL
);

-- Create indexes for better performance
CREATE INDEX idx_purchase_order_ops_supplier ON purchase_order_ops(supplier_id);
CREATE INDEX idx_purchase_order_ops_status ON purchase_order_ops(status);
CREATE INDEX idx_purchase_order_ops_created_by ON purchase_order_ops(created_by);
CREATE INDEX idx_purchase_order_ops_date ON purchase_order_ops(date);
CREATE INDEX idx_purchase_order_ops_source ON purchase_order_ops(source_type, source_id);
CREATE INDEX idx_purchase_order_ops_items_po ON purchase_order_ops_items(purchase_order_ops_id);
CREATE INDEX idx_purchase_order_ops_items_source ON purchase_order_ops_items(source_type, source_id);
