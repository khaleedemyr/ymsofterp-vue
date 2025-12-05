# Firebase Cloud Messaging (FCM) Setup Guide

## Overview
Firebase Cloud Messaging (FCM) adalah layanan gratis dari Google untuk mengirim push notification ke aplikasi mobile (Android & iOS). FCM mendukung notifikasi ke perangkat Android, iOS, dan web.

## Kenapa Firebase?
✅ **Gratis** - Tidak ada biaya untuk penggunaan standar  
✅ **Mudah Setup** - Integrasi yang sederhana  
✅ **Multi Platform** - Support Android, iOS, dan Web  
✅ **Reliable** - Infrastruktur Google yang handal  
✅ **Scalable** - Bisa handle jutaan notifikasi  

## Langkah Setup Firebase

### 1. Buat Firebase Project

1. Buka [Firebase Console](https://console.firebase.google.com/)
2. Klik "Add project" atau pilih project yang sudah ada
3. Isi nama project (contoh: "Member App")
4. Enable Google Analytics (opsional)
5. Klik "Create project"

### 2. Tambahkan Android App

1. Di Firebase Console, klik ikon Android
2. Masukkan:
   - **Package name**: Package name dari aplikasi Android Anda (contoh: `com.yourcompany.memberapp`)
   - **App nickname**: Nama aplikasi (opsional)
   - **Debug signing certificate SHA-1**: (opsional, untuk development)
3. Download `google-services.json`
4. Letakkan file di `android/app/` folder aplikasi Android

### 3. Tambahkan iOS App (jika ada)

1. Di Firebase Console, klik ikon iOS
2. Masukkan:
   - **Bundle ID**: Bundle ID dari aplikasi iOS Anda
   - **App nickname**: Nama aplikasi (opsional)
3. Download `GoogleService-Info.plist`
4. Letakkan file di folder iOS project

### 4. Dapatkan Server Key

1. Di Firebase Console, klik ikon ⚙️ (Settings) > **Project settings**
2. Pilih tab **Cloud Messaging**
3. Di bagian **Cloud Messaging API (Legacy)**, copy **Server key**
4. Atau gunakan **Cloud Messaging API (V1)** untuk setup yang lebih modern

### 5. Setup di Backend Laravel

1. Tambahkan ke file `.env`:
```env
FCM_SERVER_KEY=your-server-key-here
```

2. Server key ini akan digunakan untuk mengirim notifikasi dari backend ke device.

## Setup di Mobile App

### Android (Flutter/React Native/Native)

#### Flutter:
```dart
// Install package
dependencies:
  firebase_messaging: ^14.0.0
  firebase_core: ^2.0.0

// Setup
import 'package:firebase_messaging/firebase_messaging.dart';

FirebaseMessaging messaging = FirebaseMessaging.instance;

// Request permission
NotificationSettings settings = await messaging.requestPermission(
  alert: true,
  badge: true,
  sound: true,
);

// Get token
String? token = await messaging.getToken();
print('FCM Token: $token');

// Send token to backend
// POST /api/member/device-token
{
  "device_token": token,
  "device_type": "android"
}
```

#### React Native:
```bash
npm install @react-native-firebase/app @react-native-firebase/messaging
```

```javascript
import messaging from '@react-native-firebase/messaging';

// Request permission
const authStatus = await messaging().requestPermission();

// Get token
const token = await messaging().getToken();
console.log('FCM Token:', token);

// Send to backend
```

### iOS (Flutter/React Native/Native)

1. **Enable Push Notifications**:
   - Di Xcode, pilih target app
   - Tab "Signing & Capabilities"
   - Klik "+ Capability"
   - Pilih "Push Notifications"

2. **Setup APNs Certificate**:
   - Di Firebase Console > Project Settings > Cloud Messaging
   - Upload APNs Authentication Key atau Certificate

3. **Kode sama seperti Android**, hanya device_type berbeda:
```javascript
{
  "device_token": token,
  "device_type": "ios"
}
```

## API Endpoint untuk Mobile App

### Register Device Token
```
POST /api/member/device-token
Content-Type: application/json
Authorization: Bearer {member_token}

{
  "device_token": "fcm_token_here",
  "device_type": "android", // atau "ios"
  "device_id": "unique_device_id",
  "app_version": "1.0.0"
}
```

### Update Device Token (jika token berubah)
```
PUT /api/member/device-token/{id}
{
  "device_token": "new_token",
  "is_active": true
}
```

## Testing

### Test dari Backend
1. Login ke admin panel
2. Buka menu "Member App Settings" > Tab "Push Notification"
3. Buat notifikasi baru
4. Pilih target (All Members, Specific, atau Filter)
5. Klik "Send"

### Test dengan cURL
```bash
curl -X POST https://fcm.googleapis.com/fcm/send \
  -H "Authorization: key=YOUR_SERVER_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "to": "DEVICE_TOKEN",
    "notification": {
      "title": "Test Notification",
      "body": "This is a test message"
    }
  }'
```

## Best Practices

1. **Token Management**:
   - Token bisa berubah, selalu update ke backend
   - Handle token refresh di mobile app
   - Deactivate token lama saat user logout

2. **Notification Handling**:
   - Handle notification saat app di foreground
   - Handle notification saat app di background
   - Handle notification saat app ditutup

3. **Error Handling**:
   - Handle invalid token (token expired)
   - Handle network errors
   - Retry mechanism untuk failed notifications

4. **Security**:
   - Jangan expose Server Key di frontend
   - Gunakan HTTPS untuk semua API calls
   - Validate device token di backend

## Troubleshooting

### Token tidak terdaftar
- Pastikan mobile app sudah request permission
- Pastikan token dikirim ke backend dengan benar
- Check database apakah token tersimpan

### Notifikasi tidak muncul
- Check FCM Server Key di .env
- Check device token valid
- Check permission di mobile app
- Check Firebase Console untuk error logs

### Notifikasi muncul tapi tidak bisa diklik
- Pastikan action_url di-set dengan benar
- Handle deep linking di mobile app
- Check notification data payload

## Resources

- [FCM Documentation](https://firebase.google.com/docs/cloud-messaging)
- [FCM REST API](https://firebase.google.com/docs/cloud-messaging/http-server-ref)
- [Flutter Firebase Messaging](https://firebase.flutter.dev/docs/messaging/overview)
- [React Native Firebase](https://rnfirebase.io/messaging/usage)

