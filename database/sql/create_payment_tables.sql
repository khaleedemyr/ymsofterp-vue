-- Create pr_payments table
CREATE TABLE `pr_payments` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_number` varchar(50) NOT NULL,
  `purchase_requisition_id` bigint(20) UNSIGNED NOT NULL,
  `purchase_order_id` bigint(20) UNSIGNED NULL,
  `supplier_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_method` enum('cash','transfer','check') NOT NULL DEFAULT 'transfer',
  `payment_date` date NOT NULL,
  `due_date` date NULL,
  `status` enum('pending','approved','paid','rejected') NOT NULL DEFAULT 'pending',
  `description` text NULL,
  `reference_number` varchar(100) NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `approved_by` bigint(20) UNSIGNED NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pr_payments_payment_number_unique` (`payment_number`),
  KEY `pr_payments_purchase_requisition_id_foreign` (`purchase_requisition_id`),
  KEY `pr_payments_purchase_order_id_foreign` (`purchase_order_id`),
  KEY `pr_payments_supplier_id_foreign` (`supplier_id`),
  KEY `pr_payments_created_by_foreign` (`created_by`),
  KEY `pr_payments_approved_by_foreign` (`approved_by`),
  CONSTRAINT `pr_payments_purchase_requisition_id_foreign` FOREIGN KEY (`purchase_requisition_id`) REFERENCES `purchase_requisitions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pr_payments_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_order_ops` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pr_payments_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pr_payments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pr_payments_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create pr_payment_approval_flows table
CREATE TABLE `pr_payment_approval_flows` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_id` bigint(20) UNSIGNED NOT NULL,
  `approval_level` int(11) NOT NULL,
  `approver_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `comments` text NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pr_payment_approval_flows_payment_id_foreign` (`payment_id`),
  KEY `pr_payment_approval_flows_approver_id_foreign` (`approver_id`),
  CONSTRAINT `pr_payment_approval_flows_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `pr_payments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pr_payment_approval_flows_approver_id_foreign` FOREIGN KEY (`approver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create pr_payment_attachments table
CREATE TABLE `pr_payment_attachments` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_id` bigint(20) UNSIGNED NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` bigint(20) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `uploaded_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pr_payment_attachments_payment_id_foreign` (`payment_id`),
  KEY `pr_payment_attachments_uploaded_by_foreign` (`uploaded_by`),
  CONSTRAINT `pr_payment_attachments_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `pr_payments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pr_payment_attachments_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
