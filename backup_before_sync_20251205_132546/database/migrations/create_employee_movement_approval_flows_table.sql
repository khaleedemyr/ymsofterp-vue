-- Employee Movement Approval Flow Table
CREATE TABLE IF NOT EXISTS employee_movement_approval_flows (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_movement_id BIGINT UNSIGNED NOT NULL,
    approver_id BIGINT UNSIGNED NOT NULL,
    approval_level INT NOT NULL DEFAULT 1,
    status ENUM('PENDING', 'APPROVED', 'REJECTED') DEFAULT 'PENDING',
    approved_at TIMESTAMP NULL DEFAULT NULL,
    rejected_at TIMESTAMP NULL DEFAULT NULL,
    comments TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (employee_movement_id) REFERENCES employee_movements(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE CASCADE,
    
    UNIQUE KEY unique_em_approver (employee_movement_id, approver_id),
    INDEX idx_approval_level (approval_level),
    INDEX idx_status (status)
);

