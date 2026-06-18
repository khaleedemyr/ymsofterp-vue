-- Just Academy: bank soal + subset acak per attempt
-- Jalankan sekali pada database yang sudah punya tabel ja_quizzes

ALTER TABLE `ja_quizzes`
    ADD COLUMN `questions_per_attempt` INT UNSIGNED NULL
        COMMENT 'Jumlah soal yang ditampilkan per tes. NULL = semua soal di bank'
        AFTER `time_limit_min`,
    ADD COLUMN `randomize_questions` TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 = pilih & urutkan soal secara acak setiap attempt'
        AFTER `questions_per_attempt`,
    ADD COLUMN `randomize_options` TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 = acak urutan opsi jawaban setiap attempt'
        AFTER `randomize_questions`;

ALTER TABLE `ja_quiz_attempts`
    ADD COLUMN `question_ids` JSON NULL
        COMMENT 'Urutan ID soal yang ditampilkan pada attempt ini'
        AFTER `user_id`,
    ADD COLUMN `option_orders` JSON NULL
        COMMENT 'Map question_id => urutan option_id untuk attempt ini'
        AFTER `question_ids`;
