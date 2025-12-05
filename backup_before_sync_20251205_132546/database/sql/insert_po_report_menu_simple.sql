-- Insert menu Report PO GR ke tabel erp_menu
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
('Report PO GR', 'po_report', 6, '/po-report', 'fa-solid fa-chart-line', NOW(), NOW());

-- Ambil ID menu yang baru dibuat
SET @po_report_menu_id = LAST_INSERT_ID();

-- Insert permissions untuk menu Report PO GR
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@po_report_menu_id, 'view', 'po_report_view', NOW(), NOW()),
(@po_report_menu_id, 'create', 'po_report_create', NOW(), NOW()),
(@po_report_menu_id, 'update', 'po_report_update', NOW(), NOW()),
(@po_report_menu_id, 'delete', 'po_report_delete', NOW(), NOW());

-- Tampilkan hasil
SELECT 'Menu Report PO GR berhasil diinsert dengan ID:' as message, @po_report_menu_id as menu_id;
