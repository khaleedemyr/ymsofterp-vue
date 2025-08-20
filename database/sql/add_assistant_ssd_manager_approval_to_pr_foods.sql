-- Add assistant SSD manager approval fields to pr_foods table
ALTER TABLE pr_foods ADD COLUMN assistant_ssd_manager_approved_at TIMESTAMP NULL AFTER ssd_manager_note;
ALTER TABLE pr_foods ADD COLUMN assistant_ssd_manager_approved_by BIGINT UNSIGNED NULL AFTER assistant_ssd_manager_approved_at;
ALTER TABLE pr_foods ADD COLUMN assistant_ssd_manager_note TEXT NULL AFTER assistant_ssd_manager_approved_by;

-- Add foreign key constraint
ALTER TABLE pr_foods ADD CONSTRAINT fk_pr_foods_assistant_ssd_manager_approved_by 
FOREIGN KEY (assistant_ssd_manager_approved_by) REFERENCES users(id) ON DELETE SET NULL;
