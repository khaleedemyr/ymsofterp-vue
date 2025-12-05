-- Script untuk memperbaiki field day_of_year di tabel db_justus_quotes
-- Jalankan script ini untuk mengisi day_of_year dengan benar (1-365)

-- Update day_of_year berdasarkan ID (asumsi ID 1 = day 1, ID 2 = day 2, dst)
UPDATE db_justus_quotes 
SET day_of_year = id 
WHERE day_of_year IS NULL OR day_of_year = 0;

-- Jika ada lebih dari 365 quotes, reset day_of_year untuk ID > 365
UPDATE db_justus_quotes 
SET day_of_year = ((id - 1) % 365) + 1 
WHERE id > 365;

-- Verifikasi hasil
SELECT id, quote, author, day_of_year, created_at 
FROM db_justus_quotes 
ORDER BY day_of_year 
LIMIT 10;
