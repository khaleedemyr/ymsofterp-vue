-- =====================================================
-- FIX MISSING HRD APPROVALS
-- =====================================================
-- Script ini untuk memperbaiki data approval yang sudah disetujui
-- semua supervisor tapi tidak muncul di HRD karena hrd_status belum diupdate
-- 
-- Kondisi yang diperbaiki:
-- 1. approval_requests.status = 'approved' (sudah disetujui semua supervisor)
-- 2. approval_requests.hrd_status IS NULL atau tidak 'pending'
-- 3. approval_requests.hrd_approver_id IS NULL
-- 4. Tidak ada approval flow yang masih PENDING (untuk new flow)
-- =====================================================

-- Step 1: Update approval_requests yang sudah approved tapi belum ada hrd_status
-- Untuk new flow (ada absent_request_approval_flows)
UPDATE approval_requests ar
INNER JOIN absent_requests abr ON ar.id = abr.approval_request_id
LEFT JOIN (
    -- Cek apakah masih ada approval flow yang PENDING
    SELECT DISTINCT absent_request_id
    FROM absent_request_approval_flows
    WHERE status = 'PENDING'
) pending_flows ON abr.id = pending_flows.absent_request_id
SET 
    ar.hrd_status = 'pending',
    ar.hrd_approver_id = (
        SELECT id FROM users 
        WHERE division_id = 6 AND status = 'A' 
        LIMIT 1
    ),
    ar.updated_at = NOW()
WHERE 
    ar.status = 'approved'
    AND (ar.hrd_status IS NULL OR ar.hrd_status != 'pending')
    AND ar.hrd_approver_id IS NULL
    AND pending_flows.absent_request_id IS NULL -- Tidak ada pending flows (semua sudah approve)
    AND abr.status = 'supervisor_approved' -- Absent request sudah supervisor approved
;

-- Step 2: Update approval_requests untuk old flow (tidak ada absent_request_approval_flows)
UPDATE approval_requests ar
LEFT JOIN absent_requests abr ON ar.id = abr.approval_request_id
LEFT JOIN absent_request_approval_flows arf ON abr.id = arf.absent_request_id
SET 
    ar.hrd_status = 'pending',
    ar.hrd_approver_id = (
        SELECT id FROM users 
        WHERE division_id = 6 AND status = 'A' 
        LIMIT 1
    ),
    ar.updated_at = NOW()
WHERE 
    ar.status = 'approved'
    AND (ar.hrd_status IS NULL OR ar.hrd_status != 'pending')
    AND ar.hrd_approver_id IS NULL
    AND arf.id IS NULL -- Tidak ada approval flows (old flow)
;

-- Step 3: Update absent_requests yang statusnya supervisor_approved tapi approval_requests belum diupdate
UPDATE absent_requests abr
INNER JOIN approval_requests ar ON abr.approval_request_id = ar.id
SET 
    abr.status = 'supervisor_approved',
    abr.updated_at = NOW()
WHERE 
    abr.status != 'approved' -- Belum final approved
    AND abr.status != 'rejected' -- Belum rejected
    AND ar.status = 'approved'
    AND ar.hrd_status = 'pending'
;

-- Step 3b: Fix absent_requests yang status supervisor_approved tapi approved_by NULL
-- Ambil approved_by dari approval_flows yang terakhir approve
UPDATE absent_requests abr
INNER JOIN absent_request_approval_flows arf ON abr.id = arf.absent_request_id
INNER JOIN (
    -- Get the last approver (highest level yang sudah APPROVED)
    SELECT 
        absent_request_id,
        approved_by,
        approved_at,
        approval_level
    FROM absent_request_approval_flows
    WHERE status = 'APPROVED'
    AND (absent_request_id, approval_level) IN (
        SELECT absent_request_id, MAX(approval_level)
        FROM absent_request_approval_flows
        WHERE status = 'APPROVED'
        GROUP BY absent_request_id
    )
) last_approver ON abr.id = last_approver.absent_request_id
SET 
    abr.approved_by = last_approver.approved_by,
    abr.approved_at = last_approver.approved_at,
    abr.updated_at = NOW()
