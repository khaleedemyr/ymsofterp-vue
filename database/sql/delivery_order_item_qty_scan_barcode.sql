-- Pisah qty scan barcode vs nomor seri pada baris item DO yang sama
ALTER TABLE delivery_order_items
    ADD COLUMN qty_scan_barcode DECIMAL(12,4) NOT NULL DEFAULT 0 AFTER qty_scan;
