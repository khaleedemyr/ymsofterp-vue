-- Fix tokenable_type yang terpotong
-- Pastikan kolom tokenable_type cukup panjang untuk menyimpan full namespace

-- 1. Cek struktur kolom saat ini
-- DESCRIBE personal_access_tokens;

-- 2. Jika kolom terlalu pendek, alter table (uncomment jika perlu)
-- ALTER TABLE personal_access_tokens MODIFY COLUMN tokenable_type VARCHAR(255);

-- 3. Update data yang sudah ada (fix yang terpotong)
UPDATE personal_access_tokens 
SET tokenable_type = 'App\\Models\\MemberAppsMember'
WHERE tokenable_type = 'App\\Models\\MemberApps'
  AND tokenable_id = 1;

-- 4. Verifikasi
SELECT 
    id,
    tokenable_type,
    tokenable_id,
    name,
    created_at
FROM personal_access_tokens
WHERE tokenable_id = 1
ORDER BY created_at DESC
LIMIT 10;

