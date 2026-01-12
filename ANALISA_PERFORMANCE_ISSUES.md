# Analisa Masalah Performa Server - CPU 100%

## Masalah yang Teridentifikasi

### 1. **Scheduled Tasks yang Banyak dan Overlapping**
   - **Lokasi**: `app/Console/Kernel.php`
   - **Masalah**: Terdapat 19+ scheduled tasks yang berjalan setiap hari
   - **Dampak**: Beberapa task mungkin overlap dan menyebabkan CPU spike
   - **Rekomendasi**:
     - Pastikan semua task menggunakan `withoutOverlapping()`
     - Spread out waktu eksekusi task agar tidak bersamaan
     - Monitor log untuk task yang gagal atau stuck

### 2. **Query Berat di Marketing Dashboard**
   - **Lokasi**: `app/Services/AIDatabaseHelper.php`
   - **Masalah**: 
     - Query dengan subquery dan EXISTS clause yang kompleks
     - Query tanpa limit pada beberapa method
     - Query yang scan banyak data tanpa index yang tepat
   - **Dampak**: Marketing dashboard bisa sangat lambat dan consume banyak CPU
   - **Rekomendasi**:
     - Tambahkan index pada kolom yang sering di-query (created_at, kode_outlet, member_id)
     - Tambahkan pagination/limit pada semua query
     - Cache hasil query yang tidak sering berubah
     - Gunakan queue untuk query yang berat

### 3. **Database Connection Pooling**
   - **Lokasi**: `config/database.php`
   - **Masalah**: 
     - Connection timeout hanya 5 detik (baik, tapi perlu monitor)
     - Tidak ada connection pooling yang jelas
   - **Dampak**: Banyak connection yang tidak di-cleanup bisa menyebabkan masalah
   - **Rekomendasi**:
     - Monitor jumlah connection aktif
     - Pastikan PHP-FPM cleanup connection dengan benar
     - Pertimbangkan menggunakan persistent connection dengan hati-hati

### 4. **Queue Workers yang Mungkin Stuck**
   - **Lokasi**: Queue configuration di `config/queue.php`
   - **Masalah**: Queue workers yang stuck bisa consume CPU terus-menerus
   - **Dampak**: CPU 100% karena worker yang tidak selesai
   - **Rekomendasi**:
     - Cek apakah ada queue workers yang running
     - Restart queue workers secara berkala
     - Monitor failed jobs

### 5. **N+1 Query Problems**
   - **Lokasi**: Multiple controllers dengan foreach loops
   - **Masalah**: Banyak foreach loop yang mungkin menyebabkan N+1 queries
   - **Dampak**: Banyak query kecil yang dijalankan berulang-ulang
   - **Rekomendasi**:
     - Gunakan eager loading (with()) untuk relationships
     - Batch queries jika memungkinkan
     - Gunakan DB::select() untuk complex queries

## Langkah Diagnostik

### Step 1: Cek MySQL Processes
Jalankan script `DIAGNOSTIC_SERVER_PERFORMANCE.sql` untuk melihat:
- Process yang stuck (> 30 detik)
- Process yang membuat index/alter table
- Metadata locks
- Connection count

### Step 2: Cek Queue Workers
```bash
# Cek apakah ada queue workers yang running
ps aux | grep "queue:work"

# Cek failed jobs
php artisan queue:failed

# Restart queue workers jika perlu
php artisan queue:restart
```

### Step 3: Cek Scheduled Tasks
```bash
# Cek log scheduled tasks
tail -f storage/logs/schedule.log
tail -f storage/logs/holiday-attendance.log
tail -f storage/logs/extra-off-detection.log
# ... dll

# Cek apakah schedule:run berjalan
ps aux | grep "schedule:run"
```

### Step 4: Cek PHP-FPM Processes
```bash
# Cek jumlah PHP-FPM processes
ps aux | grep php-fpm | wc -l

# Cek PHP-FPM status (jika enabled)
curl http://localhost/php-fpm-status
```

### Step 5: Cek Laravel Logs
```bash
# Cek error logs
tail -f storage/logs/laravel.log

# Cari error yang berulang
grep -i "error\|exception\|timeout" storage/logs/laravel.log | tail -50
```

## Solusi Cepat (Quick Fixes)

### 1. Kill Stuck MySQL Processes
```sql
-- Lihat process yang stuck
SHOW PROCESSLIST;

-- Kill process yang stuck (ganti ID dengan process ID yang sebenarnya)
KILL [PROCESS_ID];
```

### 2. Restart Queue Workers
```bash
php artisan queue:restart
```

### 3. Disable Scheduled Tasks Sementara
Edit `app/Console/Kernel.php` dan comment out task yang tidak critical sementara:
```php
// $schedule->command('attendance:process-holiday')
//     ->dailyAt('06:00')
//     ...
```

### 4. Optimize Database Queries
- Tambahkan index pada kolom yang sering di-query
- Gunakan EXPLAIN untuk melihat query plan
- Tambahkan limit pada query yang tidak perlu semua data

### 5. Clear Cache
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

## Solusi Jangka Panjang

### 1. Implementasi Caching
- Cache hasil query yang tidak sering berubah
- Gunakan Redis untuk caching
- Cache dashboard data dengan TTL yang sesuai

### 2. Optimasi Query
- Review semua query di AIDatabaseHelper
- Tambahkan index yang diperlukan
- Gunakan pagination untuk semua list queries
- Optimize subqueries dan EXISTS clauses

### 3. Queue Heavy Operations
- Pindahkan heavy queries ke queue
- Process dashboard data secara async
- Gunakan background jobs untuk scheduled tasks yang berat

### 4. Monitoring
- Setup monitoring untuk:
  - CPU usage
  - Memory usage
  - Database connections
  - Query execution time
  - Queue workers status

### 5. Database Optimization
- Regular maintenance (OPTIMIZE TABLE)
- Monitor slow queries
- Setup query cache jika memungkinkan
- Consider read replicas untuk heavy read operations

## File-file Penting untuk Diperiksa

1. `app/Console/Kernel.php` - Scheduled tasks
2. `app/Services/AIDatabaseHelper.php` - Heavy queries
3. `app/Http/Controllers/MarketingDashboardController.php` - Dashboard queries
4. `config/database.php` - Database configuration
5. `config/queue.php` - Queue configuration
6. `storage/logs/` - Application logs

## Checklist Troubleshooting

- [ ] Cek MySQL processes yang stuck
- [ ] Cek queue workers status
- [ ] Cek scheduled tasks logs
- [ ] Cek PHP-FPM processes
- [ ] Cek Laravel error logs
- [ ] Cek database connections
- [ ] Cek CPU dan memory usage
- [ ] Cek disk I/O
- [ ] Cek network latency ke database
- [ ] Review recent code changes
