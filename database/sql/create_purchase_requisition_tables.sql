-- Create Purchase Requisition Tables
-- This script creates tables for the Purchase Requisition Ops system

-- 1. Purchase Requisition Categories Table
CREATE TABLE IF NOT EXISTS purchase_requisition_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    division ENUM('MARKETING', 'MAINTENANCE', 'ASSET', 'PROJECT_ENHANCEMENT') NOT NULL,
    subcategory VARCHAR(255) NOT NULL,
    budget_limit DECIMAL(15,2) NOT NULL,
    description TEXT,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL
);

-- 2. Purchase Requisitions Table (Updated to match existing structure)
CREATE TABLE IF NOT EXISTS purchase_requisitions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pr_number VARCHAR(50) NOT NULL UNIQUE,
    date DATE NOT NULL,
    warehouse_id BIGINT UNSIGNED NOT NULL,
    requested_by BIGINT UNSIGNED NOT NULL,
    department VARCHAR(255) NOT NULL,
    division_id BIGINT UNSIGNED NULL,
    category_id BIGINT UNSIGNED NULL,
    outlet_id BIGINT UNSIGNED NULL,
    ticket_id BIGINT UNSIGNED NULL,
    title VARCHAR(255) NULL,
    description TEXT NULL,
    amount DECIMAL(15,2) NULL,
    currency VARCHAR(3) DEFAULT 'IDR',
    status ENUM('DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED', 'PROCESSED', 'COMPLETED') DEFAULT 'DRAFT',
    priority ENUM('LOW', 'MEDIUM', 'HIGH', 'URGENT') DEFAULT 'MEDIUM',
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    approved_ssd_by BIGINT UNSIGNED NULL,
    approved_ssd_at TIMESTAMP NULL DEFAULT NULL,
    approved_cc_by BIGINT UNSIGNED NULL,
    approved_cc_at TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (division_id) REFERENCES divisis(id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES purchase_requisition_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (outlet_id) REFERENCES outlets(id) ON DELETE SET NULL,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_ssd_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_cc_by) REFERENCES users(id) ON DELETE SET NULL
);

-- 3. Purchase Requisition Attachments Table
CREATE TABLE IF NOT EXISTS purchase_requisition_attachments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    purchase_requisition_id BIGINT UNSIGNED NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    uploaded_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (purchase_requisition_id) REFERENCES purchase_requisitions(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);

-- 4. Purchase Requisition Comments Table
CREATE TABLE IF NOT EXISTS purchase_requisition_comments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    purchase_requisition_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    comment TEXT NOT NULL,
    is_internal BOOLEAN DEFAULT FALSE,
    attachment_path VARCHAR(255) NULL,
    attachment_name VARCHAR(255) NULL,
    attachment_size BIGINT UNSIGNED NULL,
    attachment_mime_type VARCHAR(100) NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (purchase_requisition_id) REFERENCES purchase_requisitions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 5. Purchase Requisition History Table
CREATE TABLE IF NOT EXISTS purchase_requisition_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    purchase_requisition_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(100) NOT NULL,
    old_status VARCHAR(50) NULL,
    new_status VARCHAR(50) NULL,
    description TEXT,
    created_at TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (purchase_requisition_id) REFERENCES purchase_requisitions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 6. Division Budget Tracking Table
CREATE TABLE IF NOT EXISTS division_budgets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    division ENUM('MARKETING', 'MAINTENANCE', 'ASSET', 'PROJECT_ENHANCEMENT') NOT NULL,
    year YEAR NOT NULL,
    total_budget DECIMAL(15,2) NOT NULL,
    used_budget DECIMAL(15,2) DEFAULT 0.00,
    remaining_budget DECIMAL(15,2) NOT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    
    UNIQUE KEY unique_division_year (division, year)
);

-- Create indexes for better performance
CREATE INDEX idx_purchase_requisitions_division ON purchase_requisitions(division);
CREATE INDEX idx_purchase_requisitions_status ON purchase_requisitions(status);
CREATE INDEX idx_purchase_requisitions_outlet ON purchase_requisitions(outlet_id);
CREATE INDEX idx_purchase_requisitions_ticket ON purchase_requisitions(ticket_id);
CREATE INDEX idx_purchase_requisitions_requested_by ON purchase_requisitions(requested_by);
CREATE INDEX idx_purchase_requisitions_created_at ON purchase_requisitions(created_at);
