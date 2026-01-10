-- ============================================
-- Query untuk Membuat User MySQL (Akses Jaringan)
-- ============================================

-- ============================================
-- CONTOH CEPAT: Akses dari SEMUA HOST
-- ============================================
-- Ganti: username, password123, database_name sesuai kebutuhan
-- CATATAN: '%' berarti bisa akses dari IP manapun di jaringan

-- 1. Buat user untuk akses dari semua host
CREATE USER 'username'@'%' IDENTIFIED BY 'password123';

-- 2. Berikan privileges untuk database tertentu
GRANT ALL PRIVILEGES ON database_name.* TO 'username'@'%';

-- 3. Flush privileges (WAJIB!)
FLUSH PRIVILEGES;

-- ============================================
-- CONTOH ALTERNATIF: Akses dari subnet tertentu (LEBIH AMAN)
-- ============================================
-- Hanya bisa akses dari IP 192.168.1.0 - 192.168.1.255
-- CREATE USER 'username'@'192.168.1.%' IDENTIFIED BY 'password123';
-- GRANT ALL PRIVILEGES ON database_name.* TO 'username'@'192.168.1.%';
-- FLUSH PRIVILEGES;

-- ============================================
-- VARIASI CREATE USER
-- ============================================

-- 1. CREATE USER untuk akses dari SEMUA HOST di jaringan (PALING UMUM)
-- Format: CREATE USER 'username'@'%' IDENTIFIED BY 'password';
-- CATATAN: '%' berarti bisa akses dari IP manapun (hati-hati untuk production!)
-- Gunakan ini jika aplikasi/client bisa akses dari berbagai IP
CREATE USER 'username'@'%' IDENTIFIED BY 'password123';

-- 2. CREATE USER untuk akses dari subnet tertentu (LEBIH AMAN)
-- Contoh: Hanya bisa akses dari IP 192.168.1.0 - 192.168.1.255
CREATE USER 'username'@'192.168.1.%' IDENTIFIED BY 'password123';

-- 3. CREATE USER untuk akses dari IP spesifik (PALING AMAN)
-- Contoh: Hanya bisa akses dari IP 192.168.1.100
CREATE USER 'username'@'192.168.1.100' IDENTIFIED BY 'password123';

-- 4. CREATE USER untuk akses dari beberapa subnet
-- Contoh: Bisa akses dari 192.168.1.x dan 10.0.0.x
CREATE USER 'username'@'192.168.1.%' IDENTIFIED BY 'password123';
CREATE USER 'username'@'10.0.0.%' IDENTIFIED BY 'password123';

-- 5. CREATE USER dengan mysql_native_password (untuk kompatibilitas)
-- Berguna jika aplikasi belum support caching_sha2_password
CREATE USER 'username'@'%' IDENTIFIED WITH mysql_native_password BY 'password123';

-- 6. CREATE USER dengan caching_sha2_password (default MySQL 8.0+)
CREATE USER 'username'@'%' IDENTIFIED WITH caching_sha2_password BY 'password123';

-- ============================================
-- GRANT PRIVILEGES (setelah user dibuat)
-- ============================================

-- 7. Grant ALL PRIVILEGES untuk database tertentu (akses jaringan)
GRANT ALL PRIVILEGES ON database_name.* TO 'username'@'%';

-- 8. Grant SELECT, INSERT, UPDATE, DELETE untuk database tertentu
GRANT SELECT, INSERT, UPDATE, DELETE ON database_name.* TO 'username'@'%';

-- 9. Grant SELECT saja (read-only)
GRANT SELECT ON database_name.* TO 'username'@'%';

-- 10. Grant untuk semua database (HATI-HATI! Sangat berbahaya untuk akses jaringan)
GRANT ALL PRIVILEGES ON *.* TO 'username'@'%';

-- 11. Grant untuk subnet tertentu (lebih aman)
GRANT ALL PRIVILEGES ON database_name.* TO 'username'@'192.168.1.%';

-- 12. Grant untuk tabel tertentu saja
GRANT SELECT, INSERT, UPDATE ON database_name.table_name TO 'username'@'%';

-- 13. Grant dengan WITH GRANT OPTION (bisa memberikan privileges ke user lain)
GRANT ALL PRIVILEGES ON database_name.* TO 'username'@'%' WITH GRANT OPTION;

-- ============================================
-- FLUSH PRIVILEGES (wajib setelah GRANT)
-- ============================================
FLUSH PRIVILEGES;

