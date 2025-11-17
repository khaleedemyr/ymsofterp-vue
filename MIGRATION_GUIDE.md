# 📦 Panduan Copy Project Flutter & Controller ke Project Ini

## ✅ **BISA COPY**, tapi perlu di-organize dengan benar!

## 📋 **Yang Sudah Ada di Project Ini**

### Controllers yang Sudah Ada:
1. ✅ `app/Http/Controllers/Mobile/RegisterController.php` - Register user
2. ✅ `app/Http/Controllers/Api/MobileAuthController.php` - Login mobile
3. ✅ `app/Http/Controllers/Mobile/Member/DeviceTokenController.php` - Device token (baru dibuat)

### Routes yang Sudah Ada:
```php
// routes/api.php
Route::post('/mobile/register', ...);
Route::post('/mobile/login', ...);
Route::prefix('mobile/member')->middleware(['auth:sanctum'])->group(function () {
    Route::post('/device-token/register', ...);
    // ...
});
```

## 🎯 **Struktur yang Disarankan**

### Untuk Member App (yang baru):
```
app/Http/Controllers/Mobile/Member/
├── DeviceTokenController.php      ✅ SUDAH ADA
├── AuthController.php             ⬅️ COPY KESINI (ganti MobileAuthController)
├── ProfileController.php          ⬅️ COPY KESINI
├── PointController.php            ⬅️ COPY KESINI
├── VoucherController.php          ⬅️ COPY KESINI
└── ...
```

### Untuk Employee App (yang lama):
```
app/Http/Controllers/Mobile/
├── RegisterController.php         ✅ SUDAH ADA (untuk employee)
└── ... (controller employee lainnya)
```

## 📝 **Langkah-langkah Copy**

### 1. **Copy Controller dari Project Lain**

#### A. Tanyakan dulu:
- Di mana lokasi project Flutter/controller yang sudah dibuat?
- Controller apa saja yang sudah dibuat?
- Apakah untuk Member App atau Employee App?

#### B. Setelah tahu, organize seperti ini:

**Jika controller untuk Member App:**
```bash
# Copy ke folder Mobile/Member/
app/Http/Controllers/Mobile/Member/AuthController.php
app/Http/Controllers/Mobile/Member/ProfileController.php
# dst...
```

**Jika controller untuk Employee App:**
```bash
# Copy ke folder Mobile/ (root)
app/Http/Controllers/Mobile/RegisterController.php  ✅ SUDAH ADA
app/Http/Controllers/Mobile/OtherController.php
# dst...
```

### 2. **Update Namespace**

Setelah copy, pastikan namespace benar:

**Untuk Member App:**
```php
<?php
namespace App\Http\Controllers\Mobile\Member;

use App\Http\Controllers\Controller;
// ...
```

**Untuk Employee App:**
```php
<?php
namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
// ...
```

### 3. **Update Routes di `routes/api.php`**

**Untuk Member App:**
```php
Route::prefix('mobile/member')->middleware(['auth:sanctum'])->group(function () {
    Route::post('/auth/login', [\App\Http\Controllers\Mobile\Member\AuthController::class, 'login']);
    Route::post('/auth/register', [\App\Http\Controllers\Mobile\Member\AuthController::class, 'register']);
    Route::post('/auth/logout', [\App\Http\Controllers\Mobile\Member\AuthController::class, 'logout']);
    Route::get('/profile', [\App\Http\Controllers\Mobile\Member\ProfileController::class, 'show']);
    Route::put('/profile', [\App\Http\Controllers\Mobile\Member\ProfileController::class, 'update']);
    // dst...
});
```

**Untuk Employee App:**
```php
Route::prefix('mobile')->group(function () {
    Route::post('/register', [\App\Http\Controllers\Mobile\RegisterController::class, 'register']);
    Route::post('/login', [\App\Http\Controllers\Api\MobileAuthController::class, 'login']);
    // dst...
});
```

### 4. **Copy Flutter Project**

