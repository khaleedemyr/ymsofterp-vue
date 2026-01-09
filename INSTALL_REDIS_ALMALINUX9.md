# 🔴 Panduan Install Redis untuk AlmaLinux 9

## 🎯 **TUJUAN**

Install Redis server dan PHP Redis extension untuk Laravel caching di AlmaLinux 9.

---

## 📋 **CHECKLIST SEBELUM INSTALL**

**Check apakah Redis sudah terinstall:**
```bash
# Check Redis server
redis-cli ping
# Jika sudah terinstall, akan return: PONG

# Check PHP Redis extension
php -m | grep redis
# Jika sudah terinstall, akan return: redis
```

**Jika sudah terinstall, skip ke bagian "Konfigurasi Laravel"**

---

## ⚡ **STEP 1: Install Redis Server (AlmaLinux 9)**

### **Via DNF (Recommended untuk AlmaLinux 9)**

```bash
# Login sebagai root
ssh root@your-server

# Update system (optional, tapi recommended)
dnf update -y

# Install Redis server
dnf install -y redis

# Start Redis service
systemctl start redis

# Enable Redis on boot
systemctl enable redis

# Check Redis status
systemctl status redis

# Test Redis connection
redis-cli ping
# Expected output: PONG
```

**Jika paket `redis` tidak ditemukan:**
```bash
# Install EPEL repository (jika belum)
dnf install -y epel-release

# Install Redis server
dnf install -y redis
```

---

## ⚡ **STEP 2: Install PHP Redis Extension (AlmaLinux 9)**

### **Opsi 1: Via DNF (Paling Mudah)**

**Untuk PHP 8.2 (ea-php82):**
```bash
# Install PHP Redis extension
dnf install -y ea-php82-php-redis

# Restart PHP-FPM
systemctl restart ea-php82-php-fpm

# Check PHP Redis extension
/opt/cpanel/ea-php82/root/usr/bin/php -m | grep redis
# Expected output: redis
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
```

---

### **Opsi 2: Via PECL (Jika DNF tidak tersedia)**

```bash
# Install dependencies
dnf install -y php-pear php-devel gcc make

# Install PHP Redis extension via PECL
pecl install redis

# Atau untuk PHP 8.2 khusus
/opt/cpanel/ea-php82/root/usr/bin/pecl install redis

# Add extension to php.ini
echo "extension=redis.so" >> /opt/cpanel/ea-php82/root/etc/php.ini
# atau
echo "extension=redis.so" >> /etc/php.ini

# Restart PHP-FPM
systemctl restart ea-php82-php-fpm

# Check
php -m | grep redis
# atau
/opt/cpanel/ea-php82/root/usr/bin/php -m | grep redis
```

---

## ⚡ **STEP 3: Konfigurasi Redis Server**

### **Edit Redis Config**

```bash
# Edit Redis config (AlmaLinux 9 biasanya di /etc/redis.conf)
nano /etc/redis.conf

# Atau jika menggunakan Redis 7+
nano /etc/redis/redis.conf
```

**Ubah settings berikut:**
```conf
# Bind ke localhost saja (untuk security)
bind 127.0.0.1

# Set max memory (sesuaikan dengan RAM server)
# Contoh: 1GB untuk cache
maxmemory 1gb
maxmemory-policy allkeys-lru

# Enable persistence (optional)
save 900 1
save 300 10
save 60 10000

# Protected mode (untuk security)
protected-mode yes
```

**Restart Redis:**
```bash
systemctl restart redis
systemctl status redis
```

---

## ⚡ **STEP 4: Konfigurasi Firewall (Jika Aktif)**

**AlmaLinux 9 menggunakan firewalld:**

```bash
# Check firewall status
systemctl status firewalld

# Jika firewall aktif, Redis sudah bind ke 127.0.0.1 (localhost)
# Jadi tidak perlu buka port (aman)

# Jika perlu akses dari luar (TIDAK RECOMMENDED untuk production):
# firewall-cmd --permanent --add-port=6379/tcp
# firewall-cmd --reload
```

**Note:** Untuk production, Redis sebaiknya hanya accessible dari localhost (127.0.0.1).

---

## ⚡ **STEP 5: Konfigurasi Laravel (.env)**

**Update `.env` file:**

```env
# Cache Driver - Ubah dari 'database' ke 'redis'
CACHE_DRIVER=redis
CACHE_STORE=redis

# Session Driver - Ubah dari 'database' ke 'redis' (optional, tapi recommended)
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

# Queue Connection - Ubah dari 'database' ke 'redis' (optional, tapi recommended)
QUEUE_CONNECTION=redis

# Redis Configuration
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Redis Cache Prefix (optional)
CACHE_PREFIX=ymsofterp_
```

