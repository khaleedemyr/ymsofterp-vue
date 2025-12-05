-- Purchase Order Ops Approval Flow Table
-- This script creates the approval flow table for Purchase Order Ops system

CREATE TABLE IF NOT EXISTS purchase_order_ops_approval_flows (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    purchase_order_ops_id BIGINT UNSIGNED NOT NULL,
    approver_id BIGINT UNSIGNED NOT NULL,
    approval_level INT NOT NULL DEFAULT 1,
    status ENUM('PENDING', 'APPROVED', 'REJECTED') DEFAULT 'PENDING',
    approved_at TIMESTAMP NULL DEFAULT NULL,
    rejected_at TIMESTAMP NULL DEFAULT NULL,
    comments TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (purchase_order_ops_id) REFERENCES purchase_order_ops(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE CASCADE,
    
    UNIQUE KEY unique_po_approver (purchase_order_ops_id, approver_id),
    INDEX idx_approval_level (approval_level),
    INDEX idx_status (status)
);
