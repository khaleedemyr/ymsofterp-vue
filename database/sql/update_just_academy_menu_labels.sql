-- Ubah label menu Just Academy: Categories → Method, Schedules → Training Plan
UPDATE `erp_menu` SET `name` = 'Method', `updated_at` = NOW()
WHERE `code` = 'just_academy_categories';

UPDATE `erp_menu` SET `name` = 'Training Plan', `updated_at` = NOW()
WHERE `code` = 'just_academy_schedules';
