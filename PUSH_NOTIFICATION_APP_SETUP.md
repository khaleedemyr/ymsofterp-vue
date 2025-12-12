# Push Notification Setup untuk Approval App (ymsoftapp)

## Ringkasan
Push notification untuk approval app (ymsoftapp) telah diaktifkan kembali di backend. Implementasi ini memisahkan device token antara:
- **Web ERP**: menggunakan tabel `web_device_tokens`
- **Approval App (ymsoftapp)**: menggunakan tabel `employee_device_tokens` (baru dibuat)

## Yang Sudah Dilakukan

### 1. Database
- ✅ File SQL untuk membuat tabel `employee_device_tokens` telah dibuat
- 📁 Lokasi: `database/sql/create_employee_device_tokens_table.sql`
- ⚠️ **PENTING**: Jalankan SQL ini di database untuk membuat tabel

### 2. Model
- ✅ Model `EmployeeDeviceToken` telah dibuat
- 📁 Lokasi: `app/Models/EmployeeDeviceToken.php`

### 3. Controller
- ✅ Controller `DeviceTokenController` untuk approval-app telah dibuat
- 📁 Lokasi: `app/Http/Controllers/Mobile/ApprovalApp/DeviceTokenController.php`
- Endpoint yang tersedia:
  - `POST /api/approval-app/device-token/register` - Register device token
  - `POST /api/approval-app/device-token/unregister` - Unregister device token
  - `GET /api/approval-app/device-token` - Get semua device tokens user

### 4. Routes
- ✅ Routes untuk device token registration telah ditambahkan di `routes/api.php`
- Routes berada di dalam group `approval-app` dengan middleware `approval.app.auth`

### 5. NotificationObserver
- ✅ `NotificationObserver` telah diupdate untuk mengirim push notification ke:
  - Web device tokens (untuk web ERP) - **TIDAK BERUBAH, tetap berfungsi**
  - Employee device tokens (untuk approval app) - **BARU DITAMBAHKAN**
- 📁 Lokasi: `app/Observers/NotificationObserver.php`

## Yang Perlu Dilakukan

### 1. Database Migration
Jalankan SQL untuk membuat tabel `employee_device_tokens`:
```sql
-- File: database/sql/create_employee_device_tokens_table.sql
```

### 2. Setup Firebase di Flutter App
Flutter app (ymsoftapp) perlu setup Firebase Messaging:

#### a. Install Dependencies
Tambahkan ke `pubspec.yaml`:
```yaml
dependencies:
  firebase_core: ^2.24.2
  firebase_messaging: ^14.7.10
```

#### b. Setup Firebase
1. Download `google-services.json` untuk Android (jika belum ada)
2. Download `GoogleService-Info.plist` untuk iOS (jika belum ada)
3. Setup Firebase di `main.dart`

#### c. Implementasi Device Token Registration
Buat service untuk register device token ke backend:
- Ambil FCM token menggunakan `FirebaseMessaging.instance.getToken()`
- Kirim token ke endpoint: `POST /api/approval-app/device-token/register`
- Include `device_type` (android/ios), `device_id`, dan `app_version`

#### d. Handle Push Notifications
- Setup background message handler
- Setup foreground message handler
- Handle notification tap untuk navigasi ke halaman yang sesuai

## Struktur Tabel

### employee_device_tokens
- `id` - Primary key
- `user_id` - Foreign key ke `users` table
- `device_token` - FCM token dari mobile app
- `device_type` - enum('android', 'ios')
- `device_id` - Unique device identifier (optional)
- `app_version` - Versi app (optional)
- `is_active` - Status aktif/tidak aktif
- `last_used_at` - Timestamp terakhir digunakan
- `created_at`, `updated_at` - Timestamps

## API Endpoints

### Register Device Token
```
POST /api/approval-app/device-token/register
Headers:
  Authorization: Bearer {token}
Body:
  {
    "device_token": "FCM_TOKEN_HERE",
    "device_type": "android", // atau "ios"
    "device_id": "optional_device_id",
    "app_version": "1.0.0"
  }
```

### Unregister Device Token
```
POST /api/approval-app/device-token/unregister
Headers:
  Authorization: Bearer {token}
Body:
  {
    "device_token": "FCM_TOKEN_HERE"
  }
```

### Get Device Tokens
```
GET /api/approval-app/device-token
Headers:
  Authorization: Bearer {token}
```

## Cara Kerja

1. **User login di approval app** → App mendapatkan FCM token → Register token ke backend
2. **Notification dibuat di web ERP** → `NotificationObserver` triggered
3. **Observer mengirim push notification ke**:
   - Web device tokens (untuk web browser)
   - Employee device tokens (untuk approval app)
4. **User menerima notification di mobile app**

## Catatan Penting

1. **Device token web dan app terpisah**: 
   - Web menggunakan `web_device_tokens`
   - App menggunakan `employee_device_tokens`
   - Tidak akan saling mengganggu

2. **NotificationObserver sudah diupdate**:
   - Tetap mengirim ke web device tokens (tidak ada perubahan)
   - Sekarang juga mengirim ke employee device tokens
   - Keduanya bekerja secara independen

3. **Tidak akan menyebabkan error di web ERP**:
   - Web device token functionality tetap sama
   - Employee device token hanya ditambahkan, tidak mengubah yang sudah ada

## Testing

1. **Test Web Push Notification**:
   - Pastikan web push notification masih berfungsi normal
   - Tidak ada error di console

2. **Test App Push Notification**:
   - Register device token dari app
   - Buat notification di web ERP
   - Pastikan notification diterima di app

3. **Test Error Handling**:
   - Test dengan invalid token
   - Test dengan user yang tidak punya device token
   - Pastikan tidak ada error yang mengganggu web ERP

## Troubleshooting

### Error: Table doesn't exist
- Pastikan SQL migration sudah dijalankan
- Cek apakah tabel `employee_device_tokens` sudah ada di database

### Error: Route not found
- Pastikan routes sudah ditambahkan di `routes/api.php`
- Clear route cache: `php artisan route:clear`

### Push notification tidak terkirim
- Cek log di `storage/logs/laravel.log`
- Pastikan FCM credentials sudah benar di `.env`
- Pastikan device token sudah terdaftar di database

### Web push notification error
- Pastikan `web_device_tokens` masih berfungsi
- Cek apakah ada perubahan yang tidak sengaja di `NotificationObserver`
- Rollback jika perlu (file backup ada di backup folder)

