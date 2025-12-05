-- Outlet Food Inventory Adjustment Approval Flow Table
CREATE TABLE IF NOT EXISTS outlet_food_inventory_adjustment_approval_flows (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    adjustment_id BIGINT UNSIGNED NOT NULL,
    approver_id BIGINT UNSIGNED NOT NULL,
    approval_level INT NOT NULL DEFAULT 1,
    status ENUM('PENDING', 'APPROVED', 'REJECTED') DEFAULT 'PENDING',
    approved_at TIMESTAMP NULL DEFAULT NULL,
    rejected_at TIMESTAMP NULL DEFAULT NULL,
    comments TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (adjustment_id) REFERENCES outlet_food_inventory_adjustments(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE CASCADE,
    
    UNIQUE KEY unique_adjustment_approver (adjustment_id, approver_id),
    INDEX idx_approval_level (approval_level),
    INDEX idx_status (status)
);

