# Optimasi PHP-FPM untuk Ratusan Concurrent Users
## Server: 8 vCPU, 16GB RAM

## 🔴 Analisa Kebutuhan untuk Ratusan Concurrent Users

### Skenario:
- **Concurrent Users**: 200-500 users
- **Server Spec**: 8 vCPU, 16GB RAM
- **Masalah**: CPU 100%, Load 15-16

### Perhitungan Resource:

#### 1. PHP-FPM Workers Calculation
```
Formula untuk High Concurrency:
- Max Children = (vCPU × 4-6) untuk high traffic
- Dengan 8 vCPU: 8 × 4 = 32 (minimum)
- Ideal: 8 × 5 = 40 (optimal)
- Maximum: 8 × 6 = 48 (jika memory cukup)

Memory Calculation:
- Total RAM: 16GB
- System: ~2GB
- MySQL: ~4-6GB (untuk high traffic)
- Buffer: ~2GB
- Available untuk PHP-FPM: ~6-8GB

Per Process Memory:
- Laravel dengan caching: ~100-150MB
- Laravel tanpa caching: ~150-200MB
- Dengan optimasi: ~80-120MB

Jika 40 processes × 120MB = 4.8GB ✅ (masih aman)
Jika 48 processes × 120MB = 5.76GB ✅ (masih aman)
```

#### 2. Concurrent Request Handling
```
Dengan 40 PHP-FPM workers:
- Setiap worker bisa handle 1 request
- 40 workers = 40 concurrent requests
- Jika rata-rata request time = 200ms
- Throughput = 40 / 0.2s = 200 requests/second

Untuk 200-500 concurrent users:
- Tidak semua user request secara bersamaan
- Biasanya hanya 20-30% yang aktif request
- 200 users × 30% = 60 active requests
- 500 users × 30% = 150 active requests

Dengan 40 workers, kita bisa handle:
- 40 concurrent requests langsung
- Request lainnya akan queue (normal)
- Response time mungkin sedikit naik saat peak
```

## ✅ Rekomendasi Settingan untuk Ratusan Users

### Settingan PHP-FPM (cPanel):

```
Max Children: 40 (dari 24)
- Formula: 8 vCPU × 5 = 40
- Memory: 40 × 120MB = 4.8GB (masih aman)
- Bisa handle ~40 concurrent requests

Start Servers: 20 (50% dari max)
- Quick response untuk initial load

Min Spare Servers: 12 (30% dari max)
- Handle traffic spike

Max Spare Servers: 20 (50% dari max)
- Handle traffic spike

Max Requests: 50 (dari 100)
- Prevent memory leak
- Restart process setiap 50 requests

Process Idle Timeout: 10s (dari 30s)
- Kill idle process lebih cepat
- Free up memory
```

### Perbandingan:

| Setting | Current | Recommended | Impact |
|--------|---------|-------------|--------|
| Max Children | 24 | **40** | ✅ Handle lebih banyak concurrent users |
| Max Requests | 100 | **50** | ✅ Prevent memory leak |
| Idle Timeout | 30s | **10s** | ✅ Free memory lebih cepat |
| Memory Usage | ~3.6GB | ~4.8GB | ⚠️ Masih dalam limit (6-8GB available) |
| Concurrent Requests | ~24 | **~40** | ✅ 66% lebih banyak |

## 🚨 TAPI! Masalah CPU 100% Bukan Hanya PHP-FPM

Dengan ratusan concurrent users, masalah CPU 100% kemungkinan besar disebabkan oleh:

### 1. Database Queries yang Lambat (PALING PENTING!)
```
Dengan 200-500 users:
- Setiap user mungkin trigger 5-10 queries per request
- 200 users × 5 queries = 1000 queries/second
- Jika ada query yang lambat (> 1 detik), akan block semua

Solusi:
✅ Tambahkan index pada kolom yang sering di-query
✅ Optimize N+1 queries (gunakan eager loading)
✅ Cache query results yang tidak sering berubah
✅ Gunakan database connection pooling
✅ Monitor slow query log
```

### 2. Laravel N+1 Query Problems
```
Contoh masalah:
- Loop 200 users
- Setiap loop query database
- Total: 200 queries untuk 1 request

Solusi:
✅ Gunakan eager loading: User::with('profile', 'orders')->get()
✅ Batch queries jika memungkinkan
✅ Cache frequently accessed data
```

### 3. Missing Database Indexes
```
Query tanpa index = Full table scan
- Table dengan 100K rows = sangat lambat
- Dengan ratusan concurrent users = CPU 100%

Solusi:
✅ Cek query yang lambat dengan EXPLAIN
✅ Tambahkan index pada:
   - Foreign keys
   - Columns di WHERE clause
   - Columns di ORDER BY
   - Columns di JOIN conditions
```

