-- Outlet Rejection — mode nomor seri (mode qty tetap didukung)

ALTER TABLE outlet_rejections
    ADD COLUMN rejection_mode ENUM('normal', 'serial', 'mixed') NOT NULL DEFAULT 'normal' AFTER notes;

CREATE TABLE IF NOT EXISTS outlet_rejection_serial_items (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    outlet_rejection_id BIGINT NOT NULL,
    serial_id BIGINT NOT NULL,
    serial_number VARCHAR(50) NOT NULL,
    item_id BIGINT NOT NULL,
    unit_id INT NULL,
    unit_name VARCHAR(50) NULL,
    qty_rejected DECIMAL(12,4) NOT NULL DEFAULT 1,
    qty_small DECIMAL(12,4) NOT NULL DEFAULT 0,
    qty_received DECIMAL(12,4) NOT NULL DEFAULT 0,
    rejection_reason VARCHAR(255) NULL,
    item_condition ENUM('good', 'damaged', 'expired', 'other') NOT NULL DEFAULT 'good',
    condition_notes TEXT NULL,
    mac_cost DECIMAL(15,4) NOT NULL DEFAULT 0,
    delivery_order_id BIGINT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_or_id (outlet_rejection_id),
    INDEX idx_serial_id (serial_id),
    INDEX idx_serial_number (serial_number),
    INDEX idx_item_id (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE inventory_item_serials
    ADD COLUMN out_outlet_rejection_id BIGINT NULL AFTER out_warehouse_sale_id,
    ADD INDEX idx_out_orj_id (out_outlet_rejection_id);

-- Sesuaikan ENUM movement_type di DB Anda (gabungkan dengan nilai yang sudah ada)
ALTER TABLE inventory_serial_movements
    MODIFY COLUMN movement_type ENUM(
        'out', 'return', 'transfer_out', 'transfer_in',
        'iwt_out', 'iwt_in', 'wt_out', 'wt_in',
        'rws_out', 'rws_return',
        'iuw_out', 'iuw_return',
        'whs_out', 'whs_in', 'whs_return',
        'orj_out', 'orj_in'
    ) NOT NULL;

ALTER TABLE inventory_serial_movements
    ADD COLUMN outlet_rejection_id BIGINT NULL AFTER warehouse_sale_id,
    ADD INDEX idx_orj_id (outlet_rejection_id);
