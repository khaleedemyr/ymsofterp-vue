-- Rename Asset Management menu labels (sidebar i18n + erp_menu.name)
-- Safe to run multiple times.

UPDATE `erp_menu` SET `name` = 'Asset Lost & Breakage', `updated_at` = NOW()
WHERE `code` = 'lost_breakage';

UPDATE `erp_menu` SET `name` = 'Asset Repair & Maintenance', `updated_at` = NOW()
WHERE `code` = 'asset_service_order';
