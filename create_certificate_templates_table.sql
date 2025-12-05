-- Create Certificate Templates Table
CREATE TABLE certificate_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL COMMENT 'Nama template',
    description TEXT NULL COMMENT 'Deskripsi template',
    background_image VARCHAR(255) NOT NULL COMMENT 'Path ke gambar background',
    text_positions JSON NOT NULL COMMENT 'Posisi text fields (JSON)',
    style_settings JSON NULL COMMENT 'Font, size, color settings',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NOT NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add index for better performance
CREATE INDEX idx_certificate_templates_status ON certificate_templates(status);
CREATE INDEX idx_certificate_templates_created_by ON certificate_templates(created_by);
CREATE INDEX idx_certificate_templates_deleted_at ON certificate_templates(deleted_at);
