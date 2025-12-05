# Cara Perbaiki Authentication Issue

## 🔍 Masalah yang Terjadi:
- Token ada di database `personal_access_tokens`
- Tapi API mengembalikan 401 Unauthenticated
- Mobile app tidak bisa akses protected routes

## ✅ Langkah Perbaikan:

### 1. Cek Table `personal_access_tokens` di Database
Jalankan query di `database/sql/check_personal_access_tokens.sql`:
```bash
mysql -u username -p database_name < database/sql/check_personal_access_tokens.sql
```

Atau langsung di phpMyAdmin/MySQL client.

### 2. Pastikan Sanctum Sudah Terinstall
```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

### 3. Cek Konfigurasi Sanctum
Pastikan di `config/sanctum.php` (jika ada) atau cek apakah Sanctum sudah ter-register di `config/app.php`:
```php
'providers' => [
    // ...
    Laravel\Sanctum\SanctumServiceProvider::class,
],
```

### 4. Cek Middleware di `app/Http/Kernel.php`
Pastikan tidak ada konflik dengan middleware lain.

### 5. Test Token Validation
Buat route test (temporary) untuk debug:
```php
// Di routes/api.php (temporary untuk testing)
Route::get('/test-auth', function (Request $request) {
    $user = $request->user();
    return response()->json([
        'authenticated' => $user !== null,
        'user_id' => $user ? $user->id : null,
        'token_preview' => $request->bearerToken() ? substr($request->bearerToken(), 0, 20) . '...' : 'no token'
    ]);
})->middleware('auth:sanctum');
```

### 6. Cek Log Laravel
```bash
tail -f storage/logs/laravel.log
```

Saat mobile app coba akses API, lihat log untuk error detail.

### 7. Pastikan Token Format Benar
Sanctum token format: `{id}|{hash}`
- Pastikan token yang dikirim dari mobile app dalam format ini
- Token harus dikirim di header: `Authorization: Bearer {token}`

### 8. Cek Database Connection
Pastikan database connection di `.env` benar dan bisa diakses:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nama_database
DB_USERNAME=username
DB_PASSWORD=password
```

### 9. Clear Cache (Jika Perlu)
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

### 10. Test dengan Postman/curl
Test langsung dengan token dari database:
```bash
# Ambil token dari database (kolom token di personal_access_tokens)
# Format: {id}|{hash}

curl -X GET https://ymsofterp.com/api/mobile/member/auth/me \
  -H "Authorization: Bearer {token_dari_database}" \
  -H "Accept: application/json"
```

## 🚨 Yang Sudah Diperbaiki:

1. ✅ **HandleCors.php** - Sudah diisi dengan logic yang aman untuk web dan mobile
2. ✅ **AuthController.php** - Sudah ditambahkan logging untuk debugging
3. ✅ **Query SQL** - Sudah dibuat untuk cek token di database

## 📝 Checklist Debugging:

- [ ] Table `personal_access_tokens` ada dan bisa diakses
- [ ] Token ada di database setelah login
- [ ] Token format benar (`{id}|{hash}`)
- [ ] Header `Authorization: Bearer {token}` dikirim dengan benar
- [ ] Middleware `auth:sanctum` terpasang di route
- [ ] Database connection berfungsi
- [ ] Log Laravel tidak ada error
- [ ] Sanctum sudah terinstall dan ter-register

## 🔧 Jika Masih Error:

1. **Cek log Laravel** untuk error detail
2. **Test dengan Postman** menggunakan token dari database
3. **Cek apakah token di mobile app sama** dengan yang di database
4. **Pastikan tidak ada middleware lain** yang mengganggu

## ⚠️ PENTING:
- Jangan hapus token yang masih aktif
- Backup database sebelum hapus token lama
- Test di development dulu sebelum production

