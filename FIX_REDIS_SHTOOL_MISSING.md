# 🔴 Fix: build/shtool Tidak Ada Setelah Extract

## 🎯 **MASALAH**

**Setelah extract `redis-5.3.7.tgz`:**
- File `build/shtool` tidak ada
- Error: `ls: cannot access 'build/shtool': No such file or directory`

**Ini NORMAL!** `shtool` dibuat oleh `phpize`, bukan dari source code.

---

## ⚡ **SOLUSI: Run phpize Dulu**

**`shtool` akan dibuat otomatis saat run `phpize`.**

### **STEP 1: Run phpize**

```bash
# Pastikan sudah di directory redis-5.3.7
cd /tmp/redis-5.3.7

# Run phpize (ini akan membuat build/shtool)
/opt/cpanel/ea-php82/root/usr/bin/phpize
```

**Expected output:**
```
Configuring for:
PHP Api Version:         20220829
Zend Module Api No:      20220829
Zend Extension Api No:   420220829
```

**Setelah phpize, check shtool:**
```bash
ls -la build/shtool
# Expected: File akan ada sekarang
```

---

### **STEP 2: Configure**

```bash
# Configure
./configure --with-php-config=/opt/cpanel/ea-php82/root/usr/bin/php-config
```

---

### **STEP 3: Make**

```bash
# Compile
make
```

---

### **STEP 4: Install**

```bash
# Install
make install
```

---

### **STEP 5: Enable Extension**

```bash
# Enable extension
echo "extension=redis.so" >> /opt/cpanel/ea-php82/root/etc/php.ini
```

---

### **STEP 6: Restart PHP-FPM**

```bash
# Restart PHP-FPM
systemctl restart ea-php82-php-fpm
```

---

### **STEP 7: Verify**

```bash
# Verify
/opt/cpanel/ea-php82/root/usr/bin/php -m | grep redis
# Expected: redis
```

---

## ✅ **PROSEDUR LENGKAP - DARI AWAL**

```bash
# 1. Pastikan di /tmp
cd /tmp

# 2. Extract (jika belum)
tar -xzf redis-5.3.7.tgz
cd redis-5.3.7

# 3. Run phpize (INI YANG PENTING - akan membuat build/shtool)
/opt/cpanel/ea-php82/root/usr/bin/phpize

# 4. Check shtool (sekarang harus ada)
ls -la build/shtool

# 5. Configure
./configure --with-php-config=/opt/cpanel/ea-php82/root/usr/bin/php-config

# 6. Make
make

# 7. Install
make install

# 8. Enable extension
echo "extension=redis.so" >> /opt/cpanel/ea-php82/root/etc/php.ini

# 9. Restart PHP-FPM
systemctl restart ea-php82-php-fpm

# 10. Verify
/opt/cpanel/ea-php82/root/usr/bin/php -m | grep redis
```

---

## 🎯 **KESIMPULAN**

**`build/shtool` tidak ada setelah extract = NORMAL!**

**Solusi:**
1. ✅ **Run `phpize` dulu** - ini akan membuat `build/shtool`
2. ✅ **Setelah itu baru configure, make, install**

**Urutan yang benar:**
1. Extract source
2. **Run phpize** (membuat build/shtool)
3. Configure
4. Make
5. Install

**Status:** ✅ **Run `phpize` dulu, `shtool` akan dibuat otomatis!**
