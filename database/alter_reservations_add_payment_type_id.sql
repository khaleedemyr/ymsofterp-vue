-- Tambah kolom payment_type_id ke tabel reservations (untuk jenis pembayaran DP per outlet/region)
-- Jalankan manual di MySQL/MariaDB, tidak pakai php artisan migrate.

-- 1. Tambah kolom
ALTER TABLE reservations
  ADD COLUMN payment_type_id BIGINT UNSIGNED NULL COMMENT 'Jenis pembayaran DP (per outlet/region)' AFTER dp;

-- 2. Tambah foreign key (kalau hapus payment_type, payment_type_id di reservasi jadi NULL)
ALTER TABLE reservations
  ADD CONSTRAINT reservations_payment_type_id_foreign
  FOREIGN KEY (payment_type_id) REFERENCES payment_types(id) ON DELETE SET NULL;

-- ----- Rollback (jika perlu batalkan) -----
-- ALTER TABLE reservations DROP FOREIGN KEY reservations_payment_type_id_foreign;
-- ALTER TABLE reservations DROP COLUMN payment_type_id;
