# Setup Scheduler Laravel

## Status: ✅ SUDAH BERJALAN

Schedule sudah terdeteksi dan cron job sudah terpasang. Scheduler akan berjalan otomatis setiap menit.

## ⚠️ Supervisor vs Scheduler

**Supervisor TIDAK PERLU diubah untuk scheduler!**

- **Supervisor** digunakan untuk **Queue Workers** (berjalan terus menerus)
- **Cron Job** digunakan untuk **Scheduler** (berjalan setiap menit, mengecek dan menjalankan task yang sudah waktunya)

**Keduanya berbeda dan tidak saling terkait:**
- Supervisor → `php artisan queue:work` (untuk memproses queue jobs)
- Cron Job → `php artisan schedule:run` (untuk menjalankan scheduled tasks)

## Verifikasi

### 1. Cron Job (Sudah Terpasang)

Cron job yang sudah ada:
```bash
* * * * * cd /home/ymsuperadmin/public_html && php artisan schedule:run >> /dev/null 2>&1
```

Cron job ini akan:
- Berjalan setiap menit (`* * * * *`)
- Masuk ke direktori `/home/ymsuperadmin/public_html`
- Menjalankan `php artisan schedule:run`
- Menjalankan semua task yang sudah waktunya

### 2. Test Scheduler Secara Manual

Untuk test apakah scheduler berjalan dengan benar:
```bash
php artisan schedule:run
```

Command ini akan:
- Mengecek semua schedule yang sudah waktunya
- Menjalankan task yang sudah waktunya
- Tidak menjalankan task yang belum waktunya

### 3. Verifikasi Schedule Terdaftar

Jalankan command berikut untuk melihat semua schedule yang terdaftar:
```bash
php artisan schedule:list
```

**Output yang diharapkan:** Menampilkan 19 scheduled tasks termasuk:
- `vouchers:distribute-birthday` - Daily 01:00
- `attendance:process-holiday` - Daily 06:00 & 23:00
- `member:notify-inactive` - Daily 10:00
- dll.

### 4. Monitor Scheduler Berjalan

**Cek log untuk memastikan scheduler berjalan:**
```bash
# Cek log birthday voucher distribution
tail -f storage/logs/birthday-vouchers-distribution.log

# Cek log lainnya
tail -f storage/logs/holiday-attendance.log
tail -f storage/logs/inactive-member-notifications.log
```

**Atau cek log Laravel umum:**
```bash
tail -f storage/logs/laravel.log | grep -i "schedule\|birthday\|voucher"
```

### 5. Test Command Secara Manual (Opsional)

Untuk test command secara langsung tanpa menunggu scheduler:
```bash
php artisan vouchers:distribute-birthday
php artisan attendance:process-holiday
php artisan member:notify-inactive
```

## Daftar Schedule yang Aktif

Semua schedule sudah didefinisikan di `bootstrap/app.php` menggunakan `withSchedule()`:

| Command | Schedule | Log File |
|---------|----------|----------|
| `vouchers:distribute-birthday` | Daily 01:00 | `birthday-vouchers-distribution.log` |
| `attendance:process-holiday` | Daily 06:00 & 23:00 | `holiday-attendance.log` |
| `extra-off:detect` | Daily 07:00 & 23:30 | `extra-off-detection.log` |
| `employee-movements:execute` | Daily 08:00 | `employee-movements-execution.log` |
| `leave:monthly-credit` | Monthly 1st 00:00 | - |
| `leave:burn-previous-year` | Yearly Mar 1st 00:00 | - |
| `members:update-tiers` | Monthly 1st 00:00 | `member-tiers-update.log` |
| `points:expire` | Daily 00:00 | `points-expiry.log` |
| `member:notify-incomplete-profile` | Hourly | `incomplete-profile-notifications.log` |
| `member:notify-incomplete-challenge` | Hourly | `incomplete-challenge-notifications.log` |
| `member:notify-inactive` | Daily 10:00 | `inactive-member-notifications.log` |
| `member:notify-long-inactive` | Daily 11:00 | `long-inactive-member-notifications.log` |
| `member:notify-expiring-points` | Daily 09:00 | `expiring-points-notifications.log` |
| `member:notify-monthly-inactive` | Daily 10:30 | `monthly-inactive-member-notifications.log` |
| `member:notify-expiring-vouchers` | Daily 09:30 | `expiring-vouchers-notifications.log` |
| `device-tokens:cleanup` | Daily 02:00 | `device-tokens-cleanup.log` |

## Cara Kerja

1. **Cron Job** berjalan setiap menit dan menjalankan `php artisan schedule:run`
2. **Laravel Scheduler** mengecek semua task yang terdaftar
3. **Task yang sudah waktunya** akan dijalankan otomatis
4. **Output** ditulis ke log file yang sudah dikonfigurasi

## Troubleshooting

**Jika scheduler tidak berjalan:**
1. Pastikan cron job masih aktif: cek di cPanel → Cron Jobs
2. Test manual: `php artisan schedule:run`
3. Cek log: `tail -f storage/logs/laravel.log`
4. Verifikasi schedule: `php artisan schedule:list`

**Jika log tidak muncul:**
- Pastikan direktori `storage/logs/` writable
- Cek permission folder: `chmod -R 775 storage/logs`

