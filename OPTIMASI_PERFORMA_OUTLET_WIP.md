# Optimasi Performa Outlet WIP Production

## ✅ Optimasi yang Sudah Dilakukan

### 1. Database Indexing
- ✅ File: `optimize_outlet_wip_indexes.sql`
- ✅ Status: **Siap dijalankan** (manual SQL, bukan migration)
- ✅ Index untuk:
  - `outlet_wip_production_headers` (outlet_id, production_date, status, warehouse_outlet_id, composite indexes)
  - `outlet_wip_productions` (header_id, item_id, outlet_id, production_date)
  - `outlet_food_inventory_items` (item_id)
  - `outlet_food_inventory_stocks` (inventory_item_id, id_outlet, warehouse_outlet_id, composite)
  - `item_bom` (item_id, material_item_id)

### 2. Query Optimization
- ✅ **Pagination di Database Level**: Menggunakan `UNION ALL` dengan `LIMIT/OFFSET` di database, bukan di PHP
- ✅ **Batch Query untuk BOM**: Fixed N+1 query di `getBomAndStock()` dengan batch-fetch inventory items dan stocks
- ✅ **Query Hanya Data yang Ditampilkan**: Production details hanya di-fetch untuk headers yang ditampilkan di halaman saat ini

### 3. Redis Caching
- ✅ **Items Cache**: Cache items WIP (1 jam TTL)
- ✅ **Warehouse Outlets Cache**: Cache warehouse outlets per outlet (1 jam TTL)
- ✅ **Outlets Cache**: Cache outlets untuk superuser (1 jam TTL)
- ✅ **Redis Configuration**: Sudah di-setup di `.env`

## ⚠️ Masalah yang Masih Bisa Dioptimasi

### 1. Query Items & Units di Production Details
**Lokasi**: Method `index()` line 196-236

**Masalah**: 
- Query production details dengan `leftJoin` items dan units untuk setiap header
- Bisa dioptimasi dengan batch-loading items dan units terlebih dahulu

**Solusi** (Opsional, jika masih lambat):
```php
// Batch load semua items dan units yang dibutuhkan
$itemIds = $productions->pluck('item_id')->unique();
$unitIds = $productions->pluck('unit_id')->unique();

$itemsMap = DB::table('items')->whereIn('id', $itemIds)->get()->keyBy('id');
$unitsMap = DB::table('units')->whereIn('id', $unitIds)->get()->keyBy('id');

// Map di PHP instead of join
```

### 2. Query dengan LIKE untuk Search
**Lokasi**: Method `index()` line 124-142

**Masalah**:
- Query dengan `LIKE '%search%'` tidak bisa menggunakan index (full table scan)
- Jika data banyak, ini bisa lambat

**Solusi** (Opsional):
- Gunakan full-text search jika MySQL support
- Atau batasi search hanya pada kolom tertentu yang sudah di-index
- Atau gunakan Elasticsearch untuk search yang kompleks

### 3. Database Index Belum Dijalankan
**Status**: ⚠️ **PENTING - Belum Dijalankan**

**File**: `optimize_outlet_wip_indexes.sql`

**Cara Jalankan**:
```sql
-- Buka MySQL client atau phpMyAdmin
-- Copy-paste isi file optimize_outlet_wip_indexes.sql
-- Jalankan query tersebut
```

**Catatan**: 
- Backup database dulu!
- Query ini akan memakan waktu jika data sudah banyak
- Monitor performa setelah index ditambahkan

### 4. Redis Cache Belum Aktif
**Status**: ⚠️ **PENTING - Perlu Clear Cache**

**Setelah Update .env**:
```bash
php artisan config:clear
php artisan cache:clear
php artisan config:cache
```

**Test Redis**:
```bash
php artisan tinker
Cache::put('test', 'Hello', 60);
Cache::get('test'); // Harus return "Hello"
```

## 📋 Checklist Optimasi

### Database
- [ ] **Jalankan SQL Index**: File `optimize_outlet_wip_indexes.sql`
- [ ] **Monitor Query Time**: Cek slow query log MySQL
- [ ] **Cek Index Usage**: `EXPLAIN` query yang lambat

### Redis
- [ ] **Update .env**: Pastikan `CACHE_STORE=redis`, `SESSION_DRIVER=redis`
- [ ] **Clear Cache**: `php artisan config:clear && php artisan cache:clear && php artisan config:cache`
- [ ] **Test Redis**: Pastikan cache bekerja dengan `php artisan tinker`
- [ ] **Monitor Redis Memory**: `redis-cli info memory`

### Code
- [x] **Pagination di Database**: ✅ Done
- [x] **Batch Query BOM**: ✅ Done
- [x] **Cache Items & Warehouse**: ✅ Done
- [ ] **Optimasi Production Details Query**: ⏳ Optional (jika masih lambat)

### Server
- [ ] **PHP-FPM Config**: Pastikan `Max Children` sesuai (20-24 untuk 8 vCPU)
- [ ] **MySQL Config**: Pastikan `innodb_buffer_pool_size` cukup besar
- [ ] **Redis Config**: Set `maxmemory` dan `maxmemory-policy`

## 🔍 Monitoring Performa

### 1. Cek Slow Query Log MySQL
```sql
-- Enable slow query log
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 2; -- Query > 2 detik

-- Cek slow queries
SELECT * FROM mysql.slow_log ORDER BY start_time DESC LIMIT 10;
```

### 2. Monitor Redis
```bash
# Cek memory usage
redis-cli info memory

# Cek connected clients
redis-cli client list

# Monitor real-time
redis-cli monitor
```

### 3. Monitor PHP-FPM
```bash
# Cek status
systemctl status ea-php82-php-fpm

# Cek process count
ps aux | grep php-fpm | wc -l
```

### 4. Monitor Server Resources
```bash
# CPU & Memory
top
htop

# Disk I/O
iostat -x 1

# Network
iftop
```

## 🎯 Target Performa

Setelah semua optimasi:
- ✅ Response time index page: **< 2 detik** (dari > 10 detik)
- ✅ Database query count: **Berkurang 50-70%** (dari cache)
- ✅ CPU usage: **Turun dari 100%** ke **< 70%**
- ✅ Memory usage: **Stabil** (dengan Redis caching)

## 🚨 Troubleshooting

### Masih Lambat Setelah Optimasi?

1. **Cek Index Database**:
   ```sql
   SHOW INDEX FROM outlet_wip_production_headers;
   -- Pastikan index sudah ada
   ```

2. **Cek Redis Cache**:
   ```bash
   redis-cli keys "ymsofterp_cache:*"
   # Harus ada cache keys
   ```

3. **Cek Query Time**:
   ```sql
   EXPLAIN SELECT ... FROM outlet_wip_production_headers ...
   -- Cek apakah menggunakan index
   ```

4. **Cek PHP-FPM**:
   ```bash
   # Cek apakah process terlalu banyak
   ps aux | grep php-fpm | wc -l
   # Harus < Max Children
   ```

## 📝 Next Steps

1. **PRIORITAS TINGGI**:
   - [ ] Jalankan SQL index (`optimize_outlet_wip_indexes.sql`)
   - [ ] Update `.env` dan clear cache Laravel
   - [ ] Test Redis connection

2. **PRIORITAS SEDANG**:
   - [ ] Monitor performa setelah optimasi
   - [ ] Cek slow query log
   - [ ] Optimasi query production details jika masih lambat

3. **PRIORITAS RENDAH**:
   - [ ] Optimasi search query (jika perlu)
   - [ ] Implementasi full-text search (jika perlu)
   - [ ] Fine-tune PHP-FPM dan MySQL config
