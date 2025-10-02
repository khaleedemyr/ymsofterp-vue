-- Coaching approvers table structure
CREATE TABLE `coaching_approvers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `coaching_id` bigint(20) unsigned NOT NULL,
  `approver_id` bigint(20) unsigned NOT NULL,
  `approval_level` int(11) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `coaching_approvers_coaching_id_foreign` (`coaching_id`),
  KEY `coaching_approvers_approver_id_foreign` (`approver_id`),
  CONSTRAINT `coaching_approvers_coaching_id_foreign` FOREIGN KEY (`coaching_id`) REFERENCES `coachings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `coaching_approvers_approver_id_foreign` FOREIGN KEY (`approver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
