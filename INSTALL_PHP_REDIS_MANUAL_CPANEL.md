# 🔴 Install PHP Redis Extension Manual untuk cPanel (PHP 8.2)

## 🎯 **MASALAH**

**Paket tidak tersedia:**
- `ea-php82-php-pear` → Tidak ditemukan
- `php-pear` → Di-exclude oleh filter
- `ea-php82-php-devel` → ✅ Sudah terinstall
- `gcc`, `make`, `autoconf` → ✅ Sudah terinstall

**Kesimpulan:** Install Redis extension secara manual (download dan compile)

---

## ⚡ **SOLUSI: Install Manual (Download & Compile)**

### **STEP 1: Check Dependencies**

```bash
# Check apakah dependencies sudah terinstall
which gcc
which make
which autoconf
/opt/cpanel/ea-php82/root/usr/bin/phpize -v

# Expected: Semua command harus ada output
```

**Jika `phpize` tidak ada:**
```bash
# Check apakah php-devel sudah terinstall
rpm -qa | grep ea-php82-php-devel

# Jika sudah terinstall, phpize harus ada di:
/opt/cpanel/ea-php82/root/usr/bin/phpize
```

---

### **STEP 2: Download Redis Extension Source**

```bash
# Login sebagai root
ssh root@your-server

# Download Redis extension source code
cd /tmp
wget https://pecl.php.net/get/redis-5.3.7.tgz

# Atau versi terbaru
# wget https://pecl.php.net/get/redis-latest.tgz

# Extract
tar -xzf redis-5.3.7.tgz
cd redis-5.3.7
```

---

### **STEP 3: Compile Redis Extension**

```bash
# Prepare build environment
/opt/cpanel/ea-php82/root/usr/bin/phpize

# Configure
./configure --with-php-config=/opt/cpanel/ea-php82/root/usr/bin/php-config

# Compile
make

# Install
make install
```

**Expected output:**
```
Installing shared extensions:     /opt/cpanel/ea-php82/root/usr/lib64/php/modules/
```

---

### **STEP 4: Enable Extension di php.ini**

```bash
# Check apakah extension file sudah dibuat
ls -la /opt/cpanel/ea-php82/root/usr/lib64/php/modules/redis.so

# Add extension ke php.ini
echo "extension=redis.so" >> /opt/cpanel/ea-php82/root/etc/php.ini

# Verify
grep -i redis /opt/cpanel/ea-php82/root/etc/php.ini
```

---

### **STEP 5: Restart PHP-FPM**

```bash
# Restart PHP-FPM
systemctl restart ea-php82-php-fpm

# Check status
systemctl status ea-php82-php-fpm
```

---

### **STEP 6: Verify Installation**

```bash
# Check PHP Redis extension
/opt/cpanel/ea-php82/root/usr/bin/php -m | grep redis
# Expected: redis

# Test Redis connection
/opt/cpanel/ea-php82/root/usr/bin/php -r "try { \$r = new Redis(); \$r->connect('127.0.0.1', 6379); echo 'Connected!'; } catch (Exception \$e) { echo 'Failed: ' . \$e->getMessage(); }"
# Expected: Connected!
```

---

## 🔧 **TROUBLESHOOTING**

### **Error: "phpize: command not found"**

**Solusi:**
```bash
# Check apakah phpize ada
/opt/cpanel/ea-php82/root/usr/bin/phpize -v

# Jika tidak ada, check php-devel
rpm -qa | grep ea-php82-php-devel

# Jika tidak terinstall, coba install via cPanel atau check path lain
find /opt/cpanel -name phpize 2>/dev/null
```

---

### **Error: "configure: error: Cannot find php-config"**

**Solusi:**
```bash
# Check php-config path
/opt/cpanel/ea-php82/root/usr/bin/php-config --version

# Gunakan path yang benar
./configure --with-php-config=/opt/cpanel/ea-php82/root/usr/bin/php-config
```

---

### **Error: "make: *** [Makefile:xxx: target] Error 1"**

**Solusi:**
```bash
# Check error detail
make 2>&1 | tail -20

# Common issues:
# 1. Missing dependencies - install yang diperlukan
# 2. Wrong PHP version - check PHP version
# 3. Compiler error - check gcc version

# Clean dan coba lagi
make clean
./configure --with-php-config=/opt/cpanel/ea-php82/root/usr/bin/php-config
make
```

---

### **Error: "extension=redis.so" tidak bekerja**

**Solusi:**
```bash
# Check extension file
ls -la /opt/cpanel/ea-php82/root/usr/lib64/php/modules/redis.so

# Check extension dir di php.ini
/opt/cpanel/ea-php82/root/usr/bin/php -i | grep extension_dir

# Pastikan path benar
# Jika extension_dir berbeda, gunakan full path:
# extension=/opt/cpanel/ea-php82/root/usr/lib64/php/modules/redis.so
```

---

## 📊 **ALTERNATIVE: Install via cPanel Interface**

**Jika manual install tidak berhasil, coba via cPanel:**

1. **Login cPanel**
2. **Software → Select PHP Version**
3. **Pilih PHP 8.2** (ea-php82)
4. **Klik "Extensions"** atau **"PECL"**
5. **Cari "redis"**
6. **Install PHP Redis extension**
7. **Restart PHP-FPM**

**Atau via WHM:**
1. **Login WHM**
2. **Software → EasyApache 4** (atau **MultiPHP Manager**)
3. **Pilih PHP 8.2**
4. **Install Redis extension**
5. **Rebuild PHP**

---

## ✅ **CHECKLIST INSTALL MANUAL**

- [ ] **Check dependencies** (`gcc`, `make`, `autoconf`, `phpize`)
- [ ] **Download Redis source** (`wget https://pecl.php.net/get/redis-5.3.7.tgz`)
- [ ] **Extract source** (`tar -xzf redis-5.3.7.tgz`)
- [ ] **Run phpize** (`/opt/cpanel/ea-php82/root/usr/bin/phpize`)
- [ ] **Configure** (`./configure --with-php-config=...`)
- [ ] **Compile** (`make`)
- [ ] **Install** (`make install`)
- [ ] **Enable extension** (`echo "extension=redis.so" >> php.ini`)
- [ ] **Restart PHP-FPM** (`systemctl restart ea-php82-php-fpm`)
- [ ] **Verify** (`php -m | grep redis`)

---

## 🎯 **QUICK INSTALL SCRIPT**

```bash
#!/bin/bash

# Install PHP Redis Extension Manual
cd /tmp
wget https://pecl.php.net/get/redis-5.3.7.tgz
tar -xzf redis-5.3.7.tgz
cd redis-5.3.7

/opt/cpanel/ea-php82/root/usr/bin/phpize
./configure --with-php-config=/opt/cpanel/ea-php82/root/usr/bin/php-config
make
make install

echo "extension=redis.so" >> /opt/cpanel/ea-php82/root/etc/php.ini
systemctl restart ea-php82-php-fpm

/opt/cpanel/ea-php82/root/usr/bin/php -m | grep redis
```

---

## 🎯 **KESIMPULAN**

**Paket tidak tersedia → Install manual (download & compile)**

**Solusi:**
1. ✅ **Download Redis source** dari PECL
2. ✅ **Compile manual** dengan `phpize`, `configure`, `make`
3. ✅ **Install** dengan `make install`
4. ✅ **Enable extension** di php.ini
5. ✅ **Restart PHP-FPM** dan verify

**Status:** 🔴 **Install manual adalah cara terakhir jika PECL tidak tersedia!**
