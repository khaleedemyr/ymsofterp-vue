# Mobile App Integration Guide - Device Token Registration

## Overview
Device token harus di-register ke backend ketika member login atau app pertama kali dibuka. Token ini digunakan untuk mengirim push notification ke device member.

## Kapan Device Token Di-Register?

### 1. **Saat Member Login** ✅ (Recommended)
- Setelah member berhasil login
- Dapatkan FCM token dari Firebase
- Kirim token ke backend

### 2. **Saat App Pertama Kali Dibuka**
- Jika member sudah login sebelumnya (auto-login)
- Dapatkan FCM token
- Update token ke backend

### 3. **Saat Token Refresh**
- FCM token bisa berubah
- App harus detect perubahan token
- Update token ke backend

### 4. **Saat Member Logout**
- Deactivate token (tidak delete, untuk history)
- Member tidak akan menerima notifikasi lagi

## API Endpoints

### 1. Register Device Token
**POST** `/api/mobile/member/device-token/register`

**Headers:**
```
Authorization: Bearer {member_access_token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "device_token": "fcm_token_dari_firebase",
  "device_type": "android", // atau "ios" atau "web"
  "device_id": "unique_device_id", // optional
  "app_version": "1.0.0" // optional
}
```

**Response Success (201):**
```json
{
  "success": true,
  "message": "Device token registered successfully",
  "data": {
    "id": 1,
    "member_id": 123,
    "device_token": "fcm_token_dari_firebase",
    "device_type": "android",
    "device_id": "unique_device_id",
    "app_version": "1.0.0",
    "is_active": true,
    "last_used_at": "2024-01-15T10:30:00.000000Z",
    "created_at": "2024-01-15T10:30:00.000000Z",
    "updated_at": "2024-01-15T10:30:00.000000Z"
  }
}
```

**Response Error (422):**
```json
{
  "success": false,
  "message": "Validation error",
  "errors": {
    "device_token": ["The device token field is required."]
  }
}
```

### 2. Unregister Device Token (Logout)
**POST** `/api/mobile/member/device-token/unregister`

**Headers:**
```
Authorization: Bearer {member_access_token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "device_token": "fcm_token_yang_akan_di_unregister"
}
```

**Response Success:**
```json
{
  "success": true,
  "message": "Device token unregistered successfully"
}
```

### 3. Get All Active Tokens
**GET** `/api/mobile/member/device-token`

**Headers:**
```
Authorization: Bearer {member_access_token}
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "member_id": 123,
      "device_token": "token1",
      "device_type": "android",
      "is_active": true
    },
    {
      "id": 2,
      "member_id": 123,
      "device_token": "token2",
      "device_type": "ios",
      "is_active": true
    }
  ]
}
```

## Contoh Implementasi di Mobile App

### Flutter Example

```dart
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';

class DeviceTokenService {
  final String baseUrl = 'https://your-api.com/api/member';
  final String? accessToken; // From login

  // Register token after login
  Future<void> registerDeviceToken() async {
    try {
      // Get FCM token
      FirebaseMessaging messaging = FirebaseMessaging.instance;
      
      // Request permission
      NotificationSettings settings = await messaging.requestPermission(
        alert: true,
        badge: true,
        sound: true,
      );

      if (settings.authorizationStatus == AuthorizationStatus.authorized) {
        // Get token
        String? token = await messaging.getToken();
        
        if (token != null) {
          // Send to backend
          await _sendTokenToBackend(token);
        }

        // Listen for token refresh
        messaging.onTokenRefresh.listen((newToken) {
          _sendTokenToBackend(newToken);
        });
      }
    } catch (e) {
      print('Error registering device token: $e');
    }
  }

  Future<void> _sendTokenToBackend(String fcmToken) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/mobile/member/device-token/register'),
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

      if (response.statusCode == 201) {
        print('Device token registered successfully');
      } else {
        print('Failed to register token: ${response.body}');
      }
    } catch (e) {
      print('Error sending token to backend: $e');
    }
  }

  // Unregister on logout
  Future<void> unregisterDeviceToken(String fcmToken) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/mobile/member/device-token/unregister'),
        headers: {
          'Authorization': 'Bearer $accessToken',
          'Content-Type': 'application/json',
        },
        body: jsonEncode({
          'device_token': fcmToken,
        }),
      );

      if (response.statusCode == 200) {
        print('Device token unregistered');
      }
    } catch (e) {
      print('Error unregistering token: $e');
    }
  }

  Future<String> _getDeviceId() async {
    // Use device_info_plus package
    // return deviceId;
    return 'device_unique_id';
  }

  Future<String> _getAppVersion() async {
    // Use package_info_plus package
    // return packageInfo.version;
    return '1.0.0';
  }
}

// Usage in login flow
void onLoginSuccess(String accessToken) async {
  final tokenService = DeviceTokenService(accessToken: accessToken);
  await tokenService.registerDeviceToken();
}

// Usage in logout flow
void onLogout(String fcmToken, String accessToken) async {
  final tokenService = DeviceTokenService(accessToken: accessToken);
  await tokenService.unregisterDeviceToken(fcmToken);
}
```

