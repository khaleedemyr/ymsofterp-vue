# Konfigurasi FCM di .env

## Status
✅ Service Account JSON sudah ada di: `storage/app/justusgroup-46e18-firebase-adminsdk-fbsvc-b06710fd56.json`
✅ FCM V1 API sudah berfungsi untuk employee devices
⚠️ Web devices masih menggunakan Legacy API (perlu update .env)

## Update .env File

Tambahkan atau update konfigurasi berikut di file `.env`:

```env
# FCM HTTP v1 API Configuration (REQUIRED - Legacy API sudah deprecated)
FCM_SERVICE_ACCOUNT_PATH=justusgroup-46e18-firebase-adminsdk-fbsvc-b06710fd56.json
FCM_PROJECT_ID=justusgroup-46e18
FCM_USE_V1_API=true

# Legacy FCM API (DEPRECATED - tidak digunakan lagi, bisa dihapus)
# FCM_SERVER_KEY=... (tidak perlu lagi)
```

## Verifikasi

1. **Cek file Service Account ada:**
   ```bash
   ls -la storage/app/justusgroup-46e18-firebase-adminsdk-fbsvc-b06710fd56.json
   ```

2. **Clear config cache:**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

3. **Test push notification:**
   ```bash
   php artisan test:notification-push 26
   ```

4. **Cek log:**
   ```bash
   tail -f storage/logs/laravel.log | grep "FCM V1"
   ```

   Harus muncul:
   - "FCM V1 access token obtained"
   - "Using FCM V1 API"
   - "FCM V1 notification sent successfully"

## Catatan

- **Legacy API sudah deprecated**: Endpoint `https://fcm.googleapis.com/fcm/send` tidak bisa digunakan lagi
- **Harus menggunakan V1 API**: Semua device (web dan mobile) sekarang menggunakan V1 API
- **Service Account path**: Relatif dari `storage/app/` atau bisa absolute path

## Troubleshooting

### Error: Service Account file not found
- Pastikan path di `.env` benar
- File harus ada di `storage/app/`
- Cek permission file

### Error: Invalid Service Account JSON
- Pastikan file JSON valid
- File harus dari Firebase Console
- Cek format JSON

### Masih error 404
- Pastikan sudah `php artisan config:clear`
- Pastikan `.env` sudah di-update
- Restart web server jika perlu

