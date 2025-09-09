-- Add approver user_id fields to employee_movements table
ALTER TABLE employee_movements 
ADD COLUMN hod_approver_id BIGINT UNSIGNED NULL AFTER hod_approval,
ADD COLUMN gm_approver_id BIGINT UNSIGNED NULL AFTER gm_approval,
ADD COLUMN gm_hr_approver_id BIGINT UNSIGNED NULL AFTER gm_hr_approval,
ADD COLUMN bod_approver_id BIGINT UNSIGNED NULL AFTER bod_approval;

-- Add foreign key constraints
ALTER TABLE employee_movements 
ADD CONSTRAINT fk_employee_movements_hod_approver 
    FOREIGN KEY (hod_approver_id) REFERENCES users(id) ON DELETE SET NULL,
ADD CONSTRAINT fk_employee_movements_gm_approver 
    FOREIGN KEY (gm_approver_id) REFERENCES users(id) ON DELETE SET NULL,
ADD CONSTRAINT fk_employee_movements_gm_hr_approver 
    FOREIGN KEY (gm_hr_approver_id) REFERENCES users(id) ON DELETE SET NULL,
ADD CONSTRAINT fk_employee_movements_bod_approver 
    FOREIGN KEY (bod_approver_id) REFERENCES users(id) ON DELETE SET NULL;

-- Add indexes for better performance
ALTER TABLE employee_movements 
ADD INDEX idx_hod_approver_id (hod_approver_id),
ADD INDEX idx_gm_approver_id (gm_approver_id),
ADD INDEX idx_gm_hr_approver_id (gm_hr_approver_id),
ADD INDEX idx_bod_approver_id (bod_approver_id);
