# Setup Queue untuk Member Notification - Panduan Lengkap

## 📋 Langkah-langkah Setup

### 1. Buat Table Queue di Database

Jalankan query SQL berikut di database MySQL:

**File:** `database/sql/setup_notification_queue.sql`

Atau jalankan query ini langsung di phpMyAdmin/MySQL:

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

Pastikan di file `.env` ada setting berikut:

```env
QUEUE_CONNECTION=database
```

Jika belum ada, tambahkan baris di atas ke file `.env`.

### 3. Jalankan Queue Worker

#### Opsi A: Menggunakan Script Batch (Windows - Paling Mudah)

1. Double-click file: `start_notification_queue.bat`
2. Biarkan terminal terbuka (jangan ditutup)
3. Queue worker akan berjalan terus dan memproses notifikasi

#### Opsi B: Manual via Command Prompt

1. Buka Command Prompt
2. Masuk ke folder project:
   ```
   cd D:\Gawean\web\ymsofterp
   ```
3. Jalankan queue worker:
   ```
   php artisan queue:work --queue=notifications --tries=3 --timeout=300
   ```

#### Opsi C: Windows Task Scheduler (Untuk Production)

1. Buka Task Scheduler (Windows + R → `taskschd.msc`)
2. Create Task (bukan Create Basic Task)
3. **General Tab:**
   - Name: `Laravel Queue Worker - Notifications`
   - ✅ Run whether user is logged on or not
   - ✅ Run with highest privileges
4. **Triggers Tab:**
   - New → Begin the task: "At startup" → Enabled
5. **Actions Tab:**
   - New → Start a program
   - Program: `C:\php\php.exe` (sesuaikan path PHP Anda)
   - Arguments: `artisan queue:work --queue=notifications --tries=3 --timeout=300 --sleep=3 --max-jobs=1000 --max-time=3600`
   - Start in: `D:\Gawean\web\ymsofterp`
6. **Settings Tab:**
   - ✅ Allow task to be run on demand
   - ✅ Run task as soon as possible after a scheduled start is missed
   - ✅ If the task fails, restart every: 1 minute
   - ✅ Stop the task if it runs longer than: 1 hour (opsional)

## ✅ Verifikasi Setup

### Cek Table Queue
Jalankan query ini di database:

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

### Cek Queue Worker Berjalan
1. Buka halaman "Kirim Notifikasi Member"
2. Kirim notifikasi ke 1000+ member
3. Cek di halaman detail notifikasi, status akan update secara bertahap

### Cek Jobs di Queue
Jalankan command:
```
php artisan queue:monitor
```

## 🔍 Monitoring

### Cek Status Queue
```bash
# Cek jobs yang sedang diproses
php artisan queue:monitor

# Cek failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

### Cek Log
- Log queue worker: `storage/logs/laravel.log`
- Cek error di halaman detail notifikasi

## ⚠️ Troubleshooting

### Queue tidak jalan
1. Pastikan queue worker sedang berjalan (cek Task Manager atau terminal)
2. Pastikan `QUEUE_CONNECTION=database` di `.env`
3. Pastikan table `jobs` sudah dibuat
4. Restart queue worker: `php artisan queue:restart`

### Notifikasi tidak terkirim
1. Cek error message di halaman detail notifikasi
2. Cek failed jobs: `php artisan queue:failed`
3. Cek log: `storage/logs/laravel.log`

### Jobs stuck di queue
1. Restart queue worker
2. Clear stuck jobs:
   ```bash
   php artisan queue:flush
   php artisan queue:restart
   ```

## 📝 Catatan Penting

- **Queue worker HARUS berjalan terus** untuk memproses notifikasi
- Jika queue worker berhenti, notifikasi akan menunggu di queue sampai worker jalan lagi
- Untuk production, gunakan Windows Task Scheduler atau NSSM agar queue worker auto-start
- Notifikasi < 1000 member akan diproses langsung (tidak pakai queue)
- Notifikasi ≥ 1000 member akan diproses via queue (background)