**Lokasi yang Disarankan:**
```
ymsofterp/
├── app/                    # Laravel backend
├── resources/              # Laravel frontend
├── mobile-app/             ⬅️ COPY FLUTTER PROJECT KESINI
│   ├── lib/
│   ├── pubspec.yaml
│   └── ...
└── ...
```

**Atau bisa di folder terpisah:**
```
D:\Gawean\YM\web\
├── ymsofterp/              # Laravel project
└── ymsofterp-mobile/       # Flutter project (terpisah)
```

## ⚠️ **Yang Perlu Diperhatikan**

### 1. **Check Conflict dengan Controller yang Sudah Ada**

Sebelum copy, cek apakah ada nama controller yang sama:

```bash
# Cek controller yang sudah ada
ls app/Http/Controllers/Mobile/
ls app/Http/Controllers/Mobile/Member/
ls app/Http/Controllers/Api/
```

### 2. **Update Base URL di Flutter**

Setelah copy Flutter project, update base URL:

```dart
// lib/config/api_config.dart
class ApiConfig {
  // Development
  static const String baseUrl = 'http://localhost:8000/api';
  
  // Production
  // static const String baseUrl = 'https://your-api.com/api';
}
```

### 3. **Update API Endpoints di Flutter**

Pastikan endpoint sesuai dengan route yang sudah dibuat:

```dart
// Member App endpoints
static const String login = '/mobile/member/auth/login';
static const String register = '/mobile/member/auth/register';
static const String deviceToken = '/mobile/member/device-token/register';

// Employee App endpoints (jika ada)
static const String employeeLogin = '/mobile/login';
static const String employeeRegister = '/mobile/register';
```

## 🔄 **Reorganize yang Sudah Ada (Opsional)**

Jika ingin lebih konsisten, bisa reorganize:

### 1. **Pindahkan MobileAuthController**

**Dari:**
```
app/Http/Controllers/Api/MobileAuthController.php
```

**Ke:**
```
app/Http/Controllers/Mobile/AuthController.php  (untuk employee)
ATAU
app/Http/Controllers/Mobile/Member/AuthController.php  (untuk member)
```

### 2. **Update Route**

```php
// routes/api.php
// Ganti dari:
Route::post('/mobile/login', [\App\Http\Controllers\Api\MobileAuthController::class, 'login']);

// Menjadi:
Route::post('/mobile/login', [\App\Http\Controllers\Mobile\AuthController::class, 'login']);
// ATAU
Route::post('/mobile/member/auth/login', [\App\Http\Controllers\Mobile\Member\AuthController::class, 'login']);
```

## 📋 **Checklist Sebelum Copy**

- [ ] Tentukan: Controller untuk Member App atau Employee App?
- [ ] Cek apakah ada nama controller yang conflict
- [ ] Siapkan folder tujuan yang tepat
- [ ] Update namespace setelah copy
- [ ] Update routes di `routes/api.php`
- [ ] Update base URL di Flutter project
- [ ] Update API endpoints di Flutter
- [ ] Test semua endpoint setelah copy

## 🚀 **Quick Start**

1. **Tentukan lokasi project Flutter/controller yang akan di-copy**
2. **Beri tahu saya:**
   - Lokasi file controller
   - Controller apa saja yang ada
   - Untuk Member App atau Employee App
3. **Saya akan bantu:**
   - Copy dan organize controller
   - Update namespace
   - Update routes
   - Buat struktur folder yang benar

## 💡 **Rekomendasi**

**Lebih baik copy controller dulu, baru Flutter project**, karena:
1. Controller perlu di-integrate dengan database yang sudah ada
2. Perlu adjust namespace dan routes
3. Flutter bisa di-copy setelah controller siap

---

**Silakan beri tahu:**
1. Di mana lokasi project Flutter/controller yang sudah dibuat?
2. Controller apa saja yang sudah dibuat?
3. Untuk Member App atau Employee App?

Saya akan bantu organize dengan benar! 🎯

