# Checklist: Cek Authentication di Server

## 🔍 Masalah:
Login berhasil, token didapat, tapi `/auth/me` return 401 Unauthenticated.

## ✅ Checklist di Server Production:

### 1. Cek Token di Database

SSH ke server dan jalankan:
```sql
-- Cek token terbaru untuk member ID 1
SELECT 
    id,
    tokenable_type,
    tokenable_id,
    name,
    SUBSTRING(token, 1, 20) as token_preview,
    abilities,
    last_used_at,
    expires_at,
    created_at
FROM personal_access_tokens
WHERE tokenable_id = 1
ORDER BY created_at DESC
LIMIT 5;
```

**Pastikan:**
- ✅ Token ada di database
- ✅ `tokenable_type` = `App\Models\MemberAppsMember`
- ✅ `tokenable_id` = 1 (atau sesuai member ID)
- ✅ `expires_at` = NULL (tidak expired) atau belum expired

### 2. Cek Sanctum Configuration

**File: `config/sanctum.php`**
```php
'guard' => ['web'], // Pastikan ini ada
```

**File: `config/auth.php`**
Pastikan ada guard `sanctum`:
```php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
    'sanctum' => [
        'driver' => 'sanctum',
        'provider' => 'users', // atau 'members' jika ada
    ],
],
```

### 3. Cek Model MemberAppsMember

**File: `app/Models/MemberAppsMember.php`**
Pastikan menggunakan:
```php
use Laravel\Sanctum\HasApiTokens;

class MemberAppsMember extends Authenticatable
{
    use HasApiTokens, Notifiable;
    // ...
}
```

### 4. Cek Route Middleware

**File: `routes/api.php`**
Pastikan route `/auth/me` ada di dalam:
```php
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
});
```

### 5. Clear Cache di Server

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan optimize:clear
```

### 6. Cek Laravel Logs

```bash
tail -f storage/logs/laravel.log
```

Cari log saat test `/auth/me`:
- `Auth me called but no user found` → Token tidak terdeteksi
- `Auth me successful` → Token berhasil

### 7. Test Token dengan Debug Route

Test dengan route debug:
```
GET https://ymsofterp.com/api/mobile/member/auth/test-token
Authorization: Bearer 97|ouQRcvxpwko2CAPllFs7Tewh3qS7CwnUOnyyjTYl9ae4f0b5
```

Route ini akan cek:
- Token ada atau tidak
- Token format valid atau tidak
- Token ada di database atau tidak
- Member ditemukan atau tidak

### 8. Cek .env

Pastikan di `.env`:
```env
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1,ymsofterp.com
SESSION_DRIVER=cookie
```

### 9. Cek Middleware Order

**File: `app/Http/Kernel.php` atau `bootstrap/app.php`**
Pastikan Sanctum middleware sudah terdaftar dan di urutan yang benar.

---

## 🔧 Quick Fix:

Jika semua sudah benar tapi masih 401, coba:

1. **Restart PHP-FPM / Web Server:**
```bash
sudo systemctl restart php8.1-fpm  # atau versi PHP yang digunakan
sudo systemctl restart nginx        # atau apache
```

2. **Cek apakah Sanctum sudah di-install:**
```bash
composer show laravel/sanctum
```

3. **Re-publish Sanctum config (jika perlu):**
```bash
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

---

## 📝 Langkah Test:

1. **Login** → Dapat token
2. **Cek token di database** dengan query di atas
3. **Test dengan debug route** `/auth/test-token`
4. **Cek log** untuk melihat error detail
5. **Clear cache** dan test lagi

---

## 🚨 Jika Masih Error:

Kemungkinan masalah:
1. **Token tidak tersimpan** → Cek query di atas
2. **Sanctum tidak terkonfigurasi** → Cek config files
3. **Middleware tidak jalan** → Cek Kernel.php
4. **Model tidak menggunakan HasApiTokens** → Sudah dicek, sudah benar

Silakan jalankan checklist di atas dan beri tahu hasilnya.

