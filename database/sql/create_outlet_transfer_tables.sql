-- Tabel untuk header outlet transfer
CREATE TABLE IF NOT EXISTS outlet_transfers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transfer_number VARCHAR(50) UNIQUE NOT NULL,
    transfer_date DATE NOT NULL,
    warehouse_outlet_from_id BIGINT UNSIGNED NOT NULL,
    warehouse_outlet_to_id BIGINT UNSIGNED NOT NULL,
    outlet_id BIGINT UNSIGNED NOT NULL,
    notes TEXT NULL,
    status ENUM('draft', 'approved', 'rejected') DEFAULT 'draft',
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_transfer_number (transfer_number),
    INDEX idx_transfer_date (transfer_date),
    INDEX idx_warehouse_outlet_from (warehouse_outlet_from_id),
    INDEX idx_warehouse_outlet_to (warehouse_outlet_to_id),
    INDEX idx_outlet_id (outlet_id),
    INDEX idx_status (status),
    INDEX idx_created_by (created_by)
);

-- Tabel untuk detail outlet transfer
CREATE TABLE IF NOT EXISTS outlet_transfer_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    outlet_transfer_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    quantity DECIMAL(15,2) NOT NULL,
    unit_id BIGINT UNSIGNED NOT NULL,
    qty_small DECIMAL(15,2) NOT NULL DEFAULT 0,
    qty_medium DECIMAL(15,2) NOT NULL DEFAULT 0,
    qty_large DECIMAL(15,2) NOT NULL DEFAULT 0,
    note TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_outlet_transfer_id (outlet_transfer_id),
    INDEX idx_item_id (item_id),
    INDEX idx_unit_id (unit_id)
);
