# 🔧 Solusi: Scheduler Tidak Terdeteksi

## Masalah
Saat menjalankan `php artisan schedule:list`, muncul pesan:
```
INFO No scheduled tasks have been defined.
```

Padahal di `app/Console/Kernel.php` sudah ada scheduled tasks.

---

## ✅ SOLUSI LENGKAP

### **LANGKAH 1: Clear Laravel Cache**

Jalankan di server:
```bash
cd /home/ymsuperadmin/public_html
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

**Kemudian rebuild cache:**
```bash
php artisan config:cache
```

**Test lagi:**
```bash
php artisan schedule:list
```

---

### **LANGKAH 2: Check Kernel.php**

Pastikan file `app/Console/Kernel.php` ada dan benar:

```bash
# Check file ada
ls -la app/Console/Kernel.php

# Check isi file
cat app/Console/Kernel.php | head -20

# Check scheduled tasks ada
grep -c "\$schedule->command" app/Console/Kernel.php
```

**Harusnya output menunjukkan jumlah scheduled tasks (19 tasks).**

---

### **LANGKAH 3: Check Method schedule()**

Pastikan method `schedule()` ada di Kernel.php:

```bash
grep -A 5 "protected function schedule" app/Console/Kernel.php
```

**Harusnya muncul:**
```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('attendance:process-holiday')
    ...
}
```

---

### **LANGKAH 4: Dump Autoload**

Jika menggunakan Composer:

```bash
composer dump-autoload
```

---

### **LANGKAH 5: Check Syntax Error**

```bash
# Check apakah ada error
php artisan list

# Atau check syntax PHP
php -l app/Console/Kernel.php
```

**Jika ada error, perbaiki dulu.**

---

### **LANGKAH 6: Check File Permission**

```bash
# Set permission yang benar
chmod 644 app/Console/Kernel.php
chown ymsuperadmin:ymsuperadmin app/Console/Kernel.php
```

---

### **LANGKAH 7: Restart PHP-FPM (Jika Perlu)**

```bash
# CentOS/RHEL
systemctl restart php-fpm

# Ubuntu/Debian
systemctl restart php8.1-fpm
# atau
systemctl restart php8.2-fpm
```

---

## 🔍 Troubleshooting Detail

### Problem 1: Cache Issue

**Gejala:** Scheduled tasks ada di code tapi tidak terdeteksi.

**Solusi:**
```bash
php artisan config:clear
php artisan cache:clear
php artisan config:cache
php artisan schedule:list
```

### Problem 2: Kernel.php Tidak Ter-Load

**Gejala:** File ada tapi Laravel tidak membaca.

**Solusi:**
1. Check namespace: `namespace App\Console;`
2. Check class name: `class Kernel extends ConsoleKernel`
3. Check method: `protected function schedule(Schedule $schedule): void`
4. Dump autoload: `composer dump-autoload`

### Problem 3: Syntax Error

**Gejala:** Ada error saat `php artisan list`.

**Solusi:**
```bash
# Check syntax
php -l app/Console/Kernel.php

# Check error detail
php artisan list 2>&1 | grep -i error
```

### Problem 4: Method schedule() Kosong

**Gejala:** Method ada tapi tidak ada `$schedule->command()` di dalamnya.

**Solusi:**
- Check isi method `schedule()` di Kernel.php
- Pastikan ada scheduled tasks di dalamnya

### Problem 5: Wrong Laravel Version

**Gejala:** Menggunakan Laravel versi lama yang berbeda syntax.

**Solusi:**
- Check Laravel version: `php artisan --version`
- Pastikan syntax sesuai versi Laravel

---

## ✅ Checklist Verifikasi

Setelah perbaikan, verifikasi dengan:

```bash
# 1. Check scheduled tasks di code
grep -c "\$schedule->command" app/Console/Kernel.php
# Harusnya: 19

# 2. Clear cache
php artisan config:clear && php artisan cache:clear

# 3. Rebuild cache
php artisan config:cache

# 4. Test schedule:list
php artisan schedule:list
# Harusnya menampilkan semua scheduled tasks

# 5. Test schedule:run
php artisan schedule:run -v
# Harusnya menampilkan tasks yang akan dijalankan atau di-skip
```

---

## 🚀 Quick Fix Script

Gunakan script yang sudah dibuat:
```bash
bash fix-scheduler-not-detected.sh
```

Script ini akan:
- ✅ Check Kernel.php ada
- ✅ Count scheduled tasks
- ✅ Clear cache
- ✅ Rebuild cache
- ✅ Test schedule:list
- ✅ Check syntax error
- ✅ Dump autoload

---

## 📋 Langkah Manual (Jika Script Tidak Bisa)

1. **Clear cache:**
   ```bash
   cd /home/ymsuperadmin/public_html
   php artisan config:clear
   php artisan cache:clear
   ```

2. **Rebuild cache:**
   ```bash
   php artisan config:cache
   ```

3. **Dump autoload:**
   ```bash
   composer dump-autoload
   ```

4. **Test:**
   ```bash
   php artisan schedule:list
   ```

5. **Jika masih kosong, check Kernel.php:**
   ```bash
   cat app/Console/Kernel.php
   # Pastikan method schedule() ada dan berisi scheduled tasks
   ```

---

## ⚠️ CATATAN PENTING

1. **Cache Laravel** bisa menyebabkan scheduler tidak terdeteksi
2. **Autoload** perlu di-update jika ada perubahan di Kernel.php
3. **File permission** harus benar (644 untuk file, 755 untuk directory)
4. **Syntax error** akan menyebabkan Laravel tidak bisa load Kernel.php
5. **Restart PHP-FPM** kadang diperlukan setelah perubahan

---

## 🔗 File Terkait

- `app/Console/Kernel.php` - File scheduler
- `fix-scheduler-not-detected.sh` - Script auto-fix
- `check-scheduler.sh` - Script check scheduler

---

## 📞 Jika Masih Tidak Bisa

Jika setelah semua langkah di atas masih tidak bisa:

1. **Check Laravel version:**
   ```bash
   php artisan --version
   ```

2. **Check PHP version:**
   ```bash
   php -v
   ```

3. **Check error log:**
   ```bash
   tail -50 storage/logs/laravel.log
   ```

4. **Test manual Kernel.php:**
   ```bash
   php -r "require 'vendor/autoload.php'; \$app = require_once 'bootstrap/app.php'; \$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();"
   ```

5. **Check apakah ada custom Kernel:**
   ```bash
   grep -r "extends.*Kernel" app/
   ```

