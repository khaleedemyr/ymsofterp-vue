-- Query SQL untuk menambahkan kolom tanggal pada tabel cctv_access_requests
-- Jalankan query ini di database Anda

ALTER TABLE `cctv_access_requests` 
ADD COLUMN `date_from` date NULL COMMENT 'Tanggal mulai untuk playback' AFTER `area`,
ADD COLUMN `date_to` date NULL COMMENT 'Tanggal selesai untuk playback' AFTER `date_from`;

