# 🔴 Install PHP Redis Extension untuk cPanel (PHP 8.2)

## 🎯 **MASALAH**

**Error: "Class 'Redis' not found"**

**Dari test:**
- `php -m | grep redis` → Tidak ada output (extension belum terinstall)
- `Cache::store('redis')->put()` → Error "Class 'Redis' not found"

**Kesimpulan:** PHP Redis extension belum terinstall untuk PHP 8.2 (ea-php82)

---

## ⚡ **SOLUSI: Install PHP Redis Extension**

### **Opsi 1: Via cPanel (Paling Mudah)** ✅ RECOMMENDED

1. **Login cPanel**
2. **Software → Select PHP Version**
3. **Pilih PHP 8.2** (ea-php82)
4. **Klik "Extensions"** atau **"PECL"**
5. **Cari "redis"** atau **"php-redis"**
6. **Install PHP Redis extension**
7. **Restart PHP-FPM** (otomatis atau manual)

---

### **Opsi 2: Via Command Line (AlmaLinux 9)** ✅ ALTERNATIVE

**Install PHP Redis extension untuk ea-php82:**

```bash
# Login sebagai root
ssh root@your-server

# Install PHP Redis extension untuk ea-php82
dnf install -y ea-php82-php-redis

# Restart PHP-FPM
systemctl restart ea-php82-php-fpm

# Verify
/opt/cpanel/ea-php82/root/usr/bin/php -m | grep redis
# Expected: redis
```

**Jika paket `ea-php82-php-redis` tidak ditemukan:**

```bash
# Install Remi repository (untuk PHP packages)
dnf install -y https://rpms.remirepo.net/enterprise/remi-release-9.rpm

# Enable Remi PHP 8.2 repository
dnf module reset php -y
dnf module enable php:remi-8.2 -y

# Install PHP Redis extension
dnf install -y php-redis

# Restart PHP-FPM
systemctl restart php-fpm
# atau
systemctl restart ea-php82-php-fpm

# Verify
php -m | grep redis
# atau
/opt/cpanel/ea-php82/root/usr/bin/php -m | grep redis
```

---

### **Opsi 3: Via PECL (Jika DNF tidak tersedia)** ⚠️ ADVANCED

```bash
# Install dependencies
dnf install -y php-pear php-devel gcc make

# Install PHP Redis extension via PECL untuk ea-php82
/opt/cpanel/ea-php82/root/usr/bin/pecl install redis

# Add extension to php.ini
echo "extension=redis.so" >> /opt/cpanel/ea-php82/root/etc/php.ini

# Restart PHP-FPM
systemctl restart ea-php82-php-fpm

# Verify
/opt/cpanel/ea-php82/root/usr/bin/php -m | grep redis
```

---

## ✅ **VERIFY INSTALLATION**

**Setelah install, verify:**

```bash
# Check PHP Redis extension
/opt/cpanel/ea-php82/root/usr/bin/php -m | grep redis
# Expected: redis

# Test Redis connection dari PHP
/opt/cpanel/ea-php82/root/usr/bin/php -r "try { \$r = new Redis(); \$r->connect('127.0.0.1', 6379); echo 'Connected to Redis!'; } catch (Exception \$e) { echo 'Failed: ' . \$e->getMessage(); }"
# Expected: Connected to Redis!

# Test via Laravel Tinker
cd /path/to/laravel
php artisan tinker

# Test Redis connection
Redis::connection()->ping();
# Expected: "PONG"

# Test cache dengan Redis
Cache::store('redis')->put('test', 'Hello Redis', 60);
Cache::store('redis')->get('test');
# Expected: "Hello Redis"
```

---

## 🔧 **TROUBLESHOOTING**

### **Error: "Package ea-php82-php-redis not found"**

**Solusi:**
```bash
# Install Remi repository
dnf install -y https://rpms.remirepo.net/enterprise/remi-release-9.rpm

# Enable Remi PHP 8.2 repository
dnf module reset php -y
dnf module enable php:remi-8.2 -y

# Install PHP Redis extension
dnf install -y php-redis

# Restart PHP-FPM
systemctl restart php-fpm
```

---

### **Error: "Extension redis.so not found"**

**Solusi:**
```bash
# Check extension path
/opt/cpanel/ea-php82/root/usr/bin/php -i | grep extension_dir

# Install via PECL
/opt/cpanel/ea-php82/root/usr/bin/pecl install redis

# Check extension file
ls -la /opt/cpanel/ea-php82/root/usr/lib64/php/modules/redis.so

# Add to php.ini
echo "extension=redis.so" >> /opt/cpanel/ea-php82/root/etc/php.ini

# Restart PHP-FPM
systemctl restart ea-php82-php-fpm
```

---

### **Error: Masih "Class 'Redis' not found" setelah install**

**Solusi:**
```bash
# Check apakah extension loaded
/opt/cpanel/ea-php82/root/usr/bin/php -i | grep redis

# Check php.ini
grep -i redis /opt/cpanel/ea-php82/root/etc/php.ini

# Manual load extension
echo "extension=redis.so" >> /opt/cpanel/ea-php82/root/etc/php.ini

# Restart PHP-FPM
systemctl restart ea-php82-php-fpm

# Clear OPcache (jika enabled)
php artisan opcache:clear
# atau
php artisan config:clear
```

---

## 📊 **CHECKLIST INSTALL**

- [ ] **Install PHP Redis extension** (`dnf install -y ea-php82-php-redis`)
- [ ] **Restart PHP-FPM** (`systemctl restart ea-php82-php-fpm`)
- [ ] **Verify extension** (`php -m | grep redis`)
- [ ] **Test Redis connection** (`php -r "new Redis()..."`)
- [ ] **Test via Laravel Tinker** (`Redis::connection()->ping()`)
- [ ] **Test cache dengan Redis** (`Cache::store('redis')->put/get`)

---

## 🎯 **KESIMPULAN**

**Error "Class 'Redis' not found" = PHP Redis extension belum terinstall**

**Solusi:**
1. ✅ **Install PHP Redis extension** (`dnf install -y ea-php82-php-redis`)
2. ✅ **Restart PHP-FPM** (`systemctl restart ea-php82-php-fpm`)
3. ✅ **Verify** (`php -m | grep redis`)
4. ✅ **Test** (`Redis::connection()->ping()`)

**Setelah install, baru bisa switch ke Redis!**

**Status:** 🔴 **Install PHP Redis extension dulu, lalu test sebelum switch!**
