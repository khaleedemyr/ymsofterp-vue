# FCM iOS Setup Guide

Panduan lengkap untuk mengkonfigurasi Firebase Cloud Messaging (FCM) untuk iOS.

## 📋 Daftar Isi

1. [Persyaratan](#persyaratan)
2. [Setup Firebase Console](#setup-firebase-console)
3. [Setup iOS App](#setup-ios-app)
4. [Setup Backend (Laravel)](#setup-backend-laravel)
5. [Testing](#testing)
6. [Troubleshooting](#troubleshooting)

---

## 1. Persyaratan

### Yang Diperlukan:
- ✅ Apple Developer Account (berbayar, $99/tahun)
- ✅ Xcode terinstall di Mac
- ✅ iOS device untuk testing (simulator tidak support push notifications)
- ✅ Firebase project sudah dibuat
- ✅ Android app sudah terkonfigurasi di Firebase (opsional, tapi recommended)

---

## 2. Setup Firebase Console

### 2.1. Tambahkan iOS App ke Firebase Project

1. Buka [Firebase Console](https://console.firebase.google.com/)
2. Pilih project Anda
3. Klik ikon **Settings (⚙️)** > **Project settings**
4. Scroll ke bagian **Your apps**
5. Klik **Add app** > **iOS**
6. Isi informasi:
   - **iOS bundle ID**: Bundle ID dari iOS app (contoh: `com.justusgroup.memberapp`)
     - Cek di `ios/Runner.xcodeproj` atau `ios/Runner/Info.plist`
   - **App nickname** (opsional): Nama untuk identifikasi
   - **App Store ID** (opsional): Jika sudah di App Store
7. Klik **Register app**

### 2.2. Download GoogleService-Info.plist

1. Setelah iOS app terdaftar, download file `GoogleService-Info.plist`
2. **JANGAN** commit file ini ke Git (sudah ada di `.gitignore`)

### 2.3. Upload APNs Authentication Key (Recommended) atau Certificate

**Opsi A: APNs Authentication Key (Recommended - Lebih Mudah)**

1. Buka [Apple Developer Portal](https://developer.apple.com/account/)
2. Masuk ke **Certificates, Identifiers & Profiles**
3. Klik **Keys** di sidebar kiri
4. Klik **+** untuk membuat key baru
5. Isi:
   - **Key Name**: `Firebase Cloud Messaging Key` (atau nama lain)
   - Centang **Apple Push Notifications service (APNs)**
6. Klik **Continue** > **Register**
7. **Download** file `.p8` (hanya bisa didownload sekali!)
8. Simpan **Key ID** yang ditampilkan
9. Kembali ke Firebase Console:
   - Masuk ke **Project Settings** > **Cloud Messaging** tab
   - Scroll ke **Apple app configuration**
   - Klik **Upload** di bagian **APNs Authentication Key**
   - Upload file `.p8`
   - Masukkan **Key ID**
   - Masukkan **Team ID** (dari Apple Developer Portal)

**Opsi B: APNs Certificate (Legacy - Lebih Rumit)**

1. Buat APNs Certificate di Apple Developer Portal
2. Download certificate
3. Upload ke Firebase Console (Project Settings > Cloud Messaging > APNs Certificates)

---

## 3. Setup iOS App

### 3.1. Tambahkan GoogleService-Info.plist

1. Copy file `GoogleService-Info.plist` yang sudah didownload
2. Paste ke folder: `ios/Runner/GoogleService-Info.plist`
3. Pastikan file ada di Xcode project:
   - Buka `ios/Runner.xcworkspace` di Xcode
   - Drag & drop `GoogleService-Info.plist` ke folder `Runner` di Xcode
   - Centang **"Copy items if needed"**
   - Pastikan target **Runner** tercentang

### 3.2. Update Info.plist

Tambahkan permission untuk notifications di `ios/Runner/Info.plist`:

```xml
<key>UIBackgroundModes</key>
<array>
    <string>remote-notification</string>
</array>
```

**Lokasi file**: `D:\Gawean\YM\web\frontend\ios\Runner\Info.plist`

Tambahkan sebelum tag `</dict>` terakhir.

### 3.3. Update AppDelegate.swift

Update file `ios/Runner/AppDelegate.swift` untuk menginisialisasi Firebase:

```swift
import Flutter
import UIKit
import FirebaseCore
import FirebaseMessaging

@main
@objc class AppDelegate: FlutterAppDelegate {
  override func application(
    _ application: UIApplication,
    didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?
  ) -> Bool {
    // Initialize Firebase
    FirebaseApp.configure()
    
    // Set messaging delegate for handling notifications
    if #available(iOS 10.0, *) {
      UNUserNotificationCenter.current().delegate = self
      let authOptions: UNAuthorizationOptions = [.alert, .badge, .sound]
      UNUserNotificationCenter.current().requestAuthorization(
        options: authOptions,
        completionHandler: { _, _ in }
      )
    } else {
      let settings: UIUserNotificationSettings =
        UIUserNotificationSettings(types: [.alert, .badge, .sound], categories: nil)
      application.registerUserNotificationSettings(settings)
    }
    
    application.registerForRemoteNotifications()
    
    GeneratedPluginRegistrant.register(with: self)
    return super.application(application, didFinishLaunchingWithOptions: launchOptions)
  }
  
  // Handle APNs token registration
  override func application(_ application: UIApplication,
                            didRegisterForRemoteNotificationsWithDeviceToken deviceToken: Data) {
    Messaging.messaging().apnsToken = deviceToken
    super.application(application, didRegisterForRemoteNotificationsWithDeviceToken: deviceToken)
  }
  
  // Handle APNs token registration failure
  override func application(_ application: UIApplication,
                            didFailToRegisterForRemoteNotificationsWithError error: Error) {
    print("Failed to register for remote notifications: \(error)")
    super.application(application, didFailToRegisterForRemoteNotificationsWithError: error)
  }
}
```

### 3.4. Update Podfile

Pastikan `ios/Podfile` sudah menginclude Firebase:

```ruby
platform :ios, '12.0'

target 'Runner' do
  use_frameworks!
  use_modular_headers!

  flutter_install_all_ios_pods File.dirname(File.realpath(__FILE__))
end

post_install do |installer|
  installer.pods_project.targets.each do |target|
    flutter_additional_ios_build_settings(target)
  end
end
```

Jalankan:
```bash
cd ios
pod install
cd ..
```

### 3.5. Enable Push Notifications Capability

1. Buka `ios/Runner.xcworkspace` di Xcode
2. Pilih target **Runner**
3. Masuk ke tab **Signing & Capabilities**
4. Klik **+ Capability**
5. Tambahkan **Push Notifications**
6. Tambahkan **Background Modes** (jika belum ada)
   - Centang **Remote notifications**

### 3.6. Update Bundle Identifier

Pastikan Bundle Identifier di Xcode sesuai dengan yang didaftarkan di Firebase:
- Buka Xcode > Runner target > General tab
- **Bundle Identifier**: harus sama dengan yang di Firebase (contoh: `com.justusgroup.memberapp`)

---

## 4. Setup Backend (Laravel)

### 4.1. Environment Variables

Tambahkan ke `.env`:

```env
# FCM Configuration
FCM_USE_V1_API=true
FCM_SERVICE_ACCOUNT_PATH=firebase/service-account-key.json
FCM_PROJECT_ID=your-project-id

# iOS Key (optional, untuk Legacy API fallback)
FCM_IOS_KEY=your-ios-server-key-if-using-legacy-api
```

**Catatan**: 
- Jika menggunakan **FCM HTTP v1 API** (recommended), hanya perlu `FCM_SERVICE_ACCOUNT_PATH` dan `FCM_PROJECT_ID`
- `FCM_IOS_KEY` hanya diperlukan jika menggunakan Legacy API

### 4.2. Verifikasi Konfigurasi

Backend sudah mendukung iOS melalui:
- ✅ `FCMV1Service.php` - Sudah ada konfigurasi APNS
- ✅ `FCMService.php` - Sudah ada support untuk iOS device type
- ✅ `DeviceTokenController.php` - Sudah menerima `device_type: 'ios'`

**Tidak perlu perubahan kode backend** jika sudah menggunakan FCM HTTP v1 API.

### 4.3. Test Backend Configuration

Jalankan command untuk cek konfigurasi:

```bash
php artisan fcm:check-setup
```

---

## 5. Testing

### 5.1. Build dan Run di Device iOS

**WAJIB menggunakan device fisik**, karena simulator tidak support push notifications:

```bash
cd frontend
flutter build ios
# Atau buka di Xcode dan run di device
```

### 5.2. Test Notification dari Backend

1. Dapatkan FCM token dari device iOS (cek di log atau dari API)
2. Test dengan command:

```bash
php artisan fcm:test --device_token=YOUR_IOS_TOKEN --device_type=ios
```

### 5.3. Verifikasi Permission

Pastikan permission sudah granted:
- Saat pertama kali buka app, iOS akan minta permission
- User harus klik **"Allow"**
- Cek di Settings > [App Name] > Notifications

---

## 6. Troubleshooting

### 6.1. Token Tidak Terdaftar

**Masalah**: Token tidak terdaftar di backend

**Solusi**:
1. Pastikan user sudah login
2. Cek log di Flutter app: `FCM token registered successfully`
3. Cek di database: table `member_apps_device_tokens`
4. Pastikan `device_type` = `'ios'`

### 6.2. Notification Tidak Muncul

**Masalah**: Notification tidak muncul di iOS device

**Checklist**:
- ✅ Permission sudah granted (Settings > App > Notifications)
- ✅ APNs Authentication Key sudah diupload ke Firebase
- ✅ Bundle ID di Xcode sama dengan di Firebase
- ✅ `GoogleService-Info.plist` sudah ditambahkan ke Xcode project
- ✅ App di-build dan di-run di **device fisik** (bukan simulator)
- ✅ Background Modes > Remote notifications sudah enabled

### 6.3. Error: "No valid 'aps-environment' entitlement"

**Masalah**: Error saat build atau run

**Solusi**:
1. Pastikan **Push Notifications** capability sudah ditambahkan di Xcode
2. Pastikan **Signing & Capabilities** sudah dikonfigurasi dengan benar
3. Clean build: Product > Clean Build Folder (Shift+Cmd+K)
4. Rebuild project

### 6.4. Error: "FirebaseApp.configure()" crash

**Masalah**: App crash saat startup

**Solusi**:
1. Pastikan `GoogleService-Info.plist` sudah ditambahkan ke Xcode project
2. Pastikan file ada di folder `ios/Runner/`
3. Pastikan target **Runner** tercentang saat menambahkan file
4. Jalankan `pod install` lagi

### 6.5. Notification Hanya Muncul Saat App Foreground

**Masalah**: Notification hanya muncul saat app terbuka

**Solusi**:
1. Pastikan `UIBackgroundModes` dengan `remote-notification` sudah ditambahkan di `Info.plist`
2. Pastikan `AppDelegate.swift` sudah memanggil `application.registerForRemoteNotifications()`
3. Pastikan APNs token sudah di-set: `Messaging.messaging().apnsToken = deviceToken`

### 6.6. Badge Tidak Update

**Masalah**: Badge number tidak update di app icon

**Solusi**:
1. Pastikan permission untuk badge sudah granted
2. Backend sudah mengirim `badge` di payload APNS
3. Cek di `FCMV1Service.php` - sudah ada `'badge' => 1` di APNS payload

---

## 7. Perbedaan iOS vs Android

| Fitur | Android | iOS |
|-------|---------|-----|
| **Permission** | Otomatis granted | Harus request permission |
| **Token** | Otomatis didapat | Perlu permission dulu |
| **Icon** | Custom icon support | Menggunakan app icon |
| **Sound** | Custom sound support | Default sound |
| **Background** | Otomatis | Perlu `UIBackgroundModes` |
| **Testing** | Bisa di emulator | Harus device fisik |
| **APNs** | Tidak perlu | Perlu APNs key/certificate |

---

## 8. Checklist Final

Sebelum production, pastikan:

- [ ] Apple Developer Account sudah aktif
- [ ] APNs Authentication Key sudah diupload ke Firebase
- [ ] `GoogleService-Info.plist` sudah ditambahkan ke Xcode project
- [ ] `Info.plist` sudah ada `UIBackgroundModes` dengan `remote-notification`
- [ ] `AppDelegate.swift` sudah menginisialisasi Firebase
- [ ] Push Notifications capability sudah enabled di Xcode
- [ ] Background Modes > Remote notifications sudah enabled
- [ ] Bundle ID di Xcode sama dengan di Firebase
- [ ] Sudah test di device fisik (bukan simulator)
- [ ] Permission sudah granted oleh user
- [ ] Token sudah terdaftar di backend
- [ ] Test notification sudah berhasil dikirim dan diterima

---

## 9. Referensi

- [Firebase iOS Setup](https://firebase.google.com/docs/ios/setup)
- [Firebase Cloud Messaging for iOS](https://firebase.google.com/docs/cloud-messaging/ios/client)
- [APNs Authentication Key](https://developer.apple.com/documentation/usernotifications/setting_up_a_remote_notification_server/establishing_a_token-based_connection_to_apns)
- [Flutter Firebase Messaging](https://firebase.flutter.dev/docs/messaging/overview)

---

**Catatan Penting**: 
- iOS push notifications **HANYA** bekerja di device fisik, tidak di simulator
- User harus **grant permission** terlebih dahulu sebelum token bisa didapat
- APNs Authentication Key hanya bisa didownload **sekali**, simpan dengan aman!

