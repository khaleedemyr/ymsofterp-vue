-- Pisah dasar potongan BPJS: kesehatan (JKN) & ketenagakerjaan (TK)
-- Jalankan setelah kolom nilai_dasar_potongan_bpjs ada

ALTER TABLE `tbl_data_level`
    ADD COLUMN `nilai_dasar_potongan_bpjs_kesehatan` INT UNSIGNED NOT NULL DEFAULT 0
        AFTER `nilai_dasar_potongan_bpjs`,
    ADD COLUMN `nilai_dasar_potongan_bpjs_ketenagakerjaan` INT UNSIGNED NOT NULL DEFAULT 0
        AFTER `nilai_dasar_potongan_bpjs_kesehatan`;

UPDATE `tbl_data_level`
SET
    `nilai_dasar_potongan_bpjs_kesehatan` = `nilai_dasar_potongan_bpjs`,
    `nilai_dasar_potongan_bpjs_ketenagakerjaan` = `nilai_dasar_potongan_bpjs`
WHERE `nilai_dasar_potongan_bpjs` > 0;
