# 🔔 FCM Token Setup - Mengganti Test Token dengan FCM Token Asli

## ✅ **Status API Key**

**API Key yang Anda berikan SUDAH DIGUNAKAN di backend!**

- ✅ **FCMService.php** sudah menggunakan key Anda
- ✅ Key tersimpan di: `app/Services/FCMService.php` (line 16)
- ✅ Format: `AAAAslzPpRc:APA91bEHothpRmZG8xt9mkS_mqMD8dRJhxAwGnv-7eLudDdfydMBo12cw31GEFYQN7c0tsGbi22Wa3gqObbE17pBmTDXpmxUwtkdN7hqEkpgLxgVCFKkdH--RcpfiN3E1LyXCr1LHRSc`

---

## 🔍 **Kenapa Masih "test_device"?**

Saat ini device token masih **"test_device"** karena:
1. ❌ Firebase belum di-setup di Flutter app
2. ❌ Code untuk get FCM token sudah di-uncomment, tapi Firebase belum di-initialize
3. ✅ Fallback ke test token untuk development

---

## 🚀 **Setup Firebase di Flutter**

### **1. Install Package (Sudah ditambahkan)**

```yaml
# pubspec.yaml
firebase_core: ^3.6.0
firebase_messaging: ^15.1.3
```

**Jalankan:**
```bash
cd frontend
flutter pub get
```

### **2. Setup Firebase Project**

1. **Buka [Firebase Console](https://console.firebase.google.com/)**
2. **Pilih atau buat project**
3. **Tambahkan Android App:**
   - Package name: Cek di `android/app/build.gradle` (applicationId)
   - Download `google-services.json`
   - Letakkan di: `frontend/android/app/google-services.json`

4. **Tambahkan iOS App (jika perlu):**
   - Bundle ID: Cek di `ios/Runner.xcodeproj`
   - Download `GoogleService-Info.plist`
   - Letakkan di: `frontend/ios/Runner/GoogleService-Info.plist`

### **3. Update Android Configuration**

**File: `frontend/android/build.gradle`**
```gradle
buildscript {
    dependencies {
        // Add this line
        classpath 'com.google.gms:google-services:4.4.0'
    }
}
```

**File: `frontend/android/app/build.gradle`**
```gradle
// Add at the bottom
apply plugin: 'com.google.gms.google-services'
```

### **4. Initialize Firebase di Flutter**

**File: `frontend/lib/main.dart`**
```dart
import 'package:firebase_core/firebase_core.dart';
import 'firebase_options.dart'; // Will be generated

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  
  // Initialize Firebase
  await Firebase.initializeApp(
    options: DefaultFirebaseOptions.currentPlatform,
  );
  
  runApp(MyApp());
}
```

**Generate firebase_options.dart:**
```bash
cd frontend
flutterfire configure
```

### **5. Update Code (Sudah di-update)**

Code di `home_screen.dart` sudah di-update untuk:
- ✅ Try get FCM token dari Firebase
- ✅ Fallback ke test token jika Firebase belum setup
- ✅ Log untuk debugging

---

## 📋 **Cara Kerja Sekarang**

### **Jika Firebase Sudah Setup:**
```
1. User Login
   ↓
2. Home Screen load
   ↓
3. _registerDeviceToken() dipanggil
   ↓
4. FirebaseMessaging.instance.getToken() ✅
   ↓
5. Dapat FCM token asli (format: AAA...)
   ↓
6. Register ke backend
   ↓
7. Database: device_token = FCM token asli ✅
```

### **Jika Firebase Belum Setup (Saat Ini):**
```
1. User Login
   ↓
2. Home Screen load
   ↓
3. _registerDeviceToken() dipanggil
   ↓
4. FirebaseMessaging.instance.getToken() ❌ (error)
   ↓
5. Fallback ke test token
   ↓
6. Register ke backend
   ↓
7. Database: device_token = test_device_... (untuk testing)
```

---

## ✅ **Yang Sudah Siap**

1. ✅ **Backend FCMService** - API key sudah digunakan
2. ✅ **Flutter Package** - firebase_core & firebase_messaging ditambahkan
3. ✅ **Code Logic** - Sudah di-update untuk get FCM token
4. ⏳ **Firebase Setup** - Perlu setup di Firebase Console

---

## 🎯 **Next Steps**

1. **Setup Firebase Project** (jika belum)
2. **Download `google-services.json`** untuk Android
3. **Jalankan `flutterfire configure`** untuk generate `firebase_options.dart`
4. **Initialize Firebase** di `main.dart`
5. **Test Login** - Device token akan menjadi FCM token asli

---

## 🔍 **Cek Status**

**Cek di database:**
- ✅ Jika `device_token` = `test_device_...` → Firebase belum setup
- ✅ Jika `device_token` = `AAA...` (panjang) → Firebase sudah setup, menggunakan FCM token asli

---

**Status: Code sudah siap, tinggal setup Firebase di Flutter!** 🚀

