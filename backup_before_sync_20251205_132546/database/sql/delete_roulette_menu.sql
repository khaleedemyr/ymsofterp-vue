-- Delete permissions for Data Roulette
DELETE FROM `erp_permission` WHERE `menu_id` = 100;

-- Delete menu for Data Roulette
DELETE FROM `erp_menu` WHERE `id` = 100; 