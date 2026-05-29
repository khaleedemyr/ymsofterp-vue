-- Tambah mode 'mixed' pada delivery_orders.scan_mode (barcode + nomor seri dalam 1 DO)
ALTER TABLE delivery_orders
    MODIFY COLUMN scan_mode ENUM('barcode', 'serial', 'mixed') NOT NULL DEFAULT 'barcode';
