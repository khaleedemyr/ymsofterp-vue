-- Tabel pengajuan WFH (jalankan jika belum migrate)

CREATE TABLE IF NOT EXISTS `wfh_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `number` varchar(255) NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `wfh_date` date NOT NULL,
  `reason` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'SUBMITTED',
  `outlet_id` int unsigned DEFAULT NULL,
  `shift_id` bigint unsigned DEFAULT NULL,
  `shift_name` varchar(255) DEFAULT NULL,
  `time_start` time DEFAULT NULL,
  `time_end` time DEFAULT NULL,
  `sn` varchar(255) DEFAULT NULL,
  `pin` varchar(255) DEFAULT NULL,
  `att_log_written_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wfh_requests_number_unique` (`number`),
  KEY `wfh_requests_user_id_wfh_date_index` (`user_id`,`wfh_date`),
  KEY `wfh_requests_status_index` (`status`),
  KEY `wfh_requests_created_by_index` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wfh_request_tasks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `wfh_request_id` bigint unsigned NOT NULL,
  `sort_order` smallint unsigned NOT NULL DEFAULT 1,
  `description` varchar(500) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `wfh_request_tasks_wfh_request_id_sort_order_index` (`wfh_request_id`,`sort_order`),
  CONSTRAINT `wfh_request_tasks_wfh_request_id_foreign` FOREIGN KEY (`wfh_request_id`) REFERENCES `wfh_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wfh_request_approval_flows` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `wfh_request_id` bigint unsigned NOT NULL,
  `approver_id` bigint unsigned NOT NULL,
  `approval_level` int unsigned NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'PENDING',
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `comments` text,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_wfh_flow_request_level` (`wfh_request_id`,`approval_level`),
  KEY `idx_wfh_flow_approver_status` (`approver_id`,`status`),
  CONSTRAINT `fk_wfh_flow_request` FOREIGN KEY (`wfh_request_id`) REFERENCES `wfh_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
