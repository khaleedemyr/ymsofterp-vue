# FCM Push Notification Setup Guide

## Masalah: App Menggunakan Project Firebase yang Berbeda

Jika mobile app menggunakan Firebase project yang berbeda dengan server key yang ada, ada 2 solusi:

### Solusi 1: Gunakan Server Key dari Project yang Sama dengan Mobile App (RECOMMENDED)

**Langkah-langkah:**

1. **Cek Firebase Project di Mobile App**
   - Buka file konfigurasi Firebase di mobile app (biasanya `google-services.json` untuk Android atau `GoogleService-Info.plist` untuk iOS)
   - Atau cek di Firebase Console, lihat project mana yang digunakan mobile app

2. **Ambil Server Key dari Project yang Sama**
   - Buka Firebase Console: https://console.firebase.google.com/
   - Pilih project yang sama dengan yang digunakan di mobile app
   - Settings (gear icon) > Project Settings
   - Tab "Cloud Messaging"
   - Di bagian "Cloud Messaging API (Legacy)", copy "Server key"
   - Format: `AAAA...` (panjang)

3. **Update .env**
   ```env
   FCM_SERVER_KEY=AAAA...server_key_dari_project_yang_sama...
   ```

4. **Restart Server**
   ```bash
   # Restart Laravel server
   php artisan config:clear
   php artisan cache:clear
   ```

### Solusi 2: Setup Firebase Project Baru (Jika Perlu)

Jika ingin membuat project Firebase baru khusus untuk backend:

1. **Buat Firebase Project Baru**
   - Buka: https://console.firebase.google.com/
   - Klik "Add project" atau "Create a project"
   - Isi nama project
   - Pilih Google Analytics (optional)
   - Create project

2. **Enable Cloud Messaging**
   - Di Firebase Console, pilih project baru
   - Settings > Project Settings
   - Tab "Cloud Messaging"
   - Enable "Cloud Messaging API (Legacy)" jika belum enabled
   - Copy "Server key"

3. **Update Mobile App (Jika Perlu)**
   - Download `google-services.json` (Android) atau `GoogleService-Info.plist` (iOS)
   - Update konfigurasi Firebase di mobile app
   - Rebuild mobile app

4. **Update Backend .env**
   ```env
   FCM_SERVER_KEY=AAAA...server_key_dari_project_baru...
   ```

## Cara Cek Project Firebase di Mobile App

### Android:
- File: `android/app/google-services.json`
- Cari field `project_id` atau `project_number`

### iOS:
- File: `ios/Runner/GoogleService-Info.plist`
- Cari key `PROJECT_ID` atau `PROJECT_NUMBER`

### Flutter:
- File: `android/app/google-services.json` (Android)
- File: `ios/Runner/GoogleService-Info.plist` (iOS)

## Verifikasi Setup

Setelah setup, test dengan:

```bash
# Test konfigurasi
php artisan fcm:check-setup

# Test kirim notifikasi
php artisan fcm:test --member_id=1
```

## Checklist

- [ ] Server key dari Firebase project yang sama dengan mobile app
- [ ] Cloud Messaging API (Legacy) enabled di Firebase Console
- [ ] Server key sudah di-set di `.env`
- [ ] Server sudah di-restart setelah update `.env`
- [ ] Device token sudah ter-register di database
- [ ] Member `allow_notification = 1`

## Troubleshooting

### Error 404:
- Server key tidak valid atau tidak match dengan project
- Cek kembali server key di Firebase Console
- Pastikan project Firebase sama dengan mobile app

### Error 401:
- Server key salah atau expired
- Ambil server key baru dari Firebase Console

### No notification received:
- Cek device token valid dan aktif
- Cek member `allow_notification = 1`
- Cek log untuk error detail

