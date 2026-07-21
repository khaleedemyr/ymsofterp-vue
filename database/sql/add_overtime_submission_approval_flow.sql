-- Overtime submission approval flow
-- Existing rows di-set APPROVED agar tetap dipakai di attendance report

ALTER TABLE overtime_submissions
    ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'SUBMITTED' AFTER notes;

UPDATE overtime_submissions SET status = 'APPROVED' WHERE deleted_at IS NULL;

CREATE TABLE IF NOT EXISTS overtime_submission_approval_flows (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    overtime_submission_id BIGINT UNSIGNED NOT NULL,
    approver_id BIGINT UNSIGNED NOT NULL,
    approval_level INT UNSIGNED NOT NULL DEFAULT 1,
    status ENUM('PENDING', 'APPROVED', 'REJECTED') NOT NULL DEFAULT 'PENDING',
    approved_at DATETIME NULL,
    rejected_at DATETIME NULL,
    comments TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_ot_flow_submission (overtime_submission_id),
    INDEX idx_ot_flow_approver_status (approver_id, status),
    CONSTRAINT fk_ot_flow_submission FOREIGN KEY (overtime_submission_id)
        REFERENCES overtime_submissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
