# Setup Queue untuk Member Notification di cPanel

## 📋 Langkah-langkah Setup di cPanel

### 1. Buat Table Queue di Database

1. Login ke **cPanel**
2. Buka **phpMyAdmin**
3. Pilih database yang digunakan aplikasi
4. Klik tab **SQL**
5. Copy-paste query dari file `database/sql/setup_notification_queue.sql`
6. Klik **Go** untuk menjalankan query

**Atau** jalankan query ini langsung:

```sql
-- Table jobs
CREATE TABLE IF NOT EXISTS `jobs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `queue` VARCHAR(255) NOT NULL,
    `payload` LONGTEXT NOT NULL,
    `attempts` TINYINT UNSIGNED NOT NULL,
    `reserved_at` INT UNSIGNED NULL DEFAULT NULL,
    `available_at` INT UNSIGNED NOT NULL,
    `created_at` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_jobs_queue` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table failed_jobs
CREATE TABLE IF NOT EXISTS `failed_jobs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid` VARCHAR(255) NOT NULL,
    `connection` TEXT NOT NULL,
    `queue` TEXT NOT NULL,
    `payload` LONGTEXT NOT NULL,
    `exception` LONGTEXT NOT NULL,
    `failed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table job_batches
CREATE TABLE IF NOT EXISTS `job_batches` (
    `id` VARCHAR(255) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `total_jobs` INT NOT NULL,
    `pending_jobs` INT NOT NULL,
    `failed_jobs` INT NOT NULL,
    `failed_job_ids` LONGTEXT NOT NULL,
    `options` MEDIUMTEXT NULL DEFAULT NULL,
    `cancelled_at` INT NULL DEFAULT NULL,
    `created_at` INT NOT NULL,
    `finished_at` INT NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2. Pastikan Konfigurasi Queue di .env

1. Login ke **cPanel File Manager**
2. Buka file `.env` di root folder aplikasi
3. Pastikan ada baris:
   ```env
   QUEUE_CONNECTION=database
   ```
4. Jika belum ada, tambahkan baris tersebut
5. Simpan file

### 3. Setup Cron Job di cPanel

#### Cara 1: Menggunakan cPanel Cron Jobs (Recommended)

1. Login ke **cPanel**
2. Buka menu **Cron Jobs**
3. Pilih **Standard (cPanel v82+)** atau **Advanced (Unix Style)**
4. Tambahkan cron job baru dengan setting berikut:

**Untuk Standard (cPanel v82+):**
- **Minute:** `*`
- **Hour:** `*`
- **Day:** `*`
- **Month:** `*`
- **Weekday:** `*`
- **Command:** 
  ```
  cd /home/username/public_html/ymsofterp && /usr/bin/php artisan queue:work --queue=notifications --tries=3 --timeout=300 --sleep=3 --max-jobs=1000 --max-time=3600 --stop-when-empty
  ```
  *(Ganti `/home/username/public_html/ymsofterp` dengan path folder aplikasi Anda)*

**Untuk Advanced (Unix Style):**
- **Common Settings:** Pilih "Every Minute"
- **Command:**
  ```
  cd /home/username/public_html/ymsofterp && /usr/bin/php artisan queue:work --queue=notifications --tries=3 --timeout=300 --sleep=3 --max-jobs=1000 --max-time=3600 --stop-when-empty
  ```

**Catatan:** 
- Ganti `/home/username/public_html/ymsofterp` dengan path lengkap folder aplikasi Anda
- Path PHP biasanya `/usr/bin/php` atau `/opt/cpanel/ea-php81/root/usr/bin/php` (sesuaikan versi PHP)
- Untuk cek path PHP, jalankan: `which php` via SSH atau cek di cPanel → Select PHP Version

#### Cara 2: Menggunakan Shell Script (Lebih Stabil)

1. Buat file `queue-worker.sh` di root folder aplikasi via File Manager
2. Isi dengan:
   ```bash
   #!/bin/bash
   cd /home/username/public_html/ymsofterp
   /usr/bin/php artisan queue:work --queue=notifications --tries=3 --timeout=300 --sleep=3 --max-jobs=1000 --max-time=3600 --stop-when-empty
   ```
   *(Ganti path sesuai dengan folder aplikasi Anda)*

3. Set permission file menjadi `755` (executable)
   - Klik kanan file → Change Permissions → Centang "Execute" untuk Owner, Group, dan Public

4. Setup cron job di cPanel:
   - **Command:** 
     ```
     /home/username/public_html/ymsofterp/queue-worker.sh
     ```
   - **Schedule:** Every Minute (`* * * * *`)

### 4. Verifikasi Setup

#### Cek Table Queue
Jalankan query ini di phpMyAdmin:
```sql
SELECT 
    'jobs' as table_name,
    COUNT(*) as row_count
FROM `jobs`
UNION ALL
SELECT 
    'failed_jobs' as table_name,
    COUNT(*) as row_count
FROM `failed_jobs`;
```

#### Cek Cron Job Berjalan
1. Login ke cPanel
2. Buka **Cron Jobs**
3. Cek log cron job (jika ada)
4. Atau cek di **Email** → cek email dari cron job (jika ada error)

#### Test Notifikasi
1. Buka halaman "Kirim Notifikasi Member"
2. Kirim notifikasi ke 1000+ member
3. Cek di halaman detail notifikasi, status akan update secara bertahap
4. Cek table `jobs` di database, seharusnya ada jobs yang diproses

## 🔍 Monitoring

### Cek Status Queue via SSH (jika ada akses SSH)

```bash
# Masuk ke folder aplikasi
cd /home/username/public_html/ymsofterp

# Cek jobs di queue
php artisan queue:monitor

# Cek failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

### Cek via Database

Jalankan query di phpMyAdmin:
```sql
-- Cek jobs yang sedang diproses
SELECT * FROM `jobs` WHERE `queue` = 'notifications' ORDER BY `id` DESC LIMIT 10;

-- Cek failed jobs
SELECT * FROM `failed_jobs` ORDER BY `failed_at` DESC LIMIT 10;

-- Cek jumlah jobs per queue
SELECT `queue`, COUNT(*) as total FROM `jobs` GROUP BY `queue`;
```

### Cek Log

1. Buka **cPanel File Manager**
2. Buka folder `storage/logs`
3. Buka file `laravel.log`
4. Cari error terkait queue

## ⚠️ Troubleshooting

### Queue tidak jalan

1. **Cek cron job aktif:**
   - Login cPanel → Cron Jobs
   - Pastikan cron job enabled dan schedule benar

2. **Cek path PHP:**
   - Login cPanel → Select PHP Version
   - Catat path PHP yang digunakan
   - Update command cron job dengan path PHP yang benar

3. **Cek path aplikasi:**
   - Pastikan path di cron job sesuai dengan folder aplikasi
   - Untuk cek path, buka File Manager dan lihat path di address bar

4. **Cek permission file:**
   - Pastikan file `artisan` memiliki permission executable (755)
   - File Manager → Klik kanan `artisan` → Change Permissions → 755

5. **Cek .env:**
   - Pastikan `QUEUE_CONNECTION=database` ada di `.env`

### Notifikasi tidak terkirim

1. **Cek failed jobs:**
   - Via SSH: `php artisan queue:failed`
   - Via database: Query `SELECT * FROM failed_jobs`

2. **Cek error di log:**
   - File Manager → `storage/logs/laravel.log`

3. **Cek FCM API key:**
   - Pastikan FCM API key sudah dikonfigurasi di `.env`

### Jobs stuck di queue

1. **Restart queue:**
   - Via SSH: `php artisan queue:restart`
   - Atau tunggu cron job berikutnya (akan auto-restart)

2. **Clear stuck jobs:**
   - Via SSH: 
     ```bash
     php artisan queue:flush
     php artisan queue:restart
     ```

## 📝 Catatan Penting

- **Cron job akan berjalan setiap menit** dan memproses jobs yang ada di queue
- Jika tidak ada job, cron akan selesai dengan cepat (karena `--stop-when-empty`)
- Untuk production, pastikan cron job berjalan setiap menit
- Notifikasi < 1000 member akan diproses langsung (tidak pakai queue)
- Notifikasi ≥ 1000 member akan diproses via queue (background)
- Queue worker akan auto-restart setiap jam (karena `--max-time=3600`)

## 🔧 Alternatif: Long-Running Process (Jika cPanel Support)

Jika hosting Anda support long-running process (via SSH atau terminal), Anda bisa menjalankan queue worker secara terus-menerus:

```bash
# Via SSH
cd /home/username/public_html/ymsofterp
nohup php artisan queue:work --queue=notifications --tries=3 --timeout=300 --sleep=3 --max-jobs=1000 --max-time=3600 > storage/logs/queue-worker.log 2>&1 &
```

Ini akan menjalankan queue worker di background dan akan terus berjalan sampai server restart.

