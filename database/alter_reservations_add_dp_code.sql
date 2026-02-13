-- Kode DP 8 karakter (untuk dipakai di transaksi POS) + status sudah dipakai
-- Jalankan manual di MySQL/MariaDB.

-- 1. Kode unik 8 karakter (angka + huruf)
ALTER TABLE reservations
  ADD COLUMN dp_code VARCHAR(8) NULL UNIQUE COMMENT 'Kode DP untuk transaksi POS (8 char)' AFTER payment_type_id;

-- 2. Timestamp ketika kode DP dipakai di transaksi (supaya tidak dipakai 2x)
ALTER TABLE reservations
  ADD COLUMN dp_used_at TIMESTAMP NULL COMMENT 'Waktu kode DP dipakai di transaksi' AFTER dp_code;

-- Index untuk cek kode cepat
CREATE INDEX reservations_dp_code_index ON reservations(dp_code);

-- ----- Rollback -----
-- DROP INDEX reservations_dp_code_index ON reservations;
-- ALTER TABLE reservations DROP COLUMN dp_used_at, DROP COLUMN dp_code;
