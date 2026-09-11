-- Tracking edit pengajuan lembur (alasan + diff highlight)
-- Eksekusi sekali. Jika kolom sudah ada, skip statement yang error.

ALTER TABLE `overtime_submissions`
    ADD COLUMN `edit_reason` TEXT NULL AFTER `status`,
    ADD COLUMN `edit_changes` JSON NULL AFTER `edit_reason`,
    ADD COLUMN `edited_at` TIMESTAMP NULL AFTER `edit_changes`,
    ADD COLUMN `edited_by` BIGINT UNSIGNED NULL AFTER `edited_at`;
