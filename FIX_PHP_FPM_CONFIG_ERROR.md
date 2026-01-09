# 🚨 FIX: PHP-FPM Config Error - "unknown entry 'pm'"

## ⚠️ **ERROR YANG TERJADI**

```
ERROR: [/opt/cpanel/ea-php82/root/etc/php-fpm.d/[nama-file].conf:1] unknown entry 'pm'
ERROR: Unable to include /opt/cpanel/ea-php82/root/etc/php-fpm.d/[nama-file].conf
```

**Penyebab:** File config yang di-edit salah atau format tidak benar.

---

## ✅ **SOLUSI: Fix Config File**

### **LANGKAH 1: Cari File Config yang Benar**

```bash
# Cari semua file config PHP-FPM
ls -la /opt/cpanel/ea-php82/root/etc/php-fpm.d/

# Atau cari file yang baru saja di-edit (berdasarkan timestamp)
find /opt/cpanel/ea-php82/root/etc/php-fpm.d -name "*.conf" -mmin -10
```

**Catatan:** File config biasanya nama domain atau username, bukan `[nama-file]`

---

### **LANGKAH 2: Check File Config yang Error**

```bash
# List semua file config
ls -la /opt/cpanel/ea-php82/root/etc/php-fpm.d/*.conf

# Check file yang error (cari yang baru di-edit)
cat /opt/cpanel/ea-php82/root/etc/php-fpm.d/[nama-file-aktual].conf
```

**Jika file tidak ada atau kosong**, berarti file yang di-edit salah.

---

### **LANGKAH 3: Restore Config File (Jika Salah Edit)**

**Opsi A: Hapus File yang Error (Jika Bukan File Utama)**

```bash
# Backup dulu
cp /opt/cpanel/ea-php82/root/etc/php-fpm.d/[nama-file-error].conf /opt/cpanel/ea-php82/root/etc/php-fpm.d/[nama-file-error].conf.backup

# Hapus file yang error
rm /opt/cpanel/ea-php82/root/etc/php-fpm.d/[nama-file-error].conf
```

**Opsi B: Edit File Config yang Benar**

File config PHP-FPM di cPanel biasanya formatnya seperti ini:

```ini
[www]
user = ymsuperadmin
group = ymsuperadmin
listen = /opt/cpanel/ea-php82/root/var/run/php-fpm/ymsuperadmin.sock
listen.owner = ymsuperadmin
listen.group = ymsuperadmin
listen.mode = 0660

pm = dynamic
pm.max_children = 24
pm.start_servers = 12
pm.min_spare_servers = 8
pm.max_spare_servers = 12
pm.max_requests = 100
pm.process_idle_timeout = 10s

php_admin_value[error_log] = /home/ymsuperadmin/public_html/storage/logs/php-fpm-error.log
php_admin_flag[log_errors] = on
```

**PENTING:** Harus ada `[www]` atau `[pool-name]` di bagian atas!

---

### **LANGKAH 4: Cari File Config yang Benar untuk Domain**

```bash
# Cari file config berdasarkan domain atau username
grep -r "ymsuperadmin" /opt/cpanel/ea-php82/root/etc/php-fpm.d/*.conf

# Atau list semua file dan cek satu per satu
for file in /opt/cpanel/ea-php82/root/etc/php-fpm.d/*.conf; do
    echo "=== $file ==="
    head -5 "$file"
    echo ""
done
```

---

### **LANGKAH 5: Edit File Config yang Benar**

**JANGAN edit file yang tidak ada atau salah!**

1. **Cari file config yang benar:**
   ```bash
   ls -la /opt/cpanel/ea-php82/root/etc/php-fpm.d/
   ```

2. **Check isi file (harusnya sudah ada setting):**
   ```bash
   cat /opt/cpanel/ea-php82/root/etc/php-fpm.d/[nama-file-benar].conf
   ```

3. **Edit file yang benar:**
   ```bash
   nano /opt/cpanel/ea-php82/root/etc/php-fpm.d/[nama-file-benar].conf
   ```

4. **Cari baris `pm.max_children` dan edit:**
   ```ini
   pm.max_children = 24
   pm.start_servers = 12
   pm.min_spare_servers = 8
   pm.max_spare_servers = 12
   pm.max_requests = 100
   ```

5. **JANGAN tambahkan `pm = dynamic` jika sudah ada!**

6. **Save (Ctrl+O, Enter, Ctrl+X)**

---

### **LANGKAH 6: Atau Lebih Aman - Edit via cPanel Saja**

**Jika bingung file mana yang harus di-edit, lebih baik:**

1. **Hapus file yang error** (jika ada file baru yang dibuat salah)
2. **Edit via cPanel saja** - cPanel akan handle config file otomatis
3. **Hanya ubah Max Children di cPanel** - tidak perlu edit file manual

---

### **LANGKAH 7: Restart PHP-FPM**

Setelah fix config:

```bash
# Test config dulu
/opt/cpanel/ea-php82/root/usr/sbin/php-fpm -t

# Jika OK, restart
systemctl restart ea-php82-php-fpm

# Check status
systemctl status ea-php82-php-fpm
```

---

## 🔧 **TROUBLESHOOTING**

### **Masalah 1: File Config Tidak Ditemukan**

```bash
# List semua file config
ls -la /opt/cpanel/ea-php82/root/etc/php-fpm.d/

# Check main config file
cat /opt/cpanel/ea-php82/root/etc/php-fpm.conf | grep include
```

### **Masalah 2: Syntax Error**

```bash
# Test syntax config
/opt/cpanel/ea-php82/root/usr/sbin/php-fpm -t

# Akan muncul error detail jika ada syntax error
```

### **Masalah 3: File Config Kosong atau Salah Format**

**Restore dari backup atau hapus file yang error:**

```bash
# Backup dulu
cp /opt/cpanel/ea-php82/root/etc/php-fpm.d/[file-error].conf /opt/cpanel/ea-php82/root/etc/php-fpm.d/[file-error].conf.backup

# Hapus file error
rm /opt/cpanel/ea-php82/root/etc/php-fpm.d/[file-error].conf

# Restart PHP-FPM (akan regenerate config)
systemctl restart ea-php82-php-fpm
```

---

## 📋 **SOLUSI CEPAT (Jika Bingung)**

**Opsi Teraman:**

1. **Hapus semua file config yang baru dibuat:**
   ```bash
   # List file yang baru di-edit (dalam 10 menit terakhir)
   find /opt/cpanel/ea-php82/root/etc/php-fpm.d -name "*.conf" -mmin -10
   
   # Hapus file yang error (ganti [nama-file] dengan nama file yang ditemukan)
   rm /opt/cpanel/ea-php82/root/etc/php-fpm.d/[nama-file-error].conf
   ```

2. **Edit via cPanel saja:**
   - Login cPanel → MultiPHP Manager → PHP-FPM Settings
   - Ubah Max Children: 80 → 24
   - Klik Update
   - cPanel akan handle config file otomatis

3. **Restart PHP-FPM:**
   ```bash
   systemctl restart ea-php82-php-fpm
   systemctl status ea-php82-php-fpm
   ```

---

## ⚠️ **CATATAN PENTING**

1. **JANGAN edit file config jika tidak yakin file mana yang benar**
2. **Lebih aman edit via cPanel** - cPanel handle config otomatis
3. **Jika edit manual, pastikan format benar** - harus ada `[pool-name]` di bagian atas
4. **Backup dulu sebelum edit** - `cp file.conf file.conf.backup`

---

**Lakukan Langkah 1-7 untuk fix error!** ✅
