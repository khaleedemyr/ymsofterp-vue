-- Stock cut minus qty tracking (tanpa migration Laravel)
-- Jalankan manual di database production/staging

CREATE TABLE IF NOT EXISTS stock_cut_variances (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stock_cut_log_id BIGINT UNSIGNED NOT NULL,
    outlet_id INT UNSIGNED NOT NULL,
    warehouse_outlet_id BIGINT UNSIGNED NOT NULL,
    inventory_item_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    tanggal DATE NOT NULL,
    type_filter VARCHAR(32) NULL COMMENT 'food, beverages, atau NULL = semua',
    qty_needed DECIMAL(18, 4) NOT NULL DEFAULT 0,
    qty_available_before DECIMAL(18, 4) NOT NULL DEFAULT 0,
    qty_shortfall DECIMAL(18, 4) NOT NULL DEFAULT 0 COMMENT 'Qty minus (shortfall)',
    qty_after DECIMAL(18, 4) NOT NULL DEFAULT 0 COMMENT 'Saldo qty_small setelah cut',
    cost_per_small DECIMAL(18, 4) NOT NULL DEFAULT 0,
    value_booked DECIMAL(18, 2) NOT NULL DEFAULT 0 COMMENT 'Full theoretical cost (qty_needed x cost)',
    shortfall_value_info DECIMAL(18, 2) NOT NULL DEFAULT 0 COMMENT 'Info qty_shortfall x cost, bukan expense tambahan',
    executed_by BIGINT UNSIGNED NULL,
    status ENUM('open', 'closed') NOT NULL DEFAULT 'open',
    closed_at DATETIME NULL,
    closed_via VARCHAR(64) NULL COMMENT 'grn, transfer_in, adjustment, weekly_opname, rollback, other',
    closed_reference_type VARCHAR(64) NULL,
    closed_reference_id BIGINT UNSIGNED NULL,
    closed_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_scv_outlet_status (outlet_id, status),
    INDEX idx_scv_stock_cut_log (stock_cut_log_id),
    INDEX idx_scv_inventory_wh_status (inventory_item_id, outlet_id, warehouse_outlet_id, status),
    INDEX idx_scv_tanggal (tanggal),
    INDEX idx_scv_executed_by (executed_by),
    CONSTRAINT fk_scv_stock_cut_log FOREIGN KEY (stock_cut_log_id) REFERENCES stock_cut_logs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Opsional: flag ringkas di log stock cut (abaikan error jika kolom sudah ada)
ALTER TABLE stock_cut_logs
    ADD COLUMN has_variance TINYINT(1) NOT NULL DEFAULT 0 AFTER total_modifiers_cut;

ALTER TABLE stock_cut_logs
    ADD COLUMN total_variance_qty DECIMAL(18, 4) NOT NULL DEFAULT 0 AFTER has_variance;

ALTER TABLE stock_cut_logs
    ADD COLUMN total_variance_items INT UNSIGNED NOT NULL DEFAULT 0 AFTER total_variance_qty;
