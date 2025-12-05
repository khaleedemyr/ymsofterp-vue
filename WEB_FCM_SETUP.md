# Web FCM Push Notification Setup

Panduan setup push notification FCM untuk web browser di aplikasi ymsofterp.

## 📋 Daftar Isi

1. [Overview](#overview)
2. [Database Setup](#database-setup)
3. [Backend Setup](#backend-setup)
4. [Frontend Setup](#frontend-setup)
5. [Testing](#testing)

---

## 1. Overview

Sistem ini akan mengirim push notification FCM ke web browser setiap kali ada insert ke table `notifications`.

**Flow:**
1. Insert ke table `notifications` → Trigger Observer
2. Observer mengambil device tokens dari table `web_device_tokens`
3. Observer mengirim FCM push notification ke semua device tokens user tersebut
4. Browser menerima notification dan menampilkannya

---

## 2. Database Setup

### 2.1. Create Table

Jalankan query SQL berikut:

```sql
-- File: database/sql/create_web_device_tokens_table.sql
CREATE TABLE IF NOT EXISTS `web_device_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `device_token` text NOT NULL COMMENT 'FCM token from web browser',
  `browser` varchar(50) DEFAULT NULL COMMENT 'Browser name (Chrome, Firefox, Safari, etc)',
  `user_agent` text DEFAULT NULL COMMENT 'Full user agent string',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_user_active` (`user_id`, `is_active`),
  CONSTRAINT `fk_web_device_token_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.2. Update Notification Table (jika belum ada field title)

Jika table `notifications` belum ada field `title`, tambahkan:

```sql
ALTER TABLE `notifications` 
ADD COLUMN `title` varchar(255) NULL DEFAULT NULL AFTER `type`;
```

---

## 3. Backend Setup

### 3.1. Files yang Sudah Dibuat

✅ **Model**: `app/Models/WebDeviceToken.php`
✅ **Controller**: `app/Http/Controllers/Api/WebDeviceTokenController.php`
✅ **Observer**: `app/Observers/NotificationObserver.php`
✅ **Routes**: Sudah ditambahkan di `routes/api.php`

### 3.2. Routes

```
POST /api/web/device-token/register
POST /api/web/device-token/unregister
```

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body (register):**
```json
{
  "device_token": "FCM_TOKEN_FROM_BROWSER",
  "browser": "Chrome",
  "user_agent": "Mozilla/5.0..."
}
```

### 3.3. Observer

Observer sudah ter-register di `AppServiceProvider.php`:
- Setiap insert ke table `notifications` akan trigger `NotificationObserver::created()`
- Observer akan mengirim FCM push notification ke semua device tokens user tersebut

---

## 4. Frontend Setup

### 4.1. Install Firebase SDK

Di frontend web (Vue/React/HTML), install Firebase:

```bash
npm install firebase
```

### 4.2. Initialize Firebase

```javascript
// firebase-config.js
import { initializeApp } from 'firebase/app';
import { getMessaging, getToken, onMessage } from 'firebase/messaging';

const firebaseConfig = {
  apiKey: "YOUR_API_KEY",
  authDomain: "YOUR_AUTH_DOMAIN",
  projectId: "YOUR_PROJECT_ID",
  storageBucket: "YOUR_STORAGE_BUCKET",
  messagingSenderId: "YOUR_MESSAGING_SENDER_ID",
  appId: "YOUR_APP_ID"
};

const app = initializeApp(firebaseConfig);
const messaging = getMessaging(app);
```

### 4.3. Request Permission & Get Token

```javascript
// request-notification-permission.js
import { getMessaging, getToken } from 'firebase/messaging';

async function requestNotificationPermission() {
  try {
    const permission = await Notification.requestPermission();
    
    if (permission === 'granted') {
      const messaging = getMessaging();
      const token = await getToken(messaging, {
        vapidKey: 'YOUR_VAPID_KEY' // Dapatkan dari Firebase Console
      });
      
      if (token) {
        // Register token ke backend
        await registerTokenToBackend(token);
      }
    }
  } catch (error) {
    console.error('Error requesting notification permission:', error);
  }
}

async function registerTokenToBackend(token) {
  try {
    const response = await fetch('/api/web/device-token/register', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${getAuthToken()}` // Token dari auth system
      },
      body: JSON.stringify({
        device_token: token,
        browser: getBrowserName(),
        user_agent: navigator.userAgent
      })
    });
    
    const data = await response.json();
    console.log('Token registered:', data);
  } catch (error) {
    console.error('Error registering token:', error);
  }
}