### React Native Example

```javascript
import messaging from '@react-native-firebase/messaging';
import {Platform} from 'react-native';

class DeviceTokenService {
  constructor(accessToken) {
    this.baseUrl = 'https://your-api.com/api/member';
    this.accessToken = accessToken;
  }

  async registerDeviceToken() {
    try {
      // Request permission
      const authStatus = await messaging().requestPermission();
      
      if (authStatus === messaging.AuthorizationStatus.AUTHORIZED) {
        // Get token
        const token = await messaging().getToken();
        
        if (token) {
          await this.sendTokenToBackend(token);
        }

        // Listen for token refresh
        messaging().onTokenRefresh(async (newToken) => {
          await this.sendTokenToBackend(newToken);
        });
      }
    } catch (error) {
      console.error('Error registering device token:', error);
    }
  }

  async sendTokenToBackend(fcmToken) {
    try {
      const response = await fetch(`${this.baseUrl}/device-token/register`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${this.accessToken}`,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          device_token: fcmToken,
          device_type: Platform.OS === 'android' ? 'android' : 'ios',
          device_id: await this.getDeviceId(),
          app_version: '1.0.0',
        }),
      });

      const data = await response.json();
      if (response.ok) {
        console.log('Device token registered:', data);
      } else {
        console.error('Failed to register token:', data);
      }
    } catch (error) {
      console.error('Error sending token:', error);
    }
  }

  async unregisterDeviceToken(fcmToken) {
    try {
      const response = await fetch(`${this.baseUrl}/device-token/unregister`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${this.accessToken}`,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          device_token: fcmToken,
        }),
      });

      const data = await response.json();
      if (response.ok) {
        console.log('Device token unregistered:', data);
      }
    } catch (error) {
      console.error('Error unregistering token:', error);
    }
  }

  async getDeviceId() {
    // Use react-native-device-info
    // return DeviceInfo.getUniqueId();
    return 'device_unique_id';
  }
}

// Usage
const onLoginSuccess = async (accessToken) => {
  const tokenService = new DeviceTokenService(accessToken);
  await tokenService.registerDeviceToken();
};

const onLogout = async (fcmToken, accessToken) => {
  const tokenService = new DeviceTokenService(accessToken);
  await tokenService.unregisterDeviceToken(fcmToken);
};
```

## Flow Diagram

```
1. Member Login
   ↓
2. Get FCM Token from Firebase
   ↓
3. POST /api/member/device-token/register
   ↓
4. Backend menyimpan token
   ↓
5. Admin kirim push notification
   ↓
6. Backend kirim ke FCM dengan token
   ↓
7. FCM kirim ke device member
   ↓
8. Notifikasi muncul di HP member
```

## Best Practices

1. **Register setelah login sukses**
   - Jangan register sebelum login
   - Pastikan member sudah authenticated

2. **Handle token refresh**
   - FCM token bisa berubah
   - Always listen untuk `onTokenRefresh`
   - Update token ke backend

3. **Unregister saat logout**
   - Deactivate token saat logout
   - Member tidak akan terima notifikasi lagi

4. **Error handling**
   - Handle network errors
   - Retry mechanism jika gagal
   - Log errors untuk debugging

5. **Multiple devices**
   - Satu member bisa punya multiple devices
   - Semua token aktif akan terima notifikasi
   - Unregister hanya token device yang logout

## Testing

### Test dengan Postman/cURL

```bash
# Register token
curl -X POST https://your-api.com/api/mobile/member/device-token/register \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "device_token": "test_fcm_token_123",
    "device_type": "android",
    "device_id": "test_device_id",
    "app_version": "1.0.0"
  }'
```

## Notes

- Device token harus unique (UNIQUE constraint di database)
- Jika token sudah ada, akan di-update (bukan create baru)
- Token di-deactivate saat logout (tidak di-delete)
- Satu member bisa punya multiple active tokens (multiple devices)

