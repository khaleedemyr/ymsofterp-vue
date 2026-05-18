-- Tabel master kategori BPJS (jalankan sekali di MySQL)
-- Setara: database/migrations/2026_05_18_000001_create_tbl_bpjs_kategori_table.php

CREATE TABLE IF NOT EXISTS `tbl_bpjs_kategori` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nama_kategori` VARCHAR(150) NOT NULL,
    `pct_kes_perusahaan` DECIMAL(8, 4) NOT NULL DEFAULT 0.0000,
    `pct_kes_karyawan` DECIMAL(8, 4) NOT NULL DEFAULT 0.0000,
    `pct_jht_perusahaan` DECIMAL(8, 4) NOT NULL DEFAULT 0.0000,
    `pct_jp_perusahaan` DECIMAL(8, 4) NOT NULL DEFAULT 0.0000,
    `pct_jkk_perusahaan` DECIMAL(8, 4) NOT NULL DEFAULT 0.0000,
    `pct_jkm_perusahaan` DECIMAL(8, 4) NOT NULL DEFAULT 0.0000,
    `pct_jht_karyawan` DECIMAL(8, 4) NOT NULL DEFAULT 0.0000,
    `pct_jp_karyawan` DECIMAL(8, 4) NOT NULL DEFAULT 0.0000,
    `status` CHAR(1) NOT NULL DEFAULT 'A',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data awal (opsional; hapus blok ini jika tabel sudah berisi)
INSERT INTO `tbl_bpjs_kategori` (
    `nama_kategori`,
    `pct_kes_perusahaan`,
    `pct_kes_karyawan`,
    `pct_jht_perusahaan`,
    `pct_jp_perusahaan`,
    `pct_jkk_perusahaan`,
    `pct_jkm_perusahaan`,
    `pct_jht_karyawan`,
    `pct_jp_karyawan`,
    `status`
) VALUES
    ('Leader Outlet (dengan JHT)', 4, 1, 3.7, 0, 0.54, 0.3, 2, 0, 'A'),
    ('Crew (tanpa JHT)', 4, 1, 0, 0, 0.54, 0.3, 0, 0, 'A'),
    ('HO (JHT + JP)', 4, 1, 3.7, 2, 0.54, 0.3, 2, 1, 'A');
