# 🔴 Panduan Install Redis untuk Server cPanel

## 🎯 **TUJUAN**

Install Redis server dan PHP Redis extension untuk Laravel caching.

**Manfaat:**
- ✅ Mengurangi query database (cache master data)
- ✅ Mengurangi CPU usage per request
- ✅ Meningkatkan response time
- ✅ Aplikasi lebih lancar

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

## ⚡ **STEP 1: Install Redis Server**

### **Via cPanel (Recommended)**

1. **Login cPanel**
2. **Software → Software Manager** (atau **Select PHP Version**)
3. **Cari "Redis"** atau **"php-redis"**
4. **Install Redis server** (jika tersedia)

**Jika tidak tersedia di cPanel, install via command line:**

### **Via Command Line (CentOS/RHEL)**

```bash
# Login sebagai root
ssh root@your-server

# Install EPEL repository (jika belum)
yum install -y epel-release

# Install Redis server
yum install -y redis

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

---

## ⚡ **STEP 2: Install PHP Redis Extension**

### **Via cPanel (Recommended)**

1. **Login cPanel**
2. **Software → Select PHP Version**
3. **Klik "Extensions"** atau **"PECL"**
4. **Cari "redis"** atau **"php-redis"**
5. **Install PHP Redis extension**

**Jika tidak tersedia di cPanel, install via PECL:**

### **Via PECL (Command Line)**

```bash
# Login sebagai root
ssh root@your-server

# Install PECL (jika belum)
yum install -y php-pear php-devel gcc

# Install PHP Redis extension via PECL
pecl install redis

# Atau install via yum (lebih mudah)
yum install -y php-redis

# Restart PHP-FPM
systemctl restart ea-php82-php-fpm

# Check PHP Redis extension
php -m | grep redis
# Expected output: redis
```

**Jika menggunakan PHP 8.2 (ea-php82):**
```bash
# Install untuk PHP 8.2 khusus
/opt/cpanel/ea-php82/root/usr/bin/pecl install redis

# Atau via yum
yum install -y ea-php82-php-redis

# Restart PHP-FPM
systemctl restart ea-php82-php-fpm

# Check
/opt/cpanel/ea-php82/root/usr/bin/php -m | grep redis
```

---

## ⚡ **STEP 3: Konfigurasi Redis Server**

### **Edit Redis Config**

```bash
# Edit Redis config
nano /etc/redis.conf

# Atau jika menggunakan Redis 6+
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
```

**Restart Redis:**
```bash
systemctl restart redis
systemctl status redis
```

---

## ⚡ **STEP 4: Konfigurasi Laravel (.env)**

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

# Redis Configuration (sudah ada di .env Anda)
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

## ⚡ **STEP 5: Test Redis Connection**

### **Test via Command Line**

```bash
# Test Redis server
redis-cli ping
# Expected: PONG

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

### **Test via Browser/API**

**Buat test route di `routes/web.php`:**
```php
Route::get('/test-redis', function () {
    try {
        // Test cache
        Cache::put('test', 'Hello Redis', 60);
        $value = Cache::get('test');
        
        // Test Redis connection
        $redis = Redis::connection();
        $info = $redis->info();
        
        return response()->json([
            'status' => 'success',
            'cache_test' => $value,
            'redis_version' => $info['redis_version'] ?? 'unknown',
            'connected_clients' => $info['connected_clients'] ?? 0,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
});
```

**Akses:** `https://your-domain.com/test-redis`

**Expected response:**
```json
{
    "status": "success",
    "cache_test": "Hello Redis",
    "redis_version": "6.2.0",
    "connected_clients": 1
}
```

---

## ⚡ **STEP 6: Implementasi Caching di Aplikasi**

### **Contoh: Cache Master Data**

**Di Controller:**
```php
use Illuminate\Support\Facades\Cache;

// Before (setiap request query database)
$outlets = Outlet::all();

// After (cache 1 jam)
$outlets = Cache::remember('outlets', 3600, function () {
    return Outlet::all();
});

// Atau dengan tag (jika perlu invalidate)
$outlets = Cache::tags(['master-data'])->remember('outlets', 3600, function () {
    return Outlet::all();
});
```

### **Contoh: Cache User Permissions**

```php
// Cache user permissions
$permissions = Cache::remember("user_permissions_{$userId}", 3600, function () use ($userId) {
    return User::find($userId)->permissions;
});
```

### **Contoh: Cache Frequently Accessed Data**

```php
// Cache divisions
$divisions = Cache::remember('divisions', 3600, function () {
    return Division::where('status', 'A')->get();
});

// Cache positions
$positions = Cache::remember('positions', 3600, function () {
    return Position::all();
});
```

### **Invalidate Cache (jika data berubah)**

```php
// Invalidate cache saat data berubah
public function update(Outlet $outlet, Request $request)
{
    $outlet->update($request->all());
    
    // Invalidate cache
    Cache::forget('outlets');
    // Atau jika menggunakan tag
    Cache::tags(['master-data'])->flush();
    
    return redirect()->back();
}
```

---

## 🔧 **TROUBLESHOOTING**

### **Error: "Class 'Redis' not found"**

**Solusi:**
```bash
# Check apakah PHP Redis extension terinstall
php -m | grep redis

# Jika tidak ada, install lagi
yum install -y ea-php82-php-redis
# atau
pecl install redis

# Restart PHP-FPM
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

# Restart Redis
systemctl restart redis
```

### **Redis menggunakan terlalu banyak memory**

**Solusi:**
```bash
# Edit Redis config
nano /etc/redis.conf

# Set max memory (contoh: 1GB)
maxmemory 1gb
maxmemory-policy allkeys-lru

# Restart Redis
systemctl restart redis

# Check memory usage
redis-cli info memory
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

### **Monitor Redis via Laravel**

```php
// Di Controller atau Service
$redis = Redis::connection();
$info = $redis->info();

// Memory usage
$memory = $redis->info('memory');
echo "Used memory: " . $memory['used_memory_human'];

// Connected clients
$clients = $redis->info('clients');
echo "Connected clients: " . $clients['connected_clients'];
```

---

## ✅ **CHECKLIST INSTALL**

- [ ] Install Redis server
- [ ] Install PHP Redis extension
- [ ] Start Redis service
- [ ] Enable Redis on boot
- [ ] Test Redis connection (redis-cli ping)
- [ ] Test PHP Redis extension (php -m | grep redis)
- [ ] Update .env (CACHE_DRIVER=redis)
- [ ] Clear Laravel config cache
- [ ] Test Redis via Laravel (Cache::put/get)
- [ ] Implementasi caching di aplikasi
- [ ] Monitor Redis memory usage

---

## 🎯 **KESIMPULAN**

**Setelah install Redis:**
1. ✅ **CACHE_DRIVER=redis** di .env
2. ✅ **Implementasi caching** di aplikasi (master data, permissions, dll)
3. ✅ **Monitor Redis** memory usage
4. ✅ **Expected:** CPU usage per request turun, aplikasi lebih lancar!

**Status:** 🎯 **Install Redis → Enable Caching → Aplikasi Lebih Lancar!**
