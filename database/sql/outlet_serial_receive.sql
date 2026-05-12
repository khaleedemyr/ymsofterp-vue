-- =====================================================
-- Outlet Serial Receive
-- User outlet scan nomor seri, auto-detect DO/outlet/warehouse,
-- langsung proses masuk inventory outlet.
-- =====================================================

-- 1. Tambah kolom tracking penerimaan di inventory_item_serials
ALTER TABLE inventory_item_serials
    ADD COLUMN is_received TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN received_at TIMESTAMP NULL,
    ADD COLUMN received_by BIGINT NULL,
    ADD COLUMN received_outlet_gr_id BIGINT NULL,
    ADD INDEX idx_is_received (is_received);

-- 2. Tabel baru untuk log penerimaan serial di outlet
CREATE TABLE outlet_serial_receives (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
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
    received_by BIGINT NULL,
    received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_serial_id (serial_id),
    INDEX idx_serial_number (serial_number),
    INDEX idx_delivery_order_id (delivery_order_id),
    INDEX idx_outlet_id (outlet_id),
    INDEX idx_received_at (received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
