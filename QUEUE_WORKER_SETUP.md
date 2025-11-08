# Queue Worker Setup Guide

## 📋 Perbedaan Queue Worker vs Cron Job

### Queue Worker (Long-Running Process)
- **Fungsi**: Memproses jobs dari antrian secara terus-menerus
- **Cara kerja**: Berjalan terus (tidak pernah berhenti) dan menunggu job masuk
- **Contoh**: Stock Cut, Email sending, Background processing
- **Command**: `php artisan queue:work`

### Cron Job (Scheduled Tasks)
- **Fungsi**: Menjalankan task pada waktu tertentu secara periodik
- **Cara kerja**: Dijalankan setiap menit, lalu cek apakah ada task yang harus dijalankan
- **Contoh**: Daily report, Monthly credit, Cleanup logs
- **Command**: `php artisan schedule:run` (dijalankan setiap menit via cron)

---

## 🚀 Setup Queue Worker

### Untuk Windows (Development/Production)

#### Opsi 1: Windows Task Scheduler (Recommended untuk Production)

1. **Buka Task Scheduler**
   - Windows + R → ketik `taskschd.msc`

2. **Create Task**
   - Klik "Create Task" (bukan "Create Basic Task")

3. **General Tab**
   - Name: `Laravel Queue Worker - Stock Cut`
   - Description: `Process stock cut jobs from queue`
   - ✅ Check: "Run whether user is logged on or not"
   - ✅ Check: "Run with highest privileges"

4. **Triggers Tab**
   - Click "New"
   - Begin the task: "At startup"
   - ✅ Check: "Enabled"
   - Click OK

5. **Actions Tab**
   - Click "New"
   - Action: "Start a program"
   - Program/script: `C:\php\php.exe` (sesuaikan path PHP Anda)
   - Add arguments: `artisan queue:work --queue=stock-cut --tries=3 --timeout=300 --sleep=3 --max-jobs=1000 --max-time=3600`
   - Start in: `D:\Gawean\YM\web\ymsofterp`
   - Click OK

6. **Conditions Tab**
   - Uncheck: "Start the task only if the computer is on AC power"
   - ✅ Check: "Start the task only if the following network connection is available" (optional)

7. **Settings Tab**
   - ✅ Check: "Allow task to be run on demand"
   - ✅ Check: "Run task as soon as possible after a scheduled start is missed"
   - ✅ Check: "If the task fails, restart every: 1 minute"
   - Maximum number of restart attempts: 10

8. **Save**
   - Click OK
   - Masukkan password user Windows jika diminta

**Parameter Queue Worker:**
- `--queue=stock-cut`: Hanya proses queue "stock-cut"
- `--tries=3`: Retry maksimal 3 kali jika gagal
- `--timeout=300`: Timeout 5 menit per job
- `--sleep=3`: Tunggu 3 detik jika tidak ada job
- `--max-jobs=1000`: Restart setelah 1000 jobs (prevent memory leak)
- `--max-time=3600`: Restart setelah 1 jam (prevent memory leak)

#### Opsi 2: NSSM (Non-Sucking Service Manager) - Recommended untuk Production

1. **Download NSSM**
   - Download dari: https://nssm.cc/download
   - Extract ke folder, misal: `C:\nssm`

2. **Install Service**
   ```cmd
   cd C:\nssm\win64
   nssm install LaravelQueueWorker
   ```

3. **Configure Service**
   - Application tab:
     - Path: `C:\php\php.exe` (sesuaikan path PHP)
     - Startup directory: `D:\Gawean\YM\web\ymsofterp`
     - Arguments: `artisan queue:work --queue=stock-cut --tries=3 --timeout=300 --sleep=3 --max-jobs=1000 --max-time=3600`
   
   - Details tab:
     - Display name: `Laravel Queue Worker`
     - Description: `Process Laravel queue jobs`
   
   - Logging tab:
     - Output: `D:\Gawean\YM\web\ymsofterp\storage\logs\queue-worker.log`
     - Error: `D:\Gawean\YM\web\ymsofterp\storage\logs\queue-worker-error.log`

4. **Start Service**
   ```cmd
   nssm start LaravelQueueWorker
   ```

5. **Manage Service**
   ```cmd
   nssm restart LaravelQueueWorker
   nssm stop LaravelQueueWorker
   nssm status LaravelQueueWorker
   ```

#### Opsi 3: Manual (Development Only)

Jalankan di terminal/PowerShell:
```powershell
cd D:\Gawean\YM\web\ymsofterp
php artisan queue:work --queue=stock-cut --tries=3 --timeout=300
```

