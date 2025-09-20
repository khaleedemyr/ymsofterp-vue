-- Create ticketing system tables

-- 1. Ticket Categories (Kategori tiket)
CREATE TABLE IF NOT EXISTS `ticket_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text,
  `color` varchar(7) DEFAULT '#3B82F6', -- Hex color for UI
  `icon` varchar(50) DEFAULT 'fa-ticket', -- FontAwesome icon
  `status` enum('A','I') DEFAULT 'A' COMMENT 'A=Active, I=Inactive',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Ticket Priorities (Prioritas tiket)
CREATE TABLE IF NOT EXISTS `ticket_priorities` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `level` int(11) NOT NULL COMMENT '1=Low, 2=Medium, 3=High, 4=Critical',
  `max_days` int(11) DEFAULT 7 COMMENT 'Maximum days to resolve ticket',
  `color` varchar(7) DEFAULT '#6B7280',
  `description` text,
  `status` enum('A','I') DEFAULT 'A',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Ticket Statuses (Status tiket)
CREATE TABLE IF NOT EXISTS `ticket_statuses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(50) NOT NULL UNIQUE,
  `color` varchar(7) DEFAULT '#6B7280',
  `description` text,
  `is_final` tinyint(1) DEFAULT 0 COMMENT '1=Final status (closed/resolved)',
  `order` int(11) DEFAULT 0,
  `status` enum('A','I') DEFAULT 'A',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Main Tickets Table
CREATE TABLE IF NOT EXISTS `tickets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_number` varchar(50) NOT NULL UNIQUE,
  `title` varchar(255) NOT NULL,
  `description` text,
  `category_id` bigint(20) unsigned NOT NULL,
  `priority_id` bigint(20) unsigned NOT NULL,
  `status_id` bigint(20) unsigned NOT NULL,
  `divisi_id` bigint(20) unsigned NOT NULL COMMENT 'Divisi yang concern',
  `outlet_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `due_date` datetime NULL,
  `resolved_at` datetime NULL,
  `closed_at` datetime NULL,
  `source` enum('daily_report','manual','api') DEFAULT 'manual' COMMENT 'Source of ticket creation',
  `source_id` bigint(20) unsigned NULL COMMENT 'ID dari source (daily_report_id jika dari daily report)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tickets_category_id_foreign` (`category_id`),
  KEY `tickets_priority_id_foreign` (`priority_id`),
  KEY `tickets_status_id_foreign` (`status_id`),
  KEY `tickets_divisi_id_foreign` (`divisi_id`),
  KEY `tickets_outlet_id_foreign` (`outlet_id`),
  KEY `tickets_created_by_foreign` (`created_by`),
  KEY `tickets_source_source_id_index` (`source`, `source_id`),
  CONSTRAINT `tickets_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `ticket_categories` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `tickets_priority_id_foreign` FOREIGN KEY (`priority_id`) REFERENCES `ticket_priorities` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `tickets_status_id_foreign` FOREIGN KEY (`status_id`) REFERENCES `ticket_statuses` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `tickets_divisi_id_foreign` FOREIGN KEY (`divisi_id`) REFERENCES `tbl_data_divisi` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `tickets_outlet_id_foreign` FOREIGN KEY (`outlet_id`) REFERENCES `tbl_data_outlet` (`id_outlet`) ON DELETE RESTRICT,
  CONSTRAINT `tickets_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Ticket Comments (Komentar/update tiket)
CREATE TABLE IF NOT EXISTS `ticket_comments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `comment` text NOT NULL,
  `is_internal` tinyint(1) DEFAULT 0 COMMENT '1=Internal comment (tidak visible ke customer)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ticket_comments_ticket_id_foreign` (`ticket_id`),
  KEY `ticket_comments_user_id_foreign` (`user_id`),
  CONSTRAINT `ticket_comments_ticket_id_foreign` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ticket_comments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Ticket Attachments (File attachments)
CREATE TABLE IF NOT EXISTS `ticket_attachments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` bigint(20) unsigned NOT NULL,
  `comment_id` bigint(20) unsigned NULL COMMENT 'Jika attachment dari comment',
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` bigint(20) unsigned NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `uploaded_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ticket_attachments_ticket_id_foreign` (`ticket_id`),
  KEY `ticket_attachments_comment_id_foreign` (`comment_id`),
  KEY `ticket_attachments_uploaded_by_foreign` (`uploaded_by`),
  CONSTRAINT `ticket_attachments_ticket_id_foreign` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ticket_attachments_comment_id_foreign` FOREIGN KEY (`comment_id`) REFERENCES `ticket_comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ticket_attachments_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Ticket History (Audit trail)
CREATE TABLE IF NOT EXISTS `ticket_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `action` varchar(100) NOT NULL COMMENT 'created, updated, assigned, status_changed, etc',
  `field_name` varchar(100) NULL COMMENT 'Field yang diubah',
  `old_value` text NULL,
  `new_value` text NULL,
  `description` text NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ticket_history_ticket_id_foreign` (`ticket_id`),
  KEY `ticket_history_user_id_foreign` (`user_id`),
  CONSTRAINT `ticket_history_ticket_id_foreign` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ticket_history_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Ticket Assignments (Multiple assignees)
CREATE TABLE IF NOT EXISTS `ticket_assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `assigned_by` bigint(20) unsigned NOT NULL,
  `assigned_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `is_primary` tinyint(1) DEFAULT 0 COMMENT '1=Primary assignee',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ticket_assignments_ticket_user_unique` (`ticket_id`, `user_id`),
  KEY `ticket_assignments_user_id_foreign` (`user_id`),
  KEY `ticket_assignments_assigned_by_foreign` (`assigned_by`),
  CONSTRAINT `ticket_assignments_ticket_id_foreign` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ticket_assignments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ticket_assignments_assigned_by_foreign` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
