# Debug: Sanctum Authentication Masih 401

## 🔍 Status:
- ✅ Authorization header sudah terkirim
- ✅ Token terdeteksi (`has_token: true`)
- ✅ Token ada di database (`db_token_found: true`)
- ✅ Member ditemukan (`member_found: true`)
- ❌ Tapi `/auth/me` masih 401 (`$request->user()` = null)

## 🔍 Kemungkinan Masalah:

### 1. Token Hash Tidak Cocok

Sanctum menyimpan token sebagai hash di database, tapi membandingkan dengan plain token dari request. Mungkin ada masalah dengan hash comparison.

**Cek di database:**
```sql
SELECT 
    id,
    tokenable_type,
    tokenable_id,
    name,
    token,  -- Ini adalah hash, bukan plain token
    abilities,
    created_at
FROM personal_access_tokens
WHERE id = 108;  -- Ganti dengan token_id terbaru
```

### 2. Guard Configuration

Pastikan guard `sanctum` sudah benar di `config/auth.php`:
```php
'sanctum' => [
    'driver' => 'sanctum',
    'provider' => 'users',
],
```

### 3. Provider Configuration

Pastikan provider `users` menggunakan model yang benar. Tapi karena kita pakai `MemberAppsMember`, mungkin perlu provider khusus.

### 4. Test dengan Debug Route yang Pakai Middleware

Test route `/auth/test-token-auth` yang pakai `auth:sanctum`:
```bash
curl -X GET "https://ymsofterp.com/api/mobile/member/auth/test-token-auth" \
  -H "Authorization: Bearer TOKEN" \
  -H "Accept: application/json"
```

Lihat apakah `user_authenticated: true` atau masih `false`.

---

## ✅ Solusi:

### Opsi 1: Cek Laravel Logs

SSH ke server dan cek log:
```bash
tail -f storage/logs/laravel.log
```

Saat test `/auth/me`, lihat apakah ada log:
- `Auth me called but no user found` → Token tidak ter-authenticate
- `Auth me successful` → Token berhasil

### Opsi 2: Test dengan Debug Route Auth

Test route yang pakai `auth:sanctum` middleware untuk lihat apakah Sanctum bisa authenticate:
```powershell
# Edit test-token-debug.ps1 untuk test /auth/test-token-auth juga
```

### Opsi 3: Cek Token Hash

Sanctum menyimpan token sebagai hash. Pastikan token yang dikirim cocok dengan hash di database.

**Test:** Coba login ulang dan langsung test dengan token baru (jangan tunggu lama).

### Opsi 4: Clear Cache Lagi

Mungkin perlu clear cache lagi setelah fix .htaccess:
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

---

## 📝 Next Steps:

1. **Cek Laravel logs** untuk melihat error detail
2. **Test dengan debug route auth** (`/auth/test-token-auth`)
3. **Clear cache** lagi
4. **Test dengan token yang baru** (login ulang)

