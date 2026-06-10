-- Regional Management: target kunjungan outlet per bulan (untuk KPI D022)
-- Jalankan sekali di MySQL setelah backup user_regional.

ALTER TABLE `user_regional`
    ADD COLUMN `target_outlet_visits` INT UNSIGNED NULL DEFAULT NULL
        COMMENT 'Target jumlah kunjungan outlet per bulan (KPI regional)'
        AFTER `area`;
