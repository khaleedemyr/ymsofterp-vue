-- Tabel untuk menyimpan history chat AI Q&A
CREATE TABLE IF NOT EXISTS `ai_chat_history` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NULL COMMENT 'ID user yang melakukan chat (nullable untuk guest)',
  `session_id` VARCHAR(255) NOT NULL COMMENT 'Session ID untuk mengelompokkan chat dalam satu session',
  `question` TEXT NOT NULL COMMENT 'Pertanyaan yang diajukan user',
  `answer` TEXT NOT NULL COMMENT 'Jawaban dari AI',
  `date_from` DATE NULL COMMENT 'Filter date_from yang digunakan',
  `date_to` DATE NULL COMMENT 'Filter date_to yang digunakan',
  `metadata` JSON NULL COMMENT 'Metadata tambahan (provider, tokens, cost, dll)',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_session_id` (`session_id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_session_created` (`session_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='History chat AI Q&A untuk dashboard';

-- Query untuk mengambil chat history berdasarkan session_id
-- SELECT * FROM ai_chat_history WHERE session_id = 'session_123' ORDER BY created_at ASC;

-- Query untuk mengambil chat history user tertentu
-- SELECT * FROM ai_chat_history WHERE user_id = 1 ORDER BY created_at DESC LIMIT 50;

-- Query untuk menghapus chat history lama (lebih dari 30 hari)
-- DELETE FROM ai_chat_history WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Query untuk menghitung total chat per session
-- SELECT session_id, COUNT(*) as total_chats, MIN(created_at) as first_chat, MAX(created_at) as last_chat 
-- FROM ai_chat_history 
-- GROUP BY session_id 
-- ORDER BY last_chat DESC;

-- ============================================
-- QUERY TRACKING PENGGUNA AI
-- ============================================

-- 1. Melihat siapa saja yang menggunakan AI (dengan info user)
SELECT 
    ach.user_id,
    u.name as user_name,
    u.email as user_email,
    u.username as user_username,
    COUNT(*) as total_questions,
    MIN(ach.created_at) as first_question,
    MAX(ach.created_at) as last_question,
    COUNT(DISTINCT ach.session_id) as total_sessions
FROM ai_chat_history ach
LEFT JOIN users u ON ach.user_id = u.id
WHERE ach.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY ach.user_id, u.name, u.email, u.username
ORDER BY total_questions DESC;

-- 2. Melihat detail pertanyaan per user
SELECT 
    ach.id,
    ach.user_id,
    u.name as user_name,
    u.email as user_email,
    ach.question,
    LEFT(ach.answer, 100) as answer_preview,
    ach.date_from,
    ach.date_to,
    ach.created_at,
    JSON_EXTRACT(ach.metadata, '$.ip_address') as ip_address,
    JSON_EXTRACT(ach.metadata, '$.provider') as ai_provider
FROM ai_chat_history ach
LEFT JOIN users u ON ach.user_id = u.id
WHERE ach.user_id IS NOT NULL
ORDER BY ach.created_at DESC
LIMIT 100;

-- 3. Statistik penggunaan AI per hari
SELECT 
    DATE(ach.created_at) as date,
    COUNT(*) as total_questions,
    COUNT(DISTINCT ach.user_id) as unique_users,
    COUNT(DISTINCT ach.session_id) as unique_sessions
FROM ai_chat_history ach
WHERE ach.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(ach.created_at)
ORDER BY date DESC;

-- 4. Top 10 user yang paling banyak menggunakan AI
SELECT 
    ach.user_id,
    u.name as user_name,
    u.email as user_email,
    COUNT(*) as total_questions,
    COUNT(DISTINCT ach.session_id) as total_sessions,
    AVG(LENGTH(ach.question)) as avg_question_length,
    AVG(LENGTH(ach.answer)) as avg_answer_length
FROM ai_chat_history ach
LEFT JOIN users u ON ach.user_id = u.id
WHERE ach.user_id IS NOT NULL
GROUP BY ach.user_id, u.name, u.email
ORDER BY total_questions DESC
LIMIT 10;

-- 5. Melihat chat history dengan info user lengkap
SELECT 
    ach.*,
    u.name as user_name,
    u.email as user_email,
    u.username as user_username,
    JSON_EXTRACT(ach.metadata, '$.user_info') as user_info_json,
    JSON_EXTRACT(ach.metadata, '$.ip_address') as ip_address,
    JSON_EXTRACT(ach.metadata, '$.provider') as ai_provider
FROM ai_chat_history ach
LEFT JOIN users u ON ach.user_id = u.id
ORDER BY ach.created_at DESC
LIMIT 50;

-- 6. Melihat user yang belum pernah menggunakan AI (untuk tracking)
SELECT 
    u.id,
    u.name,
    u.email,
    u.username,
    u.created_at as user_created_at
FROM users u
LEFT JOIN ai_chat_history ach ON u.id = ach.user_id
WHERE ach.user_id IS NULL
ORDER BY u.created_at DESC;

-- ============================================
-- QUERY DENGAN FILTER ROLE (SUPERADMIN vs USER)
-- ============================================

-- 7. Superadmin: Melihat semua chat history (tanpa filter user)
-- Ganti '5af56935b011a' dengan id_role superadmin jika berbeda
SELECT 
    ach.*,
    u.name as user_name,
    u.email as user_email,
    u.username as user_username,
    u.id_role,
    JSON_EXTRACT(ach.metadata, '$.ip_address') as ip_address
FROM ai_chat_history ach
LEFT JOIN users u ON ach.user_id = u.id
WHERE u.id_role = '5af56935b011a' OR TRUE  -- Superadmin bisa lihat semua
ORDER BY ach.created_at DESC
LIMIT 100;

-- 8. User biasa: Hanya melihat chat miliknya sendiri
-- Ganti USER_ID dengan ID user yang ingin dicek
SELECT 
    ach.*,
    u.name as user_name,
    u.email as user_email,
    u.username as user_username
FROM ai_chat_history ach
LEFT JOIN users u ON ach.user_id = u.id
WHERE ach.user_id = USER_ID  -- Ganti dengan ID user
ORDER BY ach.created_at DESC;

-- 9. Superadmin: Melihat semua chat dengan filter tanggal dan user
SELECT 
    ach.id,
    ach.user_id,
    u.name as user_name,
    u.email as user_email,
    ach.question,
    LEFT(ach.answer, 200) as answer_preview,
    ach.date_from,
    ach.date_to,
    ach.session_id,
    ach.created_at,
    JSON_EXTRACT(ach.metadata, '$.ip_address') as ip_address
FROM ai_chat_history ach
LEFT JOIN users u ON ach.user_id = u.id
WHERE ach.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)  -- Filter 7 hari terakhir
  AND (u.id_role = '5af56935b011a' OR TRUE)  -- Superadmin bisa lihat semua
ORDER BY ach.created_at DESC;

-- 10. User biasa: Statistik penggunaan AI miliknya sendiri
-- Ganti USER_ID dengan ID user
SELECT 
    DATE(ach.created_at) as date,
    COUNT(*) as total_questions,
    COUNT(DISTINCT ach.session_id) as total_sessions,
    AVG(LENGTH(ach.question)) as avg_question_length,
    AVG(LENGTH(ach.answer)) as avg_answer_length
FROM ai_chat_history ach
WHERE ach.user_id = USER_ID  -- Ganti dengan ID user
  AND ach.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(ach.created_at)
ORDER BY date DESC;

