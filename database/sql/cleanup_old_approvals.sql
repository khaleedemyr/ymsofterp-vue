-- Cleanup old approval izin cuti dan schedule/attendance correction yang < bulan ini
-- Query ini akan menghapus approval yang sudah lewat dari bulan ini
-- Urutan penghapusan: child records dulu, baru parent records

-- 1. Hapus absent_request_approval_flows yang terkait dengan approval yang created_at < tanggal 1 bulan ini
DELETE arf FROM `absent_request_approval_flows` arf
INNER JOIN `absent_requests` ar ON arf.absent_request_id = ar.id
INNER JOIN `approval_requests` apr ON ar.approval_request_id = apr.id
WHERE arf.status = 'PENDING'
  AND (
    (`apr`.`status` = 'pending')
    OR (`apr`.`status` = 'approved' AND `apr`.`hrd_status` = 'pending')
  )
  AND DATE(apr.created_at) < DATE_FORMAT(CURDATE(), '%Y-%m-01');

-- 2. Hapus absent_requests yang terkait dengan approval yang created_at < tanggal 1 bulan ini
--    dan status masih pending/supervisor_approved
DELETE ar FROM `absent_requests` ar
INNER JOIN `approval_requests` apr ON ar.approval_request_id = apr.id
WHERE ar.status IN ('pending', 'supervisor_approved')
  AND (
    (`apr`.`status` = 'pending')
    OR (`apr`.`status` = 'approved' AND `apr`.`hrd_status` = 'pending')
  )
  AND DATE(apr.created_at) < DATE_FORMAT(CURDATE(), '%Y-%m-01');

-- 3. Hapus approval izin cuti (approval_requests) yang created_at < tanggal 1 bulan ini
--    - Supervisor approvals: status = 'pending'
--    - HRD approvals: status = 'approved' AND hrd_status = 'pending'
DELETE FROM `approval_requests`
WHERE (
    (`status` = 'pending')
    OR (`status` = 'approved' AND `hrd_status` = 'pending')
  )
  AND DATE(`created_at`) < DATE_FORMAT(CURDATE(), '%Y-%m-01');

-- 4. Hapus schedule/attendance correction approvals yang tanggal < tanggal 1 bulan ini
--    Hanya hapus yang status masih pending
DELETE FROM `schedule_attendance_correction_approvals`
WHERE `status` = 'pending'
  AND `tanggal` < DATE_FORMAT(CURDATE(), '%Y-%m-01');

-- 5. Hapus notifications untuk leave_approval_request dan leave_hrd_approval_request yang created_at < tanggal 1 bulan ini
DELETE FROM `notifications`
WHERE `type` IN ('leave_approval_request', 'leave_hrd_approval_request')
  AND DATE(`created_at`) < DATE_FORMAT(CURDATE(), '%Y-%m-01');

-- 6. Hapus notifications untuk schedule_correction_approval yang created_at < tanggal 1 bulan ini
DELETE FROM `notifications`
WHERE `type` = 'schedule_correction_approval'
  AND DATE(`created_at`) < DATE_FORMAT(CURDATE(), '%Y-%m-01');

-- 7. Hapus employee_movement_approval_flows yang terkait dengan employee_movements yang created_at < tanggal 1 bulan ini
--    Hanya hapus yang status masih PENDING
DELETE emaf FROM `employee_movement_approval_flows` emaf
INNER JOIN `employee_movements` em ON emaf.employee_movement_id = em.id
WHERE emaf.status = 'PENDING'
  AND LOWER(em.status) = 'pending'
  AND DATE(em.created_at) < DATE_FORMAT(CURDATE(), '%Y-%m-01');

-- 8. Hapus employee_movements yang created_at < tanggal 1 bulan ini dan status masih pending
--    Hanya hapus yang status masih pending (belum di-approve/reject/execute)
DELETE FROM `employee_movements`
WHERE LOWER(`status`) = 'pending'
  AND DATE(`created_at`) < DATE_FORMAT(CURDATE(), '%Y-%m-01');

-- Catatan:
-- - Query ini akan menghapus approval yang dibuat sebelum bulan ini (berdasarkan created_at)
-- - Untuk supervisor approvals: menghapus yang status = 'pending'
-- - Untuk HRD approvals: menghapus yang status = 'approved' AND hrd_status = 'pending'
-- - Untuk schedule/attendance correction, menggunakan kolom `tanggal` (tanggal koreksi)
-- - Untuk izin cuti, menggunakan kolom `created_at` dari approval_requests (tanggal dibuat)
-- - Untuk employee movements, menggunakan kolom `created_at` dari employee_movements (tanggal dibuat)
-- - Notifications juga akan dihapus jika created_at < tanggal 1 bulan ini
-- - Absent_requests yang terkait juga akan dihapus jika status masih pending/supervisor_approved
-- - Employee movement approval flows yang terkait juga akan dihapus jika status masih PENDING
