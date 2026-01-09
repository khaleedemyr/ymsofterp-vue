# 🔴 Troubleshoot: Error 500 Saat Switch ke Redis

## 🎯 **MASALAH**

**Error 500 saat `CACHE_DRIVER=redis`** → Sudah kembali ke `CACHE_DRIVER=database`

**Kemungkinan penyebab:**
1. 🔴 **PHP Redis extension belum terinstall**
2. 🔴 **Redis connection failed**
3. 🔴 **Laravel config error**
4. 🔴 **Permission issue**

---

## ⚡ **STEP 1: Check PHP Redis Extension** (PRIORITAS TINGGI!)

**Check apakah PHP Redis extension sudah terinstall:**

```bash
# Check PHP Redis extension
php -m | grep redis

# Atau untuk cPanel (ea-php82)
/opt/cpanel/ea-php82/root/usr/bin/php -m | grep redis
```

**Jika output: `redis`** → ✅ **Sudah terinstall, lanjut ke STEP 2**

**Jika tidak ada output** → 🔴 **PERLU INSTALL!**

### **Install PHP Redis Extension:**

```bash
# Install PHP Redis extension (AlmaLinux 9)
dnf install -y php-redis

# Atau untuk cPanel
dnf install -y ea-php82-php-redis

# Restart PHP-FPM
systemctl restart php-fpm
# atau
systemctl restart ea-php82-php-fpm

# Check lagi
php -m | grep redis
```

---

## ⚡ **STEP 2: Test Redis Connection dari PHP**

**Test apakah PHP bisa connect ke Redis:**

```bash
# Test Redis connection dari PHP
php -r "try { \$r = new Redis(); \$r->connect('127.0.0.1', 6379); echo 'Connected to Redis!'; } catch (Exception \$e) { echo 'Failed: ' . \$e->getMessage(); }"
```

**Expected output:** `Connected to Redis!`

**Jika error:**
- 🔴 **"Class 'Redis' not found"** → PHP Redis extension belum terinstall (STEP 1)
- 🔴 **"Connection refused"** → Redis service tidak running (STEP 3)
- 🔴 **"Permission denied"** → Redis config issue (STEP 4)

---

## ⚡ **STEP 3: Check Redis Service**

**Check apakah Redis service running:**

```bash
# Check Redis status
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

**Jika Redis tidak running:**
```bash
# Start Redis
systemctl start redis

# Check status
systemctl status redis
```

---

## ⚡ **STEP 4: Check Laravel Error Log**

**Check error log untuk detail error:**

```bash
# Check Laravel error log
tail -50 /path/to/laravel/storage/logs/laravel.log

# Check PHP error log
tail -50 /var/log/php-fpm/error.log
# atau
tail -50 /opt/cpanel/ea-php82/root/var/log/php-fpm-error.log
```

**Cari error seperti:**
- `Class 'Redis' not found`
- `Connection refused`
- `Call to undefined method`
- `Cache store [redis] is not defined`

---

## ⚡ **STEP 5: Check Laravel Config**

**Check apakah Redis config sudah benar:**

```bash
cd /path/to/laravel

# Check config
php artisan config:show cache

# Check Redis config
php artisan config:show database.redis
```

**Expected output:**
```php
'driver' => 'redis',
'connection' => 'default',
...
```

**Jika error:** Check `config/cache.php` dan `config/database.php`

---

## ⚡ **STEP 6: Test Redis via Laravel Tinker**

**Test Redis dari Laravel (dengan CACHE_DRIVER=database dulu):**

```bash
cd /path/to/laravel

# Masuk ke Tinker
php artisan tinker

# Test Redis connection langsung
Redis::connection()->ping();
# Expected: "PONG"

# Test cache dengan Redis
Cache::store('redis')->put('test', 'Hello Redis', 60);
Cache::store('redis')->get('test');
# Expected: "Hello Redis"
```

**Jika error di sini:**
- 🔴 **"Class 'Redis' not found"** → PHP Redis extension belum terinstall
- 🔴 **"Connection refused"** → Redis service tidak running
- 🔴 **"Call to undefined method"** → Laravel Redis config error

---

## 🔧 **TROUBLESHOOTING SPESIFIK**

### **Error: "Class 'Redis' not found"**

**Solusi:**
```bash
# Install PHP Redis extension
dnf install -y php-redis
# atau
dnf install -y ea-php82-php-redis

# Restart PHP-FPM
systemctl restart php-fpm
# atau
systemctl restart ea-php82-php-fpm

# Verify
php -m | grep redis
```

**Jika masih error setelah install:**
```bash
# Check php.ini
php --ini

