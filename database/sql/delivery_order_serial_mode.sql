-- =====================================================
-- Delivery Order Serial Mode
-- Menambahkan mode scan nomor seri di Delivery Order
-- =====================================================

-- 1. Tambah kolom scan_mode di delivery_orders
ALTER TABLE delivery_orders
    ADD COLUMN scan_mode ENUM('barcode', 'serial') NOT NULL DEFAULT 'barcode' AFTER source_type;

-- 2. Tambah kolom tracking di inventory_item_serials
ALTER TABLE inventory_item_serials
    ADD COLUMN is_out TINYINT(1) NOT NULL DEFAULT 0 AFTER updated_at,
    ADD COLUMN out_at TIMESTAMP NULL AFTER is_out,
    ADD COLUMN out_delivery_order_id BIGINT NULL AFTER out_at,
    ADD COLUMN out_outlet_id VARCHAR(20) NULL AFTER out_delivery_order_id,
    ADD COLUMN out_warehouse_outlet_id BIGINT NULL AFTER out_outlet_id,
    ADD INDEX idx_is_out (is_out),
    ADD INDEX idx_out_delivery_order_id (out_delivery_order_id),
    ADD INDEX idx_out_outlet_id (out_outlet_id);

-- 3. Tabel baru inventory_serial_movements (log pergerakan serial)
CREATE TABLE inventory_serial_movements (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    serial_id BIGINT NOT NULL,
    serial_number VARCHAR(50) NOT NULL,
    movement_type ENUM('out', 'return') NOT NULL,
    delivery_order_id BIGINT NULL,
    delivery_order_number VARCHAR(50) NULL,
    outlet_id VARCHAR(20) NULL,
    warehouse_outlet_id BIGINT NULL,
    item_id BIGINT NOT NULL,
    qty DECIMAL(12,4) NOT NULL DEFAULT 1,
    unit_id INT NULL,
    moved_by BIGINT NULL,
    moved_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_serial_id (serial_id),
    INDEX idx_serial_number (serial_number),
    INDEX idx_delivery_order_id (delivery_order_id),
    INDEX idx_outlet_id (outlet_id),
    INDEX idx_movement_type (movement_type),
    INDEX idx_moved_at (moved_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tambah kolom serial_numbers (JSON) di delivery_order_items untuk menyimpan serial yang di-scan
ALTER TABLE delivery_order_items
    ADD COLUMN serial_numbers JSON NULL AFTER unit;
