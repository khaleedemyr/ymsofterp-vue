# FCM Push Notification Troubleshooting

## Error 404 - Not Found

Jika Anda mendapat error 404 dari FCM API, kemungkinan penyebabnya:

### 1. Server Key Tidak Valid atau Tidak Match

**Masalah:**
- Server key yang digunakan tidak valid
- Server key tidak match dengan Firebase project yang digunakan untuk generate device token
- Server key sudah expired atau di-reset

**Solusi:**
1. Buka Firebase Console: https://console.firebase.google.com/
2. Pilih project yang sama dengan yang digunakan di mobile app
3. Settings (gear icon) > Project Settings
4. Tab "Cloud Messaging"
5. Di bagian "Cloud Messaging API (Legacy)", cari "Server key"
6. Copy Server Key yang baru
7. Update di `.env`:
   ```env
   FCM_SERVER_KEY=AAAA...your_new_server_key...
   ```
8. Restart server

### 2. Project Firebase Tidak Match

**Masalah:**
- Server key dari project Firebase A
- Device token di-generate dari project Firebase B
- Keduanya harus dari project yang sama!

**Solusi:**
- Pastikan server key dan device token berasal dari project Firebase yang sama
- Cek di mobile app, pastikan menggunakan Firebase project yang sama

### 3. Legacy API Disabled

**Masalah:**
- Legacy FCM API (`/fcm/send`) mungkin sudah disabled untuk project ini
- Google sedang migrasi ke FCM HTTP v1 API

**Solusi:**
- Gunakan Firebase Admin SDK dengan HTTP v1 API
- Atau enable Legacy API di Firebase Console

## Cara Verifikasi Server Key

### Test dengan cURL:

```bash
curl -X POST https://fcm.googleapis.com/fcm/send \
  -H "Authorization: key=YOUR_SERVER_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "to": "DEVICE_TOKEN",
    "notification": {
      "title": "Test",
      "body": "Test notification"
    }
  }'
```

Jika berhasil, akan return JSON dengan `success: 1`
Jika error 404, berarti server key tidak valid

## Checklist

- [ ] Server key format benar (AAAA... panjang)
- [ ] Server key dari Firebase Console yang sama dengan project mobile app
- [ ] Server key tidak expired (ambil yang terbaru)
- [ ] Device token valid dan aktif
- [ ] Project Firebase di mobile app match dengan server key
- [ ] Legacy FCM API enabled di Firebase Console

## Alternative: Gunakan Firebase Admin SDK

Jika Legacy API tidak bekerja, bisa menggunakan Firebase Admin SDK:

1. Install package:
```bash
composer require kreait/firebase-php
```

2. Download service account JSON dari Firebase Console
3. Update FCMService untuk menggunakan Admin SDK

## Test Manual

Test dengan command:
```bash
php artisan fcm:test --member_id=1
```

Atau test dengan device token langsung:
```bash
php artisan fcm:test --device_token=YOUR_TOKEN --device_type=android
```

