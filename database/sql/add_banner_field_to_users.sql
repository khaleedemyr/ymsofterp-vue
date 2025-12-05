-- Query untuk menambahkan field banner ke tabel users
-- Jalankan query ini di database untuk menambahkan field banner

ALTER TABLE users 
ADD COLUMN banner VARCHAR(255) NULL AFTER avatar;

-- Tambahkan index untuk performance (opsional)
ALTER TABLE users 
ADD INDEX idx_banner (banner);
