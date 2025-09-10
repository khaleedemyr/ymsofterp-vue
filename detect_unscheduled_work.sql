-- Query untuk mendeteksi karyawan yang kerja tanpa ada jadwal shift
-- Query ini bisa dijalankan harian untuk mendeteksi extra off yang harus diberikan

-- 1. Deteksi karyawan yang kerja di hari libur mereka (tidak ada shift)
SELECT 
    u.id as user_id,
    u.nama_lengkap,
    u.nik,
    a.scan_date as work_date,
    DATE(a.scan_date) as work_date_only,
    'Hari libur tanpa shift' as reason,
    CONCAT('Karyawan kerja di hari libur (', DATE(a.scan_date), ') tanpa ada jadwal shift') as description
FROM `att_log` a
INNER JOIN `user_pins` up ON a.pin = up.pin
INNER JOIN `users` u ON up.user_id = u.id
LEFT JOIN `user_shifts` us ON u.id = us.user_id 
    AND DATE(a.scan_date) = DATE(us.tanggal)
WHERE 
    -- Cek apakah ada attendance (check-in)
    a.inoutmode = 1
    -- Cek apakah tidak ada shift di tanggal tersebut
    AND us.id IS NULL
    -- Cek apakah user masih aktif
    AND u.status = 'A'
    -- Cek apakah belum ada transaksi extra off untuk tanggal ini
    AND NOT EXISTS (
        SELECT 1 FROM `extra_off_transactions` eot 
        WHERE eot.user_id = u.id 
        AND eot.source_date = DATE(a.scan_date)
        AND eot.source_type = 'unscheduled_work'
        AND eot.transaction_type = 'earned'
    )
    -- Cek apakah bukan hari libur nasional (karena itu sudah ditangani di holiday_attendance_compensations)
    AND NOT EXISTS (
        SELECT 1 FROM `tbl_kalender_perusahaan` kp 
        WHERE kp.tgl_libur = DATE(a.scan_date)
    )
    -- Cek apakah tanggal dalam range yang wajar (misal 30 hari terakhir)
    AND DATE(a.scan_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    -- Cek apakah tanggal tidak di masa depan
    AND DATE(a.scan_date) <= CURDATE()
GROUP BY u.id, DATE(a.scan_date)
ORDER BY DATE(a.scan_date) DESC, u.id;

-- 2. Query untuk insert transaksi extra off yang dideteksi
-- (Ini contoh query untuk insert, sesuaikan dengan hasil query di atas)
/*
INSERT INTO `extra_off_transactions` (
    `user_id`, 
    `transaction_type`, 
    `amount`, 
    `source_type`, 
    `source_date`, 
    `description`, 
    `status`,
    `created_at`, 
    `updated_at`
)
SELECT 
    u.id as user_id,
    'earned' as transaction_type,
    1 as amount,
    'unscheduled_work' as source_type,
    DATE(a.scan_date) as source_date,
    CONCAT('Extra off dari kerja tanpa shift di tanggal ', DATE(a.scan_date)) as description,
    'approved' as status,
    NOW() as created_at,
    NOW() as updated_at
FROM `att_log` a
INNER JOIN `user_pins` up ON a.pin = up.pin
INNER JOIN `users` u ON up.user_id = u.id
LEFT JOIN `user_shifts` us ON u.id = us.user_id 
    AND DATE(a.scan_date) = DATE(us.tanggal)
WHERE 
    a.inoutmode = 1
    AND us.id IS NULL
    AND u.status = 'A'
    AND NOT EXISTS (
        SELECT 1 FROM `extra_off_transactions` eot 
        WHERE eot.user_id = u.id 
        AND eot.source_date = DATE(a.scan_date)
        AND eot.source_type = 'unscheduled_work'
        AND eot.transaction_type = 'earned'
    )
    AND NOT EXISTS (
        SELECT 1 FROM `tbl_kalender_perusahaan` kp 
        WHERE kp.tgl_libur = DATE(a.scan_date)
    )
    AND DATE(a.scan_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    AND DATE(a.scan_date) <= CURDATE()
GROUP BY u.id, DATE(a.scan_date);
*/

-- 3. Query untuk update balance setelah insert transaksi
-- (Jalankan setelah insert transaksi)
/*
UPDATE `extra_off_balance` eob
SET 
    balance = (
        SELECT COALESCE(SUM(amount), 0) 
        FROM `extra_off_transactions` eot 
        WHERE eot.user_id = eob.user_id 
        AND eot.status = 'approved'
    ),
    updated_at = NOW()
WHERE eob.user_id IN (
    SELECT DISTINCT user_id 
    FROM `extra_off_transactions` 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
);
*/
