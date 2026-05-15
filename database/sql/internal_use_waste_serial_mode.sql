-- Warehouse Internal Use & Waste — mode nomor seri

ALTER TABLE internal_use_waste_headers
    ADD COLUMN document_mode ENUM('normal', 'serial', 'mixed') NOT NULL DEFAULT 'normal' AFTER notes;

CREATE TABLE IF NOT EXISTS internal_use_waste_serial_items (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    internal_use_waste_header_id BIGINT NOT NULL,
    serial_id BIGINT NOT NULL,
    serial_number VARCHAR(50) NOT NULL,
    item_id BIGINT NOT NULL,
    unit_id INT NULL,
    unit_name VARCHAR(50) NULL,
    qty DECIMAL(12,4) NOT NULL DEFAULT 1,
    qty_small DECIMAL(12,4) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_iuw_header_id (internal_use_waste_header_id),
    INDEX idx_serial_id (serial_id),
    INDEX idx_serial_number (serial_number),
    INDEX idx_item_id (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE inventory_item_serials
    ADD COLUMN out_internal_use_waste_id BIGINT NULL AFTER out_retail_warehouse_sale_id,
    ADD INDEX idx_out_iuw_id (out_internal_use_waste_id);

ALTER TABLE inventory_serial_movements
    MODIFY COLUMN movement_type ENUM(
        'out', 'return', 'transfer_out', 'transfer_in',
        'iwt_out', 'iwt_in', 'wt_out', 'wt_in',
        'rws_out', 'rws_return',
        'iuw_out', 'iuw_return'
    ) NOT NULL;

ALTER TABLE inventory_serial_movements
    ADD COLUMN internal_use_waste_header_id BIGINT NULL AFTER retail_warehouse_sale_id,
    ADD INDEX idx_iuw_header_id (internal_use_waste_header_id);
