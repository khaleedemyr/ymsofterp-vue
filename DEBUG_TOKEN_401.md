# Debug: Token 401 Unauthorized

## 🔍 Masalah:
Login berhasil dan dapat token, tapi `/auth/me` return 401 Unauthorized.

## ✅ Checklist di Server:

### 1. Cek Token di Database
```sql
SELECT * FROM personal_access_tokens 
WHERE tokenable_id = 1 
ORDER BY created_at DESC 
LIMIT 5;
```

Pastikan:
- Token ada di database
- `tokenable_type` = `App\Models\MemberAppsMember`
- `tokenable_id` sesuai dengan member ID
- `expires_at` belum expired (atau NULL)

### 2. Cek Sanctum Configuration

**File: `config/sanctum.php`**
```php
'guard' => ['web'], // Pastikan ini sesuai
```

**File: `config/auth.php`**
```php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
    'sanctum' => [
        'driver' => 'sanctum',
        'provider' => 'users',
    ],
],
```

### 3. Cek Middleware

**File: `app/Http/Kernel.php` atau `bootstrap/app.php`**
Pastikan Sanctum middleware sudah terdaftar.

### 4. Cek Model

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

### 5. Cek Route Middleware

**File: `routes/api.php`**
Pastikan route `/auth/me` ada di dalam:
```php
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
});
```

### 6. Cek Token Format

Token dari `createToken()` formatnya: `{id}|{hash}`

Contoh: `96|ORREFxkIBXHs4a4B10GuV2u2cAqYL621I5EMEMPkbc64de99`

Pastikan:
- Token dikirim dengan format: `Authorization: Bearer {token}`
- Tidak ada space di awal/akhir token
- Header `Accept: application/json` ada

### 7. Test Token Langsung di Database

```sql
-- Cek token terbaru
SELECT 
    id,
    tokenable_type,
    tokenable_id,
    name,
    token,
    abilities,
    last_used_at,
    expires_at,
    created_at
FROM personal_access_tokens
WHERE tokenable_id = 1
ORDER BY created_at DESC
LIMIT 1;
```

### 8. Cek Laravel Logs

```bash
tail -f storage/logs/laravel.log
```

Cari log:
- `Auth me called but no user found` → Token tidak terdeteksi
- `Auth me successful` → Token berhasil

### 9. Test dengan Debug Route

Test dengan route debug yang sudah dibuat:
```
GET https://ymsofterp.com/api/mobile/member/auth/test-token
Authorization: Bearer {TOKEN}
```

Route ini akan cek:
- Token ada atau tidak
- Token format valid atau tidak
- Token ada di database atau tidak
- Member ditemukan atau tidak

---

## 🔧 Solusi Sementara:

Jika semua sudah benar tapi masih 401, coba:

1. **Clear cache:**
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

2. **Restart server** (jika pakai queue/worker)

3. **Cek `.env`:**
```env
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1,ymsofterp.com
SESSION_DRIVER=cookie
```

4. **Test dengan Postman** untuk pastikan bukan masalah PowerShell

---

## 📝 Next Steps:

1. Jalankan script `test-auth.ps1` lagi
2. Cek log di server: `tail -f storage/logs/laravel.log`
3. Cek token di database dengan query di atas
4. Test dengan route debug `/auth/test-token`