### 4. Queue Workers yang Stuck
```
Jika ada queue workers yang stuck:
- Mereka consume CPU terus-menerus
- Dengan ratusan users, queue bisa penuh

Solusi:
✅ Monitor queue workers
✅ Restart queue workers secara berkala
✅ Limit jumlah queue workers (max 4-6 untuk 8 vCPU)
```

### 5. Scheduled Tasks yang Overlap
```
Dengan ratusan users, scheduled tasks bisa overlap
- Multiple instances running bersamaan
- Consume CPU dan memory

Solusi:
✅ Pastikan semua task pakai withoutOverlapping()
✅ Spread out execution time
✅ Monitor task logs
```

## 🛠️ Langkah Implementasi (Prioritas)

### PRIORITAS 1: Optimasi Database (PALING PENTING!)
```sql
-- 1. Cek slow queries
SHOW VARIABLES LIKE 'slow_query_log%';
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 1; -- Log query > 1 detik

-- 2. Cek queries tanpa index
-- Jalankan EXPLAIN pada query yang sering dipanggil

-- 3. Tambahkan index yang diperlukan
-- Lihat file CREATE_MISSING_INDEXES.sql
```

### PRIORITAS 2: Update PHP-FPM Settings
```
Di cPanel → System PHP-FPM Settings:
1. Max Children: 24 → 40
2. Max Requests: 100 → 50
3. Process Idle Timeout: 30 → 10
4. Restart PHP-FPM
```

### PRIORITAS 3: Optimasi Laravel Code
```php
// 1. Gunakan eager loading
// BAD:
$users = User::all();
foreach ($users as $user) {
    echo $user->profile->name; // N+1 query
}

// GOOD:
$users = User::with('profile')->get();
foreach ($users as $user) {
    echo $user->profile->name; // No additional query
}

// 2. Cache frequently accessed data
Cache::remember('users.active', 3600, function () {
    return User::where('is_active', true)->get();
});

// 3. Use database indexes
// Pastikan semua foreign keys dan frequently queried columns punya index
```

### PRIORITAS 4: Setup Caching
```php
// config/cache.php
'default' => env('CACHE_DRIVER', 'redis'), // Gunakan Redis

// Cache query results
$users = Cache::remember('users.list', 3600, function () {
    return User::all();
});
```

### PRIORITAS 5: Monitor & Tune
```bash
# Monitor PHP-FPM status
watch -n 2 'curl -s http://localhost/php-fpm-status'

# Monitor MySQL processes
mysql -e "SHOW PROCESSLIST;" | grep -v Sleep

# Monitor CPU & Memory
top -bn1 | head -20
```

## 📊 Expected Results

Setelah optimasi lengkap:
- ✅ CPU usage: 100% → 60-80% (normal untuk high traffic)
- ✅ Load average: 15-16 → 8-12
- ✅ Response time: < 500ms (dari mungkin > 2s)
- ✅ Concurrent users: Bisa handle 200-500 users
- ✅ Database queries: < 100ms average (dari mungkin > 1s)

## ⚠️ Jika Masih CPU 100% Setelah Optimasi

Kemungkinan perlu:
1. **Scale Up Server**: Upgrade ke 16 vCPU, 32GB RAM
2. **Load Balancing**: Split traffic ke multiple servers
3. **Database Optimization**: 
   - Read replicas untuk read-heavy operations
   - Query optimization yang lebih agresif
4. **Application Architecture**:
   - Move heavy operations ke queue
   - Implement caching layer yang lebih agresif
   - Consider microservices untuk heavy modules

## 🔍 Monitoring Checklist

Setelah implementasi, monitor:
- [ ] PHP-FPM active processes (should be < 40)
- [ ] PHP-FPM idle processes (should be 10-20)
- [ ] MySQL connections (should be < 100)
- [ ] MySQL slow queries (should be < 10 per hour)
- [ ] CPU usage (should be 60-80% during peak)
- [ ] Memory usage (should be < 80%)
- [ ] Response time (should be < 500ms average)
- [ ] Error rate (should be < 1%)

## 📝 Quick Reference

### PHP-FPM Settings untuk Ratusan Users:
```
Max Children: 40
Start Servers: 20
Min Spare: 12
Max Spare: 20
Max Requests: 50
Idle Timeout: 10s
```

### Database Optimization:
```
- Enable slow query log
- Add indexes on frequently queried columns
- Use eager loading in Laravel
- Cache query results
- Connection pooling
```

### Application Optimization:
```
- Redis caching
- Queue heavy operations
- Optimize N+1 queries
- Batch operations
- Lazy loading where appropriate
```
