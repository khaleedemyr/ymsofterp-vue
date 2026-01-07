-- Query SQL untuk mengubah kolom playback_file_path menjadi JSON array untuk mendukung multiple files
-- Jalankan query ini di database Anda

-- Ubah kolom playback_file_path menjadi TEXT untuk menyimpan JSON array
ALTER TABLE `cctv_access_requests` 
MODIFY COLUMN `playback_file_path` TEXT NULL COMMENT 'Array JSON path file playback yang diupload oleh tim IT (maksimal 5 file)';

-- Ubah kolom playback_uploaded_at menjadi nullable karena bisa ada multiple uploads
ALTER TABLE `cctv_access_requests` 
MODIFY COLUMN `playback_uploaded_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu file playback pertama kali diupload';