function getBrowserName() {
  const ua = navigator.userAgent;
  if (ua.includes('Chrome')) return 'Chrome';
  if (ua.includes('Firefox')) return 'Firefox';
  if (ua.includes('Safari')) return 'Safari';
  if (ua.includes('Edge')) return 'Edge';
  return 'Unknown';
}
```

### 4.4. Handle Foreground Messages

```javascript
// handle-notifications.js
import { getMessaging, onMessage } from 'firebase/messaging';

const messaging = getMessaging();

onMessage(messaging, (payload) => {
  console.log('Message received:', payload);
  
  // Tampilkan notification
  const notificationTitle = payload.notification.title;
  const notificationOptions = {
    body: payload.notification.body,
    icon: '/icon-192x192.png', // Icon untuk notification
    badge: '/badge-72x72.png',
    data: payload.data
  };
  
  if ('Notification' in window && Notification.permission === 'granted') {
    new Notification(notificationTitle, notificationOptions);
  }
});
```

### 4.5. Service Worker (untuk background notifications)

Buat file `public/firebase-messaging-sw.js`:

```javascript
// firebase-messaging-sw.js
importScripts('https://www.gstatic.com/firebasejs/9.0.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/9.0.0/firebase-messaging-compat.js');

firebase.initializeApp({
  apiKey: "YOUR_API_KEY",
  authDomain: "YOUR_AUTH_DOMAIN",
  projectId: "YOUR_PROJECT_ID",
  storageBucket: "YOUR_STORAGE_BUCKET",
  messagingSenderId: "YOUR_MESSAGING_SENDER_ID",
  appId: "YOUR_APP_ID"
});

const messaging = firebase.messaging();

messaging.onBackgroundMessage((payload) => {
  console.log('Background message received:', payload);
  
  const notificationTitle = payload.notification.title;
  const notificationOptions = {
    body: payload.notification.body,
    icon: '/icon-192x192.png',
    badge: '/badge-72x72.png',
    data: payload.data
  };
  
  return self.registration.showNotification(notificationTitle, notificationOptions);
});
```

---

## 5. Testing

### 5.1. Test Token Registration

1. Buka web app di browser
2. Request notification permission
3. Cek di database: table `web_device_tokens` harus ada data

### 5.2. Test Push Notification

1. Insert notification ke table `notifications`:
```sql
INSERT INTO notifications (user_id, type, title, message, url, is_read, created_at, updated_at)
VALUES (1, 'general', 'Test Notification', 'This is a test notification', '/dashboard', 0, NOW(), NOW());
```

2. Cek log Laravel untuk melihat apakah FCM dikirim
3. Browser harus menerima push notification

### 5.3. Test via API

```bash
# Register token
curl -X POST http://localhost/api/web/device-token/register \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "device_token": "YOUR_FCM_TOKEN",
    "browser": "Chrome"
  }'
```

---

## 6. Troubleshooting

### 6.1. Token Tidak Terdaftar

- Pastikan user sudah login (auth token valid)
- Cek browser console untuk error
- Cek Laravel log untuk error

### 6.2. Notification Tidak Muncul

- Pastikan permission sudah granted
- Cek service worker sudah ter-register
- Cek FCM token masih valid
- Cek Laravel log untuk error FCM

### 6.3. Error: "FCM API Key not configured"

- Pastikan FCM sudah dikonfigurasi di `.env`
- Cek `FCM_SERVICE_ACCOUNT_PATH` dan `FCM_PROJECT_ID`

---

## 7. Catatan Penting

1. **VAPID Key**: Diperlukan untuk web push notifications. Dapatkan dari Firebase Console > Project Settings > Cloud Messaging > Web Push certificates

2. **Service Worker**: Wajib untuk background notifications. File harus di `public/firebase-messaging-sw.js`

3. **HTTPS**: Web push notifications hanya bekerja di HTTPS (atau localhost untuk development)

4. **Browser Support**: 
   - ✅ Chrome/Edge (Chromium)
   - ✅ Firefox
   - ✅ Safari (iOS 16.4+)
   - ❌ Safari (macOS - limited support)

---

## 8. File yang Dibuat

1. ✅ `database/sql/create_web_device_tokens_table.sql` - SQL untuk create table
2. ✅ `app/Models/WebDeviceToken.php` - Model untuk web device tokens
3. ✅ `app/Http/Controllers/Api/WebDeviceTokenController.php` - Controller untuk register/unregister
4. ✅ `app/Observers/NotificationObserver.php` - Observer untuk trigger FCM
5. ✅ `routes/api.php` - Routes sudah ditambahkan
6. ✅ `app/Providers/AppServiceProvider.php` - Observer sudah ter-register
7. ✅ `app/Services/FCMService.php` - Sudah support web device type
8. ✅ `app/Models/Notification.php` - Sudah ditambahkan field `title`

---

**Status**: ✅ Backend sudah siap. Tinggal setup frontend (Firebase SDK + Service Worker).

