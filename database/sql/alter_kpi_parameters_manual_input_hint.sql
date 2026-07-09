-- Petunjuk khusus untuk kolom Manual/Override di evaluasi KPI (opsional per parameter)
ALTER TABLE `kpi_parameters`
    ADD COLUMN `manual_input_hint` TEXT NULL DEFAULT NULL
        AFTER `description`;
