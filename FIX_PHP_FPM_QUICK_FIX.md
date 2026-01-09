# 🚨 FIX PHP-FPM Error - Quick Fix

## ✅ **FILE YANG ERROR DITEMUKAN**

File yang menyebabkan error:
- `/opt/cpanel/ea-php82/root/etc/php-fpm.d/[nama-file].conf` ❌ **HAPUS FILE INI!**

File yang benar (jangan dihapus):
- `/opt/cpanel/ea-php82/root/etc/php-fpm.d/ymsofterp.com.conf` ✅
- `/opt/cpanel/ea-php82/root/etc/php-fpm.d/tms.ymsofterp.com.conf` ✅

---

## 🔧 **LANGKAH PERBAIKAN**

### **LANGKAH 1: Hapus File yang Error**

```bash
# Hapus file yang error
rm /opt/cpanel/ea-php82/root/etc/php-fpm.d/\[nama-file\].conf

# Atau dengan escape karakter
rm "/opt/cpanel/ea-php82/root/etc/php-fpm.d/[nama-file].conf"

# Verifikasi sudah terhapus
ls -la /opt/cpanel/ea-php82/root/etc/php-fpm.d/\[nama-file\].conf
# Harusnya: No such file or directory
```

---

### **LANGKAH 2: Check File Config yang Benar**

```bash
# Check file ymsofterp.com.conf
cat /opt/cpanel/ea-php82/root/etc/php-fpm.d/ymsofterp.com.conf | grep -E "pm.max_children|pm.start_servers"

# Check file tms.ymsofterp.com.conf
cat /opt/cpanel/ea-php82/root/etc/php-fpm.d/tms.ymsofterp.com.conf | grep -E "pm.max_children|pm.start_servers"
```

**Pastikan setting sudah benar:**
- `pm.max_children = 24` (bukan 80)
- `pm.start_servers = 12`
- `pm.min_spare_servers = 8`
- `pm.max_spare_servers = 12`

---

### **LANGKAH 3: Test Config Syntax**

```bash
# Test config syntax
/opt/cpanel/ea-php82/root/usr/sbin/php-fpm -t
```

**Harusnya output:** `test is successful` (tidak ada error)

---

### **LANGKAH 4: Restart PHP-FPM**

```bash
# Restart dengan service name yang benar (ea-php82-php-fpm, bukan php-fpm)
systemctl restart ea-php82-php-fpm

# Check status
systemctl status ea-php82-php-fpm
```

**Harusnya:** `Active: active (running)` (tidak ada error)

---

### **LANGKAH 5: Verifikasi**

```bash
# Check PHP-FPM processes
ps aux | grep php-fpm | grep -v grep | wc -l
# Expected: 12-24 (bukan 80)

# Check CPU usage
top
# Expected: CPU usage turun ke 30-50%
```

---

## 📋 **COMMAND LENGKAP (Copy-Paste Semua)**

```bash
# 1. Hapus file error
rm "/opt/cpanel/ea-php82/root/etc/php-fpm.d/[nama-file].conf"

# 2. Test config
/opt/cpanel/ea-php82/root/usr/sbin/php-fpm -t

# 3. Restart PHP-FPM
systemctl restart ea-php82-php-fpm

# 4. Check status
systemctl status ea-php82-php-fpm

# 5. Verifikasi processes
ps aux | grep php-fpm | grep -v grep | wc -l
```

---

## ⚠️ **CATATAN**

1. **Jangan hapus file `ymsofterp.com.conf` dan `tms.ymsofterp.com.conf`** - itu file yang benar
2. **Hanya hapus file `[nama-file].conf`** - itu file yang error
3. **Service name yang benar:** `ea-php82-php-fpm` (bukan `php-fpm`)

---

**Lakukan Langkah 1-5 sekarang!** ✅
