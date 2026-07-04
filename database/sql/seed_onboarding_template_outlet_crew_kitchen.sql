-- Seed: Outlet Crew Kitchen onboarding template (8 minggu)
-- Jalankan setelah create_employee_onboarding_tables.sql
-- Struktur mengikuti checklist Excel Outlet Crew Kitchen

START TRANSACTION;

INSERT INTO `onboarding_templates` (`code`, `name`, `total_weeks`, `is_active`, `notes`, `created_at`, `updated_at`)
VALUES (
    'outlet_crew_kitchen',
    'Outlet Crew Kitchen',
    8,
    1,
    'Template onboarding Outlet Crew Kitchen — 8 minggu',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `total_weeks` = VALUES(`total_weeks`),
    `is_active` = VALUES(`is_active`),
    `notes` = VALUES(`notes`),
    `updated_at` = NOW();

SET @tpl_id := (SELECT `id` FROM `onboarding_templates` WHERE `code` = 'outlet_crew_kitchen' LIMIT 1);

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

-- Areas (1 area per minggu)
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

-- Minggu 1 — Pengenalan & Adaptasi Awal
INSERT INTO `onboarding_template_items` (`template_id`, `area_id`, `checklist_text`, `pic_role_hint`, `sort_order`, `created_at`, `updated_at`) VALUES
(@tpl_id, @a1, 'Persiapan administrasi joining & penempatan kerja', 'HR / SPV', 0, NOW(), NOW()),
(@tpl_id, @a1, 'Pengenalan company profile, culture & peraturan perusahaan', 'Champion & HR', 1, NOW(), NOW()),
(@tpl_id, @a1, 'Pengenalan outlet, team & area kerja kitchen', 'Champion', 2, NOW(), NOW()),
(@tpl_id, @a1, 'Pembuatan akun ERP / system outlet', 'Champion', 3, NOW(), NOW()),
(@tpl_id, @a1, 'Aktivasi attendance & akses kerja', 'Champion', 4, NOW(), NOW()),
(@tpl_id, @a1, 'Monitoring adaptasi, grooming & disiplin awal', 'SPV/Captain', 5, NOW(), NOW());

-- Minggu 2 — SOP & Basic Operational Training (Kitchen)
INSERT INTO `onboarding_template_items` (`template_id`, `area_id`, `checklist_text`, `pic_role_hint`, `sort_order`, `created_at`, `updated_at`) VALUES
(@tpl_id, @a2, 'Training SOP dasar kitchen, food handling & hygiene', 'Champion', 0, NOW(), NOW()),
(@tpl_id, @a2, 'Training product knowledge ( bahan baku & menu kitchen )', 'Champion', 1, NOW(), NOW()),
(@tpl_id, @a2, 'Training basic operational flow ( prep / line / plating )', 'Champion', 2, NOW(), NOW()),
(@tpl_id, @a2, 'Observasi implementasi SOP & komunikasi kerja di kitchen', 'Champion', 3, NOW(), NOW()),
(@tpl_id, @a2, 'Coaching & feedback harian oleh SPV/Captain', 'SPV/Captain', 4, NOW(), NOW());

-- Minggu 3 — Operational Consistency
INSERT INTO `onboarding_template_items` (`template_id`, `area_id`, `checklist_text`, `pic_role_hint`, `sort_order`, `created_at`, `updated_at`) VALUES
(@tpl_id, @a3, 'Monitoring konsistensi operasional & SOP kitchen', 'Champion', 0, NOW(), NOW()),
(@tpl_id, @a3, 'Monitoring speed, accuracy & teamwork di kitchen', 'Champion', 1, NOW(), NOW()),
(@tpl_id, @a3, 'Pengembangan food safety, hygiene & standar kerja kitchen', 'Champion', 2, NOW(), NOW()),
(@tpl_id, @a3, 'Evaluasi product knowledge & operational understanding', 'SPV/Captain', 3, NOW(), NOW());

-- Minggu 4 — Review Bulan Pertama
INSERT INTO `onboarding_template_items` (`template_id`, `area_id`, `checklist_text`, `pic_role_hint`, `sort_order`, `created_at`, `updated_at`) VALUES
(@tpl_id, @a4, 'Review attendance, grooming & discipline', 'Champion', 0, NOW(), NOW()),
(@tpl_id, @a4, 'Review implementasi SOP kitchen & kesiapan operasional', 'Champion', 1, NOW(), NOW()),
(@tpl_id, @a4, 'Identifikasi area improvement', 'Champion', 2, NOW(), NOW()),
(@tpl_id, @a4, 'Coaching & training tambahan jika diperlukan', 'SPV/Captain', 3, NOW(), NOW());

-- Minggu 5 — Advanced Operational
INSERT INTO `onboarding_template_items` (`template_id`, `area_id`, `checklist_text`, `pic_role_hint`, `sort_order`, `created_at`, `updated_at`) VALUES
(@tpl_id, @a5, 'Keterlibatan operasional kitchen secara mandiri', 'SPV/Captain', 0, NOW(), NOW()),
(@tpl_id, @a5, 'Monitoring productivity & food quality', 'Outlet Leader', 1, NOW(), NOW()),
(@tpl_id, @a5, 'Pengembangan problem solving & responsibility', 'Outlet Leader', 2, NOW(), NOW()),
(@tpl_id, @a5, 'Coaching lanjutan & cross training station jika diperlukan', 'Outlet Leader', 3, NOW(), NOW());

-- Minggu 6 — Advanced Operational (Excel: Minggu 5-6)
INSERT INTO `onboarding_template_items` (`template_id`, `area_id`, `checklist_text`, `pic_role_hint`, `sort_order`, `created_at`, `updated_at`) VALUES
(@tpl_id, @a6, 'Keterlibatan operasional kitchen secara mandiri', 'SPV/Captain', 0, NOW(), NOW()),
(@tpl_id, @a6, 'Monitoring productivity & food quality', 'Outlet Leader', 1, NOW(), NOW()),
(@tpl_id, @a6, 'Pengembangan problem solving & responsibility', 'Outlet Leader', 2, NOW(), NOW()),
(@tpl_id, @a6, 'Coaching lanjutan & cross training station jika diperlukan', 'Outlet Leader', 3, NOW(), NOW());

-- Minggu 7 — Final Improvement
INSERT INTO `onboarding_template_items` (`template_id`, `area_id`, `checklist_text`, `pic_role_hint`, `sort_order`, `created_at`, `updated_at`) VALUES
(@tpl_id, @a7, 'Monitoring final operational consistency kitchen', 'SPV/Captain', 0, NOW(), NOW()),
(@tpl_id, @a7, 'Monitoring food safety & hygiene implementation', 'SPV/Captain', 1, NOW(), NOW()),
(@tpl_id, @a7, 'Final coaching & improvement follow up', 'Outlet Leader', 2, NOW(), NOW()),
(@tpl_id, @a7, 'Review readiness employee', 'Outlet Leader', 3, NOW(), NOW());

-- Minggu 8 — Final Evaluation & Certification
INSERT INTO `onboarding_template_items` (`template_id`, `area_id`, `checklist_text`, `pic_role_hint`, `sort_order`, `created_at`, `updated_at`) VALUES
(@tpl_id, @a8, 'Final evaluation skill & operational readiness kitchen', 'Outlet Leader', 0, NOW(), NOW()),
(@tpl_id, @a8, 'Evaluasi SOP, food safety & teamwork', 'Outlet Leader', 1, NOW(), NOW()),
(@tpl_id, @a8, 'Final competency assessment', 'Outlet Leader', 2, NOW(), NOW()),
(@tpl_id, @a8, 'Penentuan kelulusan induction / readiness employee', 'Outlet Leader', 3, NOW(), NOW()),
(@tpl_id, @a8, 'Final induction documentation & reporting selesai', 'Outlet Leader', 4, NOW(), NOW());

COMMIT;
