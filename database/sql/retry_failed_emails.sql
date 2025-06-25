-- Query untuk melihat email yang gagal dikirim
SELECT 
    id,
    to_email,
    subject,
    error_message,
    retry_count,
    created_at
FROM email_logs 
WHERE status = 'failed' 
ORDER BY created_at DESC;

-- Query untuk update status email yang berhasil dikirim
UPDATE email_logs 
SET 
    status = 'sent',
    sent_at = NOW(),
    updated_at = NOW()
WHERE id = [EMAIL_LOG_ID];

-- Query untuk increment retry count
UPDATE email_logs 
SET 
    retry_count = retry_count + 1,
    updated_at = NOW()
WHERE id = [EMAIL_LOG_ID];

-- Query untuk hapus email log yang sudah berhasil dikirim (opsional)
DELETE FROM email_logs 
WHERE status = 'sent' 
AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Query untuk reset email yang gagal (untuk retry manual)
UPDATE email_logs 
SET 
    status = 'pending',
    retry_count = 0,
    error_message = NULL,
    updated_at = NOW()
WHERE status = 'failed' 
AND retry_count < 3; 