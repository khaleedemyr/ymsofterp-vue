-- Customer Voice CAPA — approval flow per case & divisi (Service / Kitchen / Bar)
-- Eksekusi manual sekali di MySQL (polarisasi dengan purchase_order_ops_approval_flows)

CREATE TABLE IF NOT EXISTS `feedback_capa_approval_flows` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `feedback_case_id` BIGINT UNSIGNED NOT NULL,
    `division` VARCHAR(20) NOT NULL COMMENT 'service|kitchen|bar',
    `approver_id` INT UNSIGNED NOT NULL,
    `approval_level` INT UNSIGNED NOT NULL DEFAULT 1,
    `status` VARCHAR(20) NOT NULL DEFAULT 'PENDING' COMMENT 'PENDING|APPROVED|REJECTED',
    `comments` TEXT NULL,
    `approved_at` TIMESTAMP NULL DEFAULT NULL,
    `rejected_at` TIMESTAMP NULL DEFAULT NULL,
    `submitted_by_user_id` INT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `feedback_capa_approval_flows_case_division_index` (`feedback_case_id`, `division`),
    KEY `feedback_capa_approval_flows_approver_status_index` (`approver_id`, `status`),
    KEY `feedback_capa_approval_flows_case_status_index` (`feedback_case_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
