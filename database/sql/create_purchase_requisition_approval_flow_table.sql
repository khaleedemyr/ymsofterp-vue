-- Purchase Requisition Approval Flow Table
CREATE TABLE IF NOT EXISTS purchase_requisition_approval_flows (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    purchase_requisition_id BIGINT UNSIGNED NOT NULL,
    approver_id BIGINT UNSIGNED NOT NULL,
    approval_level INT NOT NULL DEFAULT 1,
    status ENUM('PENDING', 'APPROVED', 'REJECTED') DEFAULT 'PENDING',
    approved_at TIMESTAMP NULL DEFAULT NULL,
    rejected_at TIMESTAMP NULL DEFAULT NULL,
    comments TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (purchase_requisition_id) REFERENCES purchase_requisitions(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE CASCADE,
    
    UNIQUE KEY unique_pr_approver (purchase_requisition_id, approver_id),
    INDEX idx_approval_level (approval_level),
    INDEX idx_status (status)
);
