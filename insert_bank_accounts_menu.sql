-- Insert menu Master Data Bank ke erp_menu
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`)
VALUES ('Master Data Bank', 'bank_accounts', 3, '/bank-accounts', 'fa-solid fa-building-columns', NOW(), NOW());

-- Get the menu_id yang baru saja diinsert
SET @menu_id = LAST_INSERT_ID();

-- Insert permissions untuk Master Data Bank
-- View (index, show)
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
VALUES (@menu_id, 'view', 'bank_accounts_view', NOW(), NOW());

-- Create
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
VALUES (@menu_id, 'create', 'bank_accounts_create', NOW(), NOW());

-- Update
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
VALUES (@menu_id, 'update', 'bank_accounts_update', NOW(), NOW());

-- Delete
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
VALUES (@menu_id, 'delete', 'bank_accounts_delete', NOW(), NOW());

