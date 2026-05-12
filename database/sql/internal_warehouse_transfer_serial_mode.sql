-- Internal Warehouse Transfer Serial Mode
-- Transfer antar gudang dalam 1 outlet dengan mode serial number

-- 1. Tambah kolom transfer_mode di internal_warehouse_transfers
ALTER TABLE internal_warehouse_transfers
    ADD COLUMN transfer_mode ENUM('normal', 'serial', 'mixed') NOT NULL DEFAULT 'normal' AFTER notes;

-- 2. Tabel baru internal_warehouse_transfer_serial_items
CREATE TABLE internal_warehouse_transfer_serial_items (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    internal_warehouse_transfer_id BIGINT NOT NULL,
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
    INDEX idx_iwt_id (internal_warehouse_transfer_id),
    INDEX idx_serial_id (serial_id),
    INDEX idx_serial_number (serial_number),
    INDEX idx_item_id (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Extend movement_type ENUM di inventory_serial_movements (tambah iwt_out, iwt_in)
ALTER TABLE inventory_serial_movements
    MODIFY COLUMN movement_type ENUM('out', 'return', 'transfer_out', 'transfer_in', 'iwt_out', 'iwt_in') NOT NULL;

-- 4. Tambah kolom internal_warehouse_transfer reference di inventory_serial_movements
ALTER TABLE inventory_serial_movements
    ADD COLUMN internal_warehouse_transfer_id BIGINT NULL AFTER outlet_transfer_id,
    ADD INDEX idx_iwt_id (internal_warehouse_transfer_id);
