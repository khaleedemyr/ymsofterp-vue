-- Create shared_documents table
CREATE TABLE `shared_documents` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` varchar(255) NOT NULL,
    `filename` varchar(255) NOT NULL,
    `file_path` varchar(255) NOT NULL,
    `file_type` varchar(10) NOT NULL COMMENT 'xlsx, docx, pptx, etc',
    `file_size` varchar(20) NOT NULL,
    `description` text NULL,
    `created_by` bigint(20) UNSIGNED NOT NULL,
    `document_key` varchar(255) NOT NULL UNIQUE COMMENT 'OnlyOffice document key',
    `is_public` tinyint(1) NOT NULL DEFAULT 0,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `shared_documents_created_by_foreign` (`created_by`),
    KEY `shared_documents_document_key_unique` (`document_key`),
    CONSTRAINT `shared_documents_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create document_permissions table
CREATE TABLE `document_permissions` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `document_id` bigint(20) UNSIGNED NOT NULL,
    `user_id` bigint(20) UNSIGNED NOT NULL,
    `permission` enum('view','edit','admin') NOT NULL DEFAULT 'view',
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `document_permissions_document_id_user_id_unique` (`document_id`, `user_id`),
    KEY `document_permissions_document_id_foreign` (`document_id`),
    KEY `document_permissions_user_id_foreign` (`user_id`),
    CONSTRAINT `document_permissions_document_id_foreign` FOREIGN KEY (`document_id`) REFERENCES `shared_documents` (`id`) ON DELETE CASCADE,
    CONSTRAINT `document_permissions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create document_versions table
CREATE TABLE `document_versions` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `document_id` bigint(20) UNSIGNED NOT NULL,
    `version_number` varchar(20) NOT NULL,
    `file_path` varchar(255) NOT NULL,
    `change_description` text NULL,
    `created_by` bigint(20) UNSIGNED NOT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `document_versions_document_id_foreign` (`document_id`),
    KEY `document_versions_created_by_foreign` (`created_by`),
    CONSTRAINT `document_versions_document_id_foreign` FOREIGN KEY (`document_id`) REFERENCES `shared_documents` (`id`) ON DELETE CASCADE,
    CONSTRAINT `document_versions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample data (optional)
-- INSERT INTO `shared_documents` (`title`, `filename`, `file_path`, `file_type`, `file_size`, `description`, `created_by`, `document_key`, `is_public`, `created_at`, `updated_at`) VALUES
-- ('Sample Excel Document', 'sample.xlsx', 'shared-documents/1234567890_sample.xlsx', 'xlsx', '1024', 'This is a sample Excel document', 1, '550e8400-e29b-41d4-a716-446655440000', 1, NOW(), NOW()),
-- ('Sample Word Document', 'sample.docx', 'shared-documents/1234567891_sample.docx', 'docx', '2048', 'This is a sample Word document', 1, '550e8400-e29b-41d4-a716-446655440001', 0, NOW(), NOW());

-- Show table structure
DESCRIBE `shared_documents`;
DESCRIBE `document_permissions`;
DESCRIBE `document_versions`; 