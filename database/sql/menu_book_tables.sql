-- SQL Query untuk membuat table menu book
-- Jalankan query ini di database

-- Table untuk menyimpan menu books (judul buku menu)
CREATE TABLE IF NOT EXISTS `menu_books` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT 'Nama/judul buku menu',
  `description` text NULL COMMENT 'Deskripsi buku menu',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table untuk menyimpan halaman menu book
CREATE TABLE IF NOT EXISTS `menu_book_pages` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `menu_book_id` bigint(20) UNSIGNED NOT NULL COMMENT 'ID buku menu',
  `image` varchar(255) NOT NULL COMMENT 'Path ke image halaman',
  `page_order` int(11) NOT NULL DEFAULT 1 COMMENT 'Urutan halaman',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_menu_book_id` (`menu_book_id`),
  KEY `idx_page_order` (`page_order`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_pages_menu_book` FOREIGN KEY (`menu_book_id`) REFERENCES `menu_books` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table pivot untuk relasi page dengan items
CREATE TABLE IF NOT EXISTS `menu_book_page_items` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_id` bigint(20) UNSIGNED NOT NULL,

  `item_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_page_id` (`page_id`),
  KEY `idx_item_id` (`item_id`),
  UNIQUE KEY `unique_page_item` (`page_id`, `item_id`),
  CONSTRAINT `fk_page_items_page` FOREIGN KEY (`page_id`) REFERENCES `menu_book_pages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_page_items_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table pivot untuk relasi page dengan categories dan sub categories
CREATE TABLE IF NOT EXISTS `menu_book_page_categories` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_id` bigint(20) UNSIGNED NOT NULL,
  `category_id` bigint(20) UNSIGNED NOT NULL,
  `sub_category_id` bigint(20) UNSIGNED NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_page_id` (`page_id`),
  KEY `idx_category_id` (`category_id`),
  KEY `idx_sub_category_id` (`sub_category_id`),
  CONSTRAINT `fk_page_categories_page` FOREIGN KEY (`page_id`) REFERENCES `menu_book_pages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_page_categories_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_page_categories_sub_category` FOREIGN KEY (`sub_category_id`) REFERENCES `sub_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table pivot untuk relasi menu book dengan outlets
CREATE TABLE IF NOT EXISTS `menu_book_outlets` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `menu_book_id` bigint(20) UNSIGNED NOT NULL,
  `outlet_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_menu_book_id` (`menu_book_id`),
  KEY `idx_outlet_id` (`outlet_id`),
  UNIQUE KEY `unique_menu_book_outlet` (`menu_book_id`, `outlet_id`),
  CONSTRAINT `fk_menu_book_outlets_book` FOREIGN KEY (`menu_book_id`) REFERENCES `menu_books` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_menu_book_outlets_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `tbl_data_outlet` (`id_outlet`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

