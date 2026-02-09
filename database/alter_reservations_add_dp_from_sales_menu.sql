-- Tambah kolom DP, from_sales, menu ke tabel reservations
-- Jalankan di MySQL/MariaDB

ALTER TABLE reservations
  ADD COLUMN dp DECIMAL(15,2) NULL AFTER special_requests,
  ADD COLUMN from_sales TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = dari sales, 0 = bukan' AFTER dp,
  ADD COLUMN menu LONGTEXT NULL AFTER from_sales;

-- Tambah kolom sales_user_id (user sales, division_id=17)
ALTER TABLE reservations
  ADD COLUMN sales_user_id BIGINT UNSIGNED NULL AFTER from_sales,
  ADD CONSTRAINT fk_reservations_sales_user FOREIGN KEY (sales_user_id) REFERENCES users(id) ON DELETE SET NULL;

-- Rollback (hapus kolom):
-- ALTER TABLE reservations DROP COLUMN dp, DROP COLUMN from_sales, DROP COLUMN menu;
-- ALTER TABLE reservations DROP FOREIGN KEY fk_reservations_sales_user; ALTER TABLE reservations DROP COLUMN sales_user_id;