-- ============================================
-- CONTOH LENGKAP: Membuat user untuk aplikasi Laravel (Akses Jaringan)
-- ============================================
-- 1. Buat user (bisa akses dari semua host di jaringan)
CREATE USER 'laravel_user'@'%' IDENTIFIED BY 'strong_password_here';

-- 2. Berikan privileges untuk database aplikasi
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER, CREATE TEMPORARY TABLES, LOCK TABLES 
ON laravel_db.* TO 'laravel_user'@'%';

-- 3. Flush privileges
FLUSH PRIVILEGES;

-- ============================================
-- CONTOH: User untuk aplikasi dengan subnet tertentu (LEBIH AMAN)
-- ============================================
-- Hanya bisa akses dari IP 192.168.1.x
CREATE USER 'app_user'@'192.168.1.%' IDENTIFIED BY 'strong_password_here';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER, CREATE TEMPORARY TABLES, LOCK TABLES 
ON app_database.* TO 'app_user'@'192.168.1.%';
FLUSH PRIVILEGES;

-- ============================================
-- CONTOH: User untuk backup/read-only (akses jaringan)
-- ============================================
CREATE USER 'backup_user'@'192.168.1.%' IDENTIFIED BY 'backup_password_here';
GRANT SELECT, LOCK TABLES, SHOW VIEW, EVENT, TRIGGER ON database_name.* TO 'backup_user'@'192.168.1.%';
FLUSH PRIVILEGES;

-- ============================================
-- QUERY UNTUK MELIHAT USER YANG ADA
-- ============================================
-- Lihat semua user
SELECT User, Host FROM mysql.user;

-- Lihat user yang bisa akses dari jaringan (bukan localhost)
SELECT User, Host FROM mysql.user WHERE Host != 'localhost';

-- Lihat privileges user tertentu (akses jaringan)
SHOW GRANTS FOR 'username'@'%';
SHOW GRANTS FOR 'username'@'192.168.1.%';

-- ============================================
-- QUERY UNTUK MENGUBAH PASSWORD USER
-- ============================================
ALTER USER 'username'@'%' IDENTIFIED BY 'new_password_here';
FLUSH PRIVILEGES;

-- Atau untuk subnet tertentu
ALTER USER 'username'@'192.168.1.%' IDENTIFIED BY 'new_password_here';
FLUSH PRIVILEGES;

-- Atau untuk MySQL versi lama:
-- SET PASSWORD FOR 'username'@'%' = PASSWORD('new_password_here');

-- ============================================
-- QUERY UNTUK MENGHAPUS USER
-- ============================================
-- Hapus user untuk akses jaringan
DROP USER 'username'@'%';

-- Hapus user untuk subnet tertentu
DROP USER 'username'@'192.168.1.%';

-- Hapus semua host untuk user tertentu (jika ada beberapa)
DROP USER IF EXISTS 'username'@'%';
DROP USER IF EXISTS 'username'@'192.168.1.%';
DROP USER IF EXISTS 'username'@'localhost';

-- ============================================
-- QUERY UNTUK REVOKE PRIVILEGES
-- ============================================
-- Hapus semua privileges (akses jaringan)
REVOKE ALL PRIVILEGES ON database_name.* FROM 'username'@'%';

-- Hapus privileges tertentu
REVOKE INSERT, UPDATE ON database_name.* FROM 'username'@'%';

-- Atau untuk subnet tertentu
REVOKE ALL PRIVILEGES ON database_name.* FROM 'username'@'192.168.1.%';

FLUSH PRIVILEGES;

-- ============================================
-- PENTING: KONFIGURASI MySQL SERVER UNTUK AKSES JARINGAN
-- ============================================
-- 1. Edit file my.cnf atau my.ini
--    Cari baris: bind-address = 127.0.0.1
--    Ubah menjadi: bind-address = 0.0.0.0
--    Atau comment dengan: # bind-address = 127.0.0.1
--
-- 2. Restart MySQL service setelah perubahan
--    Windows: net stop MySQL80 && net start MySQL80
--    Linux: systemctl restart mysql
--
-- 3. Pastikan firewall mengizinkan port 3306
--    Windows Firewall: Allow port 3306
--    Linux: ufw allow 3306/tcp atau iptables
--
-- 4. Untuk keamanan, gunakan subnet tertentu, bukan '%'
--    Contoh: 'username'@'192.168.1.%' lebih aman dari 'username'@'%'
