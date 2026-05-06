-- Create table for PO Food approval flow (PR Ops style)
CREATE TABLE IF NOT EXISTS `purchase_order_food_approval_flows` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `purchase_order_food_id` BIGINT UNSIGNED NOT NULL,
  `approver_id` VARCHAR(30) NOT NULL,
  `approval_level` INT UNSIGNED NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'PENDING',
  `approved_at` TIMESTAMP NULL DEFAULT NULL,
  `rejected_at` TIMESTAMP NULL DEFAULT NULL,
  `comments` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `pof_approval_po_level_idx` (`purchase_order_food_id`, `approval_level`),
  KEY `pof_approval_approver_status_idx` (`approver_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
