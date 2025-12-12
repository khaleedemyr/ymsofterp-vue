# Panduan Test Push Notification

## ✅ Status: Siap untuk Testing!

Sistem push notification sudah siap untuk di-test. Setiap insert ke table `notifications` akan otomatis mengirim push notification ke mobile app.

## 📋 Prasyarat

1. ✅ Tabel `employee_device_tokens` sudah dibuat (jalankan SQL migration)
2. ✅ User ID 26 sudah memiliki device token yang terdaftar di tabel `employee_device_tokens`
3. ✅ FCM credentials sudah dikonfigurasi di `.env`

## 🧪 Cara Test

### Opsi 1: Via Artisan Command (Recommended)

```bash
php artisan test:notification-push 26
```

Atau untuk user ID lain:
```bash
php artisan test:notification-push {user_id}
```

### Opsi 2: Via Tinker

```bash
php artisan tinker
```

Lalu copy-paste:
```php
use App\Models\Notification;

$notification = Notification::create([
    'user_id' => 26,
    'title' => 'Test Push Notification',
    'message' => 'Ini adalah test notification untuk push notification ke mobile app',
    'type' => 'test',
]);

echo "Notification ID: " . $notification->id . "\n";
```

### Opsi 3: Via SQL (TIDAK akan trigger push notification)

⚠️ **PENTING**: Query SQL langsung TIDAK akan trigger observer/push notification. 
Observer hanya terpicu jika menggunakan Eloquent (`Notification::create()`).

Jika ingin test via SQL untuk insert data saja (tanpa push):
```sql
INSERT INTO `notifications` (
    `user_id`,
    `title`,
    `message`,
    `type`,
    `is_read`,
    `created_at`,
    `updated_at`
) VALUES (
    26,
    'Test Notification',
    'Ini adalah test notification',
    'test',
    0,
    NOW(),
    NOW()
);
```

## 📱 Yang Perlu Dicek

### 1. Device Token Terdaftar
Pastikan user ID 26 memiliki device token yang aktif:

```sql
SELECT * FROM `employee_device_tokens` 
WHERE `user_id` = 26 
AND `is_active` = 1;
```

Jika tidak ada, user perlu:
- Login di mobile app
- Register device token via API: `POST /api/approval-app/device-token/register`

### 2. Log File
Cek log untuk melihat proses pengiriman:

```bash
tail -f storage/logs/laravel.log
```

Atau cari log dengan keyword:
- `NotificationObserver: Sending FCM notification`
- `NotificationObserver: FCM notification sent`
- `NotificationObserver: Error sending FCM notification` (jika ada error)

### 3. Mobile App
- Pastikan app sudah login dengan user ID 26
- Pastikan device token sudah terdaftar
- Pastikan app memiliki permission untuk push notification
- Cek apakah notification muncul di mobile app

## 🔍 Troubleshooting

### Notification tidak terkirim ke mobile app

1. **Cek device token terdaftar:**
   ```sql
   SELECT * FROM employee_device_tokens WHERE user_id = 26 AND is_active = 1;
   ```

2. **Cek log untuk error:**
   ```bash
   grep "NotificationObserver" storage/logs/laravel.log | tail -20
   ```

3. **Cek FCM credentials:**
   - Pastikan `FCM_SERVER_KEY` atau `FCM_SERVICE_ACCOUNT_PATH` sudah dikonfigurasi di `.env`
   - Test FCM connection: `php artisan test:fcm-notification`

4. **Cek observer terdaftar:**
   - Pastikan di `AppServiceProvider` ada: `Notification::observe(NotificationObserver::class);`

### Error di log

1. **"Table 'employee_device_tokens' doesn't exist"**
   - Jalankan SQL migration: `database/sql/create_employee_device_tokens_table.sql`

2. **"FCM API Key not configured"**
   - Cek `.env` file, pastikan FCM credentials sudah dikonfigurasi

3. **"No active device tokens found"**
   - User belum register device token
   - Device token sudah di-deactivate (`is_active = 0`)

## 📊 Expected Log Output

Jika berhasil, log akan terlihat seperti ini:

```
[2024-XX-XX XX:XX:XX] local.INFO: NotificationObserver: Sending FCM notification 
{
    "notification_id": 123,
    "user_id": 26,
    "web_device_count": 0,
    "employee_device_count": 1,
    "total_device_count": 1,
    "title": "Test Push Notification"
}

[2024-XX-XX XX:XX:XX] local.INFO: NotificationObserver: FCM notification sent 
{
    "notification_id": 123,
    "user_id": 26,
    "web_success": 0,
    "web_failed": 0,
    "employee_success": 1,
    "employee_failed": 0,
    "total_success": 1,
    "total_failed": 0
}
```

## 🎯 Quick Test Checklist

- [ ] Tabel `employee_device_tokens` sudah dibuat
- [ ] User ID 26 memiliki device token aktif
- [ ] FCM credentials sudah dikonfigurasi
- [ ] Jalankan test command: `php artisan test:notification-push 26`
- [ ] Cek log file untuk hasil pengiriman
- [ ] Cek mobile app untuk push notification

## 📝 Catatan

1. **Observer hanya terpicu untuk Eloquent**: 
   - ✅ `Notification::create()` - AKAN trigger push
   - ❌ `DB::table('notifications')->insert()` - TIDAK trigger push

2. **Push notification hanya untuk user yang punya device token**:
   - Jika user tidak punya device token, notification tetap tersimpan tapi tidak ada push

3. **Error handling**:
   - Jika push gagal, notification tetap tersimpan di database
   - Error akan di-log, tidak mengganggu proses insert

