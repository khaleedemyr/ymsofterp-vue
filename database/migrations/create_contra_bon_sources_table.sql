-- Create table untuk menyimpan multiple sources per contra bon
CREATE TABLE IF NOT EXISTS `food_contra_bon_sources` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `contra_bon_id` BIGINT(20) UNSIGNED NOT NULL,
  `source_type` VARCHAR(50) NOT NULL COMMENT 'purchase_order, retail_food, warehouse_retail_food',
  `source_id` BIGINT(20) UNSIGNED NOT NULL,
  `po_id` BIGINT(20) UNSIGNED NULL COMMENT 'Untuk purchase_order',
  `gr_id` BIGINT(20) UNSIGNED NULL COMMENT 'Untuk purchase_order',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_contra_bon_id` (`contra_bon_id`),
  INDEX `idx_source` (`source_type`, `source_id`),
  CONSTRAINT `fk_contra_bon_sources_contra_bon` 
    FOREIGN KEY (`contra_bon_id`) 
    REFERENCES `food_contra_bons` (`id`) 
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

