# 🔧 Edit PHP-FPM Config yang Benar

## 📊 **STATUS SAAT INI**

File config: `/opt/cpanel/ea-php82/root/etc/php-fpm.d/ymsofterp.com.conf`
- `pm.max_children = 40` ❌ (harus 24)
- `pm.start_servers = 0` ❌ (harus 12)

---

## ✅ **LANGKAH EDIT CONFIG**

### **LANGKAH 1: Backup File Config**

```bash
# Backup dulu sebelum edit
cp /opt/cpanel/ea-php82/root/etc/php-fpm.d/ymsofterp.com.conf /opt/cpanel/ea-php82/root/etc/php-fpm.d/ymsofterp.com.conf.backup

# Backup juga file tms.ymsofterp.com.conf
cp /opt/cpanel/ea-php82/root/etc/php-fpm.d/tms.ymsofterp.com.conf /opt/cpanel/ea-php82/root/etc/php-fpm.d/tms.ymsofterp.com.conf.backup
```

---

### **LANGKAH 2: Edit File Config**

```bash
# Edit file config
nano /opt/cpanel/ea-php82/root/etc/php-fpm.d/ymsofterp.com.conf
```

**Cari baris berikut dan edit:**

```ini
# Cari baris ini:
pm.max_children = 40
pm.start_servers = 0

# Ubah menjadi:
pm.max_children = 24
pm.start_servers = 12
```

**Juga pastikan ada setting ini (jika belum ada, tambahkan):**

```ini
pm.min_spare_servers = 8
pm.max_spare_servers = 12
pm.max_requests = 100
pm.process_idle_timeout = 10s
```

**Save:** `Ctrl + O`, `Enter`, `Ctrl + X`

---

### **LANGKAH 3: Edit File Config Kedua (tms.ymsofterp.com.conf)**

```bash
# Edit file config kedua
nano /opt/cpanel/ea-php82/root/etc/php-fpm.d/tms.ymsofterp.com.conf
```

**Ubah setting yang sama:**
- `pm.max_children = 24`
- `pm.start_servers = 12`
- `pm.min_spare_servers = 8`
- `pm.max_spare_servers = 12`
- `pm.max_requests = 100`

**Save:** `Ctrl + O`, `Enter`, `Ctrl + X`

---

### **LANGKAH 4: Verifikasi Setting**

```bash
# Check file pertama
cat /opt/cpanel/ea-php82/root/etc/php-fpm.d/ymsofterp.com.conf | grep -E "pm.max_children|pm.start_servers|pm.min_spare|pm.max_spare"

# Check file kedua
cat /opt/cpanel/ea-php82/root/etc/php-fpm.d/tms.ymsofterp.com.conf | grep -E "pm.max_children|pm.start_servers|pm.min_spare|pm.max_spare"
```

**Expected output:**
```
pm.max_children = 24
pm.start_servers = 12
pm.min_spare_servers = 8
pm.max_spare_servers = 12
```

---

### **LANGKAH 5: Test Config Syntax**

```bash
# Test config syntax
/opt/cpanel/ea-php82/root/usr/sbin/php-fpm -t
```

**Harusnya:** `test is successful` (tidak ada error)

---

### **LANGKAH 6: Restart PHP-FPM**

```bash
# Restart PHP-FPM
systemctl restart ea-php82-php-fpm

# Check status
systemctl status ea-php82-php-fpm
```

**Harusnya:** `Active: active (running)` (tidak ada error)

---

### **LANGKAH 7: Verifikasi Processes**

```bash
# Check total PHP-FPM processes (harusnya 12-24, bukan 40+)
ps aux | grep php-fpm | grep -v grep | wc -l

# Check detail
ps aux | grep php-fpm | grep -v grep | head -5
```

**Expected:** 12-24 processes (bukan 40+)

---

## 📋 **COMMAND LENGKAP (Copy-Paste)**

