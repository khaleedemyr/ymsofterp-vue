# Troubleshooting Retail Non Food Module

## Error: "Cannot read properties of null (reading 'method')"

Error ini biasanya terjadi karena masalah dengan Inertia.js atau data yang dikirim ke Vue component.

### Solusi yang sudah diterapkan:

1. **✅ Route sudah diperbaiki**
   - Route sekarang menggunakan middleware `['auth', 'verified']`
   - Route sudah terdaftar dengan benar

2. **✅ Cache sudah di-clear**
   - Route cache: `php artisan route:clear`
   - Config cache: `php artisan config:clear`
   - View cache: `php artisan view:clear`

### Langkah selanjutnya untuk Anda:

#### 1. **Jalankan SQL untuk membuat tabel**
```sql
-- File: database/sql/retail_non_food_setup.sql
-- Jalankan query ini di database MySQL Anda
```

#### 2. **Jalankan SQL untuk insert menu & permissions**
```sql
-- File: database/sql/insert_retail_non_food_menu_simple.sql
-- Jalankan query ini di database MySQL Anda
```

#### 3. **Cek apakah tabel sudah ada**
```sql
-- File: database/sql/simple_check_tables.sql
-- Jalankan untuk mengecek status tabel
```

#### 4. **Assign permissions ke role**
```sql
-- Ganti role_id dengan ID role yang sesuai
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`, `updated_at`)
SELECT 
    1 as role_id,  -- Ganti dengan role_id admin
    p.id as permission_id,
    NOW() as created_at,
    NOW() as updated_at
FROM `erp_permission` p
WHERE p.menu_id = (SELECT id FROM erp_menus WHERE code = 'view-retail-non-food')
ON DUPLICATE KEY UPDATE updated_at = NOW();
```

### Jika masih error:

#### 1. **Cek Browser Console**
- Buka Developer Tools (F12)
- Lihat tab Console untuk error yang lebih detail
- Lihat tab Network untuk melihat response dari server

#### 2. **Cek Laravel Logs**
```bash
tail -f storage/logs/laravel.log
```

#### 3. **Test Route secara langsung**
```bash
# Test route index
curl -H "Accept: application/json" http://localhost/retail-non-food

# Test route create
curl -H "Accept: application/json" http://localhost/retail-non-food/create
```

#### 4. **Cek apakah user sudah login**
- Pastikan user sudah login
- Pastikan user memiliki permission `view-retail-non-food`
- Pastikan email user sudah verified (karena menggunakan middleware `verified`)

#### 5. **Cek apakah ada error di controller**
- Buka file `app/Http/Controllers/RetailNonFoodController.php`
- Pastikan tidak ada syntax error
- Pastikan semua method ada

#### 6. **Cek apakah Vue components ada**
- Pastikan file `resources/js/Pages/RetailNonFood/Index.vue` ada
- Pastikan file `resources/js/Pages/RetailNonFood/Form.vue` ada
- Pastikan file `resources/js/Pages/RetailNonFood/Detail.vue` ada

#### 7. **Restart Development Server**
```bash
# Stop server (Ctrl+C)
# Start server lagi
php artisan serve
```

### Debugging Steps:

1. **Cek Route List**
```bash
php artisan route:list --path=retail-non-food
```

2. **Cek Middleware**
```bash
php artisan route:list --path=retail-non-food --json
```

3. **Test Model**
```bash
php artisan tinker
>>> new App\Models\RetailNonFood;
```

4. **Cek Database Connection**
```bash
php artisan tinker
>>> DB::connection()->getPdo();
```

### Common Issues:

#### 1. **Tabel tidak ada**
- Jalankan SQL untuk membuat tabel
- Pastikan database connection benar

#### 2. **Permission tidak ada**
- Jalankan SQL untuk insert menu & permissions
- Assign permission ke role user

#### 3. **User tidak verified**
- Pastikan email user sudah verified
- Atau hapus middleware `verified` dari route

#### 4. **Vue component error**
- Cek browser console
- Pastikan semua import benar
- Pastikan tidak ada syntax error di Vue

#### 5. **Inertia.js error**
- Clear semua cache
- Restart development server
- Cek apakah Inertia.js sudah terinstall dengan benar

### Jika semua sudah benar tapi masih error:

1. **Coba akses route lain yang sudah berfungsi** (misal: `/retail-food`)
2. **Bandingkan dengan route yang berfungsi**
3. **Cek apakah ada perbedaan di middleware atau controller**
4. **Coba buat route test sederhana**

### Contact Support:
Jika masih mengalami masalah, siapkan informasi berikut:
- Error message lengkap dari browser console
- Laravel log error
- Route list output
- Database table status
- User permission status 