WHERE 
    abr.status = 'supervisor_approved'
    AND abr.approved_by IS NULL
;

-- Step 3c: Fix absent_requests untuk old flow (tidak ada approval_flows)
-- Ambil approved_by dari approval_requests.approver_id
UPDATE absent_requests abr
INNER JOIN approval_requests ar ON abr.approval_request_id = ar.id
LEFT JOIN absent_request_approval_flows arf ON abr.id = arf.absent_request_id
SET 
    abr.approved_by = ar.approver_id,
    abr.approved_at = ar.approved_at,
    abr.updated_at = NOW()
WHERE 
    abr.status = 'supervisor_approved'
    AND abr.approved_by IS NULL
    AND arf.id IS NULL -- Old flow (tidak ada approval_flows)
    AND ar.approver_id IS NOT NULL
;

-- Step 4: Buat notifikasi untuk HRD untuk approval yang baru diupdate
-- (Hanya untuk approval yang baru saja diupdate, bukan yang sudah lama)
INSERT INTO notifications (user_id, type, message, url, is_read, created_at, updated_at, approval_id)
SELECT 
    u.id as user_id,
    'leave_hrd_approval_request' as type,
    CONCAT('Permohonan izin/cuti dari ', req_user.nama_lengkap, ' untuk periode ', ar.date_from, ' - ', ar.date_to, ' telah disetujui oleh semua atasan dan membutuhkan persetujuan HRD Anda.') as message,
    CONCAT((SELECT value FROM settings WHERE key = 'app_url' LIMIT 1), '/home') as url,
    0 as is_read,
    NOW() as created_at,
    NOW() as updated_at,
    ar.id as approval_id
FROM approval_requests ar
INNER JOIN users req_user ON ar.user_id = req_user.id
CROSS JOIN users u
WHERE 
    ar.status = 'approved'
    AND ar.hrd_status = 'pending'
    AND u.division_id = 6
    AND u.status = 'A'
    AND ar.updated_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) -- Hanya yang baru diupdate (dalam 1 jam terakhir)
    AND NOT EXISTS (
        -- Jangan buat notifikasi jika sudah ada notifikasi yang sama
        SELECT 1 FROM notifications n
        WHERE n.user_id = u.id
        AND n.type = 'leave_hrd_approval_request'
        AND n.approval_id = ar.id
        AND n.is_read = 0
    )
;

-- =====================================================
-- VERIFICATION QUERIES
-- =====================================================
-- Jalankan query ini untuk melihat data yang sudah diperbaiki:

-- 1. Cek approval yang sudah diperbaiki
SELECT 
    ar.id,
    ar.user_id,
    u.nama_lengkap as user_name,
    ar.status,
    ar.hrd_status,
    ar.hrd_approver_id,
    hrd_user.nama_lengkap as hrd_approver_name,
    ar.date_from,
    ar.date_to,
    ar.updated_at
FROM approval_requests ar
INNER JOIN users u ON ar.user_id = u.id
LEFT JOIN users hrd_user ON ar.hrd_approver_id = hrd_user.id
WHERE 
    ar.status = 'approved'
    AND ar.hrd_status = 'pending'
ORDER BY ar.updated_at DESC
LIMIT 20;

-- 2. Cek jumlah approval yang perlu HRD approval
SELECT COUNT(*) as total_pending_hrd_approvals
FROM approval_requests
WHERE status = 'approved' AND hrd_status = 'pending';

-- 3. Cek approval yang masih bermasalah (approved tapi tidak ada hrd_status)
SELECT 
    ar.id,
    ar.user_id,
    u.nama_lengkap as user_name,
    ar.status,
    ar.hrd_status,
    ar.date_from,
    ar.date_to
FROM approval_requests ar
INNER JOIN users u ON ar.user_id = u.id
WHERE 
    ar.status = 'approved'
    AND (ar.hrd_status IS NULL OR ar.hrd_status != 'pending')
ORDER BY ar.updated_at DESC;

