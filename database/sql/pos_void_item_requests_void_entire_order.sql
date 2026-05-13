-- Void order penuh dari POS (modal daftar transaksi) memakai tabel yang sama dengan void per item.
-- Jalankan manual di DB ymsofterp setelah backup.

ALTER TABLE pos_void_item_requests
  ADD COLUMN void_entire_order TINYINT(1) NOT NULL DEFAULT 0
    COMMENT '1 = minta void seluruh order (bukan satu baris item)'
    AFTER order_item_id;

ALTER TABLE pos_void_item_requests
  MODIFY COLUMN order_item_id BIGINT UNSIGNED NULL
    COMMENT 'NULL bila void_entire_order = 1';
