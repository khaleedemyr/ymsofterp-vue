# Panduan Setup FCM Token di Mobile App

## 🎯 Tujuan

Mendapatkan **token FCM yang valid** dari Firebase SDK di mobile app, bukan test token.

## 📱 Prerequisites

1. ✅ Firebase project sudah dibuat (`justusgroup-46e18`)
2. ✅ Mobile app sudah ditambahkan ke Firebase project
3. ✅ Firebase SDK sudah di-install di mobile app
4. ✅ `google-services.json` (Android) atau `GoogleService-Info.plist` (iOS) sudah di-download dan ditambahkan ke project

## 🔧 Setup di Mobile App

### 1. Install Firebase SDK

#### Flutter
```yaml
# pubspec.yaml
dependencies:
  firebase_core: ^2.24.2
  firebase_messaging: ^14.7.9
```

#### React Native
```bash
npm install @react-native-firebase/app @react-native-firebase/messaging
```

#### Native Android (Kotlin/Java)
```gradle
// build.gradle (project level)
dependencies {
    classpath 'com.google.gms:google-services:4.4.0'
}

// build.gradle (app level)
apply plugin: 'com.google.gms.google-services'
dependencies {
    implementation 'com.google.firebase:firebase-messaging:23.3.1'
}
```

#### Native iOS (Swift)
```ruby
# Podfile
pod 'Firebase/Messaging'
```

### 2. Request Permission (iOS)

**WAJIB untuk iOS!** Tanpa permission, token tidak akan didapat.

```swift
// iOS - Swift
import UserNotifications

UNUserNotificationCenter.current().requestAuthorization(options: [.alert, .sound, .badge]) { granted, error in
    if granted {
        print("Notification permission granted")
    }
}
```

```dart
// Flutter
import 'package:firebase_messaging/firebase_messaging.dart';

await FirebaseMessaging.instance.requestPermission(
  alert: true,
  badge: true,
  sound: true,
);
```

### 3. Dapatkan FCM Token

#### Flutter
```dart
import 'package:firebase_messaging/firebase_messaging.dart';

// Inisialisasi Firebase
await Firebase.initializeApp();

// Request permission (iOS)
await FirebaseMessaging.instance.requestPermission();

// Dapatkan token
String? fcmToken = await FirebaseMessaging.instance.getToken();

if (fcmToken != null) {
  print('FCM Token: $fcmToken');
  // Kirim token ke backend
  await registerTokenToBackend(fcmToken);
} else {
  print('Failed to get FCM token');
}

// Listen untuk token refresh
FirebaseMessaging.instance.onTokenRefresh.listen((newToken) {
  print('FCM Token refreshed: $newToken');
  // Update token ke backend
  registerTokenToBackend(newToken);
});
```

#### React Native
```javascript
import messaging from '@react-native-firebase/messaging';

// Request permission (iOS)
const authStatus = await messaging().requestPermission();
const enabled =
  authStatus === messaging.AuthorizationStatus.AUTHORIZED ||
  authStatus === messaging.AuthorizationStatus.PROVISIONAL;

if (enabled) {
  // Dapatkan token
  const fcmToken = await messaging().getToken();
  console.log('FCM Token:', fcmToken);
  
  // Kirim token ke backend
  await registerTokenToBackend(fcmToken);
}

// Listen untuk token refresh
messaging().onTokenRefresh(token => {
  console.log('FCM Token refreshed:', token);
  registerTokenToBackend(token);
});
```

#### Native Android (Kotlin)
```kotlin
import com.google.firebase.messaging.FirebaseMessaging

FirebaseMessaging.getInstance().token.addOnCompleteListener { task ->
    if (!task.isSuccessful) {
        Log.w(TAG, "Fetching FCM registration token failed", task.exception)
        return@addOnCompleteListener
    }

    // Get new FCM registration token
    val token = task.result
    Log.d(TAG, "FCM Token: $token")
    
    // Kirim token ke backend
    registerTokenToBackend(token)
}
```

#### Native iOS (Swift)
```swift
import FirebaseMessaging

// Request permission
UNUserNotificationCenter.current().requestAuthorization(options: [.alert, .sound, .badge]) { granted, error in
    if granted {
        // Dapatkan token
        Messaging.messaging().token { token, error in
            if let error = error {
                print("Error fetching FCM registration token: \(error)")
            } else if let token = token {
                print("FCM Token: \(token)")
                // Kirim token ke backend
                registerTokenToBackend(token)
            }
        }
    }
}

// Listen untuk token refresh
Messaging.messaging().token { token, error in
    if let error = error {
        print("Error fetching FCM registration token: \(error)")
    } else if let token = token {
        print("FCM registration token: \(token)")
    }
}
```

### 4. Kirim Token ke Backend

**Endpoint:** `POST /api/mobile/device-token/register`

**Headers:**
```
Authorization: Bearer {access_token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "device_token": "dK8xYz9AbC3DeF4GhI5JkL6MnO7PqR8StU9VwX0Yz1AbC2DeF3GhI4JkL5MnO6PqR7StU8VwX9Yz0AbC1DeF2GhI3JkL4MnO5PqR6StU7VwX8Yz9AbC0DeF1GhI2JkL3MnO4PqR5StU6VwX7Yz8AbC9DeF0GhI1JkL2MnO3PqR4StU5VwX6Yz7AbC8DeF9GhI0JkL1MnO2PqR3StU4VwX5Yz6AbC7",
  "device_type": "android",  // atau "ios"
  "device_id": "optional-device-id",
  "app_version": "1.0.0"
}
```

