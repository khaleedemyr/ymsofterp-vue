-- Create inspection_cpas table for CPA (Corrective and Preventive Action)
CREATE TABLE IF NOT EXISTS inspection_cpas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inspection_id BIGINT UNSIGNED NOT NULL,
    inspection_detail_id BIGINT UNSIGNED NOT NULL,
    action_plan TEXT NOT NULL,
    responsible_person VARCHAR(255) NOT NULL,
    due_date DATE NOT NULL,
    notes TEXT NULL,
    documentation_paths JSON NULL,
    status VARCHAR(50) DEFAULT 'Open',
    completion_date DATE NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign key constraints
    FOREIGN KEY (inspection_id) REFERENCES inspections(id) ON DELETE CASCADE,
    FOREIGN KEY (inspection_detail_id) REFERENCES inspection_details(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    
    -- Indexes for better performance
    INDEX idx_inspection_cpas_inspection_id (inspection_id),
    INDEX idx_inspection_cpas_inspection_detail_id (inspection_detail_id),
    INDEX idx_inspection_cpas_status (status),
    INDEX idx_inspection_cpas_due_date (due_date),
    INDEX idx_inspection_cpas_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
