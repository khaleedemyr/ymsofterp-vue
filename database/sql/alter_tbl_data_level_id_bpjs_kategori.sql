-- Pilihan kategori BPJS per data level (jalankan setelah tbl_bpjs_kategori ada)

ALTER TABLE `tbl_data_level`
    ADD COLUMN `id_bpjs_kategori` BIGINT UNSIGNED NULL DEFAULT NULL
    AFTER `nilai_dasar_potongan_bpjs`;

ALTER TABLE `tbl_data_level`
    ADD CONSTRAINT `tbl_data_level_id_bpjs_kategori_foreign`
    FOREIGN KEY (`id_bpjs_kategori`) REFERENCES `tbl_bpjs_kategori` (`id`)
    ON DELETE SET NULL;
