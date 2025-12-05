# Sync dari Server ke Local via SSH (Tanpa Git)

Download file langsung dari server ke local menggunakan SSH/SCP, **tanpa perlu Git**.

## Prasyarat

1. **Akses SSH ke server** (sudah ada atau minta ke hosting provider)
2. **SSH client di Windows**:
   - PowerShell (built-in Windows 10/11)
   - Git Bash (jika sudah install Git)
   - PuTTY/WinSCP (optional, GUI)

## Metode 1: Via PowerShell (Windows 10/11) ⭐

### Langkah 1: Test SSH Connection

```powershell
# Test koneksi SSH ke server
ssh ymsuperadmin@your-server-ip

# Atau dengan domain
ssh ymsuperadmin@your-domain.com

# Jika berhasil, akan masuk ke server
# Ketik 'exit' untuk keluar
```

### Langkah 2: Download Folder via SCP

```powershell
# Di PowerShell (local Windows)
cd D:\Gawean\YM\web\ymsofterp

# Download folder app
scp -r ymsuperadmin@your-server-ip:/home/ymsuperadmin/public_html/app ./

# Download folder routes
scp -r ymsuperadmin@your-server-ip:/home/ymsuperadmin/public_html/routes ./

# Download folder bootstrap
scp -r ymsuperadmin@your-server-ip:/home/ymsuperadmin/public_html/bootstrap ./

# Download folder config
scp -r ymsuperadmin@your-server-ip:/home/ymsuperadmin/public_html/config ./

# Download folder database
scp -r ymsuperadmin@your-server-ip:/home/ymsuperadmin/public_html/database ./
```

### Langkah 3: Download Semua Sekaligus (Lebih Cepat)

```powershell
# Download semua folder sekaligus
scp -r ymsuperadmin@your-server-ip:/home/ymsuperadmin/public_html/{app,routes,bootstrap,config,database} ./
```

**Catatan**: Jika error dengan syntax `{app,routes,...}`, gunakan perintah terpisah seperti di Langkah 2.

---

## Metode 2: Via Git Bash (Jika Install Git)

### Langkah 1: Buka Git Bash

- Klik kanan di folder project → "Git Bash Here"
- Atau buka Git Bash dan `cd` ke folder project

### Langkah 2: Download via SCP

```bash
# Di Git Bash
cd /d/Gawean/YM/web/ymsofterp

# Download folder app
scp -r ymsuperadmin@your-server-ip:/home/ymsuperadmin/public_html/app ./

# Download folder routes
scp -r ymsuperadmin@your-server-ip:/home/ymsuperadmin/public_html/routes ./

# Download folder bootstrap
scp -r ymsuperadmin@your-server-ip:/home/ymsuperadmin/public_html/bootstrap ./

# Download folder config
scp -r ymsuperadmin@your-server-ip:/home/ymsuperadmin/public_html/config ./

# Download folder database
scp -r ymsuperadmin@your-server-ip:/home/ymsuperadmin/public_html/database ./
```

---

## Metode 3: Via WinSCP (GUI - Paling Mudah) ⭐⭐⭐

### Langkah 1: Download WinSCP

- Download: https://winscp.net/eng/download.php
- Install WinSCP

### Langkah 2: Connect ke Server

1. Buka WinSCP
2. Isi:
   - **File protocol**: SFTP
   - **Host name**: IP server atau domain
   - **Port number**: 22
   - **User name**: ymsuperadmin
   - **Password**: password SSH
3. Klik **Login**

### Langkah 3: Download Folder

1. Di panel **kanan** (server): Navigate ke `/home/ymsuperadmin/public_html`
2. Di panel **kiri** (local): Navigate ke `D:\Gawean\YM\web\ymsofterp`
3. **Drag & drop** folder dari server ke local:
   - `app` → drag ke local
   - `routes` → drag ke local
   - `bootstrap` → drag ke local
   - `config` → drag ke local
   - `database` → drag ke local

**WinSCP akan otomatis replace folder yang sudah ada.**

---

## Metode 4: Via PuTTY (PSFTP)

### Langkah 1: Download PuTTY

- Download: https://www.putty.org/
- Install PuTTY (termasuk PSFTP)

### Langkah 2: Download via PSFTP

```powershell
# Buka PowerShell atau Command Prompt
cd D:\Gawean\YM\web\ymsofterp

# Jalankan PSFTP
psftp ymsuperadmin@your-server-ip

# Setelah masuk, download folder
cd /home/ymsuperadmin/public_html
lcd D:\Gawean\YM\web\ymsofterp
get -r app
get -r routes
get -r bootstrap
get -r config
get -r database
exit
```

