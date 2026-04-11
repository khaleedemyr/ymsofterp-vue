-- Jalankan manual di MySQL (tanpa migrasi Laravel).
-- Menu & permission ERP: jalankan juga database/sql/insert_guest_comment_form_erp_menu.sql
-- lalu isi erp_role_permission untuk role yang boleh akses (permission id dari guest_comment_form_view).

CREATE TABLE IF NOT EXISTS `guest_comment_forms` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `image_path` varchar(500) NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'pending_verification',
  `ocr_raw_text` text,
  `ocr_payload` json DEFAULT NULL,
  `rating_service` varchar(20) DEFAULT NULL,
  `rating_food` varchar(20) DEFAULT NULL,
  `rating_beverage` varchar(20) DEFAULT NULL,
  `rating_cleanliness` varchar(20) DEFAULT NULL,
  `rating_staff` varchar(20) DEFAULT NULL,
  `rating_value` varchar(20) DEFAULT NULL,
  `comment_text` text,
  `guest_name` varchar(255) DEFAULT NULL,
  `guest_address` varchar(500) DEFAULT NULL,
  `guest_phone` varchar(100) DEFAULT NULL,
  `guest_dob` date DEFAULT NULL,
  `visit_date` varchar(100) DEFAULT NULL,
  `praised_staff_name` varchar(255) DEFAULT NULL,
  `praised_staff_outlet` varchar(255) DEFAULT NULL,
  `id_outlet` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `verified_by` bigint unsigned DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `guest_comment_forms_status_index` (`status`),
  KEY `guest_comment_forms_created_at_index` (`created_at`),
  KEY `guest_comment_forms_id_outlet_index` (`id_outlet`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