**Response Success:**
```json
{
  "success": true,
  "message": "Device token registered successfully",
  "data": {
    "id": 123,
    "device_type": "android",
    "is_active": true
  }
}
```

**Response Error (Invalid Token):**
```json
{
  "success": false,
  "message": "Invalid FCM token format. Token must be a valid FCM registration token from Firebase SDK, not a test/dummy token.",
  "hint": "Make sure you are using the actual FCM token from FirebaseMessaging.instance.getToken(), not a test value."
}
```

### 5. Contoh Implementasi Lengkap (Flutter)

```dart
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';

class FCMService {
  static Future<void> initialize() async {
    // Inisialisasi Firebase
    await Firebase.initializeApp();
    
    // Request permission (iOS)
    await FirebaseMessaging.instance.requestPermission(
      alert: true,
      badge: true,
      sound: true,
    );
    
    // Dapatkan token
    await getAndRegisterToken();
    
    // Listen untuk token refresh
    FirebaseMessaging.instance.onTokenRefresh.listen((newToken) {
      print('FCM Token refreshed: $newToken');
      registerTokenToBackend(newToken);
    });
  }
  
  static Future<void> getAndRegisterToken() async {
    try {
      String? fcmToken = await FirebaseMessaging.instance.getToken();
      
      if (fcmToken != null) {
        print('FCM Token obtained: ${fcmToken.substring(0, 20)}...');
        await registerTokenToBackend(fcmToken);
      } else {
        print('Failed to get FCM token');
      }
    } catch (e) {
      print('Error getting FCM token: $e');
    }
  }
  
  static Future<void> registerTokenToBackend(String fcmToken) async {
    try {
      // Ganti dengan base URL backend Anda
      final url = Uri.parse('https://your-backend.com/api/mobile/device-token/register');
      
      // Ganti dengan access token dari user yang login
      final accessToken = 'your-access-token';
      
      final response = await http.post(
        url,
        headers: {
          'Authorization': 'Bearer $accessToken',
          'Content-Type': 'application/json',
        },
        body: jsonEncode({
          'device_token': fcmToken,
          'device_type': Platform.isAndroid ? 'android' : 'ios',
          'device_id': await _getDeviceId(),
          'app_version': await _getAppVersion(),
        }),
      );
      
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        print('Token registered successfully: ${data['message']}');
      } else {
        print('Failed to register token: ${response.body}');
      }
    } catch (e) {
      print('Error registering token: $e');
    }
  }
  
  static Future<String> _getDeviceId() async {
    // Implementasi untuk mendapatkan device ID
    // Bisa pakai package seperti device_info_plus
    return 'device-id';
  }
  
  static Future<String> _getAppVersion() async {
    // Implementasi untuk mendapatkan app version
    // Bisa pakai package seperti package_info_plus
    return '1.0.0';
  }
}

// Panggil di main.dart atau saat app start
void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await FCMService.initialize();
  runApp(MyApp());
}
```

## ✅ Checklist

- [ ] Firebase SDK sudah di-install
- [ ] `google-services.json` / `GoogleService-Info.plist` sudah ditambahkan
- [ ] Permission sudah di-request (khusus iOS)
- [ ] FCM token sudah didapat dari Firebase SDK
- [ ] Token sudah dikirim ke backend via API
- [ ] Token refresh sudah di-handle
- [ ] **TIDAK menggunakan test token atau dummy token**

## 🚫 Yang TIDAK Boleh

❌ **JANGAN** gunakan test token seperti:
- `test_device_123`
- `test_token_abc`
- Token yang dibuat manual

❌ **JANGAN** hardcode token di kode

✅ **HARUS** gunakan token dari Firebase SDK:
- `FirebaseMessaging.instance.getToken()` (Flutter)
- `messaging().getToken()` (React Native)
- `FirebaseMessaging.getInstance().token` (Android)
- `Messaging.messaging().token` (iOS)

## 🔍 Verifikasi Token

Token FCM yang valid biasanya:
- ✅ Panjang (150+ karakter)
- ✅ Format: alphanumeric dengan beberapa karakter khusus
- ✅ **TIDAK** dimulai dengan `test_` atau `test_device_`
- ✅ Didapat langsung dari Firebase SDK

Contoh token valid:
```
dK8xYz9AbC3DeF4GhI5JkL6MnO7PqR8StU9VwX0Yz1AbC2DeF3GhI4JkL5MnO6PqR7StU8VwX9Yz0AbC1DeF2GhI3JkL4MnO5PqR6StU7VwX8Yz9AbC0DeF1GhI2JkL3MnO4PqR5StU6VwX7Yz8AbC9DeF0GhI1JkL2MnO3PqR4StU5VwX6Yz7AbC8DeF9GhI0JkL1MnO2PqR3StU4VwX5Yz6AbC7
```

## 🐛 Troubleshooting

### Token null atau kosong
- **iOS:** Pastikan permission sudah di-request dan granted
- **Android:** Pastikan `google-services.json` sudah benar
- Pastikan Firebase project ID sama dengan backend

### Token ditolak oleh backend
- Pastikan token dari Firebase SDK, bukan test token
- Pastikan token panjangnya cukup (min 50 karakter)
- Pastikan token tidak dimulai dengan `test_`

### Token tidak terkirim notifikasi
- Pastikan token sudah terdaftar di backend
- Pastikan member `allow_notification = true`
- Pastikan Firebase project ID sama antara mobile app dan backend

## 📞 Support

Jika masih ada masalah, cek:
1. Log backend: `storage/logs/laravel.log`
2. Firebase Console: https://console.firebase.google.com/
3. Test dengan: `php artisan fcm:test --member_id=1`

