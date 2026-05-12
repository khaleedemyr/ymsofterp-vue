-- =====================================================
-- Outlet Transfer Serial Mode
-- Menambahkan mode scan nomor seri di Outlet Transfer
-- =====================================================

-- 1. Tambah kolom transfer_mode di outlet_transfers
ALTER TABLE outlet_transfers
    ADD COLUMN transfer_mode ENUM('normal', 'serial', 'mixed') NOT NULL DEFAULT 'normal' AFTER notes;

-- 2. Tabel baru outlet_transfer_serial_items
CREATE TABLE outlet_transfer_serial_items (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    outlet_transfer_id BIGINT NOT NULL,
    serial_id BIGINT NOT NULL,
    serial_number VARCHAR(50) NOT NULL,
    item_id BIGINT NOT NULL,
    unit_id INT NULL,
    qty DECIMAL(12,4) NOT NULL DEFAULT 1,
    qty_small DECIMAL(12,4) NOT NULL DEFAULT 0,
    qty_medium DECIMAL(12,4) NOT NULL DEFAULT 0,
    qty_large DECIMAL(12,4) NOT NULL DEFAULT 0,
    cost_small DECIMAL(15,4) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_outlet_transfer_id (outlet_transfer_id),
    INDEX idx_serial_id (serial_id),
    INDEX idx_serial_number (serial_number),
    INDEX idx_item_id (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tambah kolom tracking transfer di inventory_item_serials
ALTER TABLE inventory_item_serials
    ADD COLUMN is_transferred TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN transferred_at TIMESTAMP NULL,
    ADD COLUMN transfer_id BIGINT NULL,
    ADD COLUMN transfer_from_outlet_id VARCHAR(20) NULL,
    ADD COLUMN transfer_to_outlet_id VARCHAR(20) NULL,
    ADD COLUMN transfer_to_warehouse_outlet_id BIGINT NULL,
    ADD INDEX idx_is_transferred (is_transferred),
    ADD INDEX idx_transfer_id (transfer_id);

-- 4. Extend movement_type ENUM di inventory_serial_movements
ALTER TABLE inventory_serial_movements
    MODIFY COLUMN movement_type ENUM('out', 'return', 'transfer_out', 'transfer_in') NOT NULL;

-- 5. Tambah kolom transfer reference di inventory_serial_movements
ALTER TABLE inventory_serial_movements
    ADD COLUMN outlet_transfer_id BIGINT NULL AFTER delivery_order_id,
    ADD INDEX idx_outlet_transfer_id (outlet_transfer_id);
