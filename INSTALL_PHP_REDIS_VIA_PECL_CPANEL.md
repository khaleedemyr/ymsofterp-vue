# 🔴 Install PHP Redis Extension via PECL untuk cPanel

## 🎯 **MASALAH**

**Error saat install via DNF:**
- `dnf install -y ea-php82-php-redis` → "No match for argument"
- `dnf install -y php-redis` → "All matches were filtered out by exclude filtering"

**Kesimpulan:** Paket tidak tersedia di repository default (cPanel menggunakan PHP khusus)

---

## ⚡ **SOLUSI: Install via PECL** ✅ RECOMMENDED

**PECL adalah cara yang paling reliable untuk install PHP extension di cPanel.**

### **STEP 1: Install Dependencies**

```bash
# Login sebagai root
ssh root@your-server

# Install dependencies untuk PECL
dnf install -y php-pear php-devel gcc make autoconf

# Atau untuk cPanel (ea-php82)
dnf install -y ea-php82-php-pear ea-php82-php-devel gcc make autoconf
```

---

### **STEP 2: Install Redis Extension via PECL**

**Untuk cPanel PHP 8.2 (ea-php82):**

```bash
# Install Redis extension via PECL
/opt/cpanel/ea-php82/root/usr/bin/pecl install redis

# Jika ada prompt, tekan Enter untuk default atau ketik "yes"
# Jika ada error tentang autoconf, install dulu:
# dnf install -y autoconf
```

**Jika PECL tidak tersedia:**

```bash
# Install PECL untuk ea-php82
dnf install -y ea-php82-php-pear

# Install Redis extension
/opt/cpanel/ea-php82/root/usr/bin/pecl install redis
```

---

### **STEP 3: Enable Extension di php.ini**

**Setelah install via PECL, enable extension:**

```bash
# Check apakah extension file sudah dibuat
ls -la /opt/cpanel/ea-php82/root/usr/lib64/php/modules/redis.so

# Add extension ke php.ini
echo "extension=redis.so" >> /opt/cpanel/ea-php82/root/etc/php.ini

# Atau edit manual
nano /opt/cpanel/ea-php82/root/etc/php.ini
# Tambahkan: extension=redis.so
```

---

### **STEP 4: Restart PHP-FPM**

```bash
# Restart PHP-FPM
systemctl restart ea-php82-php-fpm

# Check status
systemctl status ea-php82-php-fpm
```

---

### **STEP 5: Verify Installation**

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

### **Error: "pecl: command not found"**

**Solusi:**
```bash
# Install PECL untuk ea-php82
dnf install -y ea-php82-php-pear

# Check PECL
/opt/cpanel/ea-php82/root/usr/bin/pecl version
```

---

### **Error: "autoconf: command not found"**

**Solusi:**
```bash
# Install autoconf
dnf install -y autoconf

# Install Redis lagi
/opt/cpanel/ea-php82/root/usr/bin/pecl install redis
```

---

### **Error: "phpize: command not found"**

**Solusi:**
```bash
# Install php-devel untuk ea-php82
dnf install -y ea-php82-php-devel

# Install Redis lagi
/opt/cpanel/ea-php82/root/usr/bin/pecl install redis
```

---

### **Error: "Cannot find config.m4"**

**Solusi:**
```bash
# Install Redis extension dengan force
/opt/cpanel/ea-php82/root/usr/bin/pecl install -f redis

# Atau download dan install manual
cd /tmp
wget https://pecl.php.net/get/redis-5.3.7.tgz
tar -xzf redis-5.3.7.tgz
cd redis-5.3.7
/opt/cpanel/ea-php82/root/usr/bin/phpize
./configure --with-php-config=/opt/cpanel/ea-php82/root/usr/bin/php-config
make
make install
```

---

### **Error: Masih "Class 'Redis' not found" setelah install**

**Solusi:**
```bash
# Check extension file
ls -la /opt/cpanel/ea-php82/root/usr/lib64/php/modules/redis.so

# Check php.ini
grep -i redis /opt/cpanel/ea-php82/root/etc/php.ini

# Manual add extension
echo "extension=redis.so" >> /opt/cpanel/ea-php82/root/etc/php.ini

# Restart PHP-FPM
systemctl restart ea-php82-php-fpm

# Clear OPcache
php artisan opcache:clear
# atau
php artisan config:clear
```

---

## 📊 **ALTERNATIVE: Install via cPanel Interface**

**Jika PECL juga tidak berhasil, coba via cPanel:**

1. **Login cPanel**
2. **Software → Select PHP Version**
3. **Pilih PHP 8.2** (ea-php82)
4. **Klik "Extensions"** atau **"PECL"**
5. **Cari "redis"**
6. **Install PHP Redis extension**
7. **Restart PHP-FPM**

---

## ✅ **CHECKLIST INSTALL VIA PECL**

- [ ] **Install dependencies** (`dnf install -y ea-php82-php-pear ea-php82-php-devel gcc make autoconf`)
- [ ] **Install Redis via PECL** (`/opt/cpanel/ea-php82/root/usr/bin/pecl install redis`)
- [ ] **Enable extension** (`echo "extension=redis.so" >> /opt/cpanel/ea-php82/root/etc/php.ini`)
- [ ] **Restart PHP-FPM** (`systemctl restart ea-php82-php-fpm`)
- [ ] **Verify extension** (`php -m | grep redis`)
- [ ] **Test Redis connection** (`php -r "new Redis()..."`)
- [ ] **Test via Laravel Tinker** (`Redis::connection()->ping()`)

---

## 🎯 **KESIMPULAN**

**Paket tidak tersedia di repository default → Install via PECL**

**Solusi:**
1. ✅ **Install dependencies** (php-pear, php-devel, gcc, make, autoconf)
2. ✅ **Install Redis via PECL** (`pecl install redis`)
3. ✅ **Enable extension** di php.ini
4. ✅ **Restart PHP-FPM**
5. ✅ **Verify** dan test

**Status:** 🔴 **Install via PECL adalah cara yang paling reliable untuk cPanel!**
