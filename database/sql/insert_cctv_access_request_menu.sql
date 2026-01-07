-- Insert menu untuk CCTV Access Request
-- Jalankan query ini sekali saja
-- parent_id = 217 (Support Group)

-- Menu: CCTV Access Request
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) 
SELECT 
    'CCTV Access Request',
    'cctv_access_request',
    217,
    '/cctv-access-requests',
    'fa-solid fa-video',
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM erp_menu WHERE code = 'cctv_access_request'
);

-- Get menu_id untuk permissions
SET @cctv_menu_id = (SELECT id FROM erp_menu WHERE code = 'cctv_access_request' LIMIT 1);

-- Insert Permissions untuk CCTV Access Request
-- View (list, detail)
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) 
SELECT @cctv_menu_id, 'view', 'cctv_access_request_view', NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM erp_permission 
    WHERE menu_id = @cctv_menu_id AND action = 'view' AND code = 'cctv_access_request_view'
);

-- Create (create new request)
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) 
SELECT @cctv_menu_id, 'create', 'cctv_access_request_create', NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM erp_permission 
    WHERE menu_id = @cctv_menu_id AND action = 'create' AND code = 'cctv_access_request_create'
);

-- Update (edit pending request)
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) 
SELECT @cctv_menu_id, 'update', 'cctv_access_request_update', NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM erp_permission 
    WHERE menu_id = @cctv_menu_id AND action = 'update' AND code = 'cctv_access_request_update'
);

-- Delete (cancel pending request)
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) 
SELECT @cctv_menu_id, 'delete', 'cctv_access_request_delete', NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM erp_permission 
    WHERE menu_id = @cctv_menu_id AND action = 'delete' AND code = 'cctv_access_request_delete'
);

-- Approve (IT Manager only) - menggunakan action 'update'
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) 
SELECT @cctv_menu_id, 'update', 'cctv_access_request_approve', NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM erp_permission 
    WHERE menu_id = @cctv_menu_id AND action = 'update' AND code = 'cctv_access_request_approve'
);

-- Reject (IT Manager only) - menggunakan action 'update'
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) 
SELECT @cctv_menu_id, 'update', 'cctv_access_request_reject', NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM erp_permission 
    WHERE menu_id = @cctv_menu_id AND action = 'update' AND code = 'cctv_access_request_reject'
);

-- Revoke (IT Manager only) - menggunakan action 'update'
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) 
SELECT @cctv_menu_id, 'update', 'cctv_access_request_revoke', NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM erp_permission 
    WHERE menu_id = @cctv_menu_id AND action = 'update' AND code = 'cctv_access_request_revoke'
);

