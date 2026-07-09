/*
  SOP Development Completion - menu + permission
  Parent Ops Management = 184
  Jalankan SEMUA baris ini sekaligus di Navicat (jangan tambah SELECT di atas tanpa ;)

  Setelah sukses, cek:
  SELECT * FROM erp_menu WHERE code = 'sop_development_completion';
  SELECT * FROM erp_permission WHERE menu_id = (SELECT id FROM erp_menu WHERE code = 'sop_development_completion');
*/

INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`)
VALUES (
    'SOP Development Completion',
    'sop_development_completion',
    184,
    '/sop-development-completion',
    'fa-solid fa-file-circle-check',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
SELECT m.`id`, 'view', 'sop_development_completion_view', NOW(), NOW()
FROM `erp_menu` m
WHERE m.`code` = 'sop_development_completion'
  AND NOT EXISTS (
    SELECT 1 FROM `erp_permission` p
    WHERE p.`menu_id` = m.`id` AND p.`action` = 'view'
  );

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
SELECT m.`id`, 'create', 'sop_development_completion_create', NOW(), NOW()
FROM `erp_menu` m
WHERE m.`code` = 'sop_development_completion'
  AND NOT EXISTS (
    SELECT 1 FROM `erp_permission` p
    WHERE p.`menu_id` = m.`id` AND p.`action` = 'create'
  );

UPDATE `erp_permission` p
INNER JOIN `erp_menu` m ON m.`id` = p.`menu_id` AND m.`code` = 'sop_development_completion'
SET p.`code` = 'sop_development_completion_view', p.`updated_at` = NOW()
WHERE p.`action` = 'view' AND p.`code` <> 'sop_development_completion_view';

UPDATE `erp_permission` p
INNER JOIN `erp_menu` m ON m.`id` = p.`menu_id` AND m.`code` = 'sop_development_completion'
SET p.`code` = 'sop_development_completion_create', p.`updated_at` = NOW()
WHERE p.`action` = 'create' AND p.`code` <> 'sop_development_completion_create';

INSERT IGNORE INTO `erp_role_permission` (`role_id`, `permission_id`)
SELECT rp.`role_id`, p_new.`id`
FROM `erp_role_permission` rp
INNER JOIN `erp_permission` p_old ON p_old.`id` = rp.`permission_id`
    AND p_old.`code` IN ('purchase_requisition_ops_view', 'purchase_requisition_ops')
INNER JOIN `erp_permission` p_new ON p_new.`code` = 'sop_development_completion_view';
