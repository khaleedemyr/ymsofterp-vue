-- Query untuk cek dan debug personal_access_tokens

-- 1. Cek apakah table ada
SHOW TABLES LIKE 'personal_access_tokens';

-- 2. Cek struktur table
DESCRIBE personal_access_tokens;

-- 3. Cek token yang ada (10 terbaru)
SELECT 
    id,
    tokenable_type,
    tokenable_id,
    name,
    LEFT(token, 20) as token_preview,
    abilities,
    last_used_at,
    expires_at,
    created_at,
    updated_at
FROM personal_access_tokens 
WHERE tokenable_type = 'App\\Models\\MemberAppsMember'
ORDER BY created_at DESC 
LIMIT 10;

-- 4. Cek token yang belum pernah digunakan
SELECT 
    COUNT(*) as unused_tokens,
    MIN(created_at) as oldest_unused,
    MAX(created_at) as newest_unused
FROM personal_access_tokens 
WHERE tokenable_type = 'App\\Models\\MemberAppsMember'
AND last_used_at IS NULL;

-- 5. Cek token per member
SELECT 
    m.id as member_id,
    m.member_id,
    m.email,
    m.nama_lengkap,
    COUNT(pat.id) as token_count,
    MAX(pat.last_used_at) as last_login,
    MAX(pat.created_at) as latest_token_created
FROM member_apps_members m
LEFT JOIN personal_access_tokens pat ON pat.tokenable_id = m.id 
    AND pat.tokenable_type = 'App\\Models\\MemberAppsMember'
GROUP BY m.id
ORDER BY last_login DESC
LIMIT 10;

-- 6. Cek token yang expired (jika ada expiration)
SELECT 
    COUNT(*) as expired_tokens 
FROM personal_access_tokens 
WHERE expires_at IS NOT NULL 
AND expires_at < NOW();

-- 7. Hapus token lama yang tidak pernah digunakan (lebih dari 30 hari) - HATI-HATI!
-- UNCOMMENT JIKA PERLU:
-- DELETE FROM personal_access_tokens 
-- WHERE tokenable_type = 'App\\Models\\MemberAppsMember'
-- AND last_used_at IS NULL
-- AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

