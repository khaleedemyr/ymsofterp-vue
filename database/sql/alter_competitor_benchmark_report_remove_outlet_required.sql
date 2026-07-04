-- Jalankan jika tabel sudah dibuat dengan outlet wajib
ALTER TABLE `competitor_benchmark_reports`
    MODIFY COLUMN `outlet_id` INT UNSIGNED NULL,
    MODIFY COLUMN `outlet_name` VARCHAR(255) NULL;
