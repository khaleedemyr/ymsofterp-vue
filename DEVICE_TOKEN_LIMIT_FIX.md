# Fix: Duplicate Push Notifications

## Masalah
Push notification terkirim berulang (4x) untuk notifikasi yang sama karena user memiliki multiple device tokens aktif.

## Solusi

### 1. Limit Device Token Registration
- **File**: `app/Http/Controllers/Api/WebDeviceTokenController.php`
- **Perubahan**: Saat register device token baru, jika user sudah punya 5+ token aktif, token lama akan di-deactivate
- **Limit**: Maksimal 5 device token aktif per user

### 2. Limit Device Token di Observer
- **File**: `app/Observers/NotificationObserver.php`
- **Perubahan**: Hanya mengirim notification ke 5 device token terbaru per user
- **Limit**: Maksimal 5 device token per user (web + employee)

### 3. Automatic Cleanup Command
- **File**: `app/Console/Commands/CleanupDeviceTokens.php`
- **Command**: `php artisan device-tokens:cleanup --days=30 --limit=5`
- **Schedule**: Otomatis jalan setiap hari jam 02:00 AM
- **Fungsi**:
  - Deactivate token yang tidak digunakan > 30 hari
  - Deactivate token berlebih (keep max 5 per user)

## Testing

### Manual Cleanup
```bash
php artisan device-tokens:cleanup --days=30 --limit=5
```

### Cek Device Token per User
```sql
-- Cek web device tokens
SELECT user_id, COUNT(*) as total, 
       SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active
FROM web_device_tokens
GROUP BY user_id
HAVING SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) > 5;

-- Cek employee device tokens
SELECT user_id, COUNT(*) as total,
       SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active
FROM employee_device_tokens
GROUP BY user_id
HAVING SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) > 5;
```

### Test Notification
```bash
php artisan tinker
```
```php
$user = \App\Models\User::find(26);
\App\Models\Notification::create([
    'user_id' => $user->id,
    'title' => 'Test Push Notification',
    'message' => 'Ini adalah test notification untuk push',
    'type' => 'test',
    'is_read' => 0,
]);
```

## Catatan

1. **Multiple Browsers/Tabs adalah Normal**:
   - Jika user buka web di Chrome, Firefox, dan Edge → 3 device tokens (normal)
   - Setiap browser akan terima notification (ini benar)
   - Limit 5 token per user sudah cukup untuk kebanyakan kasus

2. **Automatic Cleanup**:
   - Cleanup otomatis jalan setiap hari jam 02:00 AM
   - Token yang tidak digunakan > 30 hari akan di-deactivate
   - Token berlebih akan di-deactivate (keep max 5 per user)

3. **Jika Masih Berulang**:
   - Cek apakah notification dibuat beberapa kali (bukan masalah device token)
   - Cek log: `storage/logs/laravel.log` untuk melihat berapa kali observer dipanggil
   - Cek apakah ada multiple notification records dengan content yang sama

## Files Changed

1. `app/Http/Controllers/Api/WebDeviceTokenController.php` - Limit saat register
2. `app/Observers/NotificationObserver.php` - Limit saat kirim notification
3. `app/Console/Commands/CleanupDeviceTokens.php` - Command untuk cleanup
4. `app/Console/Kernel.php` - Schedule cleanup command
5. `CLEANUP_OLD_DEVICE_TOKENS.sql` - SQL script untuk manual cleanup

