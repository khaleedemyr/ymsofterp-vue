-- Insert menu item for Locked Budget Food Categories
INSERT INTO `erp_menu` (`id`, `name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
(130, 'Locked Budget Food Categories', 'locked_budget_food_categories', 3, '/locked-budget-food-categories', 'fa-solid fa-lock', NOW(), NOW());

-- Insert permissions for Locked Budget Food Categories
INSERT INTO `erp_permission` (`id`, `menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(130, 130, 'view', 'locked_budget_food_categories_view', NOW(), NOW()),
(131, 130, 'create', 'locked_budget_food_categories_create', NOW(), NOW()),
(132, 130, 'update', 'locked_budget_food_categories_update', NOW(), NOW()),
(133, 130, 'delete', 'locked_budget_food_categories_delete', NOW(), NOW());