**⚠️ Catatan**: Terminal harus tetap terbuka. Jika ditutup, queue worker akan berhenti.

---

### Untuk Linux/Ubuntu (Production)

#### Opsi 1: Supervisor (Recommended)

1. **Install Supervisor**
   ```bash
   sudo apt-get update
   sudo apt-get install supervisor
   ```

2. **Create Config File**
   ```bash
   sudo nano /etc/supervisor/conf.d/laravel-queue-worker.conf
   ```

3. **Add Configuration**
   ```ini
   [program:laravel-queue-worker]
   process_name=%(program_name)s_%(process_num)02d
   command=php /var/www/ymsofterp/artisan queue:work --queue=stock-cut --tries=3 --timeout=300 --sleep=3 --max-jobs=1000 --max-time=3600
   autostart=true
   autorestart=true
   stopasgroup=true
   killasgroup=true
   user=www-data
   numprocs=1
   redirect_stderr=true
   stdout_logfile=/var/www/ymsofterp/storage/logs/queue-worker.log
   stopwaitsecs=3600
   ```

4. **Reload Supervisor**
   ```bash
   sudo supervisorctl reread
   sudo supervisorctl update
   sudo supervisorctl start laravel-queue-worker:*
   ```

5. **Check Status**
   ```bash
   sudo supervisorctl status
   ```

#### Opsi 2: Systemd Service

1. **Create Service File**
   ```bash
   sudo nano /etc/systemd/system/laravel-queue-worker.service
   ```

2. **Add Configuration**
   ```ini
   [Unit]
   Description=Laravel Queue Worker
   After=network.target

   [Service]
   Type=simple
   User=www-data
   WorkingDirectory=/var/www/ymsofterp
   ExecStart=/usr/bin/php artisan queue:work --queue=stock-cut --tries=3 --timeout=300 --sleep=3 --max-jobs=1000 --max-time=3600
   Restart=always
   RestartSec=3

   [Install]
   WantedBy=multi-user.target
   ```

3. **Enable & Start Service**
   ```bash
   sudo systemctl daemon-reload
   sudo systemctl enable laravel-queue-worker
   sudo systemctl start laravel-queue-worker
   sudo systemctl status laravel-queue-worker
   ```

---

## 🔍 Monitoring Queue Worker

### Cek Status Queue

```bash
# Cek jobs di queue
php artisan queue:monitor

# Cek failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

### Cek Database

```sql
-- Cek jobs yang sedang diproses
SELECT * FROM jobs WHERE reserved_at IS NOT NULL;

-- Cek jobs yang menunggu
SELECT * FROM jobs WHERE reserved_at IS NULL;

-- Cek failed jobs
SELECT * FROM failed_jobs ORDER BY failed_at DESC LIMIT 10;
```

### Cek Logs

```bash
# Windows
type storage\logs\queue-worker.log

# Linux
tail -f storage/logs/queue-worker.log
```

---

## ⚙️ Konfigurasi Environment

Pastikan di `.env`:
```env
QUEUE_CONNECTION=database
```

---

## 🛠️ Troubleshooting

### Queue Worker Tidak Berjalan

1. **Cek apakah service/task berjalan**
   - Windows: Task Scheduler → Check task status
   - Linux: `sudo supervisorctl status` atau `sudo systemctl status laravel-queue-worker`

2. **Cek log error**
   - Windows: `storage\logs\queue-worker-error.log`
   - Linux: `storage/logs/laravel.log`

3. **Cek permission**
   - Pastikan user memiliki akses ke folder project
   - Pastikan user memiliki akses ke database

### Jobs Stuck di Queue

1. **Restart queue worker**
   ```bash
   # Windows (via Task Scheduler atau NSSM)
   # Linux
   sudo supervisorctl restart laravel-queue-worker:*
   ```

2. **Clear stuck jobs**
   ```bash
   php artisan queue:flush
   ```

3. **Retry failed jobs**
   ```bash
   php artisan queue:retry all
   ```

### Memory Leak

Queue worker akan otomatis restart setelah:
- `--max-jobs=1000` jobs diproses
- `--max-time=3600` detik (1 jam)

Ini mencegah memory leak.

---

## 📝 Summary

**Untuk Stock Cut:**
- ✅ **Queue Worker**: Harus berjalan terus (pakai Task Scheduler/NSSM/Supervisor)
- ❌ **Bukan Cron Job**: Queue worker bukan cron job

**Untuk Scheduled Tasks (di Kernel.php):**
- ✅ **Cron Job**: Dijalankan setiap menit via cron
- Command: `* * * * * cd /path && php artisan schedule:run`

**Keduanya berbeda dan keduanya diperlukan!**

