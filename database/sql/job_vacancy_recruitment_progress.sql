-- Rekrutmen v2: config posisi di form lowongan + progress pelamar per-tahap terpisah
-- Jalankan di database ymsofterp (MySQL/MariaDB)

-- 1) Config rekrutmen per lowongan (tanpa PIC text — PIC pakai pivot user)
CREATE TABLE IF NOT EXISTS `job_vacancy_recruitment_configs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `job_vacancy_id` BIGINT UNSIGNED NOT NULL,
    `headcount_needed` INT UNSIGNED NULL COMMENT 'Kebutuhan orang, NULL jika HOLD',
    `is_hold` TINYINT(1) NOT NULL DEFAULT 0,
    `search_start_date` DATE NULL,
    `target_fulfill_date` DATE NULL,
    `hr_interview_notes` TEXT NULL,
    `user_interview_notes` TEXT NULL,
    `final_notes` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `job_vacancy_recruitment_configs_job_vacancy_id_unique` (`job_vacancy_id`),
    CONSTRAINT `job_vacancy_recruitment_configs_job_vacancy_id_foreign`
        FOREIGN KEY (`job_vacancy_id`) REFERENCES `job_vacancies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jika tabel lama punya kolom pic, bisa di-drop manual:
-- ALTER TABLE `job_vacancy_recruitment_configs` DROP COLUMN `pic`;

-- 2) PIC rekrutmen (multiselect user)
CREATE TABLE IF NOT EXISTS `job_vacancy_pics` (
    `job_vacancy_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`job_vacancy_id`, `user_id`),
    CONSTRAINT `job_vacancy_pics_job_vacancy_id_foreign`
        FOREIGN KEY (`job_vacancy_id`) REFERENCES `job_vacancies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `job_vacancy_pics_user_id_foreign`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Progress pelamar — tiap tahap terpisah (pending / ok / nok)
ALTER TABLE `job_vacancy_applications`
    ADD COLUMN `screening_status` VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER `status`,
    ADD COLUMN `hr_interview_status` VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER `screening_status`,
    ADD COLUMN `user_interview_status` VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER `hr_interview_status`,
    ADD COLUMN `loi_status` VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER `user_interview_status`,
    ADD COLUMN `stage_notes` TEXT NULL AFTER `loi_status`,
    ADD COLUMN `joined_at` DATE NULL AFTER `stage_notes`;

-- Jika sudah ada recruitment_stage dari versi sebelumnya, migrasi lalu drop:
-- UPDATE ... (lihat blok di bawah jika kolom recruitment_stage ada)

-- Migrasi dari recruitment_stage (jika kolom masih ada):
-- UPDATE job_vacancy_applications SET screening_status='ok' WHERE recruitment_stage='screening_cv_ok';
-- UPDATE job_vacancy_applications SET screening_status='nok' WHERE recruitment_stage='screening_cv_nok';
-- UPDATE job_vacancy_applications SET hr_interview_status='ok' WHERE recruitment_stage IN ('hr_interview_ok','user_interview_ok','loi','joined');
-- UPDATE job_vacancy_applications SET hr_interview_status='nok' WHERE recruitment_stage='hr_interview_nok';
-- UPDATE job_vacancy_applications SET user_interview_status='ok' WHERE recruitment_stage IN ('user_interview_ok','loi','joined');
-- UPDATE job_vacancy_applications SET user_interview_status='nok' WHERE recruitment_stage='user_interview_nok';
-- UPDATE job_vacancy_applications SET loi_status='ok' WHERE recruitment_stage IN ('loi','joined');
-- ALTER TABLE job_vacancy_applications DROP COLUMN recruitment_stage;
