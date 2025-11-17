# Fix: Sanctum Configuration Missing

## 🔍 Masalah:
File `config/sanctum.php` tidak ada di server, sehingga authentication tidak bekerja.

## ✅ Solusi:

### 1. File Config Sudah Dibuat

Saya sudah membuat file `config/sanctum.php` di local. **Deploy file ini ke server production.**

### 2. Update config/auth.php

File `config/auth.php` sudah di-update untuk menambahkan guard `sanctum`. **Deploy perubahan ini juga ke server.**

### 3. Di Server Production:

Setelah deploy file, jalankan:

```bash
# Clear cache
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan optimize:clear

# Restart PHP-FPM (jika perlu)
sudo systemctl restart php8.1-fpm  # atau versi PHP yang digunakan
```

### 4. Cek .env

Pastikan di `.env` ada:
```env
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1,ymsofterp.com
```

### 5. Test Lagi

Setelah deploy dan clear cache, test lagi dengan:
```powershell
.\test-auth.ps1
```

---

## 📝 File yang Perlu Deploy:

1. ✅ `config/sanctum.php` (file baru)
2. ✅ `config/auth.php` (sudah di-update, tambah guard sanctum)

---

## 🔧 Alternative: Publish di Server

Jika lebih mudah, bisa publish langsung di server:

```bash
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

Tapi setelah itu, tetap perlu:
1. Tambahkan guard `sanctum` di `config/auth.php`
2. Clear cache

---

## ✅ Checklist:

- [ ] Deploy `config/sanctum.php` ke server
- [ ] Deploy `config/auth.php` (yang sudah di-update) ke server
- [ ] Clear cache di server
- [ ] Restart PHP-FPM (jika perlu)
- [ ] Test dengan `test-auth.ps1`

