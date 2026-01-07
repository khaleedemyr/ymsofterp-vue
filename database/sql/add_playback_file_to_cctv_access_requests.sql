-- Query SQL untuk menambahkan kolom playback file pada tabel cctv_access_requests
-- Jalankan query ini di database Anda

ALTER TABLE `cctv_access_requests` 
ADD COLUMN `playback_file_path` varchar(255) NULL COMMENT 'Path file playback yang diupload oleh tim IT' AFTER `incident_description`,
ADD COLUMN `playback_uploaded_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu file playback diupload' AFTER `playback_file_path`,
ADD COLUMN `playback_uploaded_by` bigint(20) unsigned NULL COMMENT 'User IT yang mengupload file playback' AFTER `playback_uploaded_at`,
ADD KEY `idx_playback_uploaded_by` (`playback_uploaded_by`),
ADD CONSTRAINT `fk_cctv_access_requests_playback_uploaded_by` FOREIGN KEY (`playback_uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

