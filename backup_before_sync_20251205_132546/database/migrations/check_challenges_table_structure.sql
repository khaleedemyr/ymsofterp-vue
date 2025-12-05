-- Query untuk memeriksa struktur tabel challenges
-- Jalankan query ini untuk melihat struktur tabel

-- 1. Cek struktur tabel challenges
SHOW COLUMNS FROM `challenges`;

-- 2. Cek tipe data kolom rules
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM 
    INFORMATION_SCHEMA.COLUMNS
WHERE 
    TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'challenges'
    AND COLUMN_NAME = 'rules';

-- Jika kolom rules masih TEXT atau VARCHAR, ubah ke JSON untuk performa lebih baik
-- (Opsional - hanya jika kolom rules belum JSON)
-- ALTER TABLE `challenges` 
-- MODIFY COLUMN `rules` JSON NULL;

-- Catatan:
-- - Jika kolom rules sudah JSON, tidak perlu perubahan database
-- - JSON type sudah support array dan number untuk reward_value
-- - reward_value akan disimpan sebagai bagian dari rules JSON:
--   {
--     "reward_type": "points",
--     "reward_value": 100  // untuk points
--   }
--   atau
--   {
--     "reward_type": "item",
--     "reward_value": [1, 2, 3]  // array of item IDs
--   }
--   atau
--   {
--     "reward_type": "voucher",
--     "reward_value": [1, 2, 3]  // array of voucher IDs
--   }

