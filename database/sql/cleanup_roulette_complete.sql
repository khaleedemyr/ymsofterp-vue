-- Complete cleanup for Roulette feature
-- ======================================

-- 1. Delete permissions for Data Roulette
DELETE FROM `erp_permission` WHERE `menu_id` = 100;

-- 2. Delete menu for Data Roulette
DELETE FROM `erp_menu` WHERE `id` = 100;

-- 3. Drop roulettes table
DROP TABLE IF EXISTS `roulettes`; 