-- Create Employee Movements Table
CREATE TABLE employee_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    
    -- Employee Details (from existing user data)
    employee_name VARCHAR(255) NOT NULL,
    employee_position VARCHAR(255),
    employee_division VARCHAR(255),
    employee_unit_property VARCHAR(255),
    employee_join_date DATE,
    
                   -- Employment & Renewal
               employment_type ENUM('extend_contract_without_adjustment', 'extend_contract_with_adjustment', 'promotion', 'demotion', 'mutation', 'termination') NULL,
               employment_effective_date DATE,
    
    -- Supporting Documents
    kpi_required BOOLEAN DEFAULT FALSE,
    kpi_date DATE,
    psikotest_required BOOLEAN DEFAULT FALSE,
    psikotest_score VARCHAR(50),
    psikotest_date DATE,
    training_attendance_required BOOLEAN DEFAULT FALSE,
    training_attendance_date DATE,
    
    -- Adjustment & Movement
    position_change BOOLEAN DEFAULT FALSE,
    position_from VARCHAR(255),
    position_to VARCHAR(255),
    
    level_change BOOLEAN DEFAULT FALSE,
    level_from VARCHAR(255),
    level_to VARCHAR(255),
    
    salary_change BOOLEAN DEFAULT FALSE,
    salary_from DECIMAL(15,2),
    salary_to DECIMAL(15,2),
    
    department_change BOOLEAN DEFAULT FALSE,
    department_from VARCHAR(255),
    department_to VARCHAR(255),
    
    division_change BOOLEAN DEFAULT FALSE,
    division_from VARCHAR(255),
    division_to VARCHAR(255),
    
    unit_property_change BOOLEAN DEFAULT FALSE,
    unit_property_from VARCHAR(255),
    unit_property_to VARCHAR(255),
    
    adjustment_effective_date DATE,
    
         -- Comments
     comments TEXT,
     
     -- Attachments
     kpi_attachment VARCHAR(255),
     psikotest_attachment VARCHAR(255),
     training_attachment VARCHAR(255),
     other_attachments TEXT,
     
     -- Approval
     hod_approval VARCHAR(255),
    hod_approval_date DATETIME,
    gm_approval VARCHAR(255),
    gm_approval_date DATETIME,
    gm_hr_approval VARCHAR(255),
    gm_hr_approval_date DATETIME,
    bod_approval VARCHAR(255),
    bod_approval_date DATETIME,
    
    -- Status
    status ENUM('draft', 'pending', 'approved', 'rejected') DEFAULT 'draft',
    
    -- Timestamps
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    
    -- Foreign Key
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
    
    -- Indexes
    INDEX idx_employee_id (employee_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