```bash
# 1. Backup
cp /opt/cpanel/ea-php82/root/etc/php-fpm.d/ymsofterp.com.conf /opt/cpanel/ea-php82/root/etc/php-fpm.d/ymsofterp.com.conf.backup
cp /opt/cpanel/ea-php82/root/etc/php-fpm.d/tms.ymsofterp.com.conf /opt/cpanel/ea-php82/root/etc/php-fpm.d/tms.ymsofterp.com.conf.backup

# 2. Edit file pertama
nano /opt/cpanel/ea-php82/root/etc/php-fpm.d/ymsofterp.com.conf
# (Ubah pm.max_children = 40 → 24, pm.start_servers = 0 → 12)

# 3. Edit file kedua
nano /opt/cpanel/ea-php82/root/etc/php-fpm.d/tms.ymsofterp.com.conf
# (Ubah setting yang sama)

# 4. Test config
/opt/cpanel/ea-php82/root/usr/sbin/php-fpm -t

# 5. Restart
systemctl restart ea-php82-php-fpm

# 6. Check status
systemctl status ea-php82-php-fpm

# 7. Verifikasi
ps aux | grep php-fpm | grep -v grep | wc -l
```

---

## 🔧 **CARA EDIT YANG LEBIH CEPAT (sed command)**

Jika tidak mau edit manual, bisa pakai `sed`:

```bash
# Edit file pertama
sed -i 's/pm.max_children = 40/pm.max_children = 24/' /opt/cpanel/ea-php82/root/etc/php-fpm.d/ymsofterp.com.conf
sed -i 's/pm.start_servers = 0/pm.start_servers = 12/' /opt/cpanel/ea-php82/root/etc/php-fpm.d/ymsofterp.com.conf

# Tambahkan setting jika belum ada
grep -q "pm.min_spare_servers" /opt/cpanel/ea-php82/root/etc/php-fpm.d/ymsofterp.com.conf || echo "pm.min_spare_servers = 8" >> /opt/cpanel/ea-php82/root/etc/php-fpm.d/ymsofterp.com.conf
grep -q "pm.max_spare_servers" /opt/cpanel/ea-php82/root/etc/php-fpm.d/ymsofterp.com.conf || echo "pm.max_spare_servers = 12" >> /opt/cpanel/ea-php82/root/etc/php-fpm.d/ymsofterp.com.conf
grep -q "pm.max_requests" /opt/cpanel/ea-php82/root/etc/php-fpm.d/ymsofterp.com.conf || echo "pm.max_requests = 100" >> /opt/cpanel/ea-php82/root/etc/php-fpm.d/ymsofterp.com.conf

# Edit file kedua (sama)
sed -i 's/pm.max_children = 40/pm.max_children = 24/' /opt/cpanel/ea-php82/root/etc/php-fpm.d/tms.ymsofterp.com.conf
sed -i 's/pm.start_servers = 0/pm.start_servers = 12/' /opt/cpanel/ea-php82/root/etc/php-fpm.d/tms.ymsofterp.com.conf
grep -q "pm.min_spare_servers" /opt/cpanel/ea-php82/root/etc/php-fpm.d/tms.ymsofterp.com.conf || echo "pm.min_spare_servers = 8" >> /opt/cpanel/ea-php82/root/etc/php-fpm.d/tms.ymsofterp.com.conf
grep -q "pm.max_spare_servers" /opt/cpanel/ea-php82/root/etc/php-fpm.d/tms.ymsofterp.com.conf || echo "pm.max_spare_servers = 12" >> /opt/cpanel/ea-php82/root/etc/php-fpm.d/tms.ymsofterp.com.conf
grep -q "pm.max_requests" /opt/cpanel/ea-php82/root/etc/php-fpm.d/tms.ymsofterp.com.conf || echo "pm.max_requests = 100" >> /opt/cpanel/ea-php82/root/etc/php-fpm.d/tms.ymsofterp.com.conf

# Test dan restart
/opt/cpanel/ea-php82/root/usr/sbin/php-fpm -t
systemctl restart ea-php82-php-fpm
```

---

## ⚠️ **CATATAN**

1. **Edit kedua file** (`ymsofterp.com.conf` dan `tms.ymsofterp.com.conf`)
2. **Backup dulu** sebelum edit
3. **Test config** sebelum restart
4. **Monitor** setelah restart

---

**Lakukan edit sekarang!** ✅
