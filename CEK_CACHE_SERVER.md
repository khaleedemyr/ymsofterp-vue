# Checklist: Clear Cache di Server

## ⚠️ Masalah:
File config sudah di-deploy tapi masih 401. Kemungkinan **cache belum di-clear**.

## ✅ Langkah di Server Production:

### 1. Clear Semua Cache

SSH ke server dan jalankan:

```bash
# Clear config cache
php artisan config:clear

# Clear application cache
php artisan cache:clear

# Clear route cache
php artisan route:clear

# Clear view cache
php artisan view:clear

# Clear semua cache sekaligus
php artisan optimize:clear
```

### 2. Rebuild Cache (Optional)

Setelah clear, bisa rebuild:

```bash
php artisan config:cache
php artisan route:cache
php artisan optimize
```

**TAPI:** Jika masih development/testing, lebih baik **JANGAN** cache, biarkan fresh setiap request.

### 3. Restart PHP-FPM

Setelah clear cache, restart PHP-FPM:

```bash
# Cek versi PHP yang digunakan
php -v

# Restart sesuai versi
sudo systemctl restart php8.1-fpm   # PHP 8.1
# atau
sudo systemctl restart php8.2-fpm   # PHP 8.2
# atau
sudo systemctl restart php8.3-fpm   # PHP 8.3
```

### 4. Restart Web Server (Jika Perlu)

```bash
# Nginx
sudo systemctl restart nginx

# Apache
sudo systemctl restart apache2
```

### 5. Cek File Config

Pastikan file sudah benar-benar ter-deploy:

```bash
# Cek file sanctum.php
cat config/sanctum.php

# Cek guard sanctum di auth.php
grep -A 5 "sanctum" config/auth.php
```

### 6. Test Lagi

Setelah semua langkah di atas, test lagi dengan:
```powershell
.\test-auth.ps1
```

---

## 🔍 Debug: Cek Token di Database

Jika masih 401, cek token di database:

```sql
-- Cek token terbaru
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

Pastikan:
- ✅ Token ada di database
- ✅ `tokenable_type` = `App\Models\MemberAppsMember`
- ✅ `expires_at` = NULL (tidak expired)

---

## 📝 Quick Command (Copy-Paste):

```bash
# Clear semua cache
php artisan optimize:clear

# Restart PHP-FPM (sesuaikan versi)
sudo systemctl restart php8.1-fpm

# Test dengan curl (ganti TOKEN dengan token dari login)
curl -X GET "https://ymsofterp.com/api/mobile/member/auth/me" \
  -H "Authorization: Bearer TOKEN" \
  -H "Accept: application/json"
```

---

## 🚨 Jika Masih Error:

1. **Cek Laravel Logs:**
```bash
tail -f storage/logs/laravel.log
```

2. **Cek apakah Sanctum sudah terinstall:**
```bash
composer show laravel/sanctum
```

3. **Test dengan debug route:**
```
GET https://ymsofterp.com/api/mobile/member/auth/test-token
Authorization: Bearer TOKEN
```

Route ini akan cek apakah token terdeteksi dan ada di database.

