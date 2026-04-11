-- Guest Comment (OCR) — erp_menu + erp_permission (parent CRM = 138)
-- Eksekusi sekali di MySQL (boleh dipaste sekaligus).
-- Setelah ini, tautkan permission ke role lewat erp_role_permission jika perlu.

INSERT INTO `erp_menu` (
    `name`,
    `code`,
    `parent_id`,
    `route`,
    `icon`,
    `created_at`,
    `updated_at`
) VALUES (
    'Guest Comment (OCR)',
    'guest_comment_form',
    138,
    '/guest-comment-forms',
    'fa-solid fa-comment-dots',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'guest_comment_form' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES (
    @menu_id,
    'view',
    'guest_comment_form_view',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `menu_id` = VALUES(`menu_id`),
    `updated_at` = NOW();
