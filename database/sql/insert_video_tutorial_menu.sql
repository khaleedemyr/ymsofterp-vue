-- Insert Video Tutorial Menu and Permissions
-- Run this script in your MySQL database

-- Insert menu for Video Tutorial Groups
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) 
VALUES 
('Master Data Group Video Tutorial', 'master-data-video-tutorial-groups', 3, '/video-tutorial-groups', 'fa-folder', NOW(), NOW())
ON DUPLICATE KEY UPDATE 
    name = VALUES(name),
    route = VALUES(route),
    icon = VALUES(icon),
    updated_at = NOW();

-- Insert menu for Video Tutorials
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) 
VALUES 
('Master Data Video Tutorial', 'master-data-video-tutorials', 3, '/video-tutorials', 'fa-video', NOW(), NOW())
ON DUPLICATE KEY UPDATE 
    name = VALUES(name),
    route = VALUES(route),
    icon = VALUES(icon),
    updated_at = NOW();

-- Get menu IDs for permissions
SET @video_tutorial_groups_menu_id = (SELECT id FROM `erp_menu` WHERE code = 'master-data-video-tutorial-groups');
SET @video_tutorials_menu_id = (SELECT id FROM `erp_menu` WHERE code = 'master-data-video-tutorials');

-- Insert permissions for Video Tutorial Groups
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
VALUES 
(@video_tutorial_groups_menu_id, 'view', 'view-video-tutorial-groups', NOW(), NOW()),
(@video_tutorial_groups_menu_id, 'create', 'create-video-tutorial-groups', NOW(), NOW()),
(@video_tutorial_groups_menu_id, 'update', 'update-video-tutorial-groups', NOW(), NOW()),
(@video_tutorial_groups_menu_id, 'delete', 'delete-video-tutorial-groups', NOW(), NOW())
ON DUPLICATE KEY UPDATE 
    updated_at = NOW();

-- Insert permissions for Video Tutorials
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
VALUES 
(@video_tutorials_menu_id, 'view', 'view-video-tutorials', NOW(), NOW()),
(@video_tutorials_menu_id, 'create', 'create-video-tutorials', NOW(), NOW()),
(@video_tutorials_menu_id, 'update', 'update-video-tutorials', NOW(), NOW()),
(@video_tutorials_menu_id, 'delete', 'delete-video-tutorials', NOW(), NOW())
ON DUPLICATE KEY UPDATE 
    updated_at = NOW();

-- Show success message
SELECT 'Video Tutorial menu and permissions have been successfully added!' as message; 