---

## Script PowerShell Otomatis

Buat file `download_via_ssh.ps1`:

```powershell
# download_via_ssh.ps1
# Edit credentials di bawah

$serverHost = "your-server-ip-or-domain"
$serverUser = "ymsuperadmin"
$remotePath = "/home/ymsuperadmin/public_html"
$localPath = "D:\Gawean\YM\web\ymsofterp"

$folders = @("app", "routes", "bootstrap", "config", "database")

Write-Host "=== DOWNLOAD VIA SSH ===" -ForegroundColor Green
Write-Host "Server: $serverUser@$serverHost" -ForegroundColor Yellow
Write-Host ""

foreach ($folder in $folders) {
    Write-Host "Downloading $folder..." -ForegroundColor Cyan
    
    $remoteFolder = "$remotePath/$folder"
    $localFolder = "$localPath\$folder"
    
    # Backup dulu jika folder sudah ada
    if (Test-Path $localFolder) {
        $backupFolder = "$localFolder.backup.$(Get-Date -Format 'yyyyMMdd_HHmmss')"
        Write-Host "  Creating backup: $backupFolder" -ForegroundColor Gray
        Copy-Item -Path $localFolder -Destination $backupFolder -Recurse -Force
    }
    
    # Download via SCP
    scp -r "${serverUser}@${serverHost}:${remoteFolder}" $localPath
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host "  ✓ Downloaded: $folder" -ForegroundColor Green
    } else {
        Write-Host "  ✗ Failed: $folder" -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "=== DONE ===" -ForegroundColor Green
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Yellow
Write-Host "1. Run: composer install" -ForegroundColor White
Write-Host "2. Run: php artisan config:clear" -ForegroundColor White
```

**Cara pakai:**
```powershell
# Edit file, isi serverHost dan serverUser
# Jalankan:
.\download_via_ssh.ps1
```

---

## Setelah Download

### 1. Install Dependencies
```bash
composer install
```

### 2. Clear Cache
```bash
php artisan config:clear
php artisan route:clear
php artisan cache:clear
```

### 3. Update .env (JANGAN Replace!)
- Buka `.env` di server via SSH atau cPanel
- Copy nilai yang berbeda ke `.env` local
- Pastikan `APP_ENV=local` di local

### 4. Test
```bash
php artisan serve
```

---

## Troubleshooting

### Error: "Permission denied"

**Solusi:**
```bash
# Pastikan folder di server readable
ssh ymsuperadmin@your-server-ip
chmod -R 755 /home/ymsuperadmin/public_html/app
chmod -R 755 /home/ymsuperadmin/public_html/routes
# dst...
```

### Error: "Connection refused" atau "Connection timeout"

**Solusi:**
- Cek apakah SSH enabled di server
- Cek firewall/port 22
- Hubungi hosting provider untuk enable SSH
- Coba gunakan port lain jika port 22 diblokir

### Error: "Host key verification failed"

**Solusi:**
```powershell
# Hapus known_hosts entry untuk server
ssh-keygen -R your-server-ip

# Atau edit known_hosts manual
notepad $env:USERPROFILE\.ssh\known_hosts
```

### Download Lambat

**Solusi:**
- Gunakan WinSCP (lebih cepat untuk banyak file)
- Compress di server dulu, download ZIP, extract di local
- Gunakan `rsync` jika tersedia (lebih efisien)

---

## Perbandingan Metode

| Metode | Kelebihan | Kekurangan |
|--------|-----------|------------|
| **PowerShell SCP** | Built-in Windows, tidak perlu install | Command line, kurang user-friendly |
| **Git Bash SCP** | Sama seperti Linux/Mac | Perlu install Git |
| **WinSCP** | GUI, drag & drop, progress bar | Perlu install aplikasi |
| **PuTTY PSFTP** | Lightweight | Command line, kurang intuitif |

**Rekomendasi**: Gunakan **WinSCP** untuk kemudahan, atau **PowerShell SCP** jika sudah familiar dengan command line.

---

## Tips

- ✅ **Backup dulu** sebelum download (script otomatis sudah include backup)
- ✅ **WinSCP paling mudah** untuk pemula
- ✅ **Compress di server** jika koneksi lambat (ZIP dulu, download, extract)
- ✅ **Jangan download `vendor/`** - install via composer
- ✅ **Jangan download `node_modules/`** - install via npm

