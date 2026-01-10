-- ============================================
-- Query untuk Membuat User MySQL - Akses SEMUA HOST
-- ============================================
-- File ini khusus untuk membuat user yang bisa akses dari IP manapun
-- Ganti: username, password123, database_name sesuai kebutuhan Anda

-- ============================================
-- LANGKAH 1: Buat User
-- ============================================
CREATE USER 'username'@'%' IDENTIFIED BY 'password123';

-- ============================================
-- LANGKAH 2: Berikan Privileges
-- ============================================
-- Pilih salah satu sesuai kebutuhan:

-- Opsi A: ALL PRIVILEGES untuk database tertentu (RECOMMENDED)
GRANT ALL PRIVILEGES ON database_name.* TO 'username'@'%';

-- Opsi B: Hanya SELECT, INSERT, UPDATE, DELETE (lebih terbatas)
-- GRANT SELECT, INSERT, UPDATE, DELETE ON database_name.* TO 'username'@'%';

-- Opsi C: Hanya SELECT (read-only)
-- GRANT SELECT ON database_name.* TO 'username'@'%';

-- Opsi D: Semua database (HATI-HATI! Sangat berbahaya)
-- GRANT ALL PRIVILEGES ON *.* TO 'username'@'%';

-- ============================================
-- LANGKAH 3: Flush Privileges (WAJIB!)
-- ============================================
FLUSH PRIVILEGES;

-- ============================================
-- CONTOH LENGKAP: User untuk Aplikasi Laravel
-- ============================================
-- Uncomment dan sesuaikan jika perlu:

-- CREATE USER 'laravel_app'@'%' IDENTIFIED BY 'your_strong_password_here';
-- GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER, 
--      CREATE TEMPORARY TABLES, LOCK TABLES 
-- ON your_database_name.* TO 'laravel_app'@'%';
-- FLUSH PRIVILEGES;

-- ============================================
-- VERIFIKASI: Cek user yang sudah dibuat
-- ============================================
-- Jalankan query ini untuk melihat user:
-- SELECT User, Host FROM mysql.user WHERE User = 'username';

-- Lihat privileges user:
-- SHOW GRANTS FOR 'username'@'%';

-- ============================================
-- CATATAN PENTING:
-- ============================================
-- 1. Pastikan MySQL server dikonfigurasi untuk menerima koneksi dari jaringan:
--    - Edit my.cnf atau my.ini
--    - Cari: bind-address = 127.0.0.1
--    - Ubah menjadi: bind-address = 0.0.0.0
--    - Restart MySQL service
--
-- 2. Pastikan firewall mengizinkan port 3306:
--    Windows: Allow port 3306 di Windows Firewall
--    Linux: ufw allow 3306/tcp atau iptables
--
-- 3. Gunakan password yang kuat untuk keamanan!
--
-- 4. '%' berarti bisa akses dari IP manapun, gunakan dengan hati-hati
--    Untuk lebih aman, gunakan subnet tertentu seperti '192.168.1.%'
