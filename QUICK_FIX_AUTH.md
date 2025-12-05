# Quick Fix untuk Authentication Issue

## 🔍 Masalah:
Token ada di database tapi API mengembalikan 401 Unauthenticated

## ✅ Solusi Cepat:

### 1. Test Token dengan Route Debug
Saya sudah buat 2 route debug:
- `GET /api/mobile/member/auth/test-token` (NO AUTH - untuk cek token di database)
- `GET /api/mobile/member/auth/test-token-auth` (WITH AUTH - untuk test Sanctum)

**Cara test:**
1. Login via mobile app
2. Ambil token dari response login
3. Test dengan Postman/curl:
```bash
# Test tanpa auth (cek token di database)
curl -X GET https://ymsofterp.com/api/mobile/member/auth/test-token \
  -H "Authorization: Bearer {token_dari_login}" \
  -H "Accept: application/json"

# Test dengan auth:sanctum
curl -X GET https://ymsofterp.com/api/mobile/member/auth/test-token-auth \
  -H "Authorization: Bearer {token_dari_login}" \
  -H "Accept: application/json"
```

### 2. Cek Log Laravel
Setelah test, cek log:
```bash
tail -f storage/logs/laravel.log | grep -i "test token\|token debug"
```

### 3. Kemungkinan Masalah & Solusi:

#### A. Token Format Salah
**Gejala:** Token tidak bisa di-parse (tidak ada `|` di tengah)
**Solusi:** Pastikan token dari login response langsung digunakan, jangan di-modify

#### B. Token ID Tidak Ada di Database
**Gejala:** `db_token_found: false` di response test-token
**Solusi:** 
- Token mungkin dari database lain (development vs production)
- Login ulang untuk dapat token baru

#### C. Sanctum Tidak Bisa Validasi Token
**Gejala:** Token ada di database tapi `user_authenticated: false`
**Solusi:**
1. Pastikan Sanctum terinstall: `composer show laravel/sanctum`
2. Pastikan table `personal_access_tokens` ada dan bisa diakses
3. Cek apakah token hash match

#### D. Token Expired
**Gejala:** `expires_at` di database sudah lewat
**Solusi:** Login ulang untuk dapat token baru

### 4. Cek Sanctum Installation
```bash
# Cek apakah Sanctum terinstall
composer show laravel/sanctum

# Jika belum, install:
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

### 5. Clear Cache
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

### 6. Cek Database Connection
Pastikan database connection di `.env` benar dan table `personal_access_tokens` bisa diakses.

## 📝 Langkah Debugging:

1. **Deploy file yang sudah diperbaiki:**
   - `app/Http/Middleware/HandleCors.php`
   - `app/Http/Controllers/Mobile/Member/AuthController.php`
   - `routes/api.php` (ada route debug baru)

2. **Test dengan route debug:**
   - Login via mobile app
   - Ambil token
   - Test dengan Postman/curl ke `/api/mobile/member/auth/test-token`

3. **Cek response:**
   - Jika `db_token_found: false` → Token tidak ada di database (login ulang)
   - Jika `db_token_found: true` tapi `user_authenticated: false` → Masalah Sanctum validation
   - Jika `token_parsed: false` → Token format salah

4. **Cek log Laravel** untuk detail error

5. **Fix sesuai masalah** yang ditemukan

## 🚨 PENTING:
- Route debug (`/auth/test-token` dan `/auth/test-token-auth`) adalah TEMPORARY
- **HAPUS setelah masalah teratasi** untuk security
- Jangan expose route debug di production terlalu lama

## 🔧 Jika Masih Error Setelah Semua Langkah:

Kemungkinan besar masalahnya di:
1. **Token dari mobile app tidak match** dengan yang di database
2. **Sanctum tidak terinstall** atau tidak ter-register
3. **Database connection** tidak bisa akses table `personal_access_tokens`

Cek log Laravel untuk detail error yang lebih spesifik.

