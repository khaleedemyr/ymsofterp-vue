# Setup Scheduler Laravel

## Masalah
Scheduler tidak berjalan karena tidak ada cron job yang menjalankan `php artisan schedule:run`.

## Solusi

### 1. Pastikan ada cron job yang menjalankan scheduler

Tambahkan cron job berikut di server (via cPanel atau SSH):

```bash
* * * * * cd /path/to/ymsofterp && php artisan schedule:run >> /dev/null 2>&1
```

**Untuk Windows (development):**
- Install Task Scheduler atau gunakan Windows Task Scheduler
- Atau jalankan secara manual: `php artisan schedule:work` (untuk development)

### 2. Verifikasi scheduler berjalan

Jalankan command berikut untuk melihat schedule yang terdaftar:
```bash
php artisan schedule:list
```

Jika masih menunjukkan "No scheduled tasks have been defined", coba:
```bash
php artisan optimize:clear
php artisan config:clear
composer dump-autoload
php artisan schedule:list
```

### 3. Test scheduler secara manual

Jalankan command berikut untuk test scheduler:
```bash
php artisan schedule:test
```

Atau jalankan command secara langsung:
```bash
php artisan vouchers:distribute-birthday
```

### 4. Cek log scheduler

Semua scheduler sudah dikonfigurasi untuk menulis log ke:
- `storage/logs/birthday-vouchers-distribution.log`
- `storage/logs/holiday-attendance.log`
- `storage/logs/extra-off-detection.log`
- dll.

### 5. Command yang sudah di-schedule

Semua command sudah didefinisikan di `app/Console/Kernel.php`:
- `vouchers:distribute-birthday` - Daily 01:00
- `attendance:process-holiday` - Daily 06:00 & 23:00
- `extra-off:detect` - Daily 07:00 & 23:30
- `member:notify-inactive` - Daily 10:00
- dll.

## Catatan Penting

**Scheduler Laravel memerlukan cron job yang menjalankan `php artisan schedule:run` setiap menit!**

Tanpa cron job ini, scheduler tidak akan berjalan otomatis.

