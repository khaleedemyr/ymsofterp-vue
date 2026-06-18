-- Just Academy: mode timer quiz (total menit / per soal detik)
-- Jalankan sekali pada database yang sudah punya ja_quizzes

ALTER TABLE `ja_quizzes`
    ADD COLUMN `time_limit_mode` ENUM('none', 'quiz', 'question') NOT NULL DEFAULT 'none'
        COMMENT 'none=tanpa batas, quiz=total menit, question=detik per soal'
        AFTER `time_limit_min`,
    ADD COLUMN `time_limit_question_sec` INT UNSIGNED NULL
        COMMENT 'Detik per soal jika time_limit_mode=question'
        AFTER `time_limit_mode`;

UPDATE `ja_quizzes`
SET `time_limit_mode` = 'quiz'
WHERE `time_limit_min` IS NOT NULL AND `time_limit_min` > 0;

ALTER TABLE `ja_quiz_attempts`
    ADD COLUMN `quiz_progress` JSON NULL
        COMMENT 'Progress mode per-soal: current_index, question_started_at'
        AFTER `option_orders`;
