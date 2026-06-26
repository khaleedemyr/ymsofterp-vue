-- F&B Product Calibration — eksekusi manual sekali di MySQL

CREATE TABLE IF NOT EXISTS `fb_product_calibrations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `outlet_id` INT UNSIGNED NOT NULL,
    `outlet_name` VARCHAR(255) NOT NULL,
    `scheduled_date` DATE NOT NULL,
    `conductor_id` BIGINT UNSIGNED NOT NULL,
    `conductor_name` VARCHAR(255) NOT NULL,
    `status` ENUM('scheduled', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'scheduled',
    `notes` TEXT NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `updated_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `fb_product_calibrations_outlet_id_index` (`outlet_id`),
    KEY `fb_product_calibrations_scheduled_date_index` (`scheduled_date`),
    KEY `fb_product_calibrations_conductor_id_index` (`conductor_id`),
    KEY `fb_product_calibrations_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fb_product_calibration_products` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `calibration_id` BIGINT UNSIGNED NOT NULL,
    `item_id` INT UNSIGNED NOT NULL,
    `item_name` VARCHAR(255) NOT NULL,
    `category_name` VARCHAR(255) NULL,
    `sub_category_name` VARCHAR(255) NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `fb_product_calibration_products_calibration_id_index` (`calibration_id`),
    CONSTRAINT `fb_product_calibration_products_calibration_id_foreign`
        FOREIGN KEY (`calibration_id`) REFERENCES `fb_product_calibrations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fb_product_calibration_participants` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `calibration_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `user_name` VARCHAR(255) NOT NULL,
    `jabatan_name` VARCHAR(255) NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `fb_product_calibration_participants_unique` (`calibration_id`, `user_id`),
    CONSTRAINT `fb_product_calibration_participants_calibration_id_foreign`
        FOREIGN KEY (`calibration_id`) REFERENCES `fb_product_calibrations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fb_product_calibration_results` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `calibration_id` BIGINT UNSIGNED NOT NULL,
    `participant_id` BIGINT UNSIGNED NOT NULL,
    `calibration_product_id` BIGINT UNSIGNED NOT NULL,
    `presentation` ENUM('C', 'NC') NULL,
    `taste_profile` ENUM('C', 'NC') NULL,
    `portion_size` ENUM('C', 'NC') NULL,
    `recipe_compliance` ENUM('C', 'NC') NULL,
    `cooking_method` ENUM('C', 'NC') NULL,
    `texture` ENUM('C', 'NC') NULL,
    `temperature` ENUM('C', 'NC') NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `fb_product_calibration_results_unique` (`participant_id`, `calibration_product_id`),
    KEY `fb_product_calibration_results_calibration_id_index` (`calibration_id`),
    CONSTRAINT `fb_product_calibration_results_calibration_id_foreign`
        FOREIGN KEY (`calibration_id`) REFERENCES `fb_product_calibrations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fb_product_calibration_results_participant_id_foreign`
        FOREIGN KEY (`participant_id`) REFERENCES `fb_product_calibration_participants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fb_product_calibration_results_product_id_foreign`
        FOREIGN KEY (`calibration_product_id`) REFERENCES `fb_product_calibration_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
