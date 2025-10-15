-- Create Non Food Payments Tables
-- This script creates tables for the Non Food Payment system

-- 1. Non Food Payments Table
CREATE TABLE IF NOT EXISTS non_food_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_number VARCHAR(50) NOT NULL UNIQUE,
    purchase_order_ops_id BIGINT UNSIGNED NULL,
    purchase_requisition_id BIGINT UNSIGNED NULL,
    supplier_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    payment_method ENUM('cash', 'transfer', 'check') NOT NULL DEFAULT 'transfer',
    payment_date DATE NOT NULL,
    due_date DATE NULL,
    status ENUM('pending', 'approved', 'paid', 'rejected', 'cancelled') DEFAULT 'pending',
    description TEXT NULL,
    reference_number VARCHAR(100) NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    approved_by BIGINT UNSIGNED NULL,
    approved_at TIMESTAMP NULL DEFAULT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (purchase_order_ops_id) REFERENCES purchase_order_ops(id) ON DELETE SET NULL,
    FOREIGN KEY (purchase_requisition_id) REFERENCES purchase_requisitions(id) ON DELETE SET NULL,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

-- 2. Non Food Payment Attachments Table
CREATE TABLE IF NOT EXISTS non_food_payment_attachments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    non_food_payment_id BIGINT UNSIGNED NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    uploaded_by BIGINT UNSIGNED NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (non_food_payment_id) REFERENCES non_food_payments(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Create indexes for better performance
CREATE INDEX idx_non_food_payments_purchase_order_ops ON non_food_payments(purchase_order_ops_id);
CREATE INDEX idx_non_food_payments_purchase_requisition ON non_food_payments(purchase_requisition_id);
CREATE INDEX idx_non_food_payments_supplier ON non_food_payments(supplier_id);
CREATE INDEX idx_non_food_payments_status ON non_food_payments(status);
CREATE INDEX idx_non_food_payments_created_by ON non_food_payments(created_by);
CREATE INDEX idx_non_food_payments_payment_date ON non_food_payments(payment_date);
CREATE INDEX idx_non_food_payments_payment_number ON non_food_payments(payment_number);

CREATE INDEX idx_non_food_payment_attachments_payment ON non_food_payment_attachments(non_food_payment_id);
CREATE INDEX idx_non_food_payment_attachments_uploader ON non_food_payment_attachments(uploaded_by);
