-- Tambah field pelamar pada job_vacancy_applications
-- Jalankan di database ymsofterp (MySQL/MariaDB)

ALTER TABLE `job_vacancy_applications`
    ADD COLUMN `domicile` VARCHAR(255) NULL COMMENT 'Domisili pelamar' AFTER `phone`,
    ADD COLUMN `last_education` VARCHAR(255) NULL COMMENT 'Pendidikan terakhir' AFTER `domicile`,
    ADD COLUMN `birth_date` DATE NULL COMMENT 'Tanggal lahir' AFTER `last_education`,
    ADD COLUMN `photo_file` VARCHAR(500) NULL COMMENT 'Path foto terbaru (storage public)' AFTER `cv_file`;
