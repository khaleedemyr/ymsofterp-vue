# Panduan Sync Code dari Server ke Local

Karena server sudah di-restore dan versi di server lebih baik, ikuti langkah berikut:

## ⚠️ BACKUP DULU!

Sebelum sync, backup file penting:
```powershell
# Di PowerShell
cd D:\Gawean\YM\web\ymsofterp
xcopy app app_backup_$(Get-Date -Format 'yyyyMMdd') /E /I /Y
xcopy routes routes_backup_$(Get-Date -Format 'yyyyMMdd') /E /I /Y
xcopy bootstrap bootstrap_backup_$(Get-Date -Format 'yyyyMMdd') /E /I /Y
```

## Metode 1: Via cPanel File Manager (Paling Mudah) ⭐

### Langkah-langkah:

1. **Login ke cPanel**
   - Buka cPanel server Anda
   - Login dengan credentials

2. **Buka File Manager**
   - Cari "File Manager" di cPanel
   - Navigate ke folder project (biasanya `public_html`)

3. **Download Folder yang Perlu**
   
   **Opsi A: Download per folder (Recommended)**
   - Klik kanan folder `app` -> Compress -> ZIP
   - Download ZIP file
   - Extract di local, replace folder `app`
   - Ulangi untuk: `routes`, `bootstrap`, `config`, `database`

   **Opsi B: Download semua sekaligus**
   - Select folder: `app`, `routes`, `bootstrap`, `config`, `database`
   - Klik "Compress" -> ZIP
   - Download ZIP file besar
   - Extract di local

4. **File yang Perlu Di-download:**
   - ✅ `app/` (semua controller, models, dll)
   - ✅ `routes/` (web.php, api.php, dll)
   - ✅ `bootstrap/` (app.php, dll)
   - ✅ `config/` (jika ada perubahan)
   - ✅ `database/` (migrations, seeders jika ada)
   - ❌ `vendor/` (JANGAN download, install via composer)
   - ❌ `node_modules/` (JANGAN download, install via npm)
   - ❌ `storage/` (JANGAN download, kecuali ada custom files)
   - ❌ `.env` (JANGAN replace, tapi copy nilai yang berbeda)

## Metode 2: Via FTP Client (FileZilla/WinSCP)

1. **Download FileZilla** (jika belum punya)
   - https://filezilla-project.org/

2. **Connect ke Server**
   - Host: IP atau domain server
   - Username: ymsuperadmin (atau sesuai)
   - Password: password FTP
   - Port: 21 (FTP) atau 22 (SFTP)

3. **Download Folder**
   - Di panel kiri: folder local
   - Di panel kanan: folder server (`public_html`)
   - Drag & drop folder dari server ke local:
     - `app` -> `D:\Gawean\YM\web\ymsofterp\app`
     - `routes` -> `D:\Gawean\YM\web\ymsofterp\routes`
     - `bootstrap` -> `D:\Gawean\YM\web\ymsofterp\bootstrap`
     - `config` -> `D:\Gawean\YM\web\ymsofterp\config`
     - `database` -> `D:\Gawean\YM\web\ymsofterp\database`

## Metode 3: Via Git (Jika Server Punya Git)

⚠️ **Catatan**: GitHub tidak lagi mendukung password authentication. Perlu setup Personal Access Token atau SSH key. Lihat `GIT_AUTH_FIX.md` untuk detail.

### Opsi A: Setup Git Auth di Server (Jika ingin push)

**Setup Personal Access Token:**
```bash
# Di server
cd /home/ymsuperadmin/public_html

# Update remote URL dengan token
git remote set-url origin https://khaleedemyr:YOUR_TOKEN@github.com/khaleedemyr/ymsofterp-vue.git
# Ganti YOUR_TOKEN dengan Personal Access Token dari GitHub

# Commit dan push
git add .
git commit -m "Server version - restore from backup"
git push origin main
```

**Setup SSH Key (Lebih Aman):**
```bash
# Generate SSH key di server
ssh-keygen -t ed25519 -C "server@ymsofterp"

# Copy public key
cat ~/.ssh/id_ed25519.pub

# Tambahkan ke GitHub: Settings -> SSH and GPG keys -> New SSH key

# Update remote URL
git remote set-url origin git@github.com:khaleedemyr/ymsofterp-vue.git

# Push
git push origin main
```

### Opsi B: Download Langsung (TIDAK Perlu Push ke Git) ⭐ RECOMMENDED

**Lebih mudah download langsung dari server:**
- Gunakan **Metode 1 (cPanel)** atau **Metode 2 (FTP)**
- Tidak perlu setup Git auth di server
- Langsung download dan replace di local

## Metode 4: Via SSH/SCP (Jika Punya Akses SSH)

```bash
# Di PowerShell atau Git Bash
scp -r ymsuperadmin@your-server-ip:/home/ymsuperadmin/public_html/app D:\Gawean\YM\web\ymsofterp\
scp -r ymsuperadmin@your-server-ip:/home/ymsuperadmin/public_html/routes D:\Gawean\YM\web\ymsofterp\
scp -r ymsuperadmin@your-server-ip:/home/ymsuperadmin/public_html/bootstrap D:\Gawean\YM\web\ymsofterp\
scp -r ymsuperadmin@your-server-ip:/home/ymsuperadmin/public_html/config D:\Gawean\YM\web\ymsofterp\
```

## Setelah Download - Langkah Selanjutnya

### 1. Install Dependencies
```bash
cd D:\Gawean\YM\web\ymsofterp
composer install
```

### 2. Update .env (JANGAN Replace!)
- Buka `.env` di server (via cPanel File Manager)
- Copy nilai yang berbeda ke `.env` local
- **JANGAN replace** `.env` local sepenuhnya
- Pastikan `APP_ENV=local` di local
- Pastikan database credentials sesuai local

### 3. Clear Cache
```bash
php artisan config:clear
php artisan route:clear
php artisan cache:clear
php artisan view:clear
```

### 4. Test Aplikasi
- Jalankan: `php artisan serve`
- Test beberapa fitur penting
- Pastikan tidak ada error

### 5. Commit ke Git (Backup)
```bash
git add .
git commit -m "Sync from server - restore version"
git push origin master
```

## Checklist File yang Paling Penting

Berdasarkan perubahan yang sudah kita buat, pastikan file ini ter-sync:

- ✅ `routes/web.php` - Route fixes
- ✅ `routes/api.php` - Route fixes
- ✅ `bootstrap/app.php` - Exception handler
- ✅ `app/Providers/AppServiceProvider.php` - Notification observer
- ✅ `app/Providers/EventServiceProvider.php` - Event listeners
- ✅ `app/Observers/NotificationObserver.php` - Notification observer
- ✅ `app/Http/Controllers/Auth/AuthenticatedSessionController.php` - Bug fix

## Troubleshooting

### Jika ada conflict setelah sync:
1. Cek file yang conflict
2. Bandingkan dengan backup
3. Merge manual jika perlu

### Jika aplikasi error setelah sync:
1. Cek `.env` - pastikan config benar
2. Run `composer install` lagi
3. Clear semua cache
4. Cek log: `storage/logs/laravel.log`

### Jika permission error:
- Run `fix_permissions_cpanel.php` di server
- Atau set manual via cPanel File Manager

## Tips

- ✅ **Selalu backup dulu** sebelum replace file
- ✅ **Jangan replace `.env`** - merge manual
- ✅ **Test setelah sync** untuk memastikan tidak ada masalah
- ✅ **Commit ke Git** setelah sync untuk backup
- ❌ **Jangan download `vendor/`** - install via composer
- ❌ **Jangan download `node_modules/`** - install via npm
