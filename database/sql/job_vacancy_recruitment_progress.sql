-- Rekrutmen: breakdown per POSISI (config) + per PELAMAR (stage)
-- Dashboard = agregasi COUNT pelamar per stage, bukan input angka manual
-- Jalankan di database ymsofterp (MySQL/MariaDB)

-- 1) Config rekrutmen per lowongan (metadata posisi)
CREATE TABLE IF NOT EXISTS `job_vacancy_recruitment_configs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `job_vacancy_id` BIGINT UNSIGNED NOT NULL,
    `pic` VARCHAR(100) NULL COMMENT 'PIC rekrutmen',
    `headcount_needed` INT UNSIGNED NULL COMMENT 'Kebutuhan orang, NULL jika HOLD',
    `is_hold` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = posisi di-hold',
    `search_start_date` DATE NULL COMMENT 'Tanggal mulai pencarian',
    `target_fulfill_date` DATE NULL COMMENT 'Tanggal target fulfill',
    `hr_interview_notes` TEXT NULL COMMENT 'Keterangan lolos HR interview',
    `user_interview_notes` TEXT NULL COMMENT 'Keterangan lolos user interview',
    `final_notes` TEXT NULL COMMENT 'Keterangan akhir / nama yang join',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `job_vacancy_recruitment_configs_job_vacancy_id_unique` (`job_vacancy_id`),
    CONSTRAINT `job_vacancy_recruitment_configs_job_vacancy_id_foreign`
        FOREIGN KEY (`job_vacancy_id`) REFERENCES `job_vacancies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Stage progress per pelamar (sumber data dashboard)
ALTER TABLE `job_vacancy_applications`
    ADD COLUMN `recruitment_stage` VARCHAR(50) NOT NULL DEFAULT 'sourcing'
        COMMENT 'sourcing|screening_cv_ok|screening_cv_nok|hr_interview_ok|hr_interview_nok|user_interview_ok|user_interview_nok|loi|joined'
        AFTER `status`,
    ADD COLUMN `stage_notes` TEXT NULL COMMENT 'Catatan progress pelamar' AFTER `recruitment_stage`,
    ADD COLUMN `joined_at` DATE NULL COMMENT 'Tanggal join pelamar' AFTER `stage_notes`;

-- Migrasi data lama status -> recruitment_stage (jika sudah ada data)
UPDATE `job_vacancy_applications` SET `recruitment_stage` = 'sourcing' WHERE `status` = 'submitted' OR `status` IS NULL;
UPDATE `job_vacancy_applications` SET `recruitment_stage` = 'screening_cv_ok' WHERE `status` = 'reviewed';
UPDATE `job_vacancy_applications` SET `recruitment_stage` = 'hr_interview_ok' WHERE `status` = 'interview';
UPDATE `job_vacancy_applications` SET `recruitment_stage` = 'joined', `joined_at` = CURDATE() WHERE `status` = 'hired';
UPDATE `job_vacancy_applications` SET `recruitment_stage` = 'screening_cv_nok' WHERE `status` = 'rejected';
