-- Pelacakan kasbon setelah PR mode kasbon fully APPROVED (jalankan manual, tanpa migration Laravel)
-- MySQL / MariaDB

CREATE TABLE IF NOT EXISTS `pr_kasbons` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `purchase_requisition_id` bigint unsigned NOT NULL,
  `pr_number` varchar(64) DEFAULT NULL,
  `outlet_id` bigint unsigned DEFAULT NULL COMMENT 'id_outlet pemohon',
  `division_id` bigint unsigned DEFAULT NULL,
  `employee_user_id` bigint unsigned DEFAULT NULL COMMENT 'user pemohon (created_by PR)',
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `termin_total` tinyint unsigned NOT NULL DEFAULT 1 COMMENT 'rencana cicilan (1-3)',
  `installment_amount` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'nilai per termin (total/termin)',
  `paid_installments` tinyint unsigned NOT NULL DEFAULT 0 COMMENT 'sudah terpotong / tercatat berapa kali',
  `status` varchar(32) NOT NULL DEFAULT 'active' COMMENT 'active | completed',
  `approved_at` datetime DEFAULT NULL,
  `last_installment_at` datetime DEFAULT NULL COMMENT 'isi saat update paid_installments dari UI/API',
  `notes` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pr_kasbons_purchase_requisition_id_unique` (`purchase_requisition_id`),
  KEY `pr_kasbons_status_outlet_index` (`status`, `outlet_id`),
  KEY `pr_kasbons_employee_user_id_index` (`employee_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Opsional: foreign key (hapus baris ini jika tipe kolom id PR tidak cocok)
-- ALTER TABLE `pr_kasbons`
--   ADD CONSTRAINT `pr_kasbons_purchase_requisition_id_foreign`
--   FOREIGN KEY (`purchase_requisition_id`) REFERENCES `purchase_requisitions` (`id`) ON DELETE CASCADE;
