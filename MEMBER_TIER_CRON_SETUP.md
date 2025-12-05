# Member Tier Update - Cron Job Setup

## Overview
Tier member akan otomatis diupdate berdasarkan rolling 12-month spending setiap tanggal 1 setiap bulan menggunakan cron job.

## Current Cron Job Configuration

### 1. Laravel Scheduler (app/Console/Kernel.php)
```php
// Update member tiers based on rolling 12-month spending - run monthly on the 1st
$schedule->command('members:update-tiers')
    ->monthlyOn(1, '00:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/member-tiers-update.log'))
    ->description('Update member tiers based on rolling 12-month spending');
```

**Penjelasan:**
- **Waktu**: Setiap tanggal 1 setiap bulan jam 00:00 (midnight)
- **Command**: `php artisan members:update-tiers`
- **withoutOverlapping()**: Mencegah command berjalan bersamaan jika masih ada yang sedang berjalan
- **runInBackground()**: Menjalankan di background agar tidak blocking
- **Log**: Output disimpan di `storage/logs/member-tiers-update.log`

### 2. Server Cron Job Setup

**PENTING**: Laravel menggunakan scheduler yang berjalan setiap menit. Hanya perlu **SATU** cron job untuk menjalankan `schedule:run`, dan Laravel akan otomatis menjalankan semua scheduled commands termasuk `members:update-tiers`.

#### Untuk Linux/Ubuntu Server:
```bash
# Edit crontab
crontab -e

# Tambahkan baris berikut (jika belum ada):
* * * * * cd /home/ymsuperadmin/public_html && php artisan schedule:run >> /dev/null 2>&1
```

**Catatan**: 
- Ganti `/home/ymsuperadmin/public_html` dengan path sebenarnya ke project Laravel
- Cron job ini akan berjalan **setiap menit** dan Laravel akan otomatis menjalankan command yang sudah dijadwalkan
- Jika sudah ada entry `schedule:run` untuk command lain, tidak perlu menambahkan lagi

#### Untuk Windows Server (Task Scheduler):
1. Buka **Task Scheduler**
2. Klik **Create Basic Task**
3. **Name**: Laravel Scheduler
4. **Trigger**: 
   - **Daily** atau **At startup** (untuk berjalan setiap menit, gunakan trigger "On a schedule" dengan interval 1 minute)
5. **Action**: Start a program
   - **Program/script**: `C:\path\to\php.exe`
   - **Add arguments**: `artisan schedule:run`
   - **Start in**: `D:\Gawean\YM\web\ymsofterp` (path ke project Laravel)

#### Untuk cPanel:
1. Login ke cPanel
2. Buka **Cron Jobs**
3. Tambahkan cron job baru:
   - **Minute**: `*`
   - **Hour**: `*`
   - **Day**: `*`
   - **Month**: `*`
   - **Weekday**: `*`
   - **Command**: `cd /home/ymsuperadmin/public_html && php artisan schedule:run >> /dev/null 2>&1`

## Testing

### Test Command Manual:
```bash
# Test update tier untuk semua member
php artisan members:update-tiers

# Test update tier untuk member tertentu
php artisan members:update-tiers --member-id=1
```

### Check Logs:
```bash
# Lihat log tier update
tail -f storage/logs/member-tiers-update.log

# Lihat log Laravel umum
tail -f storage/logs/laravel.log | grep -i "tier\|rolling"
```

## Important Notes

1. **Perhitungan Rolling 12 Month**: 
   - Otomatis dihitung setiap kali ada transaksi baru (via `MemberTierService::recordTransaction()`)
   - Otomatis dihitung saat rollback transaksi
   - Cron job bulanan hanya untuk memastikan semua member tier ter-update (backup mechanism)

2. **Tier Update**:
   - Tier akan otomatis ter-update saat ada transaksi baru
   - Cron job bulanan memastikan tier semua member ter-update jika ada perubahan data

3. **Rolling 12 Month Calculation**:
   - Menghitung 12 bulan terakhir dari tanggal saat ini
   - Contoh: Jika hari ini 30 November 2025, maka menghitung dari Desember 2024 sampai November 2025

## Troubleshooting

### Cron job tidak berjalan:
1. Pastikan cron job `schedule:run` sudah di-setup di server
2. Cek log di `storage/logs/member-tiers-update.log`
3. Test manual dengan `php artisan members:update-tiers`

### Tier tidak ter-update:
1. Cek apakah `MemberTierService::recordTransaction()` dipanggil saat sync order
2. Cek log di `storage/logs/laravel.log` untuk melihat perhitungan rolling 12 month
3. Pastikan data di `member_apps_monthly_spending` sudah benar

### Perhitungan rolling 12 month salah:
1. Cek log di `storage/logs/laravel.log` dengan keyword "Rolling 12-Month Spending Calculation"
2. Pastikan data di `member_apps_monthly_spending` sudah lengkap
3. Test manual dengan `php artisan tinker`:
   ```php
   use App\Models\MemberAppsMonthlySpending;
   $spending = MemberAppsMonthlySpending::getRolling12MonthSpending(1);
   ```

