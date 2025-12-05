-- Assign permissions untuk Training Schedule ke role tertentu
-- Ganti role_id sesuai dengan role yang ingin diberi akses

-- Untuk Admin (role_id = 1)
INSERT INTO erp_role_permission (role_id, permission_id, created_at, updated_at)
SELECT 1, p.id, NOW(), NOW()
FROM erp_permission p
WHERE p.code LIKE 'lms-schedules%';

-- Untuk Manager/HR (role_id = 2) - jika ada
INSERT INTO erp_role_permission (role_id, permission_id, created_at, updated_at)
SELECT 2, p.id, NOW(), NOW()
FROM erp_permission p
WHERE p.code IN (
    'lms-schedules-view',
    'lms-schedules-create',
    'lms-schedules-update',
    'lms-schedules-qr-scanner',
    'lms-schedules-invitation',
    'lms-schedules-invitation-create',
    'lms-schedules-invitation-update',
    'lms-schedules-invitation-delete'
);

-- Untuk Trainer (role_id = 3) - jika ada
INSERT INTO erp_role_permission (role_id, permission_id, created_at, updated_at)
SELECT 3, p.id, NOW(), NOW()
FROM erp_permission p
WHERE p.code IN (
    'lms-schedules-view',
    'lms-schedules-qr-scanner',
    'lms-schedules-invitation'
);

-- Untuk Participant (role_id = 4) - jika ada
INSERT INTO erp_role_permission (role_id, permission_id, created_at, updated_at)
SELECT 4, p.id, NOW(), NOW()
FROM erp_permission p
WHERE p.code IN (
    'lms-schedules-view'
);
