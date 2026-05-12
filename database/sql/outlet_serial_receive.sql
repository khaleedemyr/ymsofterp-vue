-- =====================================================
-- Outlet Serial Receive (CRUD flow)
-- Revisi: header + items (bukan single table)
-- =====================================================

-- 1. Tambah kolom tracking penerimaan di inventory_item_serials
ALTER TABLE inventory_item_serials
    ADD COLUMN is_received TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN received_at TIMESTAMP NULL,
    ADD COLUMN received_by BIGINT NULL,
    ADD COLUMN received_outlet_gr_id BIGINT NULL,
    ADD INDEX idx_is_received (is_received);

-- 2. Drop tabel lama jika ada
DROP TABLE IF EXISTS outlet_serial_receives;

-- 3. Tabel header (1 row per GR)
CREATE TABLE outlet_serial_receive_headers (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    number VARCHAR(50) NOT NULL,
    receive_date DATE NOT NULL,
    status ENUM('completed') NOT NULL DEFAULT 'completed',
    notes TEXT NULL,
    created_by BIGINT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_number (number),
    INDEX idx_receive_date (receive_date),
    INDEX idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tabel items (1 row per serial yang diterima)
CREATE TABLE outlet_serial_receive_items (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    header_id BIGINT NOT NULL,
    serial_id BIGINT NOT NULL,
    serial_number VARCHAR(50) NOT NULL,
    delivery_order_id BIGINT NOT NULL,
    delivery_order_number VARCHAR(50) NULL,
    item_id BIGINT NOT NULL,
    unit_id INT NULL,
    qty DECIMAL(12,4) NOT NULL DEFAULT 1,
    outlet_id VARCHAR(20) NOT NULL,
    warehouse_outlet_id BIGINT NOT NULL,
    cost_small DECIMAL(15,4) NULL,
    cost_source VARCHAR(30) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_header_id (header_id),
    INDEX idx_serial_id (serial_id),
    INDEX idx_serial_number (serial_number),
    INDEX idx_delivery_order_id (delivery_order_id),
    INDEX idx_item_id (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
