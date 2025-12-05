# Web Push Notification Test Guide

## Cara Test Web Push Notifications di Mobile Browser

### 1. Prerequisites
- ✅ Firebase sudah terkonfigurasi
- ✅ VAPID key sudah di-set
- ✅ Service Worker sudah terdaftar
- ✅ User sudah login
- ✅ Browser support Service Workers (Chrome, Firefox, Edge, Safari iOS 16.4+)

### 2. Setup di Mobile Browser

#### Android (Chrome/Edge):
1. Buka web app di mobile browser
2. Login ke aplikasi
3. Browser akan otomatis minta permission untuk notifications
4. Klik **"Allow"** atau **"Izinkan"**
5. Cek console browser (jika bisa) untuk melihat:
   - "Service Worker registered"
   - "Notification permission granted"
   - "FCM Token obtained"

#### iOS Safari (16.4+):
1. Buka web app di Safari
2. Login ke aplikasi
3. Tap **Share** button (kotak dengan panah)
4. Tap **"Add to Home Screen"**
5. Buka app dari home screen (PWA mode)
6. Notifications akan muncul seperti native app

### 3. Test Notifikasi

#### Cara 1: Insert ke Database
```sql
INSERT INTO notifications (user_id, type, title, message, url, is_read, created_at, updated_at)
VALUES (1, 'general', 'Test Notification', 'Ini adalah test notification untuk mobile browser', '/dashboard', 0, NOW(), NOW());
```

#### Cara 2: Via Laravel Tinker
```php
php artisan tinker

$user = \App\Models\User::find(1);
\App\Models\Notification::create([
    'user_id' => $user->id,
    'type' => 'test',
    'title' => 'Test Notification',
    'message' => 'Ini adalah test notification',
    'url' => '/dashboard',
    'is_read' => false,
]);
```

### 4. Troubleshooting

#### Notifikasi tidak muncul:
1. **Cek Permission:**
   - Buka browser settings
   - Cari "Notifications" atau "Notifikasi"
   - Pastikan website di-allow

2. **Cek Service Worker:**
   - Buka DevTools (F12 atau inspect)
   - Tab "Application" > "Service Workers"
   - Pastikan `/firebase-messaging-sw.js` sudah terdaftar dan aktif

3. **Cek Device Token:**
   ```sql
   SELECT * FROM web_device_tokens WHERE user_id = 1 AND is_active = 1;
   ```
   - Pastikan ada data dengan device_token yang valid

4. **Cek Log Laravel:**
   ```bash
   tail -f storage/logs/laravel.log | grep NotificationObserver
   ```
   - Cek apakah notification dikirim
   - Cek apakah ada error

5. **Cek Console Browser:**
   - Buka DevTools > Console
   - Cek apakah ada error
   - Cek log "FCM Token obtained"

#### Notifikasi muncul tapi tidak bisa diklik:
- Pastikan `url` di notification data sudah benar
- Cek service worker `notificationclick` handler

#### Notifikasi muncul tapi tidak ada suara/vibrate:
- Android: Cek notification settings di device
- iOS: Notifications di Safari web app tidak support suara/vibrate (hanya badge)

### 5. Fitur yang Sudah Diimplementasikan

✅ **Background Notifications** (app closed)
- Service Worker menangani notifications saat app ditutup
- Notifications muncul seperti native app

✅ **Foreground Notifications** (app open)
- Notifications muncul saat app sedang dibuka
- Bisa diklik untuk navigate

✅ **Notification Click Handler**
- Klik notification akan navigate ke URL yang ditentukan
- Atau focus ke existing window

✅ **Mobile Optimized**
- Vibrate pattern untuk Android
- Icon dan badge support
- Large image support (jika disediakan)

### 6. Catatan Penting

⚠️ **HTTPS Required:**
- Web push notifications hanya bekerja di HTTPS
- Atau localhost untuk development

⚠️ **Browser Support:**
- ✅ Chrome/Edge Android: Full support
- ✅ Firefox Android: Full support
- ✅ Safari iOS 16.4+: Full support (harus PWA mode)
- ❌ Safari iOS < 16.4: Tidak support

⚠️ **Service Worker:**
- Harus di folder `public/`
- Harus di-akses via HTTPS
- Harus terdaftar sebelum request token

### 7. Test Checklist

- [ ] Service Worker terdaftar
- [ ] Notification permission granted
- [ ] FCM token terdaftar di database
- [ ] Notification muncul saat app ditutup
- [ ] Notification muncul saat app dibuka
- [ ] Notification bisa diklik
- [ ] Navigate ke URL yang benar
- [ ] Icon muncul di notification
- [ ] Vibrate bekerja (Android)

### 8. Debug Commands

```bash
# Cek device tokens
php artisan tinker
>>> \App\Models\WebDeviceToken::where('is_active', true)->get();

# Test send notification
>>> $user = \App\Models\User::find(1);
>>> \App\Models\Notification::create([
    'user_id' => $user->id,
    'type' => 'test',
    'title' => 'Test',
    'message' => 'Test message',
]);
```

### 9. Next Steps

Jika masih tidak muncul:
1. Cek Laravel log untuk error
2. Cek browser console untuk error
3. Cek Firebase Console > Cloud Messaging > Reports
4. Pastikan VAPID key benar
5. Pastikan service worker terdaftar dengan benar

