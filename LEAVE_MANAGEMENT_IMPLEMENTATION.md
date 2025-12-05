# Sistem Manajemen Cuti Otomatis

## 🎯 Overview
Sistem ini memberikan fitur otomatis untuk mengelola cuti/annual leave karyawan dengan fitur:
- ✅ **Kredit Cuti Bulanan**: Setiap bulan semua karyawan aktif mendapat 1 hari cuti
- ✅ **Burning Cuti**: Di bulan Maret, sisa cuti tahun sebelumnya dihapus
- ✅ **Tracking History**: Semua transaksi cuti tercatat dengan detail
- ✅ **Manual Management**: Admin bisa menyesuaikan dan mengelola cuti manual

## 📋 File yang Dibuat

### 1. Database
```sql
-- Jalankan script ini untuk membuat tabel transaksi cuti
-- File: create_leave_transactions_table.sql
```

### 2. Backend Files
- `app/Models/LeaveTransaction.php` - Model untuk transaksi cuti
- `app/Services/LeaveManagementService.php` - Service utama untuk logika cuti
- `app/Http/Controllers/LeaveManagementController.php` - Controller untuk API
- `app/Console/Commands/ProcessMonthlyLeave.php` - Command untuk kredit bulanan
- `app/Console/Commands/BurnPreviousYearLeave.php` - Command untuk burning

### 3. Frontend Files
- `resources/js/Pages/LeaveManagement/Index.vue` - Halaman manajemen cuti

### 4. Configuration Files
- `add_leave_management_routes.php` - Routes untuk leave management
- `add_leave_scheduler.php` - Scheduler configuration

## 🚀 Implementasi Step by Step

### Step 1: Database Setup
```sql
-- 1. Jalankan script untuk membuat tabel leave_transactions
-- File: create_leave_transactions_table.sql

-- 2. Pastikan field 'cuti' sudah ada di tabel users (sudah ada)
-- Field ini akan menyimpan saldo cuti real-time
```

### Step 2: Backend Setup
```bash
# 1. Copy semua file backend ke direktori yang sesuai
# 2. Register commands di app/Console/Kernel.php
```

### Step 3: Routes Setup
```php
// Tambahkan ke routes/web.php
Route::middleware(['auth'])->group(function () {
    Route::prefix('leave-management')->name('leave-management.')->group(function () {
        Route::get('/', [LeaveManagementController::class, 'index'])->name('index');
        Route::get('/history/{userId}', [LeaveManagementController::class, 'showHistory'])->name('history');
        Route::post('/manual-adjustment', [LeaveManagementController::class, 'manualAdjustment'])->name('manual-adjustment');
        Route::post('/use-leave', [LeaveManagementController::class, 'useLeave'])->name('use-leave');
        Route::post('/process-monthly-credit', [LeaveManagementController::class, 'processMonthlyCredit'])->name('process-monthly-credit');
        Route::post('/process-burning', [LeaveManagementController::class, 'processBurning'])->name('process-burning');
        Route::get('/statistics', [LeaveManagementController::class, 'getStatistics'])->name('statistics');
    });
});
```

### Step 4: Scheduler Setup
```php
// Tambahkan ke app/Console/Kernel.php di method schedule()
$schedule->command('leave:monthly-credit')
    ->monthlyOn(1, '00:00')
    ->description('Memberikan cuti bulanan ke semua karyawan aktif');

$schedule->command('leave:burn-previous-year')
    ->yearlyOn(3, 1, '00:00')
    ->description('Burning sisa cuti tahun sebelumnya');
```

### Step 5: Cron Job Setup (Production)

#### A. Setup Cron Job di Server
```bash
# Edit crontab
crontab -e

# Tambahkan baris berikut untuk menjalankan Laravel Scheduler setiap menit
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1

# Contoh dengan path yang benar:
* * * * * cd /var/www/ymsofterp && php artisan schedule:run >> /dev/null 2>&1
```

