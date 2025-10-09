-- Create purchase_order_ops_attachments table
CREATE TABLE `purchase_order_ops_attachments` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `purchase_order_ops_id` bigint(20) UNSIGNED NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` bigint(20) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `uploaded_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_order_ops_attachments_purchase_order_ops_id_foreign` (`purchase_order_ops_id`),
  KEY `purchase_order_ops_attachments_uploaded_by_foreign` (`uploaded_by`),
  CONSTRAINT `purchase_order_ops_attachments_purchase_order_ops_id_foreign` FOREIGN KEY (`purchase_order_ops_id`) REFERENCES `purchase_order_ops` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_order_ops_attachments_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
