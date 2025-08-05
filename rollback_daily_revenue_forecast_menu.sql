-- Rollback script for Daily Revenue Forecast menu and permissions

-- Delete permissions first (due to foreign key constraint)
DELETE FROM `erp_permission` WHERE `code` IN (
    'daily_revenue_forecast_view',
    'daily_revenue_forecast_create', 
    'daily_revenue_forecast_update',
    'daily_revenue_forecast_delete'
);

-- Delete menu
DELETE FROM `erp_menu` WHERE `code` = 'daily_revenue_forecast'; 