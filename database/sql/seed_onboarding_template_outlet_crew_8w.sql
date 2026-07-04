-- Seed: Outlet Crew 8 Week onboarding template
-- Jalankan setelah create_employee_onboarding_tables.sql

START TRANSACTION;

INSERT INTO `onboarding_templates` (`code`, `name`, `total_weeks`, `is_active`, `notes`, `created_at`, `updated_at`)
VALUES (
    'outlet_crew_8w',
    'Outlet Crew 8 Week',
    8,
    1,
    'Template onboarding crew outlet — 8 minggu',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `total_weeks` = VALUES(`total_weeks`),
    `is_active` = VALUES(`is_active`),
    `notes` = VALUES(`notes`),
    `updated_at` = NOW();

SET @tpl_id := (SELECT `id` FROM `onboarding_templates` WHERE `code` = 'outlet_crew_8w' LIMIT 1);

DELETE FROM `onboarding_template_week_approvers` WHERE `template_id` = @tpl_id;
DELETE FROM `onboarding_template_items` WHERE `template_id` = @tpl_id;
DELETE FROM `onboarding_template_areas` WHERE `template_id` = @tpl_id;
DELETE FROM `onboarding_template_weeks` WHERE `template_id` = @tpl_id;

-- Weeks
INSERT INTO `onboarding_template_weeks` (`template_id`, `week_number`, `week_label`, `sort_order`, `created_at`, `updated_at`) VALUES
(@tpl_id, 1, 'Pengenalan & Adaptasi Awal', 0, NOW(), NOW()),
(@tpl_id, 2, 'SOP & Basic Operational Training', 1, NOW(), NOW()),
(@tpl_id, 3, 'Operational Consistency', 2, NOW(), NOW()),
(@tpl_id, 4, 'Review Bulan Pertama', 3, NOW(), NOW()),
(@tpl_id, 5, 'Advanced Operational', 4, NOW(), NOW()),
(@tpl_id, 6, 'Advanced Operational', 5, NOW(), NOW()),
(@tpl_id, 7, 'Final Improvement', 6, NOW(), NOW()),
(@tpl_id, 8, 'Final Evaluation & Certification', 7, NOW(), NOW());

SET @w1 := (SELECT `id` FROM `onboarding_template_weeks` WHERE `template_id` = @tpl_id AND `week_number` = 1 LIMIT 1);
SET @w2 := (SELECT `id` FROM `onboarding_template_weeks` WHERE `template_id` = @tpl_id AND `week_number` = 2 LIMIT 1);
SET @w3 := (SELECT `id` FROM `onboarding_template_weeks` WHERE `template_id` = @tpl_id AND `week_number` = 3 LIMIT 1);
SET @w4 := (SELECT `id` FROM `onboarding_template_weeks` WHERE `template_id` = @tpl_id AND `week_number` = 4 LIMIT 1);
SET @w5 := (SELECT `id` FROM `onboarding_template_weeks` WHERE `template_id` = @tpl_id AND `week_number` = 5 LIMIT 1);
SET @w6 := (SELECT `id` FROM `onboarding_template_weeks` WHERE `template_id` = @tpl_id AND `week_number` = 6 LIMIT 1);
SET @w7 := (SELECT `id` FROM `onboarding_template_weeks` WHERE `template_id` = @tpl_id AND `week_number` = 7 LIMIT 1);
SET @w8 := (SELECT `id` FROM `onboarding_template_weeks` WHERE `template_id` = @tpl_id AND `week_number` = 8 LIMIT 1);

-- Areas
INSERT INTO `onboarding_template_areas` (`template_id`, `week_id`, `area_name`, `sort_order`, `created_at`, `updated_at`) VALUES
(@tpl_id, @w1, 'Pengenalan & Adaptasi Awal', 0, NOW(), NOW()),
(@tpl_id, @w2, 'SOP & Basic Operational Training', 0, NOW(), NOW()),
(@tpl_id, @w3, 'Operational Consistency', 0, NOW(), NOW()),
(@tpl_id, @w4, 'Review Bulan Pertama', 0, NOW(), NOW()),
(@tpl_id, @w5, 'Advanced Operational', 0, NOW(), NOW()),
(@tpl_id, @w6, 'Advanced Operational', 0, NOW(), NOW()),
(@tpl_id, @w7, 'Final Improvement', 0, NOW(), NOW()),
(@tpl_id, @w8, 'Final Evaluation & Certification', 0, NOW(), NOW());

SET @a1 := (SELECT `id` FROM `onboarding_template_areas` WHERE `week_id` = @w1 LIMIT 1);
SET @a2 := (SELECT `id` FROM `onboarding_template_areas` WHERE `week_id` = @w2 LIMIT 1);
SET @a3 := (SELECT `id` FROM `onboarding_template_areas` WHERE `week_id` = @w3 LIMIT 1);
SET @a4 := (SELECT `id` FROM `onboarding_template_areas` WHERE `week_id` = @w4 LIMIT 1);
SET @a5 := (SELECT `id` FROM `onboarding_template_areas` WHERE `week_id` = @w5 LIMIT 1);
SET @a6 := (SELECT `id` FROM `onboarding_template_areas` WHERE `week_id` = @w6 LIMIT 1);
SET @a7 := (SELECT `id` FROM `onboarding_template_areas` WHERE `week_id` = @w7 LIMIT 1);
SET @a8 := (SELECT `id` FROM `onboarding_template_areas` WHERE `week_id` = @w8 LIMIT 1);

-- Minggu 1
INSERT INTO `onboarding_template_items` (`template_id`, `area_id`, `checklist_text`, `pic_role_hint`, `sort_order`, `created_at`, `updated_at`) VALUES
(@tpl_id, @a1, 'Administrasi & persiapan kehadiran karyawan baru', 'HR/SPV', 0, NOW(), NOW()),
(@tpl_id, @a1, 'Company profile & culture briefing', 'HR/SPV', 1, NOW(), NOW()),
(@tpl_id, @a1, 'Pengenalan outlet & team', 'Champion & HR', 2, NOW(), NOW()),
(@tpl_id, @a1, 'Pembuatan akun ERP', 'Champion', 3, NOW(), NOW()),
(@tpl_id, @a1, 'Aktivasi absensi', 'Champion', 4, NOW(), NOW()),
(@tpl_id, @a1, 'Monitoring adaptasi awal', 'SPV/Captain', 5, NOW(), NOW());

-- Minggu 2
INSERT INTO `onboarding_template_items` (`template_id`, `area_id`, `checklist_text`, `pic_role_hint`, `sort_order`, `created_at`, `updated_at`) VALUES
(@tpl_id, @a2, 'Basic SOP training', 'Champion', 0, NOW(), NOW()),
(@tpl_id, @a2, 'Product knowledge basic', 'Champion', 1, NOW(), NOW()),
(@tpl_id, @a2, 'Basic operational flow', 'Champion', 2, NOW(), NOW()),
(@tpl_id, @a2, 'Implementation observation', 'SPV/Captain', 3, NOW(), NOW()),
(@tpl_id, @a2, 'Daily coaching & feedback', 'SPV/Captain', 4, NOW(), NOW());

-- Minggu 3
INSERT INTO `onboarding_template_items` (`template_id`, `area_id`, `checklist_text`, `pic_role_hint`, `sort_order`, `created_at`, `updated_at`) VALUES
(@tpl_id, @a3, 'Monitoring operational consistency', 'Champion', 0, NOW(), NOW()),
(@tpl_id, @a3, 'Speed, accuracy & teamwork', 'SPV/Captain', 1, NOW(), NOW()),
(@tpl_id, @a3, 'Hospitality & guest interaction', 'SPV/Captain', 2, NOW(), NOW()),
(@tpl_id, @a3, 'Evaluasi product knowledge', 'Champion', 3, NOW(), NOW());

-- Minggu 4
INSERT INTO `onboarding_template_items` (`template_id`, `area_id`, `checklist_text`, `pic_role_hint`, `sort_order`, `created_at`, `updated_at`) VALUES
(@tpl_id, @a4, 'Review attendance, grooming & discipline', 'Champion', 0, NOW(), NOW()),
(@tpl_id, @a4, 'Review SOP implementation', 'SPV/Captain', 1, NOW(), NOW()),
(@tpl_id, @a4, 'Identifikasi area improvement', 'SPV/Captain', 2, NOW(), NOW()),
(@tpl_id, @a4, 'Competency testing', 'Champion', 3, NOW(), NOW());

-- Minggu 5
INSERT INTO `onboarding_template_items` (`template_id`, `area_id`, `checklist_text`, `pic_role_hint`, `sort_order`, `created_at`, `updated_at`) VALUES
(@tpl_id, @a5, 'Independent operational involvement', 'SPV/Captain', 0, NOW(), NOW()),
(@tpl_id, @a5, 'Monitoring productivity', 'Outlet Leader', 1, NOW(), NOW()),
(@tpl_id, @a5, 'Problem solving development', 'SPV/Captain', 2, NOW(), NOW()),
(@tpl_id, @a5, 'Advanced coaching', 'Outlet Leader', 3, NOW(), NOW());

-- Minggu 6
INSERT INTO `onboarding_template_items` (`template_id`, `area_id`, `checklist_text`, `pic_role_hint`, `sort_order`, `created_at`, `updated_at`) VALUES
(@tpl_id, @a6, 'Continued independent operations', 'SPV/Captain', 0, NOW(), NOW()),
(@tpl_id, @a6, 'Advanced service standards', 'Outlet Leader', 1, NOW(), NOW()),
(@tpl_id, @a6, 'Team collaboration assessment', 'SPV/Captain', 2, NOW(), NOW());

-- Minggu 7
INSERT INTO `onboarding_template_items` (`template_id`, `area_id`, `checklist_text`, `pic_role_hint`, `sort_order`, `created_at`, `updated_at`) VALUES
(@tpl_id, @a7, 'Final consistency monitoring', 'SPV/Captain', 0, NOW(), NOW()),
(@tpl_id, @a7, 'Hospitality implementation review', 'Outlet Leader', 1, NOW(), NOW()),
(@tpl_id, @a7, 'Final coaching session', 'Outlet Leader', 2, NOW(), NOW()),
(@tpl_id, @a7, 'Readiness review', 'Outlet Leader', 3, NOW(), NOW());

-- Minggu 8
INSERT INTO `onboarding_template_items` (`template_id`, `area_id`, `checklist_text`, `pic_role_hint`, `sort_order`, `created_at`, `updated_at`) VALUES
(@tpl_id, @a8, 'Final skill & readiness evaluation', 'Outlet Leader', 0, NOW(), NOW()),
(@tpl_id, @a8, 'SOP, hospitality & teamwork evaluation', 'Outlet Leader', 1, NOW(), NOW()),
(@tpl_id, @a8, 'Final competency assessment', 'Outlet Leader', 2, NOW(), NOW()),
(@tpl_id, @a8, 'Graduation determination', 'Outlet Leader', 3, NOW(), NOW()),
(@tpl_id, @a8, 'Final documentation', 'Outlet Leader', 4, NOW(), NOW());

COMMIT;
