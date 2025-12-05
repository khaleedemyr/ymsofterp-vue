# 🔥 Firebase Initialization - Setup Guide

## ✅ **Status: Code Sudah Di-Update**

Firebase initialization sudah ditambahkan di `main.dart`, tapi masih perlu setup Firebase project.

---

## 🔍 **Error yang Terjadi**

Dari log:
```
[core/no-app] No Firebase App '[DEFAULT]' has been created - call Firebase.initializeApp()
```

**Penyebab:**
- Firebase belum di-initialize (sudah diperbaiki)
- Firebase project belum di-setup di Firebase Console
- `google-services.json` belum di-download

---

## ✅ **Yang Sudah Diperbaiki**

### **1. main.dart**
- ✅ Import `firebase_core`
- ✅ Initialize Firebase di `main()` dengan try-catch
- ✅ Fallback ke test token jika Firebase gagal

### **2. Code Logic**
- ✅ Try get FCM token dari Firebase
- ✅ Fallback ke test token jika error

---

## 🚀 **Setup Firebase (Pilih Salah Satu)**

### **Option 1: Setup Firebase Lengkap (Recommended untuk Production)**

1. **Buka [Firebase Console](https://console.firebase.google.com/)**
2. **Buat/Select Project**
3. **Add Android App:**
   - Package name: Cek di Flutter project
   - Download `google-services.json`
   - Letakkan di: `frontend/android/app/google-services.json`

4. **Setup Android:**
   - Update `android/build.gradle`:
     ```gradle
     buildscript {
         dependencies {
             classpath 'com.google.gms:google-services:4.4.0'
         }
     }
     ```
   - Update `android/app/build.gradle`:
     ```gradle
     apply plugin: 'com.google.gms.google-services'
     ```

5. **Generate firebase_options.dart:**
   ```bash
   cd frontend
   flutter pub get
   flutterfire configure
   ```

6. **Update main.dart:**
   ```dart
   import 'firebase_options.dart';
   
   await Firebase.initializeApp(
     options: DefaultFirebaseOptions.currentPlatform,
   );
   ```

### **Option 2: Skip Firebase (Untuk Development/Testing)**

**Jika belum mau setup Firebase sekarang:**
- ✅ Code sudah siap dengan fallback test token
- ✅ Test token tetap berfungsi untuk testing
- ✅ Bisa setup Firebase nanti

**Test token format:**
- `test_device_{timestamp}_{token_prefix}`
- Unique per login
- Bisa digunakan untuk testing push notification flow

---

## 📋 **Cara Kerja Sekarang**

### **Dengan Firebase Setup:**
```
main() → Firebase.initializeApp() ✅
  ↓
Login → Get FCM Token (AAA...) ✅
  ↓
Register → Database (FCM token asli) ✅
```

### **Tanpa Firebase Setup (Saat Ini):**
```
main() → Firebase.initializeApp() ❌ (error)
  ↓
Login → Error get FCM → Fallback test token ✅
  ↓
Register → Database (test_device_...) ✅
```

---

## 🎯 **Rekomendasi**

**Untuk Development/Testing:**
- ✅ Gunakan test token dulu (sudah berfungsi)
- ✅ Setup Firebase nanti saat production

**Untuk Production:**
- ⚠️ Setup Firebase project
- ⚠️ Download `google-services.json`
- ⚠️ Run `flutterfire configure`
- ⚠️ Update `main.dart` dengan `firebase_options.dart`

---

## ✅ **Status Saat Ini**

- ✅ **Code sudah siap** - Firebase initialization ditambahkan
- ✅ **Fallback berfungsi** - Test token untuk development
- ⏳ **Firebase setup** - Optional, bisa dilakukan nanti

**Test token tetap berfungsi untuk testing!** 🎉

