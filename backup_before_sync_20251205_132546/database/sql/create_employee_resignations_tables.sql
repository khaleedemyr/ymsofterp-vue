-- Create employee_resignations table
CREATE TABLE IF NOT EXISTS `employee_resignations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `resignation_number` varchar(50) NOT NULL,
  `outlet_id` int(11) NOT NULL,
  `employee_id` bigint(20) unsigned NOT NULL,
  `resignation_date` date NOT NULL,
  `resignation_type` enum('prosedural','non_prosedural') NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('draft','submitted','approved','rejected') NOT NULL DEFAULT 'draft',
  `created_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_resignations_resignation_number_unique` (`resignation_number`),
  KEY `employee_resignations_outlet_id_foreign` (`outlet_id`),
  KEY `employee_resignations_employee_id_foreign` (`employee_id`),
  KEY `employee_resignations_created_by_foreign` (`created_by`),
  KEY `employee_resignations_status_index` (`status`),
  CONSTRAINT `employee_resignations_outlet_id_foreign` FOREIGN KEY (`outlet_id`) REFERENCES `tbl_data_outlet` (`id_outlet`),
  CONSTRAINT `employee_resignations_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`),
  CONSTRAINT `employee_resignations_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create employee_resignation_approval_flows table
CREATE TABLE IF NOT EXISTS `employee_resignation_approval_flows` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_resignation_id` bigint(20) unsigned NOT NULL,
  `approver_id` bigint(20) unsigned NOT NULL,
  `approval_level` int(11) NOT NULL,
  `status` enum('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `employee_resignation_approval_flows_employee_resignation_id_foreign` (`employee_resignation_id`),
  KEY `employee_resignation_approval_flows_approver_id_foreign` (`approver_id`),
  KEY `employee_resignation_approval_flows_status_index` (`status`),
  KEY `employee_resignation_approval_flows_approval_level_index` (`approval_level`),
  CONSTRAINT `employee_resignation_approval_flows_employee_resignation_id_foreign` FOREIGN KEY (`employee_resignation_id`) REFERENCES `employee_resignations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_resignation_approval_flows_approver_id_foreign` FOREIGN KEY (`approver_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

