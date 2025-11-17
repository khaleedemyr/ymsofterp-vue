-- Update member_apps_brands table to use outlets from tbl_data_outlet
-- Add outlet_id column
ALTER TABLE `member_apps_brands` 
ADD COLUMN IF NOT EXISTS `outlet_id` int(11) NULL DEFAULT NULL AFTER `id`,
ADD KEY `idx_outlet_id` (`outlet_id`),
ADD CONSTRAINT `fk_brand_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `tbl_data_outlet` (`id_outlet`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Add new fields for PDF menu, PDF new dining experience, and logo
ALTER TABLE `member_apps_brands` 
ADD COLUMN IF NOT EXISTS `pdf_menu` varchar(255) NULL DEFAULT NULL AFTER `pdf_file`,
ADD COLUMN IF NOT EXISTS `pdf_new_dining_experience` varchar(255) NULL DEFAULT NULL AFTER `pdf_menu`,
ADD COLUMN IF NOT EXISTS `logo` varchar(255) NULL DEFAULT NULL AFTER `image`;

-- Create table for brand gallery images
CREATE TABLE IF NOT EXISTS `member_apps_brand_galleries` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `brand_id` bigint(20) UNSIGNED NOT NULL,
  `image` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_brand_id` (`brand_id`),
  KEY `idx_sort_order` (`sort_order`),
  CONSTRAINT `fk_brand_gallery_brand` FOREIGN KEY (`brand_id`) REFERENCES `member_apps_brands` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

