-- Cara membatalkan process yang stuck
-- Jalankan query ini untuk melihat semua process yang sedang berjalan

-- 1. Lihat semua process yang sedang berjalan
SHOW PROCESSLIST;

-- 2. Cari process yang stuck (biasanya yang lama sekali, atau yang membuat index)
-- Lihat kolom "Time" - jika lebih dari 60 detik, kemungkinan stuck

-- 3. Kill process yang stuck (ganti [PROCESS_ID] dengan ID dari SHOW PROCESSLIST)
-- Contoh: jika process ID = 847523, gunakan:
-- KILL 847523;

-- 4. Atau kill semua process yang membuat index (jika ada)
-- KILL QUERY [PROCESS_ID];

-- CATATAN:
-- - Jangan gunakan kurung siku [ ] di MySQL
-- - Langsung tulis angka process ID
-- - Contoh: KILL 847523; (BENAR)
-- - Contoh: KILL [847523]; (SALAH - akan error)

