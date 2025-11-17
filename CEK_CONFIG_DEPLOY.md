# Checklist: Deploy Config Auth.php

## 🔍 Masalah:
Login berhasil (ada di log), tapi `/auth/me` masih 401.

Ini berarti file `config/auth.php` yang sudah di-update **belum di-deploy** ke server atau **cache belum di-clear**.

## ✅ Checklist di Server:

### 1. Pastikan File Sudah Di-deploy

Cek di server, file `config/auth.php` harus ada:
- Provider `members` (baris ~73-76)
- Guard `sanctum` menggunakan provider `members` (baris ~44-47)

**Cek dengan:**
```bash
grep -A 3 "members" config/auth.php
grep -A 2 "sanctum" config/auth.php
```

### 2. Clear Cache (PENTING!)

Setelah deploy, **WAJIB** clear cache:
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan optimize:clear
```

### 3. Restart PHP-FPM (Jika Perlu)

```bash
sudo systemctl restart php8.1-fpm  # atau versi PHP yang digunakan
```

### 4. Verifikasi Config

Test apakah config sudah benar:
```bash
php artisan tinker
```

Lalu di tinker:
```php
config('auth.guards.sanctum.provider');  // Harus return 'members'
config('auth.providers.members.model');  // Harus return 'App\Models\MemberAppsMember'
```

### 5. Test Lagi

Setelah semua langkah di atas, test lagi:
```powershell
.\test-auth.ps1
```

---

## 📝 Perubahan yang Harus Ada di Server:

**File: `config/auth.php`**

**1. Guard sanctum (sekitar baris 44-47):**
```php
'sanctum' => [
    'driver' => 'sanctum',
    'provider' => 'members',  // <-- Harus 'members', bukan 'users'
],
```

**2. Provider members (sekitar baris 73-76):**
```php
'members' => [
    'driver' => 'eloquent',
    'model' => App\Models\MemberAppsMember::class,
],
```

---

## 🚨 Jika Masih Error:

1. **Cek Laravel Logs:**
```bash
tail -f storage/logs/laravel.log
```

Saat test `/auth/me`, lihat apakah ada log:
- `Auth me called but no user found` → Token tidak ter-authenticate
- `Auth me successful` → Token berhasil

2. **Test dengan tinker:**
```bash
php artisan tinker
```

```php
$token = '111|w0TCOXRxrE5km5AXvessd1Is8LRJTe4wqw3PwtZF15e1ab67';
$tokenParts = explode('|', $token);
$tokenId = $tokenParts[0];
$dbToken = \DB::table('personal_access_tokens')->where('id', $tokenId)->first();
$dbToken->tokenable_type;  // Harus 'App\Models\MemberAppsMember'
$member = \App\Models\MemberAppsMember::find($dbToken->tokenable_id);
$member;  // Harus ada data
```

---

## ✅ Quick Fix:

```bash
# 1. Deploy config/auth.php
# 2. Clear cache
php artisan optimize:clear

# 3. Test
curl -X GET "https://ymsofterp.com/api/mobile/member/auth/me" \
  -H "Authorization: Bearer TOKEN_DARI_LOGIN" \
  -H "Accept: application/json"
```

