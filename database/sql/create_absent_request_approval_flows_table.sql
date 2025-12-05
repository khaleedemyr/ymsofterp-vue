-- =====================================================
-- CREATE TABLE: absent_request_approval_flows
-- =====================================================
-- Table untuk multi-level approval (approver berjenjang)
-- Setelah semua approver approve, baru muncul di approval HRD
-- 
-- IMPORTANT: Table ini OPTIONAL - data lama tetap bisa digunakan
-- Jika table ini tidak ada, sistem akan menggunakan flow lama (single approver)
-- =====================================================

CREATE TABLE IF NOT EXISTS `absent_request_approval_flows` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `absent_request_id` BIGINT UNSIGNED NOT NULL,
  `approver_id` BIGINT UNSIGNED NOT NULL,
  `approval_level` INT NOT NULL COMMENT 'Level 1 = pertama, level terakhir = tertinggi',
  `status` ENUM('PENDING', 'APPROVED', 'REJECTED') NOT NULL DEFAULT 'PENDING',
  `notes` TEXT NULL,
  `approved_by` BIGINT UNSIGNED NULL,
  `approved_at` TIMESTAMP NULL,
  `rejected_at` TIMESTAMP NULL,
  `rejection_reason` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  INDEX `idx_absent_request_level` (`absent_request_id`, `approval_level`),
  INDEX `idx_approver_status` (`approver_id`, `status`),
  FOREIGN KEY (`absent_request_id`) REFERENCES `absent_requests`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`approver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
