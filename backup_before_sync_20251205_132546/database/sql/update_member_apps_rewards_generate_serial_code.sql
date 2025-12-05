-- Generate serial code for existing rewards (4 data)
-- Format: JTS-YYYYMMDD-HHMMSS-XXXX (where XXXX is random 4 characters)
UPDATE `member_apps_rewards` 
SET `serial_code` = CONCAT(
    'JTS-',
    DATE_FORMAT(created_at, '%Y%m%d'),
    '-',
    DATE_FORMAT(created_at, '%H%i%s'),
    '-',
    UPPER(SUBSTRING(MD5(CONCAT(id, created_at)), 1, 4))
)
WHERE `serial_code` IS NULL;