# Check apakah extension loaded
php -i | grep redis

# Manual load extension di php.ini
echo "extension=redis.so" >> /etc/php.ini
# atau untuk cPanel
echo "extension=redis.so" >> /opt/cpanel/ea-php82/root/etc/php.ini

# Restart PHP-FPM
systemctl restart php-fpm
```

---

### **Error: "Connection refused"**

**Solusi:**
```bash
# Check Redis status
systemctl status redis

# Start Redis jika tidak running
systemctl start redis
systemctl enable redis

# Check Redis config
cat /etc/redis.conf | grep bind
# Expected: bind 127.0.0.1

# Test Redis connection
redis-cli ping
# Expected: PONG
```

---

### **Error: "Cache store [redis] is not defined"**

**Solusi:**
```bash
# Check config/cache.php
cat config/cache.php | grep -A 10 redis

# Pastikan ada config untuk redis:
# 'redis' => [
#     'driver' => 'redis',
#     'connection' => 'default',
# ],

# Clear config cache
php artisan config:clear
```

---

### **Error: "Call to undefined method Redis::connection()"**

**Solusi:**
```bash
# Check apakah Laravel Redis package terinstall
composer show | grep predis
# atau
composer show | grep phpredis

# Install Laravel Redis package jika belum
composer require predis/predis
# atau
composer require phpredis/phpredis
```

**Note:** Laravel biasanya sudah include `predis/predis` di composer.json, tapi check dulu.

---

## ✅ **PROSEDUR AMAN - STEP BY STEP**

### **STEP 1: Install PHP Redis Extension** (WAJIB!)

```bash
# Install PHP Redis extension
dnf install -y php-redis
# atau untuk cPanel
dnf install -y ea-php82-php-redis

# Restart PHP-FPM
systemctl restart php-fpm
# atau
systemctl restart ea-php82-php-fpm

# Verify
php -m | grep redis
# Expected: redis
```

---

### **STEP 2: Test Redis Connection** (WAJIB!)

```bash
# Test Redis dari PHP
php -r "try { \$r = new Redis(); \$r->connect('127.0.0.1', 6379); echo 'Connected!'; } catch (Exception \$e) { echo 'Failed: ' . \$e->getMessage(); }"
# Expected: Connected!

# Test Redis dari command line
redis-cli ping
# Expected: PONG
```

---

### **STEP 3: Test Redis via Laravel** (WAJIB!)

```bash
cd /path/to/laravel

# Test dengan CACHE_DRIVER=database dulu
php artisan tinker

# Test Redis connection
Redis::connection()->ping();
# Expected: "PONG"

# Test cache dengan Redis
Cache::store('redis')->put('test', 'Hello', 60);
Cache::store('redis')->get('test');
# Expected: "Hello"
```

**Jika semua test berhasil, baru switch ke Redis!**

---

### **STEP 4: Switch ke Redis** (SETELAH SEMUA TEST BERHASIL!)

```bash
# Backup .env dulu
cp .env .env.backup.$(date +%Y%m%d_%H%M%S)

# Update .env
# CACHE_DRIVER=redis

# Clear config cache
php artisan config:clear
php artisan cache:clear

# Test lagi
php artisan tinker
>>> Cache::put('test', 'Hello Redis', 60);
>>> Cache::get('test');
# Expected: "Hello Redis"
```

---

## 📊 **CHECKLIST TROUBLESHOOTING**

- [ ] **PHP Redis extension terinstall** (`php -m | grep redis`)
- [ ] **Redis service running** (`systemctl status redis`)
- [ ] **Redis connection test berhasil** (`php -r "new Redis()..."`)
- [ ] **Redis ping berhasil** (`redis-cli ping`)
- [ ] **Laravel Redis test berhasil** (`Redis::connection()->ping()`)
- [ ] **Laravel cache test berhasil** (`Cache::store('redis')->put/get`)
- [ ] **Error log checked** (`tail -50 storage/logs/laravel.log`)
- [ ] **Config checked** (`php artisan config:show cache`)

---

## 🎯 **KESIMPULAN**

**Error 500 saat switch ke Redis biasanya karena:**

1. 🔴 **PHP Redis extension belum terinstall** (90% kasus)
2. 🔴 **Redis service tidak running**
3. 🔴 **Laravel config error**

**Solusi:**
1. ✅ **Install PHP Redis extension** (prioritas tinggi!)
2. ✅ **Test Redis connection** dari PHP
3. ✅ **Test Redis via Laravel** sebelum switch
4. ✅ **Switch ke Redis** setelah semua test berhasil

**Status:** 🔴 **Install PHP Redis extension dulu, lalu test sebelum switch!**
