-- NPD Service Calibration — eksekusi manual sekali di MySQL

CREATE TABLE IF NOT EXISTS `npd_service_calibrations` (
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
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `npd_service_calibrations_outlet_id_index` (`outlet_id`),
    KEY `npd_service_calibrations_scheduled_date_index` (`scheduled_date`),
    KEY `npd_service_calibrations_conductor_id_index` (`conductor_id`),
    KEY `npd_service_calibrations_status_index` (`status`),
    KEY `npd_service_calibrations_deleted_at_index` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `npd_service_calibration_products` (
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
    KEY `npd_service_calibration_products_calibration_id_index` (`calibration_id`),
    CONSTRAINT `npd_service_calibration_products_calibration_id_foreign`
        FOREIGN KEY (`calibration_id`) REFERENCES `npd_service_calibrations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `npd_service_calibration_participants` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `calibration_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `user_name` VARCHAR(255) NOT NULL,
    `jabatan_name` VARCHAR(255) NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `npd_service_calibration_participants_unique` (`calibration_id`, `user_id`),
    CONSTRAINT `npd_service_calibration_participants_calibration_id_foreign`
        FOREIGN KEY (`calibration_id`) REFERENCES `npd_service_calibrations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `npd_service_calibration_results` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `calibration_id` BIGINT UNSIGNED NOT NULL,
    `participant_id` BIGINT UNSIGNED NOT NULL,
    `calibration_product_id` BIGINT UNSIGNED NOT NULL,
    `menu_knowledge` ENUM('C', 'NC') NULL,
    `menu_explanation` ENUM('C', 'NC') NULL,
    `suggestive_selling` ENUM('C', 'NC') NULL,
    `production_presentation` ENUM('C', 'NC') NULL,
    `serving_standard` ENUM('C', 'NC') NULL,
    `handling_guest_question` ENUM('C', 'NC') NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `npd_service_calibration_results_unique` (`participant_id`, `calibration_product_id`),
    KEY `npd_service_calibration_results_calibration_id_index` (`calibration_id`),
    CONSTRAINT `npd_service_calibration_results_calibration_id_foreign`
        FOREIGN KEY (`calibration_id`) REFERENCES `npd_service_calibrations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `npd_service_calibration_results_participant_id_foreign`
        FOREIGN KEY (`participant_id`) REFERENCES `npd_service_calibration_participants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `npd_service_calibration_results_product_id_foreign`
        FOREIGN KEY (`calibration_product_id`) REFERENCES `npd_service_calibration_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