#### B. Verifikasi Cron Job
```bash
# Cek apakah cron job sudah berjalan
crontab -l

# Cek log cron job
tail -f /var/log/cron

# Test scheduler manual
php artisan schedule:list
php artisan schedule:run
```

#### C. Setup Logging untuk Monitoring
```php
// Tambahkan ke app/Console/Kernel.php untuk logging
protected function schedule(Schedule $schedule)
{
    // Log semua scheduled tasks
    $schedule->command('leave:monthly-credit')
        ->monthlyOn(1, '00:00')
        ->description('Memberikan cuti bulanan ke semua karyawan aktif')
        ->appendOutputTo(storage_path('logs/leave-management.log'));

    $schedule->command('leave:burn-previous-year')
        ->yearlyOn(3, 1, '00:00')
        ->description('Burning sisa cuti tahun sebelumnya')
        ->appendOutputTo(storage_path('logs/leave-management.log'));
}
```

#### D. Alternative: Setup dengan Supervisor (Recommended)
```ini
# File: /etc/supervisor/conf.d/laravel-scheduler.conf
[program:laravel-scheduler]
process_name=%(program_name)s_%(process_num)02d
command=php /path-to-your-project/artisan schedule:work
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/path-to-your-project/storage/logs/scheduler.log
```

#### E. Setup Cron Job Step by Step

**Step 1: Cek Environment**
```bash
# Cek PHP path
which php
# Output: /usr/bin/php

# Cek Laravel project path
pwd
# Output: /var/www/ymsofterp

# Cek permission
ls -la
```

**Step 2: Edit Crontab**
```bash
# Edit crontab sebagai user yang menjalankan web server
sudo crontab -e

# Atau edit crontab untuk user www-data
sudo crontab -e -u www-data
```

**Step 3: Tambahkan Cron Job**
```bash
# Tambahkan baris berikut ke crontab
* * * * * cd /var/www/ymsofterp && /usr/bin/php artisan schedule:run >> /dev/null 2>&1

# Atau dengan logging
* * * * * cd /var/www/ymsofterp && /usr/bin/php artisan schedule:run >> /var/log/laravel-scheduler.log 2>&1
```

**Step 4: Verifikasi Setup**
```bash
# Cek crontab
crontab -l

# Test manual
cd /var/www/ymsofterp
php artisan schedule:list
php artisan schedule:run

# Cek log
tail -f /var/log/laravel-scheduler.log
```

**Step 5: Monitoring**
```bash
# Cek apakah cron job berjalan
ps aux | grep "schedule:run"

# Monitor log files
tail -f storage/logs/laravel.log
tail -f storage/logs/leave-management.log
```

### Step 5: Frontend Setup
```bash
# 1. Copy file Index.vue ke resources/js/Pages/LeaveManagement/
# 2. Pastikan route sudah terdaftar
```

## 🔧 Fitur Utama

### 1. Kredit Cuti Bulanan Otomatis
- **Jadwal**: Setiap tanggal 1 setiap bulan
- **Target**: Semua karyawan dengan status='A'
- **Jumlah**: 1 hari cuti per karyawan
- **Tracking**: Tercatat di tabel leave_transactions

### 2. Burning Cuti Tahun Sebelumnya
- **Jadwal**: Setiap tanggal 1 Maret
- **Target**: Sisa cuti tahun sebelumnya
- **Logika**: Hanya memburning sisa cuti tahun sebelumnya, bukan tahun berjalan

### 3. Manual Management
- **Penyesuaian Saldo**: Admin bisa menambah/mengurangi saldo cuti
- **Penggunaan Cuti**: Pencatatan penggunaan cuti karyawan
- **History Tracking**: Semua transaksi tercatat dengan detail

### 4. Dashboard & Statistics
- **Daftar Karyawan**: Dengan saldo cuti real-time
- **Statistik**: Total karyawan, total saldo, rata-rata saldo
- **History**: Riwayat transaksi per karyawan

## 📊 Database Schema

