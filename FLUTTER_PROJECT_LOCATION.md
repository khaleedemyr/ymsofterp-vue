# 📱 Lokasi Flutter Project - Rekomendasi

## 🎯 **Rekomendasi: Folder Terpisah di Level yang Sama**

### ✅ **Opsi 1: Folder Terpisah (DISARANKAN)**

```
D:\Gawean\YM\web\
├── ymsofterp/                    # Laravel Backend (existing)
│   ├── app/
│   ├── routes/
│   ├── database/
│   └── ...
│
└── frontend/                    # Flutter Mobile App (NEW) ⬅️ COPY KESINI
    ├── lib/
    ├── pubspec.yaml
    ├── android/
    ├── ios/
    └── ...
```

**Keuntungan:**
- ✅ Terpisah dari Laravel project
- ✅ Mudah di-deploy terpisah
- ✅ Tidak mengganggu Laravel project
- ✅ Bisa di-git terpisah
- ✅ Struktur jelas dan rapi

**Cara Copy:**
```bash
# Buat folder baru
mkdir D:\Gawean\YM\web\frontend

# Copy Flutter project ke folder tersebut
# (copy semua file Flutter project)
```

---

### ⚠️ **Opsi 2: Di Dalam Laravel Project (TIDAK DISARANKAN untuk Production)**

```
D:\Gawean\YM\web\ymsofterp/
├── app/                          # Laravel Backend
├── routes/
├── database/
├── frontend/                     # Flutter Mobile App ⬅️ BISA TAPI TIDAK DISARANKAN
│   ├── lib/
│   ├── pubspec.yaml
│   └── ...
└── ...
```

**Kekurangan:**
- ❌ Bercampur dengan Laravel project
- ❌ Bisa mengganggu struktur Laravel
- ❌ Sulit di-deploy terpisah
- ❌ Git repository jadi besar

**Kapan Boleh:**
- ✅ Hanya untuk development/testing
- ✅ Project kecil
- ✅ Tidak akan di-deploy terpisah

---

## 📋 **Struktur yang Disarankan (Opsi 1)**

### Setup Folder

```bash
# Di D:\Gawean\YM\web\
D:\Gawean\YM\web\
├── ymsofterp/                    # Laravel Backend
│   ├── app/
│   ├── routes/
│   ├── database/
│   ├── .env
│   └── ...
│
└── frontend/                     # Flutter Mobile App
    ├── lib/
    │   ├── main.dart
    │   ├── config/
    │   │   └── api_config.dart    # Base URL configuration
    │   ├── models/
    │   ├── services/
    │   ├── screens/
    │   └── widgets/
    ├── pubspec.yaml
    ├── android/
    ├── ios/
    └── .env                       # Flutter environment (optional)
```

---

## 🔧 **Setup Setelah Copy**

### 1. Update Base URL di Flutter

**File: `lib/config/api_config.dart`**

```dart
class ApiConfig {
  // Development
  static const String baseUrl = 'http://localhost:8000/api';
  
  // Production
  // static const String baseUrl = 'https://your-api.com/api';
  
  // Member App Endpoints
  static const String brands = '/mobile/member/brands';
  static const String rewards = '/mobile/member/rewards';
  static const String banners = '/mobile/member/banners';
  static const String deviceTokenRegister = '/mobile/member/device-token/register';
  static const String deviceTokenUnregister = '/mobile/member/device-token/unregister';
}
```

### 2. Update .gitignore (jika perlu)

**File: `ymsofterp-mobile/.gitignore`**

```
# Flutter/Dart
.dart_tool/
.flutter-plugins
.flutter-plugins-dependencies
.packages
.pub-cache/
.pub/
build/
*.iml
*.ipr
*.iws
.idea/

# Android
android/.gradle/
android/app/build/
android/.idea/

# iOS
ios/Pods/
ios/.symlinks/
ios/Flutter/Flutter.framework
ios/Flutter/Flutter.podspec
```

### 3. Update pubspec.yaml (jika perlu)

Pastikan dependencies yang diperlukan sudah ada:

```yaml
dependencies:
  flutter:
    sdk: flutter
  http: ^1.1.0
  dio: ^5.0.0
  shared_preferences: ^2.2.0
  firebase_messaging: ^14.0.0
  # ... dependencies lainnya
```

---

## 📝 **Langkah-langkah Copy**

### Step 1: Buat Folder Baru

```bash
# Di Windows Explorer atau Command Prompt
mkdir D:\Gawean\YM\web\ymsofterp-mobile
```

### Step 2: Copy Flutter Project

**Opsi A: Copy Manual**
- Copy semua file dan folder dari Flutter project lama
- Paste ke `D:\Gawean\YM\web\ymsofterp-mobile`

**Opsi B: Git Clone (jika ada repo)**
```bash
cd D:\Gawean\YM\web\
git clone <your-flutter-repo-url> ymsofterp-mobile
```

### Step 3: Update Configuration

1. **Update Base URL** di `lib/config/api_config.dart`
2. **Update package name** (jika perlu) di `android/app/build.gradle` dan `ios/Runner.xcodeproj`
3. **Update app name** di `pubspec.yaml`

### Step 4: Install Dependencies

```bash
cd D:\Gawean\YM\web\ymsofterp-mobile
flutter pub get
```

### Step 5: Test Connection

```bash
# Run Flutter app
flutter run

# Atau test dengan emulator/device
flutter run -d <device-id>
```

---

## 🎯 **Rekomendasi Final**

### ✅ **Gunakan Opsi 1: Folder Terpisah**

```
D:\Gawean\YM\web\
├── ymsofterp/              # Laravel Backend
└── ymsofterp-mobile/       # Flutter Mobile App ⬅️ COPY KESINI
```

**Alasan:**
1. ✅ Struktur jelas dan terpisah
2. ✅ Mudah di-maintain
3. ✅ Bisa di-deploy terpisah
4. ✅ Tidak mengganggu Laravel project
5. ✅ Best practice untuk production

---

## 📋 **Checklist Setelah Copy**

- [ ] Flutter project sudah di-copy ke folder terpisah
- [ ] Base URL sudah di-update di `lib/config/api_config.dart`
- [ ] Dependencies sudah di-install (`flutter pub get`)
- [ ] Test koneksi ke API (`/api/mobile/member/brands`, `/rewards`, `/banners`)
- [ ] Test device token registration (setelah login)
- [ ] Update app name dan package name (jika perlu)
- [ ] Setup Firebase (jika belum)
- [ ] Test push notification

---

## 💡 **Tips**

1. **Gunakan Environment Variables** untuk base URL:
   ```dart
   // lib/config/api_config.dart
   class ApiConfig {
     static String get baseUrl {
       const String env = String.fromEnvironment('ENV', defaultValue: 'dev');
       if (env == 'prod') {
         return 'https://your-api.com/api';
       }
       return 'http://localhost:8000/api';
     }
   }
   ```

2. **Setup Multiple Environments**:
   - `lib/config/dev_config.dart`
   - `lib/config/prod_config.dart`

3. **Gunakan Dio atau HTTP package** untuk API calls

4. **Setup Error Handling** yang baik

---

**Kesimpulan: Copy Flutter project ke `D:\Gawean\YM\web\frontend\` (folder terpisah)** ✅

