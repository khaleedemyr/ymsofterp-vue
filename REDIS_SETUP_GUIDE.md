# Panduan Setup Redis untuk YMSoftERP

## Status Instalasi
✅ Redis server: **Installed & Running**  
✅ PHP Redis extension: **Installed & Loaded**  
✅ Redis connection: **Working** (tested dengan `ping()`)

## Step 1: Konfigurasi .env

Edit file `.env` di root project dan pastikan setting berikut:

```env
# Cache Configuration
CACHE_STORE=redis
CACHE_PREFIX=ymsofterp_cache

# Session Configuration  
SESSION_DRIVER=redis
SESSION_LIFETIME=120

# Queue Configuration (optional, untuk background jobs)
QUEUE_CONNECTION=redis

# Redis Configuration
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1
```

**CATATAN PENTING:**
- `REDIS_CLIENT=phpredis` → menggunakan PHP Redis extension (sudah terinstall)
- Tidak perlu install `predis/predis` karena sudah menggunakan phpredis extension
- Jika ingin menggunakan predis, ubah ke `REDIS_CLIENT=predis` dan install: `composer require predis/predis`

## Step 2: Clear Cache Laravel

Setelah update `.env`, jalankan:

```bash
cd /path/to/ymsofterp
php artisan config:clear
php artisan cache:clear
php artisan config:cache
```

## Step 3: Test Redis Connection

### Test dari Command Line:

```bash
# Test dengan PHP CLI
php -r "\$r = new Redis(); \$r->connect('127.0.0.1', 6379); echo \$r->ping();"
# Harus return: 1 (true)

# Test dengan Laravel Tinker
php artisan tinker
```

Di dalam tinker:
```php
Cache::put('test', 'Hello Redis', 60);
Cache::get('test');
// Harus return: "Hello Redis"
```

## Step 4: Implementasi Caching di Controller

### ✅ Sudah Diimplementasikan di OutletWIPController

Caching sudah ditambahkan di:
- `index()` - Cache items dan warehouse_outlets
- `create()` - Cache items dan warehouse_outlets  
- `edit()` - Cache items dan warehouse_outlets

### Contoh: Cache Query yang Sering Dipanggil

```php
use Illuminate\Support\Facades\Cache;

// Cache untuk 1 jam (3600 detik)
$items = Cache::remember('outlet_wip_items', 3600, function () {
    return DB::table('items')
        ->leftJoin('units as small_unit', 'items.small_unit_id', '=', 'small_unit.id')
        ->join('categories', 'items.category_id', '=', 'categories.id')
        ->where('items.composition_type', 'composed')
        ->where('items.status', 'active')
        ->where('items.type', 'WIP')
        ->where('categories.show_pos', '0')
        ->select('items.*', 'small_unit.name as small_unit_name')
        ->get();
});
```

### Cache Key yang Digunakan:

- `outlet_wip_items` - List items WIP (cache 1 jam)
- `outlet_wip_warehouse_outlets_all` - Warehouse outlets untuk superuser (cache 1 jam)
- `outlet_wip_warehouse_outlets_{outlet_id}` - Warehouse outlets per outlet (cache 1 jam)
- `outlet_wip_outlets_all` - List outlets untuk superuser (cache 1 jam)

### Cache dengan Tag (untuk mudah di-clear):

```php
// Set cache dengan tag
Cache::tags(['outlet_wip', 'items'])->put('items_list', $items, 3600);

// Get cache
$items = Cache::tags(['outlet_wip', 'items'])->get('items_list');

// Clear semua cache dengan tag tertentu
Cache::tags(['outlet_wip'])->flush();
```

## Step 5: Monitoring Redis

### Cek Redis Info:

```bash
redis-cli info
```

### Cek Memory Usage:

```bash
redis-cli info memory
```

### Cek Connected Clients:

```bash
redis-cli client list
```

### Monitor Real-time:

```bash
redis-cli monitor
```

## Step 6: Optimasi Redis (Optional)

### Edit `/etc/redis.conf`:

```conf
# Max memory (sesuaikan dengan RAM server)
maxmemory 2gb
maxmemory-policy allkeys-lru

# Persistence (untuk production, enable RDB atau AOF)
save 900 1
save 300 10
save 60 10000
```

### Restart Redis:

```bash
sudo systemctl restart redis
```

## Troubleshooting

### Error: "Connection refused"
- Pastikan Redis service running: `sudo systemctl status redis`
- Cek firewall: pastikan port 6379 tidak di-block

### Error: "Class 'Redis' not found"
- Pastikan PHP extension ter-load: `php -m | grep redis`
- Restart PHP-FPM: `sudo systemctl restart ea-php82-php-fpm`

### Cache tidak bekerja
- Clear config cache: `php artisan config:clear`
- Cek `.env` sudah benar
- Test connection manual

## Best Practices

1. **Cache Key Naming**: Gunakan prefix yang jelas
   ```php
   Cache::put('outlet_wip_items_' . $outlet_id, $items, 3600);
   ```

2. **Cache Expiration**: Set TTL yang sesuai
   - Data yang jarang berubah: 1-24 jam
   - Data yang sering berubah: 5-15 menit
   - Data real-time: jangan cache

3. **Cache Invalidation**: Clear cache saat data di-update
   ```php
   // Setelah update data
   Cache::forget('outlet_wip_items');
   // atau
   Cache::tags(['outlet_wip'])->flush();
   ```

4. **Monitor Memory**: Jangan biarkan Redis penuh
   - Set `maxmemory` di redis.conf
   - Monitor dengan `redis-cli info memory`

## Performance Impact

Dengan Redis caching, diharapkan:
- ✅ Response time lebih cepat (50-80% improvement)
- ✅ Database load berkurang drastis
- ✅ CPU usage turun (karena query lebih sedikit)
- ✅ Bisa handle lebih banyak concurrent users

## Next Steps

1. ✅ Setup Redis (DONE)
2. ✅ Implementasi caching di OutletWIPController (DONE)
3. ⏳ Update .env file dengan konfigurasi Redis
4. ⏳ Clear cache Laravel setelah update .env
5. ⏳ Test Redis dari Laravel
6. ⏳ Monitor performa setelah implementasi
7. ⏳ Implementasi caching di controller lain yang lambat (jika perlu)

## Checklist Setup

- [ ] Update `.env` dengan konfigurasi Redis
- [ ] Clear cache Laravel: `php artisan config:clear && php artisan cache:clear && php artisan config:cache`
- [ ] Test Redis connection dari Laravel
- [ ] Monitor performa aplikasi
- [ ] Monitor Redis memory usage

## Troubleshooting

### Error: "Connection refused" di Laravel
- Pastikan Redis service running: `sudo systemctl status redis`
- Cek `.env` sudah benar: `REDIS_HOST=127.0.0.1`, `REDIS_PORT=6379`
- Clear config cache: `php artisan config:clear`

### Cache tidak bekerja
- Cek `.env`: `CACHE_STORE=redis`
- Clear config cache: `php artisan config:clear && php artisan config:cache`
- Test manual: `php artisan tinker` → `Cache::put('test', 'value', 60); Cache::get('test');`
