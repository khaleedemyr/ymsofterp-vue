-- ========================================
-- INSERT MENU DAN PERMISSION UNTUK PO REPORT
-- ========================================

-- Step 1: Insert menu ke tabel erp_menu
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
('Report PO GR', 'po_report', 6, '/po-report', 'fa-solid fa-chart-line', NOW(), NOW());

-- Step 2: Ambil ID menu yang baru dibuat
SET @po_report_menu_id = LAST_INSERT_ID();

-- Step 3: Insert permissions ke tabel erp_permission
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@po_report_menu_id, 'view', 'po_report_view', NOW(), NOW()),
(@po_report_menu_id, 'create', 'po_report_create', NOW(), NOW()),
(@po_report_menu_id, 'update', 'po_report_update', NOW(), NOW()),
(@po_report_menu_id, 'delete', 'po_report_delete', NOW(), NOW());

-- Step 4: Verifikasi data yang diinsert
SELECT 
    'Menu dan Permission PO Report berhasil diinsert!' as status,
    @po_report_menu_id as menu_id,
    'po_report' as menu_code,
    'parent_id = 6 (Warehouse Management)' as parent_info;

-- Step 5: Tampilkan detail menu yang diinsert
SELECT 
    m.id,
    m.name,
    m.code,
    m.parent_id,
    m.route,
    m.icon,
    m.created_at
FROM erp_menu m 
WHERE m.id = @po_report_menu_id;

-- Step 6: Tampilkan detail permissions yang diinsert
SELECT 
    p.id,
    p.menu_id,
    p.action,
    p.code,
    p.created_at,
    m.name as menu_name
FROM erp_permission p
JOIN erp_menu m ON p.menu_id = m.id
WHERE p.menu_id = @po_report_menu_id
ORDER BY p.action;
