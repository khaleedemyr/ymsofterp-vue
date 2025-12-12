-- Check for duplicate device tokens (web)
-- Jika ada duplikasi, akan menyebabkan notification terkirim berulang

-- Cek duplicate web device tokens per user
SELECT 
    user_id,
    device_token,
    COUNT(*) as duplicate_count,
    GROUP_CONCAT(id) as token_ids,
    GROUP_CONCAT(browser) as browsers
FROM web_device_tokens
WHERE is_active = 1
GROUP BY user_id, device_token
HAVING COUNT(*) > 1;

-- Cek duplicate employee device tokens per user
SELECT 
    user_id,
    device_token,
    COUNT(*) as duplicate_count,
    GROUP_CONCAT(id) as token_ids,
    GROUP_CONCAT(device_type) as device_types
FROM employee_device_tokens
WHERE is_active = 1
GROUP BY user_id, device_token
HAVING COUNT(*) > 1;

-- Cek total device tokens per user (untuk debugging)
SELECT 
    user_id,
    COUNT(*) as total_web_tokens,
    COUNT(DISTINCT device_token) as unique_web_tokens
FROM web_device_tokens
WHERE is_active = 1
GROUP BY user_id
HAVING COUNT(*) != COUNT(DISTINCT device_token);

-- Cleanup duplicate web device tokens (keep the latest one)
-- HATI-HATI: Jalankan ini hanya jika yakin ada duplikasi
/*
DELETE w1 FROM web_device_tokens w1
INNER JOIN web_device_tokens w2 
WHERE w1.id < w2.id 
AND w1.user_id = w2.user_id 
AND w1.device_token = w2.device_token
AND w1.is_active = 1 
AND w2.is_active = 1;
*/

-- Cleanup duplicate employee device tokens (keep the latest one)
-- HATI-HATI: Jalankan ini hanya jika yakin ada duplikasi
/*
DELETE e1 FROM employee_device_tokens e1
INNER JOIN employee_device_tokens e2 
WHERE e1.id < e2.id 
AND e1.user_id = e2.user_id 
AND e1.device_token = e2.device_token
AND e1.is_active = 1 
AND e2.is_active = 1;
*/

