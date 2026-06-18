-- Just Academy: acak urutan opsi jawaban per attempt
-- Jalankan jika alter_just_academy_quiz_random.sql sudah pernah dijalankan

ALTER TABLE `ja_quizzes`
    ADD COLUMN `randomize_options` TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 = acak urutan opsi jawaban setiap attempt'
        AFTER `randomize_questions`;

ALTER TABLE `ja_quiz_attempts`
    ADD COLUMN `option_orders` JSON NULL
        COMMENT 'Map question_id => urutan option_id untuk attempt ini'
        AFTER `question_ids`;
