# Daftar Cron Jobs yang Perlu Ditambahkan

Berdasarkan schedule di `app/Console/Kernel.php`, berikut adalah daftar lengkap cron job yang perlu dibuat:

## ✅ Yang Sudah Ada (dari screenshot):
1. `attendance:process-holiday` - 06:00 ✓
2. `attendance:process-holiday` - 23:00 ✓
3. `extra-off:detect` - 07:00 ✓
4. `extra-off:detect` - 23:30 ✓
5. `employee-movements:execute` - 05:00 ✓ (di Kernel.php seharusnya 08:00, tapi di cron sudah ada 05:00)
6. `leave:monthly-credit` - 00:00 tanggal 1 setiap bulan ✓
7. `leave:burn-previous-year` - 00:00 tanggal 1 Maret ✓
8. `vouchers:distribute-birthday` - 01:00 ✓

## ❌ Yang Belum Ada (Perlu Ditambahkan):

### 1. Attendance Cleanup Logs (Weekly - Sunday 02:00)
- **Minute:** `0`
- **Hour:** `2`
- **Day:** `*`
- **Month:** `*`
- **Weekday:** `0` (Sunday)
- **Command:**
```bash
cd /home/ymsuperadmin/public_html && php artisan attendance:cleanup-logs >> storage/logs/attendance-cleanup.log 2>&1
```

### 2. Update Member Tiers (Monthly - 1st at 00:00)
- **Minute:** `0`
- **Hour:** `0`
- **Day:** `1`
- **Month:** `*`
- **Weekday:** `*`
- **Command:**
```bash
cd /home/ymsuperadmin/public_html && php artisan members:update-tiers >> storage/logs/member-tiers-update.log 2>&1
```

### 3. Expire Points (Daily - 00:00)
- **Minute:** `0`
- **Hour:** `0`
- **Day:** `*`
- **Month:** `*`
- **Weekday:** `*`
- **Command:**
```bash
cd /home/ymsuperadmin/public_html && php artisan points:expire >> storage/logs/points-expiry.log 2>&1
```

### 4. Notify Incomplete Profile (Hourly)
- **Minute:** `0`
- **Hour:** `*`
- **Day:** `*`
- **Month:** `*`
- **Weekday:** `*`
- **Command:**
```bash
cd /home/ymsuperadmin/public_html && php artisan member:notify-incomplete-profile >> storage/logs/incomplete-profile-notifications.log 2>&1
```

### 5. Notify Incomplete Challenge (Hourly)
- **Minute:** `0`
- **Hour:** `*`
- **Day:** `*`
- **Month:** `*`
- **Weekday:** `*`
- **Command:**
```bash
cd /home/ymsuperadmin/public_html && php artisan member:notify-incomplete-challenge >> storage/logs/incomplete-challenge-notifications.log 2>&1
```

### 6. Notify Inactive Members (Daily - 10:00)
- **Minute:** `0`
- **Hour:** `10`
- **Day:** `*`
- **Month:** `*`
- **Weekday:** `*`
- **Command:**
```bash
cd /home/ymsuperadmin/public_html && php artisan member:notify-inactive >> storage/logs/inactive-member-notifications.log 2>&1
```

### 7. Notify Long Inactive Members (Daily - 11:00)
- **Minute:** `0`
- **Hour:** `11`
- **Day:** `*`
- **Month:** `*`
- **Weekday:** `*`
- **Command:**
```bash
cd /home/ymsuperadmin/public_html && php artisan member:notify-long-inactive >> storage/logs/long-inactive-member-notifications.log 2>&1
```

### 8. Notify Expiring Points (Daily - 09:00)
- **Minute:** `0`
- **Hour:** `9`
- **Day:** `*`
- **Month:** `*`
- **Weekday:** `*`
- **Command:**
```bash
cd /home/ymsuperadmin/public_html && php artisan member:notify-expiring-points >> storage/logs/expiring-points-notifications.log 2>&1
```

### 9. Notify Monthly Inactive Members (Daily - 10:30)
- **Minute:** `30`
- **Hour:** `10`
- **Day:** `*`
- **Month:** `*`
- **Weekday:** `*`
- **Command:**
```bash
cd /home/ymsuperadmin/public_html && php artisan member:notify-monthly-inactive >> storage/logs/monthly-inactive-member-notifications.log 2>&1
```

### 10. Notify Expiring Vouchers (Daily - 09:30)
- **Minute:** `30`
- **Hour:** `9`
- **Day:** `*`
- **Month:** `*`
- **Weekday:** `*`
- **Command:**
```bash
cd /home/ymsuperadmin/public_html && php artisan member:notify-expiring-vouchers >> storage/logs/expiring-vouchers-notifications.log 2>&1
```

### 11. Cleanup Device Tokens (Daily - 02:00)
- **Minute:** `0`
- **Hour:** `2`
- **Day:** `*`
- **Month:** `*`
- **Weekday:** `*`
- **Command:**
```bash
cd /home/ymsuperadmin/public_html && php artisan device-tokens:cleanup --days=30 --limit=5 >> storage/logs/device-tokens-cleanup.log 2>&1
```

## 📝 Catatan:
- Semua command menggunakan path: `/home/ymsuperadmin/public_html`
- Semua output di-redirect ke file log masing-masing
- Error juga di-redirect ke log file (`2>&1`)
- Untuk hourly jobs, bisa juga menggunakan `*/60` di minute field jika panel tidak support `*` di hour

## ⚠️ Perhatian:
- Pastikan semua command sudah di-test manual sebelum ditambahkan ke cron
- Monitor log files setelah cron job ditambahkan untuk memastikan berjalan dengan baik
- Jika ada conflict dengan cron job yang sudah ada, sesuaikan waktu eksekusinya

