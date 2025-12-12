# Setup FCM HTTP v1 API untuk Push Notification

## Masalah
Legacy FCM API (`https://fcm.googleapis.com/fcm/send`) sudah deprecated dan mengembalikan error 404.
Error yang muncul:
```
FCM 404 Error - Legacy FCM API might be disabled for this project
```

## Solusi
Gunakan **FCM HTTP v1 API** dengan Service Account JSON.

## Langkah Setup

### 1. Download Service Account JSON dari Firebase Console

1. Buka [Firebase Console](https://console.firebase.google.com/)
2. Pilih project: **justusgroup-46e18**
3. Klik **Settings (⚙️)** > **Project settings**
4. Tab **Service accounts**
5. Klik **Generate new private key**
6. Download file JSON (contoh: `justusgroup-46e18-firebase-adminsdk-xxxxx.json`)

### 2. Simpan Service Account JSON

Simpan file JSON ke folder `storage/app/` di backend:
```
storage/app/firebase-service-account.json
```

Atau folder lain yang aman (jangan commit ke git!).

### 3. Update .env File

Tambahkan konfigurasi berikut di `.env`:

```env
# FCM HTTP v1 API Configuration (Recommended)
FCM_SERVICE_ACCOUNT_PATH=firebase-service-account.json
FCM_PROJECT_ID=justusgroup-46e18
FCM_USE_V1_API=true

# Legacy FCM API (Deprecated - tidak digunakan lagi)
# FCM_SERVER_KEY=... (tidak perlu jika sudah pakai V1 API)
```

### 4. Verifikasi Konfigurasi

Test dengan command:
```bash
php artisan test:notification-push 26
```

Cek log untuk memastikan menggunakan V1 API:
```bash
tail -f storage/logs/laravel.log | grep "FCM V1"
```

## Catatan Penting

1. **Legacy API sudah deprecated**: 
   - Endpoint `https://fcm.googleapis.com/fcm/send` tidak bisa digunakan lagi
   - Server Key tidak valid untuk project baru
   - Harus menggunakan HTTP v1 API

2. **Service Account JSON**:
   - File ini berisi credentials untuk authenticate ke Firebase
   - Jangan commit ke git (tambahkan ke `.gitignore`)
   - Simpan di folder yang aman

3. **Project ID**:
   - Dari `google-services.json`: `justusgroup-46e18`
   - Harus sama dengan project di Firebase Console

## Troubleshooting

### Error: Service Account file not found
- Pastikan path di `.env` benar
- Pastikan file ada di `storage/app/`
- Cek permission file (harus bisa dibaca)

### Error: Invalid Service Account JSON
- Pastikan file JSON valid
- Pastikan file dari Firebase Console (bukan file lain)
- Cek format JSON

### Error: Project ID mismatch
- Pastikan `FCM_PROJECT_ID` sama dengan project di Firebase Console
- Dari `google-services.json`: `justusgroup-46e18`

### Push notification masih gagal
- Cek log untuk error detail
- Pastikan Service Account memiliki permission "Firebase Cloud Messaging API Admin"
- Pastikan device token valid (dari mobile app)

## Alternatif (Jika Service Account Tidak Tersedia)

Jika tidak bisa setup Service Account, bisa:
1. Gunakan Firebase Admin SDK (lebih kompleks)
2. Atau tunggu sampai Firebase mengaktifkan kembali Legacy API (tidak disarankan)

Tapi **disarankan** untuk setup Service Account karena:
- Legacy API sudah deprecated
- V1 API lebih reliable
- V1 API mendukung fitur lebih banyak
