-- Seed: Outlet Crew Service onboarding template (8 minggu)
-- Jalankan setelah create_employee_onboarding_tables.sql
-- Struktur mengikuti checklist Excel Outlet Crew Service

START TRANSACTION;

INSERT INTO `onboarding_templates` (`code`, `name`, `total_weeks`, `is_active`, `notes`, `created_at`, `updated_at`)
VALUES (
    'outlet_crew_service',
    'Outlet Crew Service',
    8,
    1,
    'Template onboarding Outlet Crew Service — 8 minggu (checklist lengkap)',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `total_weeks` = VALUES(`total_weeks`),
    `is_active` = VALUES(`is_active`),
    `notes` = VALUES(`notes`),
    `updated_at` = NOW();

SET @tpl_id := (SELECT `id` FROM `onboarding_templates` WHERE `code` = 'outlet_crew_service' LIMIT 1);

DELETE FROM `onboarding_template_week_approvers` WHERE `template_id` = @tpl_id;
DELETE FROM `onboarding_template_items` WHERE `template_id` = @tpl_id;
DELETE FROM `onboarding_template_areas` WHERE `template_id` = @tpl_id;
DELETE FROM `onboarding_template_weeks` WHERE `template_id` = @tpl_id;

-- Weeks
INSERT INTO `onboarding_template_weeks` (`template_id`, `week_number`, `week_label`, `sort_order`, `created_at`, `updated_at`) VALUES
(@tpl_id, 1, 'Pengenalan & Adaptasi Awal', 0, NOW(), NOW()),
(@tpl_id, 2, 'SOP & Basic Operational Training', 1, NOW(), NOW()),
(@tpl_id, 3, 'SOP Lanjutan & Operational Consistency', 2, NOW(), NOW()),
(@tpl_id, 4, 'SOP Final & Review Bulan Pertama', 3, NOW(), NOW()),
(@tpl_id, 5, 'Operational Consistency & Advanced Operational', 4, NOW(), NOW()),
(@tpl_id, 6, 'Operational Consistency & Advanced Operational', 5, NOW(), NOW()),
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

-- Areas (beberapa minggu punya lebih dari 1 area)
INSERT INTO `onboarding_template_areas` (`template_id`, `week_id`, `area_name`, `sort_order`, `created_at`, `updated_at`) VALUES
(@tpl_id, @w1, 'Pengenalan & Adaptasi Awal', 0, NOW(), NOW()),
(@tpl_id, @w2, 'SOP & Basic Operational Training', 0, NOW(), NOW()),
(@tpl_id, @w3, 'SOP & Basic Operational Training', 0, NOW(), NOW()),
(@tpl_id, @w3, 'Operational Consistency', 1, NOW(), NOW()),
(@tpl_id, @w4, 'SOP & Basic Operational Training', 0, NOW(), NOW()),
(@tpl_id, @w4, 'Review Bulan Pertama', 1, NOW(), NOW()),
(@tpl_id, @w5, 'Operational Consistency', 0, NOW(), NOW()),
(@tpl_id, @w5, 'Advanced Operational', 1, NOW(), NOW()),
(@tpl_id, @w6, 'Operational Consistency', 0, NOW(), NOW()),
(@tpl_id, @w6, 'Advanced Operational', 1, NOW(), NOW()),
(@tpl_id, @w7, 'Final Improvement', 0, NOW(), NOW()),
(@tpl_id, @w8, 'Final Evaluation & Certification', 0, NOW(), NOW());

SET @a1 := (SELECT `id` FROM `onboarding_template_areas` WHERE `week_id` = @w1 AND `area_name` = 'Pengenalan & Adaptasi Awal' LIMIT 1);
SET @a2 := (SELECT `id` FROM `onboarding_template_areas` WHERE `week_id` = @w2 LIMIT 1);
SET @a3sop := (SELECT `id` FROM `onboarding_template_areas` WHERE `week_id` = @w3 AND `area_name` = 'SOP & Basic Operational Training' LIMIT 1);
SET @a3ops := (SELECT `id` FROM `onboarding_template_areas` WHERE `week_id` = @w3 AND `area_name` = 'Operational Consistency' LIMIT 1);
SET @a4sop := (SELECT `id` FROM `onboarding_template_areas` WHERE `week_id` = @w4 AND `area_name` = 'SOP & Basic Operational Training' LIMIT 1);
SET @a4rev := (SELECT `id` FROM `onboarding_template_areas` WHERE `week_id` = @w4 AND `area_name` = 'Review Bulan Pertama' LIMIT 1);
SET @a5ops := (SELECT `id` FROM `onboarding_template_areas` WHERE `week_id` = @w5 AND `area_name` = 'Operational Consistency' LIMIT 1);
SET @a5adv := (SELECT `id` FROM `onboarding_template_areas` WHERE `week_id` = @w5 AND `area_name` = 'Advanced Operational' LIMIT 1);
SET @a6ops := (SELECT `id` FROM `onboarding_template_areas` WHERE `week_id` = @w6 AND `area_name` = 'Operational Consistency' LIMIT 1);
SET @a6adv := (SELECT `id` FROM `onboarding_template_areas` WHERE `week_id` = @w6 AND `area_name` = 'Advanced Operational' LIMIT 1);
SET @a7 := (SELECT `id` FROM `onboarding_template_areas` WHERE `week_id` = @w7 LIMIT 1);
SET @a8 := (SELECT `id` FROM `onboarding_template_areas` WHERE `week_id` = @w8 LIMIT 1);

-- Minggu 1 — Pengenalan & Adaptasi Awal
INSERT INTO `onboarding_template_items` (`template_id`, `area_id`, `checklist_text`, `pic_role_hint`, `sort_order`, `created_at`, `updated_at`) VALUES
(@tpl_id, @a1, 'Persiapan administrasi joining & penempatan kerja', 'HR / SPV', 0, NOW(), NOW()),
(@tpl_id, @a1, 'Pengenalan company profile, culture & peraturan perusahaan', 'Champion & HR', 1, NOW(), NOW()),
(@tpl_id, @a1, 'Pengenalan outlet, team & area kerja', 'Champion', 2, NOW(), NOW()),
(@tpl_id, @a1, 'Pembuatan akun ERP / system outlet', 'Champion', 3, NOW(), NOW()),
(@tpl_id, @a1, 'Aktivasi attendance & akses kerja', 'Champion', 4, NOW(), NOW()),
(@tpl_id, @a1, 'Monitoring adaptasi, grooming & disiplin awal', 'SPV/Captain', 5, NOW(), NOW());

-- Minggu 2 — SOP & Basic Operational Training
INSERT INTO `onboarding_template_items` (`template_id`, `area_id`, `checklist_text`, `pic_role_hint`, `sort_order`, `created_at`, `updated_at`) VALUES
(@tpl_id, @a2, 'Training SOP dasar & sequence of service ( Point 1 - 8 )', 'Champion', 0, NOW(), NOW()),
(@tpl_id, @a2, 'Training product knowledge ( food & beverage )', 'Champion', 1, NOW(), NOW()),
(@tpl_id, @a2, 'Training basic operational flow ( Runner )', 'Champion', 2, NOW(), NOW()),
(@tpl_id, @a2, 'Observasi implementasi SOP & komunikasi kerja', 'Champion', 3, NOW(), NOW()),
(@tpl_id, @a2, 'Coaching & feedback harian oleh SPV/Captain', 'SPV/Captain', 4, NOW(), NOW()),
(@tpl_id, @a2, 'Test Kompetensi Tertulis', 'Regional', 5, NOW(), NOW());

-- Minggu 3 — SOP (Point 9-14) + Operational Consistency
INSERT INTO `onboarding_template_items` (`template_id`, `area_id`, `checklist_text`, `pic_role_hint`, `sort_order`, `created_at`, `updated_at`) VALUES
(@tpl_id, @a3sop, 'Training SOP dasar & sequence of service ( Point 9 - 14 )', 'Champion', 0, NOW(), NOW()),
(@tpl_id, @a3sop, 'Training product knowledge ( food & beverage )', 'Champion', 1, NOW(), NOW()),
(@tpl_id, @a3ops, 'Monitoring konsistensi operasional & SOP', 'Champion', 0, NOW(), NOW()),
(@tpl_id, @a3ops, 'Monitoring speed, accuracy & teamwork', 'Champion', 1, NOW(), NOW()),
(@tpl_id, @a3ops, 'Coaching & feedback harian oleh SPV/Captain', 'SPV/Captain', 2, NOW(), NOW()),
(@tpl_id, @a3ops, 'Test Kompetensi Tertulis', 'Regional', 3, NOW(), NOW());

-- Minggu 4 — SOP (Point 15-18) + Review Bulan Pertama
INSERT INTO `onboarding_template_items` (`template_id`, `area_id`, `checklist_text`, `pic_role_hint`, `sort_order`, `created_at`, `updated_at`) VALUES
(@tpl_id, @a4sop, 'Training SOP dasar & sequence of service ( Point 15 - 18 )', 'Champion', 0, NOW(), NOW()),
(@tpl_id, @a4rev, 'Review attendance, grooming & discipline', 'Champion', 0, NOW(), NOW()),
(@tpl_id, @a4rev, 'Review implementasi SOP & kesiapan operasional', 'Champion', 1, NOW(), NOW()),
(@tpl_id, @a4rev, 'Identifikasi area improvement', 'Champion', 2, NOW(), NOW()),
(@tpl_id, @a4rev, 'Evaluasi product knowledge & operational understanding', 'SPV/Captain', 3, NOW(), NOW()),
(@tpl_id, @a4rev, 'Test Kompetensi Tertulis', 'Regional', 4, NOW(), NOW());

-- Minggu 5 — Operational Consistency + Advanced Operational
INSERT INTO `onboarding_template_items` (`template_id`, `area_id`, `checklist_text`, `pic_role_hint`, `sort_order`, `created_at`, `updated_at`) VALUES
(@tpl_id, @a5ops, 'Pengembangan hospitality & guest interaction', 'Champion', 0, NOW(), NOW()),
(@tpl_id, @a5adv, 'Keterlibatan operasional secara mandiri', 'SPV/Captain', 0, NOW(), NOW()),
(@tpl_id, @a5adv, 'Monitoring productivity & service quality', 'Outlet Leader', 1, NOW(), NOW()),
(@tpl_id, @a5adv, 'Pengembangan problem solving & responsibility', 'Outlet Leader', 2, NOW(), NOW()),
(@tpl_id, @a5adv, 'Coaching lanjutan & cross training jika diperlukan', 'Outlet Leader', 3, NOW(), NOW()),
(@tpl_id, @a5adv, 'Test Kompetensi Tertulis', 'Regional', 4, NOW(), NOW());

-- Minggu 6 — sama struktur Minggu 5 (Excel: Minggu 5-6)
INSERT INTO `onboarding_template_items` (`template_id`, `area_id`, `checklist_text`, `pic_role_hint`, `sort_order`, `created_at`, `updated_at`) VALUES
(@tpl_id, @a6ops, 'Pengembangan hospitality & guest interaction', 'Champion', 0, NOW(), NOW()),
(@tpl_id, @a6adv, 'Keterlibatan operasional secara mandiri', 'SPV/Captain', 0, NOW(), NOW()),
(@tpl_id, @a6adv, 'Monitoring productivity & service quality', 'Outlet Leader', 1, NOW(), NOW()),
(@tpl_id, @a6adv, 'Pengembangan problem solving & responsibility', 'Outlet Leader', 2, NOW(), NOW()),
(@tpl_id, @a6adv, 'Coaching lanjutan & cross training jika diperlukan', 'Outlet Leader', 3, NOW(), NOW()),
(@tpl_id, @a6adv, 'Test Kompetensi Tertulis', 'Regional', 4, NOW(), NOW());

-- Minggu 7 — Final Improvement
INSERT INTO `onboarding_template_items` (`template_id`, `area_id`, `checklist_text`, `pic_role_hint`, `sort_order`, `created_at`, `updated_at`) VALUES
(@tpl_id, @a7, 'Monitoring final operational consistency', 'SPV/Captain', 0, NOW(), NOW()),
(@tpl_id, @a7, 'Monitoring hospitality implementation', 'SPV/Captain', 1, NOW(), NOW()),
(@tpl_id, @a7, 'Final coaching & improvement follow up', 'Outlet Leader', 2, NOW(), NOW()),
(@tpl_id, @a7, 'Review readiness employee', 'Outlet Leader', 3, NOW(), NOW());

-- Minggu 8 — Final Evaluation & Certification
INSERT INTO `onboarding_template_items` (`template_id`, `area_id`, `checklist_text`, `pic_role_hint`, `sort_order`, `created_at`, `updated_at`) VALUES
(@tpl_id, @a8, 'Final evaluation skill & operational readiness', 'Outlet Leader', 0, NOW(), NOW()),
(@tpl_id, @a8, 'Evaluasi SOP, hospitality & teamwork', 'Outlet Leader', 1, NOW(), NOW()),
(@tpl_id, @a8, 'Final competency assessment', 'Outlet Leader', 2, NOW(), NOW()),
(@tpl_id, @a8, 'Penentuan kelulusan induction / readiness employee', 'Outlet Leader', 3, NOW(), NOW()),
(@tpl_id, @a8, 'Final induction documentation & reporting selesai', 'Outlet Leader', 4, NOW(), NOW());

-- Default approver tetap (SPV → Outlet Manager). Regional dipilih saat submit minggu, bukan di template.
-- Contoh uncomment & sesuaikan user_id jika perlu pre-seed approver:
-- INSERT INTO `onboarding_template_week_approvers` (`template_id`, `week_number`, `approver_user_id`, `approval_level`, `created_at`, `updated_at`) VALUES
-- (@tpl_id, 1, 123, 1, NOW(), NOW()),
-- (@tpl_id, 1, 456, 2, NOW(), NOW());

COMMIT;
