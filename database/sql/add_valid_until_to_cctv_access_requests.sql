-- Query SQL untuk menambahkan kolom valid_until pada tabel cctv_access_requests
-- Jalankan query ini di database Anda

ALTER TABLE `cctv_access_requests` 
ADD COLUMN `valid_until` DATE NULL COMMENT 'Tanggal berlaku sampai kapan playback bisa diakses' AFTER `playback_uploaded_by`;