### Tabel: leave_transactions
```sql
- id: BIGINT PRIMARY KEY
- user_id: BIGINT (FK ke users)
- transaction_type: ENUM('monthly_credit', 'burning', 'manual_adjustment', 'leave_usage')
- year: INT
- month: INT (NULL untuk burning)
- amount: DECIMAL(5,2) (positif untuk tambah, negatif untuk kurang)
- balance_after: DECIMAL(5,2) (saldo setelah transaksi)
- description: TEXT
- created_by: BIGINT (FK ke users, NULL untuk otomatis)
- created_at: TIMESTAMP
- updated_at: TIMESTAMP
```

### Tabel: users (existing)
```sql
- cuti: DECIMAL (saldo cuti real-time)
- status: ENUM('A', 'N', 'B') (A = Active)
```

## 🎮 Command Usage

### Manual Execution
```bash
# Kredit cuti bulanan manual
php artisan leave:monthly-credit --year=2024 --month=3

# Burning cuti tahun sebelumnya manual
php artisan leave:burn-previous-year --year=2024

# Force execution (skip duplicate check)
php artisan leave:monthly-credit --force
```

### Scheduler (Otomatis)
```bash
# Pastikan scheduler berjalan
php artisan schedule:work

# Atau di production dengan cron
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## 🔍 API Endpoints

### GET /leave-management
- **Purpose**: Daftar karyawan dengan saldo cuti
- **Parameters**: search, per_page
- **Response**: Paginated list of users with leave balance

### GET /leave-management/history/{userId}
- **Purpose**: History transaksi cuti per karyawan
- **Parameters**: year (optional)
- **Response**: List of leave transactions

### POST /leave-management/manual-adjustment
- **Purpose**: Penyesuaian saldo cuti manual
- **Body**: user_id, amount, description
- **Response**: Success/error message

### POST /leave-management/use-leave
- **Purpose**: Penggunaan cuti
- **Body**: user_id, amount, description
- **Response**: Success/error message

### POST /leave-management/process-monthly-credit
- **Purpose**: Proses kredit bulanan manual
- **Body**: year, month
- **Response**: Processing result

### POST /leave-management/process-burning
- **Purpose**: Proses burning manual
- **Body**: year
- **Response**: Processing result

### GET /leave-management/statistics
- **Purpose**: Statistik cuti
- **Response**: Statistics data

## 🛡️ Security & Validation

### Input Validation
- **Amount**: Numeric, min 0.5 untuk penggunaan
- **Year**: Integer, range 2020-2030
- **Month**: Integer, range 1-12
- **Description**: Required, max 255 characters

### Authorization
- **Authentication**: Required untuk semua endpoints
- **Manual Operations**: Logged dengan created_by
- **Automatic Operations**: created_by = NULL

## 📈 Monitoring & Logging

### Log Files
- **Location**: storage/logs/laravel.log
- **Level**: INFO untuk success, ERROR untuk failures
- **Details**: User ID, amounts, timestamps, errors

### Error Handling
- **Database Transactions**: Rollback on errors
- **Duplicate Prevention**: Check existing transactions
- **User Feedback**: SweetAlert2 notifications

## 🔄 Migration Strategy

### Existing Data
- **Current Balance**: Field 'cuti' di tabel users tetap digunakan
- **History**: Transaksi baru akan tercatat di leave_transactions
- **Backward Compatibility**: Sistem lama tetap berfungsi

### Data Migration (Optional)
```sql
-- Jika ingin migrasi data existing ke leave_transactions
INSERT INTO leave_transactions (user_id, transaction_type, year, month, amount, balance_after, description, created_at)
SELECT 
    id, 
    'manual_adjustment', 
    YEAR(NOW()), 
    MONTH(NOW()), 
    cuti, 
    cuti, 
    'Initial balance migration', 
    NOW()
