# Incomplete Profile Notification

## Overview
Sistem ini mengirim notifikasi push kepada member yang telah registrasi 24 jam yang lalu tetapi belum melengkapi profil mereka (belum upload photo).

## Implementasi

### 1. Method Helper - Check Profile Complete
**File**: `app/Models/MemberAppsMember.php`

Method `isProfileComplete()` mengecek apakah profil member sudah lengkap:
- Profile dianggap lengkap jika `photo` tidak null/empty
- Method ini bisa digunakan di berbagai tempat untuk check status profile

```php
public function isProfileComplete(): bool
{
    return !empty($this->photo);
}
```

### 2. Scheduled Command
**File**: `app/Console/Commands/SendIncompleteProfileNotification.php`

Command ini:
- Mencari member yang registrasi 24 jam yang lalu (dengan window 23-25 jam untuk fleksibilitas)
- Filter member yang:
  - `photo` masih null
  - `is_active` = true
  - `allow_notification` = true
- Mengirim notifikasi dengan wording:
  - **Title**: "Complete Your Profile"
  - **Message**: "Complete your profile to unlock tailored rewards just for you."

### 3. Scheduled Task
**File**: `app/Console/Kernel.php`

Command dijalankan setiap jam:
```php
$schedule->command('member:notify-incomplete-profile')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/incomplete-profile-notifications.log'))
    ->description('Send notification to members who registered 24 hours ago but haven\'t completed their profile');
```

## Kriteria Profile Lengkap

Profile dianggap **lengkap** jika:
- ✅ `photo` tidak null/empty (sudah upload photo)

Profile dianggap **tidak lengkap** jika:
- ❌ `photo` masih null/empty

## Testing

### Manual Test
Jalankan command secara manual:
```bash
php artisan member:notify-incomplete-profile
```

### Test dengan Data Dummy
1. Buat member baru dengan `photo = null`
2. Update `created_at` menjadi 24 jam yang lalu:
   ```sql
   UPDATE member_apps_members 
   SET created_at = DATE_SUB(NOW(), INTERVAL 24 HOUR)
   WHERE id = [member_id];
   ```
3. Jalankan command:
   ```bash
   php artisan member:notify-incomplete-profile
   ```
4. Cek log di `storage/logs/incomplete-profile-notifications.log`
5. Verifikasi notifikasi terkirim ke device member

### Test Scheduled Task
Pastikan cron job berjalan:
```bash
# Cek apakah Laravel scheduler berjalan
php artisan schedule:list

# Test run scheduler
php artisan schedule:test
```

## Log

Command akan menulis log ke:
- **File**: `storage/logs/incomplete-profile-notifications.log`
- **Laravel Log**: `storage/logs/laravel.log`

Log entry contoh:
```
[2025-12-03 10:00:00] local.INFO: Incomplete profile notification sent {
    "member_id": 1,
    "member_member_id": "JTS251201A2B3",
    "registered_at": "2025-12-02 10:00:00",
    "hours_since_registration": 24
}
```

## Notification Payload

Notifikasi yang dikirim memiliki payload:
```json
{
    "type": "incomplete_profile",
    "member_id": 1,
    "action": "complete_profile"
}
```

## Pencegahan Duplikasi

Command ini:
- Menjalankan check setiap jam
- Window 23-25 jam untuk fleksibilitas
- Skip member yang sudah complete profile
- Skip member yang `allow_notification = false`

**Note**: Saat ini tidak ada tracking untuk mencegah notifikasi duplikat jika member masih belum complete setelah 48 jam. Jika diperlukan, bisa ditambahkan field `incomplete_profile_notified_at` di tabel `member_apps_members`.

## Troubleshooting

### Problem: Notifikasi tidak terkirim
**Solution**:
1. Cek log di `storage/logs/incomplete-profile-notifications.log`
2. Pastikan member memiliki device token yang terdaftar
3. Pastikan `allow_notification = true`
4. Pastikan FCM service berfungsi dengan baik

### Problem: Member yang sudah complete masih dapat notifikasi
**Solution**:
1. Pastikan method `isProfileComplete()` bekerja dengan benar
2. Cek apakah `photo` field sudah terisi di database
3. Pastikan command check `isProfileComplete()` sebelum kirim notifikasi

### Problem: Command tidak berjalan otomatis
**Solution**:
1. Pastikan cron job berjalan:
   ```bash
   * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
   ```
2. Cek `php artisan schedule:list` untuk melihat scheduled tasks
3. Test manual dengan `php artisan member:notify-incomplete-profile`

## Catatan Penting

1. **Window Time**: Command menggunakan window 23-25 jam untuk fleksibilitas, jadi member yang registrasi 23-25 jam yang lalu akan dapat notifikasi
2. **Frequency**: Command berjalan setiap jam, jadi member bisa dapat notifikasi beberapa kali jika masih belum complete (setiap jam sekali)
3. **Performance**: Command menggunakan `withoutOverlapping()` untuk mencegah multiple instances berjalan bersamaan
4. **Background**: Command berjalan di background untuk tidak block scheduler

## Future Enhancement

Jika diperlukan, bisa ditambahkan:
1. Field `incomplete_profile_notified_at` untuk track kapan terakhir notifikasi dikirim
2. Limit notifikasi hanya 1x per 24 jam
3. Tambah field lain untuk kriteria "profile lengkap" (misalnya: alamat, pekerjaan, dll)

