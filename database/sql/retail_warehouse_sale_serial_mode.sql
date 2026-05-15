-- Penjualan Warehouse Retail — mode nomor seri (mode barcode/qty tetap didukung)

-- 1. Mode penjualan di header
ALTER TABLE retail_warehouse_sales
    ADD COLUMN sale_mode ENUM('normal', 'serial', 'mixed') NOT NULL DEFAULT 'normal' AFTER notes;

-- 2. Detail serial per penjualan
CREATE TABLE IF NOT EXISTS retail_warehouse_sale_serial_items (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    retail_warehouse_sale_id BIGINT NOT NULL,
    serial_id BIGINT NOT NULL,
    serial_number VARCHAR(50) NOT NULL,
    item_id BIGINT NOT NULL,
    unit_id INT NULL,
    unit_name VARCHAR(50) NULL,
    qty DECIMAL(12,4) NOT NULL DEFAULT 1,
    qty_small DECIMAL(12,4) NOT NULL DEFAULT 0,
    price DECIMAL(15,4) NOT NULL DEFAULT 0,
    subtotal DECIMAL(15,4) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_rws_id (retail_warehouse_sale_id),
    INDEX idx_serial_id (serial_id),
    INDEX idx_serial_number (serial_number),
    INDEX idx_item_id (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Referensi penjualan retail pada serial (rollback saat hapus)
ALTER TABLE inventory_item_serials
    ADD COLUMN out_retail_warehouse_sale_id BIGINT NULL AFTER out_warehouse_outlet_id,
    ADD INDEX idx_out_rws_id (out_retail_warehouse_sale_id);

-- 4. Log pergerakan serial — sesuaikan ENUM movement_type di DB Anda
ALTER TABLE inventory_serial_movements
    MODIFY COLUMN movement_type ENUM(
        'out', 'return', 'transfer_out', 'transfer_in',
        'iwt_out', 'iwt_in', 'wt_out', 'wt_in',
        'rws_out', 'rws_return'
    ) NOT NULL;

ALTER TABLE inventory_serial_movements
    ADD COLUMN retail_warehouse_sale_id BIGINT NULL AFTER warehouse_transfer_id,
    ADD INDEX idx_rws_sale_id (retail_warehouse_sale_id);