FROM users 
WHERE status = 'A' AND cuti > 0;
```

## 🎯 Benefits

### For HR/Admin
- ✅ **Automated Process**: Tidak perlu manual input setiap bulan
- ✅ **Complete Tracking**: Semua transaksi tercatat
- ✅ **Flexible Management**: Bisa adjust manual jika diperlukan
- ✅ **Statistics**: Dashboard untuk monitoring

### For System
- ✅ **Scalable**: Bisa handle banyak karyawan
- ✅ **Reliable**: Database transactions untuk consistency
- ✅ **Auditable**: Complete history tracking
- ✅ **Maintainable**: Clean code structure

## 🚨 Important Notes

### Production Deployment
1. **Backup Database**: Sebelum implementasi
2. **Test Commands**: Jalankan manual dulu untuk testing
3. **Monitor Logs**: Pastikan scheduler berjalan dengan baik
4. **Data Validation**: Cek saldo cuti setelah implementasi

### Maintenance
1. **Regular Monitoring**: Cek log files untuk errors
2. **Data Cleanup**: Optional cleanup untuk transaksi lama
3. **Performance**: Index pada tabel leave_transactions
4. **Backup**: Regular backup untuk data penting

## 🔧 Cron Job Troubleshooting

### Common Issues & Solutions

#### 1. Cron Job Tidak Berjalan
```bash
# Cek apakah cron service berjalan
sudo systemctl status cron
sudo systemctl start cron

# Cek permission file crontab
ls -la /var/spool/cron/crontabs/

# Test cron job manual
php artisan schedule:run
```

#### 2. Permission Issues
```bash
# Pastikan user memiliki permission untuk menjalankan PHP
sudo chown -R www-data:www-data /path-to-your-project
sudo chmod -R 755 /path-to-your-project

# Cek permission storage/logs
sudo chmod -R 775 /path-to-your-project/storage/logs
```

#### 3. Path Issues
```bash
# Gunakan absolute path di crontab
* * * * * cd /var/www/ymsofterp && /usr/bin/php artisan schedule:run >> /dev/null 2>&1

# Cek path PHP
which php
/usr/bin/php --version
```

#### 4. Logging Issues
```bash
# Cek log files
tail -f storage/logs/laravel.log
tail -f storage/logs/leave-management.log

# Cek disk space
df -h
```

#### 5. Testing Cron Job
```bash
# Test individual commands
php artisan leave:monthly-credit --year=2024 --month=3
php artisan leave:burn-previous-year --year=2024

# Test scheduler
php artisan schedule:list
php artisan schedule:run --verbose
```

### Monitoring Commands
```bash
# Cek status cron job
crontab -l
sudo systemctl status cron

# Monitor log files
tail -f /var/log/cron
tail -f storage/logs/leave-management.log

# Cek apakah scheduler berjalan
ps aux | grep "schedule:run"
```

### Alternative Setup (Docker)
```dockerfile
# Jika menggunakan Docker, tambahkan ke Dockerfile
COPY crontab /etc/cron.d/laravel-scheduler
RUN chmod 0644 /etc/cron.d/laravel-scheduler
RUN crontab /etc/cron.d/laravel-scheduler
```

```bash
# File: crontab
* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1
```

## 🌐 Environment-Specific Setup

### Ubuntu/Debian Server
```bash
# Install cron jika belum ada
sudo apt update
sudo apt install cron

# Start dan enable cron service
sudo systemctl start cron
sudo systemctl enable cron

# Edit crontab
sudo crontab -e

# Tambahkan cron job
* * * * * cd /var/www/ymsofterp && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

### CentOS/RHEL Server
```bash
# Install cron jika belum ada
sudo yum install cronie
# atau
sudo dnf install cronie

# Start dan enable cron service
sudo systemctl start crond
sudo systemctl enable crond

# Edit crontab
sudo crontab -e

# Tambahkan cron job
* * * * * cd /var/www/ymsofterp && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

### cPanel/WHM Server
```bash
# Login ke cPanel
# Go to "Cron Jobs" section
# Add new cron job:
# Minute: *
# Hour: *
# Day: *
# Month: *
# Weekday: *
# Command: cd /home/username/public_html && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

