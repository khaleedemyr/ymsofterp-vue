# Mobile API Structure - Panduan Struktur untuk Menghindari Conflict

## ✅ **AMAN untuk ngoding mobile app di project yang sama**, dengan struktur berikut:

## 📁 Struktur Folder yang Disarankan

```
app/Http/Controllers/
├── Api/                          # API untuk internal/web admin
│   ├── MaintenancePurchaseOrderController.php
│   ├── GoodReceiveController.php
│   └── ...
│
├── Mobile/                        # API khusus untuk Mobile App
│   ├── Member/                   # API untuk Member App
│   │   ├── AuthController.php    # Login, Register, Logout
│   │   ├── DeviceTokenController.php  # Register device token
│   │   ├── ProfileController.php # Profile member
│   │   ├── PointController.php   # Point management
│   │   ├── VoucherController.php # Voucher management
│   │   └── ...
│   │
│   └── RegisterController.php    # (existing)
│
├── MemberAppsSettingsController.php  # Admin web untuk manage member app settings
└── PushNotificationAdminController.php  # Admin web untuk push notification
```

## 🎯 **Prinsip Pemisahan**

### 1. **Namespace Berbeda**
```php
// Web Admin Controller
namespace App\Http\Controllers;
class MemberAppsSettingsController extends Controller { }

// Mobile API Controller
namespace App\Http\Controllers\Mobile\Member;
class AuthController extends Controller { }
```

### 2. **Route Prefix Berbeda**
```php
// routes/web.php - Web Admin Routes
Route::post('/member-apps-settings/push-notification', ...);

// routes/api.php - Mobile API Routes
Route::prefix('mobile/member')->group(function () {
    Route::post('/device-token/register', ...);
    Route::post('/auth/login', ...);
});
```

### 3. **Middleware Berbeda**
```php
// Web Admin - menggunakan auth:web
Route::middleware(['auth', 'verified'])->group(...);

// Mobile API - menggunakan auth:sanctum atau auth:member
Route::middleware(['auth:sanctum'])->group(...);
```

## 📝 **Contoh Implementasi**

### Mobile Member API Controller
```php
<?php
namespace App\Http\Controllers\Mobile\Member;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function register(Request $request)
    {
        // Logic untuk register device token
    }
}
```

### Route di `routes/api.php`
```php
// Mobile Member API Routes
Route::prefix('mobile/member')->middleware(['auth:sanctum'])->group(function () {
    // Device Token
    Route::post('/device-token/register', [\App\Http\Controllers\Mobile\Member\DeviceTokenController::class, 'register']);
    Route::post('/device-token/unregister', [\App\Http\Controllers\Mobile\Member\DeviceTokenController::class, 'unregister']);
    
    // Auth
    Route::post('/auth/login', [\App\Http\Controllers\Mobile\Member\AuthController::class, 'login']);
    Route::post('/auth/logout', [\App\Http\Controllers\Mobile\Member\AuthController::class, 'logout']);
    
    // Profile
    Route::get('/profile', [\App\Http\Controllers\Mobile\Member\ProfileController::class, 'show']);
    Route::put('/profile', [\App\Http\Controllers\Mobile\Member\ProfileController::class, 'update']);
    
    // Points
    Route::get('/points', [\App\Http\Controllers\Mobile\Member\PointController::class, 'index']);
    Route::get('/points/history', [\App\Http\Controllers\Mobile\Member\PointController::class, 'history']);
    
    // Vouchers
    Route::get('/vouchers', [\App\Http\Controllers\Mobile\Member\VoucherController::class, 'index']);
    Route::post('/vouchers/{id}/use', [\App\Http\Controllers\Mobile\Member\VoucherController::class, 'use']);
});
```

## ⚠️ **Yang Harus Dihindari**

### ❌ JANGAN:
1. **Nama controller sama di namespace yang sama**
   ```php
   // ❌ BAD - Conflict!
   namespace App\Http\Controllers;
   class AuthController extends Controller { }
   
   namespace App\Http\Controllers;
   class AuthController extends Controller { } // ERROR!
   ```

2. **Route path yang sama tanpa prefix**
   ```php
   // ❌ BAD - Route conflict!
   Route::post('/device-token/register', ...); // Web
   Route::post('/device-token/register', ...); // Mobile - ERROR!
   ```

3. **Menggunakan controller web untuk mobile API**
   ```php
   // ❌ BAD - Mixing concerns
   Route::post('/api/member/device-token', [MemberAppsSettingsController::class, 'registerToken']);
   ```

### ✅ BOLEH:
1. **Nama controller sama, tapi namespace berbeda**
   ```php
   // ✅ GOOD - No conflict!
   namespace App\Http\Controllers;
   class AuthController extends Controller { } // Web admin
   
   namespace App\Http\Controllers\Mobile\Member;
   class AuthController extends Controller { } // Mobile API - OK!
   ```

2. **Route path berbeda dengan prefix**
   ```php
   // ✅ GOOD - No conflict!
   Route::post('/member-apps-settings/device-token', ...); // Web admin
   Route::post('/mobile/member/device-token/register', ...); // Mobile API - OK!
   ```

## 🔒 **Keamanan & Authentication**

### Web Admin (Laravel Auth)
```php
Route::middleware(['auth', 'verified'])->group(function () {
    // Web admin routes
});
```

### Mobile API (Sanctum/Passport)
```php
Route::middleware(['auth:sanctum'])->group(function () {
    // Mobile API routes
});
```

## 📊 **Perbandingan Struktur**

| Aspek | Web Admin | Mobile API |
|-------|-----------|------------|
| **Folder** | `app/Http/Controllers/` | `app/Http/Controllers/Mobile/Member/` |
| **Namespace** | `App\Http\Controllers` | `App\Http\Controllers\Mobile\Member` |
| **Route File** | `routes/web.php` | `routes/api.php` |
| **Route Prefix** | `/member-apps-settings/` | `/mobile/member/` |
| **Middleware** | `auth`, `verified` | `auth:sanctum` |
| **Response** | Inertia/Blade | JSON |
| **Example** | `MemberAppsSettingsController` | `Mobile\Member\AuthController` |

## 🚀 **Best Practices**

1. **Gunakan folder `Mobile/Member/` untuk semua mobile member API**
2. **Gunakan prefix `/mobile/member/` untuk semua mobile routes**
3. **Pisahkan authentication: Sanctum untuk mobile, Laravel Auth untuk web**
4. **Gunakan namespace yang jelas dan konsisten**
5. **Dokumentasikan semua mobile API endpoints**

## 📋 **Checklist Sebelum Menambah Controller Baru**

- [ ] Apakah ini untuk web admin atau mobile app?
- [ ] Sudah cek apakah ada controller dengan nama yang sama?
- [ ] Sudah menggunakan namespace yang tepat?
- [ ] Sudah menggunakan route prefix yang tepat?
- [ ] Sudah menggunakan middleware yang tepat?
- [ ] Sudah menambahkan route di file yang tepat (`web.php` atau `api.php`)?

## 🎯 **Kesimpulan**

✅ **AMAN** untuk ngoding mobile app di project yang sama, **ASAL**:
- Menggunakan namespace yang berbeda (`Mobile\Member\`)
- Menggunakan route prefix yang berbeda (`/mobile/member/`)
- Menggunakan middleware yang berbeda (`auth:sanctum`)
- Menggunakan route file yang tepat (`routes/api.php`)

Dengan struktur ini, **TIDAK AKAN ADA CONFLICT** antara web admin dan mobile API! 🎉

