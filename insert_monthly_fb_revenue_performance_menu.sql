-- Insert menu for Monthly FB Revenue Performance
-- First, get the parent_id using a variable
SET @parent_menu_id = (SELECT id FROM erp_menu WHERE code = 'outlet_sales_report_group');

INSERT INTO `erp_menu` (`id`, `name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
(NULL, 'Monthly FB Revenue Performance', 'monthly_fb_revenue_performance', @parent_menu_id, '/report-monthly-fb-revenue-performance', 'fa-solid fa-chart-bar', NOW(), NOW());

-- Get the menu ID for Monthly FB Revenue Performance
SET @monthly_fb_revenue_performance_menu_id = LAST_INSERT_ID();

-- Insert permissions for Monthly FB Revenue Performance
INSERT INTO `erp_permission` (`id`, `menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(NULL, @monthly_fb_revenue_performance_menu_id, 'view', 'monthly_fb_revenue_performance_view', NOW(), NOW()),
(NULL, @monthly_fb_revenue_performance_menu_id, 'create', 'monthly_fb_revenue_performance_create', NOW(), NOW()),
(NULL, @monthly_fb_revenue_performance_menu_id, 'update', 'monthly_fb_revenue_performance_update', NOW(), NOW()),
(NULL, @monthly_fb_revenue_performance_menu_id, 'delete', 'monthly_fb_revenue_performance_delete', NOW(), NOW());

-- Get permission IDs
SET @monthly_fb_revenue_performance_view_id = (SELECT id FROM erp_permission WHERE code = 'monthly_fb_revenue_performance_view');
SET @monthly_fb_revenue_performance_create_id = (SELECT id FROM erp_permission WHERE code = 'monthly_fb_revenue_performance_create');
SET @monthly_fb_revenue_performance_update_id = (SELECT id FROM erp_permission WHERE code = 'monthly_fb_revenue_performance_update');
SET @monthly_fb_revenue_performance_delete_id = (SELECT id FROM erp_permission WHERE code = 'monthly_fb_revenue_performance_delete');

-- Insert role permissions for admin role (assuming role ID 1 is admin)
INSERT INTO `erp_role_permission` (`id_role_permission`, `id_role`, `id_permission`, `status`) VALUES
(NULL, 1, @monthly_fb_revenue_performance_view_id, 'A'),
(NULL, 1, @monthly_fb_revenue_performance_create_id, 'A'),
(NULL, 1, @monthly_fb_revenue_performance_update_id, 'A'),
(NULL, 1, @monthly_fb_revenue_performance_delete_id, 'A'); 