### Plesk Server
```bash
# Login ke Plesk
# Go to "Scheduled Tasks"
# Add new task:
# Command: /usr/bin/php /var/www/vhosts/domain.com/httpdocs/artisan schedule:run
# Run: Every minute
```

### Shared Hosting
```bash
# Jika menggunakan shared hosting, gunakan cPanel atau panel hosting
# Pastikan PHP CLI tersedia
# Gunakan absolute path untuk PHP dan project
```

### Cloud Server (AWS, DigitalOcean, dll)
```bash
# Setup cron job di cloud server
sudo crontab -e

# Tambahkan dengan path yang benar
* * * * * cd /var/www/ymsofterp && /usr/bin/php artisan schedule:run >> /dev/null 2>&1

# Cek status
sudo systemctl status cron
```

## 📊 Monitoring & Alerting

### Setup Monitoring
```bash
# Cek status cron job
crontab -l
sudo systemctl status cron

# Monitor log files
tail -f storage/logs/laravel.log
tail -f storage/logs/leave-management.log

# Cek apakah scheduler berjalan
ps aux | grep "schedule:run"
```

### Setup Alerting
```php
// Tambahkan ke app/Console/Kernel.php untuk email alerting
protected function schedule(Schedule $schedule)
{
    $schedule->command('leave:monthly-credit')
        ->monthlyOn(1, '00:00')
        ->description('Memberikan cuti bulanan ke semua karyawan aktif')
        ->appendOutputTo(storage_path('logs/leave-management.log'))
        ->emailOutputOnFailure('admin@company.com');

    $schedule->command('leave:burn-previous-year')
        ->yearlyOn(3, 1, '00:00')
        ->description('Burning sisa cuti tahun sebelumnya')
        ->appendOutputTo(storage_path('logs/leave-management.log'))
        ->emailOutputOnFailure('admin@company.com');
}
```

### Health Check Script
```bash
#!/bin/bash
# File: check-leave-scheduler.sh

# Cek apakah cron job berjalan
if ! crontab -l | grep -q "schedule:run"; then
    echo "ERROR: Cron job tidak ditemukan"
    exit 1
fi

# Cek apakah scheduler berjalan
if ! ps aux | grep -q "schedule:run"; then
    echo "WARNING: Scheduler tidak berjalan"
fi

# Cek log files
if [ ! -f "storage/logs/leave-management.log" ]; then
    echo "WARNING: Log file tidak ditemukan"
fi

echo "OK: Leave scheduler berjalan dengan baik"
```

### Automated Monitoring
```bash
# Tambahkan ke crontab untuk monitoring
# Cek setiap 5 menit apakah scheduler berjalan
*/5 * * * * /path-to-your-project/check-leave-scheduler.sh >> /var/log/leave-monitor.log 2>&1
```

## 📞 Support

Jika ada masalah atau pertanyaan:
1. **Check Logs**: storage/logs/laravel.log
2. **Test Commands**: Jalankan manual untuk debugging
3. **Database Check**: Pastikan tabel dan data sudah benar
4. **Permissions**: Pastikan file permissions sudah benar
5. **Cron Status**: Cek apakah cron service berjalan
6. **Path Issues**: Pastikan path PHP dan project benar

### Quick Troubleshooting
```bash
# 1. Test manual
php artisan leave:monthly-credit --year=2024 --month=3
php artisan leave:burn-previous-year --year=2024

# 2. Cek scheduler
php artisan schedule:list
php artisan schedule:run --verbose

# 3. Cek logs
tail -f storage/logs/laravel.log
tail -f storage/logs/leave-management.log

# 4. Cek cron
crontab -l
sudo systemctl status cron
```

---

**Sistem ini memberikan solusi lengkap untuk manajemen cuti otomatis dengan tracking yang komprehensif! 🎉**
