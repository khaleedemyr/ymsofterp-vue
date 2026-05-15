-- Outlet Food Return — mode nomor seri

ALTER TABLE outlet_food_returns
    ADD COLUMN return_mode ENUM('normal', 'serial', 'mixed') NOT NULL DEFAULT 'normal' AFTER notes;

CREATE TABLE IF NOT EXISTS outlet_food_return_serial_items (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    outlet_food_return_id BIGINT NOT NULL,
    serial_id BIGINT NOT NULL,
    serial_number VARCHAR(50) NOT NULL,
    item_id BIGINT NOT NULL,
    unit_id INT NULL,
    unit_name VARCHAR(50) NULL,
    return_qty DECIMAL(12,4) NOT NULL DEFAULT 1,
    qty_small DECIMAL(12,4) NOT NULL DEFAULT 0,
    outlet_food_good_receive_item_id BIGINT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ofr_id (outlet_food_return_id),
    INDEX idx_serial_id (serial_id),
    INDEX idx_serial_number (serial_number),
    INDEX idx_item_id (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE inventory_item_serials
    ADD COLUMN out_outlet_food_return_id BIGINT NULL AFTER out_outlet_rejection_id,
    ADD INDEX idx_out_ofrt_id (out_outlet_food_return_id);

-- Sesuaikan ENUM movement_type dengan nilai yang sudah ada di DB Anda
ALTER TABLE inventory_serial_movements
    MODIFY COLUMN movement_type ENUM(
        'out', 'return', 'transfer_out', 'transfer_in',
        'iwt_out', 'iwt_in', 'wt_out', 'wt_in',
        'rws_out', 'rws_return',
        'iuw_out', 'iuw_return',
        'whs_out', 'whs_in', 'whs_return',
        'orj_out', 'orj_in',
        'ofrt_out', 'ofrt_in'
    ) NOT NULL;

ALTER TABLE inventory_serial_movements
    ADD COLUMN outlet_food_return_id BIGINT NULL AFTER outlet_rejection_id,
    ADD INDEX idx_ofrt_id (outlet_food_return_id);
