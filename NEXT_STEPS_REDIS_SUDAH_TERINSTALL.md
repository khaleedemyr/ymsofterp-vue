# ✅ Next Steps: Redis Sudah Terinstall

## 🎯 **STATUS SAAT INI**

**Dari `systemctl status redis`:**
- ✅ **Redis server:** Active (running)
- ✅ **Status:** "Ready to accept connections"
- ✅ **Listening:** 127.0.0.1:6379
- ✅ **Memory:** 9.7M (normal)
- ✅ **Uptime:** 22 hours (stabil)

**Redis server sudah siap digunakan!**

---

## ⚡ **NEXT STEPS - CHECKLIST**

### **STEP 1: Check PHP Redis Extension** ⚠️ PRIORITAS TINGGI

**Check apakah PHP Redis extension sudah terinstall:**

```bash
# Check PHP Redis extension
php -m | grep redis

# Atau untuk cPanel (ea-php82)
/opt/cpanel/ea-php82/root/usr/bin/php -m | grep redis
```

**Jika output: `redis`** → ✅ **Sudah terinstall, lanjut ke STEP 2**

**Jika tidak ada output** → ⚠️ **Perlu install PHP Redis extension:**

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

### **STEP 2: Test Redis Connection dari PHP** ✅

**Test apakah PHP bisa connect ke Redis:**

```bash
# Test Redis connection dari PHP
php -r "try { \$r = new Redis(); \$r->connect('127.0.0.1', 6379); echo 'Connected to Redis!'; } catch (Exception \$e) { echo 'Failed: ' . \$e->getMessage(); }"
```

**Expected output:** `Connected to Redis!`

**Jika error:** Check apakah PHP Redis extension sudah terinstall (STEP 1)

---

### **STEP 3: Test Redis via Laravel Tinker** ✅

**Test Redis dari Laravel:**

```bash
cd /path/to/laravel

# Masuk ke Tinker
php artisan tinker

# Test cache
Cache::put('test', 'Hello Redis', 60);
Cache::get('test');
# Expected: "Hello Redis"

# Test Redis connection
Redis::connection()->ping();
# Expected: "PONG"

# Check Redis info
Redis::connection()->info();
# Expected: Array dengan info Redis
```

**Jika error:** Check apakah `.env` sudah di-update (STEP 4)

---

### **STEP 4: Update .env File** ⚠️ PRIORITAS TINGGI

**Update `.env` untuk menggunakan Redis:**

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

**Note:** Downtime 5-10 detik saat clear config cache

---

### **STEP 5: Test Redis di Aplikasi** ✅

**Buat test route untuk verify Redis bekerja:**

**Edit `routes/web.php` (temporary, untuk testing):**

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
            'used_memory' => $info['used_memory_human'] ?? 'unknown',
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
    "connected_clients": 1,
    "used_memory": "9.7M"
}
```

**Setelah test berhasil, hapus route ini untuk security!**

---

### **STEP 6: Implementasi Caching di Aplikasi** ⚠️ PRIORITAS TINGGI

**Setelah Redis bekerja, implementasi caching untuk mengurangi CPU usage:**

**Contoh: Cache Master Data di Controller**

```php
use Illuminate\Support\Facades\Cache;

// Before (setiap request query database)
$outlets = Outlet::all();

// After (cache 1 jam)
$outlets = Cache::remember('outlets', 3600, function () {
    return Outlet::all();
});
```

**Data yang perlu di-cache:**
- ✅ Master data (outlets, divisions, positions, dll)
- ✅ User permissions
- ✅ Configuration settings
- ✅ Frequently accessed lookup tables

---

## 📊 **CHECKLIST LENGKAP**

- [ ] **Check PHP Redis extension** (`php -m | grep redis`)
- [ ] **Install PHP Redis extension** (jika belum terinstall)
- [ ] **Test Redis connection dari PHP** (`php -r "new Redis()..."`)
- [ ] **Test Redis via Laravel Tinker** (`Cache::put/get`)
- [ ] **Update .env** (`CACHE_DRIVER=redis`)
- [ ] **Clear config cache** (`php artisan config:clear`)
- [ ] **Test Redis di aplikasi** (test route)
- [ ] **Implementasi caching** di aplikasi
- [ ] **Monitor Redis memory usage** (`redis-cli info memory`)

---

## 🔧 **TROUBLESHOOTING**

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

# Check
php -m | grep redis
```

### **Error: "Connection refused"**

**Solusi:**
```bash
# Check Redis status
systemctl status redis

# Jika tidak running, start Redis
systemctl start redis

# Check Redis port
ss -tlnp | grep 6379
# Expected: tcp 0 0 127.0.0.1:6379
```

### **Error: "Cache store [redis] is not defined"**

**Solusi:**
```bash
# Pastikan .env sudah di-update
# CACHE_DRIVER=redis

# Clear config cache
php artisan config:clear

# Check config
php artisan config:show cache
```

---

## 🎯 **KESIMPULAN**

**Redis server sudah terinstall dan running!** ✅

**Langkah selanjutnya:**
1. ✅ **Check PHP Redis extension** (prioritas tinggi)
2. ✅ **Test Redis connection** dari PHP
3. ✅ **Update .env** (`CACHE_DRIVER=redis`)
4. ✅ **Implementasi caching** di aplikasi

**Setelah semua selesai:**
- ✅ CPU usage per request akan turun (dari 50% → 5-10%)
- ✅ Aplikasi akan lebih lancar
- ✅ Response time akan lebih cepat

**Status:** 🎯 **Redis sudah siap, tinggal konfigurasi Laravel!**
