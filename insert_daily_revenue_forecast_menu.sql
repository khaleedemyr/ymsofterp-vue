-- Insert menu for Daily Revenue Forecast
-- First, get the parent_id using a variable
SET @parent_menu_id = (SELECT id FROM erp_menu WHERE code = 'outlet_sales_report_group');

INSERT INTO `erp_menu` (`id`, `name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
(NULL, 'Daily Revenue Forecast', 'daily_revenue_forecast', @parent_menu_id, '/report-daily-revenue-forecast', 'fa-solid fa-chart-line', NOW(), NOW());

-- Get the menu ID for Daily Revenue Forecast
SET @daily_revenue_forecast_menu_id = LAST_INSERT_ID();

-- Insert permissions for Daily Revenue Forecast
INSERT INTO `erp_permission` (`id`, `menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(NULL, @daily_revenue_forecast_menu_id, 'view', 'daily_revenue_forecast_view', NOW(), NOW()),
(NULL, @daily_revenue_forecast_menu_id, 'create', 'daily_revenue_forecast_create', NOW(), NOW()),
(NULL, @daily_revenue_forecast_menu_id, 'update', 'daily_revenue_forecast_update', NOW(), NOW()),
(NULL, @daily_revenue_forecast_menu_id, 'delete', 'daily_revenue_forecast_delete', NOW(), NOW());

-- Get permission IDs
SET @daily_revenue_forecast_view_id = (SELECT id FROM erp_permission WHERE code = 'daily_revenue_forecast_view');
SET @daily_revenue_forecast_create_id = (SELECT id FROM erp_permission WHERE code = 'daily_revenue_forecast_create');
SET @daily_revenue_forecast_update_id = (SELECT id FROM erp_permission WHERE code = 'daily_revenue_forecast_update');
SET @daily_revenue_forecast_delete_id = (SELECT id FROM erp_permission WHERE code = 'daily_revenue_forecast_delete');

-- Insert role permissions for admin role (assuming role ID 1 is admin)
INSERT INTO `erp_role_permission` (`id_role_permission`, `id_role`, `id_permission`, `status`) VALUES
(NULL, 1, @daily_revenue_forecast_view_id, 'A'),
(NULL, 1, @daily_revenue_forecast_create_id, 'A'),
(NULL, 1, @daily_revenue_forecast_update_id, 'A'),
(NULL, 1, @daily_revenue_forecast_delete_id, 'A'); 