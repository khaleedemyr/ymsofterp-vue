-- Customer Voice Command Center - Core Tables
-- Jalankan manual di MySQL/MariaDB (tanpa migration Laravel).
-- Tujuan: menyiapkan data model case management lintas channel:
-- Google Review AI, Instagram Comment AI, dan Guest Comment OCR.

SET NAMES utf8mb4;

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `feedback_cases` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `source_type` varchar(32) NOT NULL COMMENT 'google_review|instagram_comment|guest_comment',
  `source_ref` varchar(191) NOT NULL COMMENT 'Unique reference dari sumber, contoh: google:reportId:itemId',
  `source_report_id` bigint unsigned DEFAULT NULL,
  `source_item_id` bigint unsigned DEFAULT NULL,
  `id_outlet` bigint unsigned DEFAULT NULL,
  `channel_account` varchar(100) DEFAULT NULL,
  `author_name` varchar(255) DEFAULT NULL,
  `customer_contact` varchar(120) DEFAULT NULL,
  `event_at` datetime NOT NULL,
  `severity` varchar(32) DEFAULT NULL COMMENT 'positive|neutral|mild_negative|negative|severe',
  `topics` json DEFAULT NULL,
  `summary_id` varchar(500) DEFAULT NULL,
  `raw_text` text,
  `sentiment_score` decimal(5,2) DEFAULT NULL,
  `risk_score` int unsigned NOT NULL DEFAULT 0,
  `status` varchar(24) NOT NULL DEFAULT 'new' COMMENT 'new|in_progress|resolved|ignored',
  `assigned_to` bigint unsigned DEFAULT NULL,
  `sla_minutes` int unsigned DEFAULT NULL,
  `due_at` datetime DEFAULT NULL,
  `first_response_at` datetime DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `last_alert_at` datetime DEFAULT NULL,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `feedback_cases_source_ref_unique` (`source_ref`),
  KEY `feedback_cases_source_type_index` (`source_type`),
  KEY `feedback_cases_id_outlet_index` (`id_outlet`),
  KEY `feedback_cases_status_index` (`status`),
  KEY `feedback_cases_severity_index` (`severity`),
  KEY `feedback_cases_assigned_to_index` (`assigned_to`),
  KEY `feedback_cases_due_at_index` (`due_at`),
  KEY `feedback_cases_event_at_index` (`event_at`),
  KEY `feedback_cases_risk_score_index` (`risk_score`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `feedback_case_activities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `case_id` bigint unsigned NOT NULL,
  `activity_type` varchar(40) NOT NULL COMMENT 'created|assigned|status_changed|note|alert_sent|external_followup',
  `actor_user_id` bigint unsigned DEFAULT NULL,
  `from_status` varchar(24) DEFAULT NULL,
  `to_status` varchar(24) DEFAULT NULL,
  `note` text,
  `payload` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `feedback_case_activities_case_id_index` (`case_id`),
  KEY `feedback_case_activities_activity_type_index` (`activity_type`),
  KEY `feedback_case_activities_actor_user_id_index` (`actor_user_id`),
  CONSTRAINT `feedback_case_activities_case_id_fk`
    FOREIGN KEY (`case_id`) REFERENCES `feedback_cases` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `feedback_alert_rules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `rule_code` varchar(64) NOT NULL,
  `name` varchar(120) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `source_type` varchar(32) DEFAULT NULL,
  `severity` varchar(32) DEFAULT NULL,
  `topic` varchar(64) DEFAULT NULL,
  `min_risk_score` int unsigned NOT NULL DEFAULT 0,
  `sla_minutes` int unsigned DEFAULT NULL,
  `cooldown_minutes` int unsigned NOT NULL DEFAULT 60,
  `assign_role_code` varchar(64) DEFAULT NULL,
  `notify_channels` json DEFAULT NULL COMMENT 'Contoh: ["in_app","email","telegram"]',
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `feedback_alert_rules_rule_code_unique` (`rule_code`),
  KEY `feedback_alert_rules_active_index` (`is_active`),
  KEY `feedback_alert_rules_source_index` (`source_type`),
  KEY `feedback_alert_rules_severity_index` (`severity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `feedback_alert_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `case_id` bigint unsigned DEFAULT NULL,
  `rule_id` bigint unsigned DEFAULT NULL,
  `channel` varchar(32) NOT NULL COMMENT 'in_app|email|telegram|whatsapp|slack',
  `target` varchar(255) DEFAULT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'queued' COMMENT 'queued|sent|failed|skipped',
  `message` text,
  `error_message` text,
  `sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `feedback_alert_logs_case_id_index` (`case_id`),
  KEY `feedback_alert_logs_rule_id_index` (`rule_id`),
  KEY `feedback_alert_logs_channel_index` (`channel`),
  KEY `feedback_alert_logs_status_index` (`status`),
  KEY `feedback_alert_logs_sent_at_index` (`sent_at`),
  CONSTRAINT `feedback_alert_logs_case_id_fk`
    FOREIGN KEY (`case_id`) REFERENCES `feedback_cases` (`id`) ON DELETE SET NULL,
  CONSTRAINT `feedback_alert_logs_rule_id_fk`
    FOREIGN KEY (`rule_id`) REFERENCES `feedback_alert_rules` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;

-- =========================================================
-- Optional seed rule (jalankan jika dibutuhkan)
-- =========================================================
-- INSERT INTO `feedback_alert_rules`
-- (`rule_code`, `name`, `is_active`, `source_type`, `severity`, `min_risk_score`, `sla_minutes`, `cooldown_minutes`, `assign_role_code`, `notify_channels`, `created_at`, `updated_at`)
-- VALUES
-- ('severe_default', 'Default severe escalation', 1, NULL, 'severe', 0, 30, 30, 'outlet_manager', JSON_ARRAY('in_app'), NOW(), NOW())
-- ON DUPLICATE KEY UPDATE
--   `name` = VALUES(`name`),
--   `is_active` = VALUES(`is_active`),
--   `severity` = VALUES(`severity`),
--   `sla_minutes` = VALUES(`sla_minutes`),
--   `cooldown_minutes` = VALUES(`cooldown_minutes`),
--   `assign_role_code` = VALUES(`assign_role_code`),
--   `notify_channels` = VALUES(`notify_channels`),
--   `updated_at` = NOW();
