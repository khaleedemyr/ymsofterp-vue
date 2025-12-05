# Checklist Setelah Sync dari Server

Setelah local sudah di-reset ke versi server, lakukan langkah berikut:

## 1. Install Dependencies

```powershell
# Install Composer dependencies
composer install

# Install NPM dependencies (jika ada)
npm install
```

## 2. Update .env

**PENTING:** Jangan replace `.env` local!

- Buka `.env` di local
- Pastikan `APP_ENV=local`
- Pastikan database credentials sesuai local
- Copy nilai penting dari server jika ada perubahan

## 3. Clear Cache

```powershell
php artisan config:clear
php artisan route:clear
php artisan cache:clear
php artisan view:clear
```

## 4. Generate Key (Jika Perlu)

```powershell
php artisan key:generate
```

## 5. Run Migrations (Jika Perlu)

```powershell
php artisan migrate
```

## 6. Test Aplikasi

```powershell
php artisan serve
```

Buka browser: http://localhost:8000

## 7. Commit Perubahan Local (Jika Perlu)

```powershell
git add .
git commit -m "Sync from server - local environment setup"
```

---

## Troubleshooting

### Error: "Class not found"
- Run: `composer dump-autoload`

### Error: "Route not found"
- Run: `php artisan route:clear`
- Run: `php artisan route:cache`

### Error: "Config not found"
- Run: `php artisan config:clear`
- Run: `php artisan config:cache`

### Error: Database connection
- Cek `.env` - pastikan database credentials benar
- Pastikan database server running

---

## File yang Mungkin Perlu Di-update

- `.env` - Pastikan sesuai local environment
- `config/database.php` - Jika ada perubahan
- `config/app.php` - Pastikan `APP_ENV=local`

---

## Status

✅ Local sudah di-reset ke versi server
✅ Siap untuk development

