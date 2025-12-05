# Setup Notification Icon

## Overview
Notifikasi FCM sekarang menggunakan app icon sebagai icon notifikasi. Untuk hasil terbaik, disarankan membuat icon notification khusus yang monochrome (putih dengan background transparan).

## Current Setup
- **Backend (FCM V1 API)**: Menggunakan `ic_launcher` sebagai notification icon
- **Flutter App**: Menggunakan `@mipmap/ic_launcher` untuk local notifications

## Android Notification Icon Requirements
1. Icon harus berupa drawable resource (biasanya di `res/drawable/` atau `res/mipmap/`)
2. Untuk Android 5.0+ (API 21+), icon harus monochrome (putih dengan background transparan)
3. Ukuran yang disarankan: 24x24dp untuk mdpi, 36x36dp untuk hdpi, 48x48dp untuk xhdpi, 72x72dp untuk xxhdpi, 96x96dp untuk xxxhdpi

## Membuat Notification Icon Khusus (Opsional)

### 1. Buat Icon Notification
Buat icon notification monochrome (putih dengan background transparan) dengan ukuran:
- mdpi: 24x24px
- hdpi: 36x36px
- xhdpi: 48x48px
- xxhdpi: 72x72px
- xxxhdpi: 96x96px

### 2. Simpan Icon di Android Project
Simpan icon di:
```
frontend/android/app/src/main/res/drawable-mdpi/ic_notification.png
frontend/android/app/src/main/res/drawable-hdpi/ic_notification.png
frontend/android/app/src/main/res/drawable-xhdpi/ic_notification.png
frontend/android/app/src/main/res/drawable-xxhdpi/ic_notification.png
frontend/android/app/src/main/res/drawable-xxxhdpi/ic_notification.png
```

### 3. Update Backend Code
Jika sudah membuat icon notification khusus, update `FCMV1Service.php`:
```php
'icon' => 'ic_notification', // Ganti dari 'ic_launcher' ke 'ic_notification'
```

### 4. Update Flutter Code (Optional)
Jika ingin menggunakan icon khusus untuk local notifications juga, update `fcm_service.dart`:
```dart
icon: '@drawable/ic_notification', // Ganti dari '@mipmap/ic_launcher'
```

## Testing
Setelah update, test notifikasi dan pastikan icon muncul dengan benar di notification tray.

## Catatan
- Icon notification harus monochrome (putih) untuk Android 5.0+
- Background harus transparan
- Icon akan otomatis di-tint oleh sistem Android sesuai dengan tema (light/dark)

