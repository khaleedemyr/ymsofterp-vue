-- Warehouse Transfer (Pindah Gudang) Serial Mode
-- Transfer antar gudang food dengan scan nomor seri (mode qty tetap didukung)

-- 1. Mode transfer di header
ALTER TABLE warehouse_transfers
    ADD COLUMN transfer_mode ENUM('normal', 'serial', 'mixed') NOT NULL DEFAULT 'normal' AFTER notes;

-- 2. Detail serial per transfer
CREATE TABLE IF NOT EXISTS warehouse_transfer_serial_items (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    warehouse_transfer_id BIGINT NOT NULL,
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
    INDEX idx_wt_id (warehouse_transfer_id),
    INDEX idx_serial_id (serial_id),
    INDEX idx_serial_number (serial_number),
    INDEX idx_item_id (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Pergerakan serial (wt_out / wt_in) — sesuaikan ENUM yang sudah ada di DB Anda
ALTER TABLE inventory_serial_movements
    MODIFY COLUMN movement_type ENUM(
        'out', 'return', 'transfer_out', 'transfer_in',
        'iwt_out', 'iwt_in', 'wt_out', 'wt_in'
    ) NOT NULL;

-- 4. Referensi warehouse transfer di log serial
ALTER TABLE inventory_serial_movements
    ADD COLUMN warehouse_transfer_id BIGINT NULL AFTER internal_warehouse_transfer_id,
    ADD INDEX idx_wt_id (warehouse_transfer_id);
