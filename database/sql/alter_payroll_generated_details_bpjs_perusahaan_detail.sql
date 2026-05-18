-- Simpan JSON rincian iuran BPJS perusahaan saat generate payroll (informasi saja, tidak mengurangi THP)
-- Setara: database/migrations/2026_05_18_000003_add_bpjs_perusahaan_detail_to_payroll_generated_details.php
-- Jalankan sekali di MySQL (pastikan tabel payroll_generated_details sudah ada)

ALTER TABLE `payroll_generated_details`
    ADD COLUMN `bpjs_perusahaan_detail` LONGTEXT NULL DEFAULT NULL
    AFTER `bpjs_tk`;

-- Rollback (jika perlu):
-- ALTER TABLE `payroll_generated_details` DROP COLUMN `bpjs_perusahaan_detail`;
