-- Cleanup old/inactive device tokens
-- Deactivate device tokens yang tidak digunakan dalam 30 hari terakhir

-- Deactivate old web device tokens (tidak digunakan > 30 hari)
UPDATE web_device_tokens
SET is_active = 0
WHERE is_active = 1
AND (last_used_at IS NULL OR last_used_at < DATE_SUB(NOW(), INTERVAL 30 DAY))
AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Deactivate old employee device tokens (tidak digunakan > 30 hari)
UPDATE employee_device_tokens
SET is_active = 0
WHERE is_active = 1
AND (last_used_at IS NULL OR last_used_at < DATE_SUB(NOW(), INTERVAL 30 DAY))
AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Limit: Keep only 5 most recent active tokens per user (web)
-- Deactivate older tokens
UPDATE web_device_tokens w1
SET w1.is_active = 0
WHERE w1.is_active = 1
AND (
    SELECT COUNT(*) 
    FROM web_device_tokens w2 
    WHERE w2.user_id = w1.user_id 
    AND w2.is_active = 1
    AND (
        w2.last_used_at > w1.last_used_at 
        OR (w2.last_used_at = w1.last_used_at AND w2.id > w1.id)
        OR (w2.last_used_at IS NULL AND w1.last_used_at IS NULL AND w2.id > w1.id)
    )
) >= 5;

-- Limit: Keep only 5 most recent active tokens per user (employee)
UPDATE employee_device_tokens e1
SET e1.is_active = 0
WHERE e1.is_active = 1
AND (
    SELECT COUNT(*) 
    FROM employee_device_tokens e2 
    WHERE e2.user_id = e1.user_id 
    AND e2.is_active = 1
    AND (
        e2.last_used_at > e1.last_used_at 
        OR (e2.last_used_at = e1.last_used_at AND e2.id > e1.id)
        OR (e2.last_used_at IS NULL AND e1.last_used_at IS NULL AND e2.id > e1.id)
    )
) >= 5;

-- Cek hasil setelah cleanup
SELECT 
    user_id,
    COUNT(*) as total_web_tokens,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_web_tokens
FROM web_device_tokens
GROUP BY user_id
HAVING SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) > 5;

SELECT 
    user_id,
    COUNT(*) as total_employee_tokens,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_employee_tokens
FROM employee_device_tokens
GROUP BY user_id
HAVING SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) > 5;