**Clear config cache:**
```bash
cd /path/to/laravel
php artisan config:clear
php artisan cache:clear
```

---

## ⚡ **STEP 6: Test Redis Connection**

### **Test via Command Line**

```bash
# Test Redis server
redis-cli ping
# Expected: PONG

# Test Redis info
redis-cli info server

# Test Redis dari PHP
php -r "echo (new Redis())->connect('127.0.0.1', 6379) ? 'Connected' : 'Failed';"
# Expected: Connected
```

### **Test via Laravel Tinker**

```bash
cd /path/to/laravel
php artisan tinker

# Test Redis connection
Cache::put('test', 'Hello Redis', 60);
Cache::get('test');
# Expected: "Hello Redis"

# Test Redis info
Redis::connection()->info();
# Expected: Array dengan info Redis
```

---

## 🔧 **TROUBLESHOOTING ALMALINUX 9**

### **Error: "Package redis not found"**

**Solusi:**
```bash
# Install EPEL repository
dnf install -y epel-release

# Install Redis
dnf install -y redis
```

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

# Atau install via PECL
pecl install redis
```

### **Error: "Class 'Redis' not found"**

**Solusi:**
```bash
# Check apakah PHP Redis extension terinstall
php -m | grep redis

# Jika tidak ada, install lagi
dnf install -y php-redis
# atau
pecl install redis

# Restart PHP-FPM
systemctl restart php-fpm
# atau
systemctl restart ea-php82-php-fpm
```

### **Error: "Connection refused"**

**Solusi:**
```bash
# Check apakah Redis service running
systemctl status redis

# Jika tidak running, start Redis
systemctl start redis
systemctl enable redis

# Check Redis port
ss -tlnp | grep 6379
# atau
netstat -tlnp | grep 6379
# Expected: tcp 0 0 127.0.0.1:6379
```

### **Error: "Permission denied"**

**Solusi:**
```bash
# Check Redis config
cat /etc/redis.conf | grep bind

# Pastikan bind ke 127.0.0.1 (localhost)
# Jika perlu, edit config:
nano /etc/redis.conf
# Set: bind 127.0.0.1

# Check Redis log
journalctl -u redis -n 50

# Restart Redis
systemctl restart redis
```

---

## 📊 **MONITORING**

### **Check Redis Status**

```bash
# Check Redis service
systemctl status redis

# Check Redis info
redis-cli info

# Check Redis memory
redis-cli info memory

# Check connected clients
redis-cli info clients

# Check keys
redis-cli keys "*"
redis-cli dbsize
```

### **Check PHP Redis Extension**

```bash
# Check PHP Redis extension
php -m | grep redis

# Check PHP Redis version
php -r "echo phpversion('redis');"

# Check PHP Redis info
php -r "phpinfo();" | grep -i redis
```

---

## ✅ **CHECKLIST INSTALL ALMALINUX 9**

- [ ] Update system (`dnf update -y`)
- [ ] Install EPEL repository (`dnf install -y epel-release`)
- [ ] Install Redis server (`dnf install -y redis`)
- [ ] Start Redis service (`systemctl start redis`)
- [ ] Enable Redis on boot (`systemctl enable redis`)
- [ ] Test Redis connection (`redis-cli ping`)
- [ ] Install PHP Redis extension (`dnf install -y php-redis` atau `pecl install redis`)
- [ ] Restart PHP-FPM (`systemctl restart php-fpm`)
- [ ] Test PHP Redis extension (`php -m | grep redis`)
- [ ] Update .env (`CACHE_DRIVER=redis`)
- [ ] Clear Laravel config cache (`php artisan config:clear`)
- [ ] Test Redis via Laravel (`Cache::put/get`)
- [ ] Monitor Redis memory usage

---

## 🎯 **QUICK INSTALL SCRIPT ALMALINUX 9**

```bash
#!/bin/bash

# Install Redis Server
dnf install -y epel-release
dnf install -y redis
systemctl start redis
systemctl enable redis

# Install PHP Redis Extension
dnf install -y php-redis
# atau untuk cPanel
dnf install -y ea-php82-php-redis

# Restart PHP-FPM
systemctl restart php-fpm
# atau
systemctl restart ea-php82-php-fpm

# Test
redis-cli ping
php -m | grep redis
```

---

## 🎯 **KESIMPULAN**

**Untuk AlmaLinux 9:**
1. ✅ Install Redis: `dnf install -y redis`
2. ✅ Install PHP Redis: `dnf install -y php-redis` atau `pecl install redis`
3. ✅ Start Redis: `systemctl start redis`
4. ✅ Update .env: `CACHE_DRIVER=redis`
5. ✅ Test: `redis-cli ping` dan `php -m | grep redis`

**Status:** 🎯 **AlmaLinux 9 menggunakan DNF, proses install sama seperti CentOS/RHEL!**
