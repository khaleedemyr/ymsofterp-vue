-- Update approval_requests table to add HRD approval fields
ALTER TABLE `approval_requests` 
ADD COLUMN `hrd_approver_id` bigint(20) unsigned NULL DEFAULT NULL COMMENT 'HRD yang harus approve' AFTER `approver_id`,
ADD COLUMN `hrd_status` enum('pending','approved','rejected') NULL DEFAULT NULL COMMENT 'Status approval HRD' AFTER `status`,
ADD COLUMN `hrd_approved_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu approval HRD' AFTER `approved_at`,
ADD COLUMN `hrd_rejected_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu rejection HRD' AFTER `rejected_at`,
ADD COLUMN `hrd_approval_notes` text NULL DEFAULT NULL COMMENT 'Catatan dari HRD' AFTER `approval_notes`,
ADD KEY `approval_requests_hrd_approver_id_foreign` (`hrd_approver_id`),
ADD CONSTRAINT `approval_requests_hrd_approver_id_foreign` FOREIGN KEY (`hrd_approver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
