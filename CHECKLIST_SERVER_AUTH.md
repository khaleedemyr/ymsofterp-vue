# Checklist Pengecekan Server untuk Authentication Issue

## ⚠️ PENTING: Hati-hati karena ada web application juga!

### 1. ✅ Cek Database `personal_access_tokens` Table
```sql
-- Cek apakah table ada
SHOW TABLES LIKE 'personal_access_tokens';

-- Cek token yang ada (jika table ada)
SELECT id, tokenable_type, tokenable_id, name, token, abilities, last_used_at, expires_at, created_at 
FROM personal_access_tokens 
ORDER BY created_at DESC 
LIMIT 10;

-- Cek apakah ada token yang expired
SELECT COUNT(*) as expired_tokens 
FROM personal_access_tokens 
WHERE expires_at IS NOT NULL AND expires_at < NOW();
```

**Jika table tidak ada**, jalankan migration:
```bash
php artisan migrate
```

### 2. ✅ Cek HandleCors Middleware
File: `app/Http/Middleware/HandleCors.php` - **Saat ini KOSONG!**

**Perlu diisi dengan:**
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class HandleCors
{
    public function handle(Request $request, Closure $next)
    {
        // Allow dari mobile app (tidak perlu CORS)
        if ($request->is('api/*')) {
            return $next($request);
        }

        // Untuk web, handle CORS
        $response = $next($request);
        
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        
        if ($request->getMethod() === 'OPTIONS') {
            return response('', 200);
        }
        
        return $response;
    }
}
```

### 3. ✅ Cek Sanctum Configuration
Cek file `.env` di server production:
```env
SANCTUM_STATEFUL_DOMAINS=ymsofterp.com,localhost
SESSION_DRIVER=cookie
SESSION_DOMAIN=.ymsofterp.com
```

**Untuk mobile app**, Sanctum tidak perlu stateful domains, tapi pastikan:
- Token tidak expired terlalu cepat
- Database connection ke `personal_access_tokens` table berfungsi

### 4. ✅ Cek Token Expiration
Sanctum default tidak expire token. Tapi cek apakah ada custom expiration di:
- `app/Models/MemberAppsMember.php` - method `createToken()`
- Middleware custom yang mungkin expire token

### 5. ✅ Cek API Route Middleware
File: `routes/api.php` - Pastikan route menggunakan `auth:sanctum`:
```php
Route::middleware(['auth:sanctum'])->group(function () {
    // Protected routes
});
```

### 6. ✅ Cek Database Connection
Pastikan database connection di `.env` production benar:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nama_database
DB_USERNAME=username
DB_PASSWORD=password
```

### 7. ✅ Test Token di Server
Login via mobile app, lalu cek token di database:
```sql
SELECT 
    pat.id,
    pat.tokenable_id,
    m.member_id,
    m.email,
    m.nama_lengkap,
    pat.name,
    LEFT(pat.token, 20) as token_preview,
    pat.last_used_at,
    pat.expires_at,
    pat.created_at
FROM personal_access_tokens pat
JOIN member_apps_members m ON pat.tokenable_id = m.id
WHERE pat.tokenable_type = 'App\\Models\\MemberAppsMember'
ORDER BY pat.created_at DESC
LIMIT 5;
```

### 8. ✅ Cek Log Error
Cek Laravel log untuk error authentication:
```bash
tail -f storage/logs/laravel.log | grep -i "unauth\|token\|sanctum"
```

### 9. ✅ Cek .env Production
Pastikan environment production:
```env
APP_ENV=production
APP_DEBUG=false
```

## 🚨 Yang TIDAK BOLEH Diubah (Agar Web Tetap Berfungsi):
1. ❌ Jangan ubah session driver (biarkan `cookie` untuk web)
2. ❌ Jangan ubah middleware `web` group
3. ❌ Jangan hapus CSRF protection untuk web routes
4. ❌ Jangan ubah CORS untuk web (handle dengan HandleCors middleware)

## 🔧 Langkah Perbaikan yang Aman:

1. **Isi HandleCors.php** (aman untuk web dan mobile)
2. **Cek dan buat table personal_access_tokens** (jika belum ada)
3. **Test login via mobile app** dan cek token di database
4. **Cek log** untuk error detail

## 📝 Query untuk Debugging:

```sql
-- Cek member yang sudah login
SELECT 
    m.id,
    m.member_id,
    m.email,
    m.nama_lengkap,
    COUNT(pat.id) as token_count,
    MAX(pat.last_used_at) as last_login
FROM member_apps_members m
LEFT JOIN personal_access_tokens pat ON pat.tokenable_id = m.id 
    AND pat.tokenable_type = 'App\\Models\\MemberAppsMember'
GROUP BY m.id
ORDER BY last_login DESC
LIMIT 10;
```

