-- Create Inspections table (header - session per outlet & departemen)
CREATE TABLE IF NOT EXISTS `inspections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `outlet_id` bigint(20) unsigned NOT NULL,
  `departemen` enum('Kitchen','Bar','Service') NOT NULL,
  `guidance_id` bigint(20) unsigned NOT NULL,
  `inspection_date` date NOT NULL,
  `status` enum('Draft','Completed') NOT NULL DEFAULT 'Draft',
  `total_findings` int NOT NULL DEFAULT 0,
  `total_points` int NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inspections_outlet_id_foreign` (`outlet_id`),
  KEY `inspections_guidance_id_foreign` (`guidance_id`),
  KEY `inspections_created_by_foreign` (`created_by`),
  CONSTRAINT `inspections_outlet_id_foreign` FOREIGN KEY (`outlet_id`) REFERENCES `tbl_data_outlet` (`id_outlet`) ON DELETE CASCADE,
  CONSTRAINT `inspections_guidance_id_foreign` FOREIGN KEY (`guidance_id`) REFERENCES `qa_guidances` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inspections_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create Inspection Details table (per temuan/finding)
CREATE TABLE IF NOT EXISTS `inspection_details` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `inspection_id` bigint(20) unsigned NOT NULL,
  `category_id` bigint(20) unsigned NOT NULL,
  `parameter_pemeriksaan` varchar(255) NOT NULL,
  `parameter_id` bigint(20) unsigned NOT NULL,
  `point` int NOT NULL DEFAULT 0,
  `photo_paths` json NULL,
  `notes` text NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inspection_details_inspection_id_foreign` (`inspection_id`),
  KEY `inspection_details_category_id_foreign` (`category_id`),
  KEY `inspection_details_parameter_id_foreign` (`parameter_id`),
  KEY `inspection_details_created_by_foreign` (`created_by`),
  CONSTRAINT `inspection_details_inspection_id_foreign` FOREIGN KEY (`inspection_id`) REFERENCES `inspections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inspection_details_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `qa_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inspection_details_parameter_id_foreign` FOREIGN KEY (`parameter_id`) REFERENCES `qa_parameters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inspection_details_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample data (assuming outlet with id_outlet = 1 and user with id = 1 exists)
INSERT INTO `inspections` (`outlet_id`, `departemen`, `guidance_id`, `inspection_date`, `status`, `total_findings`, `total_points`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Kitchen', 1, '2025-01-10', 'Draft', 0, 0, 1, NOW(), NOW());

INSERT INTO `inspection_details` (`inspection_id`, `category_id`, `parameter_pemeriksaan`, `parameter_id`, `point`, `photo_paths`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'Tidak ada penyimpangan dari kualitas standar (layu, busuk, pecah)', 1, 1, '["photos/inspection_1_photo1.jpg", "photos/inspection_1_photo2.jpg"]', 'Kondisi sayuran segar dan berkualitas baik', 1, NOW(), NOW()),
(1, 1, 'Tidak ada penyimpangan dari kualitas standar (layu, busuk, pecah)', 2, 3, '["photos/inspection_1_photo3.jpg"]', 'Daging segar tanpa bau busuk', 1, NOW(), NOW());
