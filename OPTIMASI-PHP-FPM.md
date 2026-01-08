# Optimasi PHP-FPM untuk Server High Traffic

## Masalah Saat Ini
- CPU 100% terus menerus
- Server: 8 vCPU, 16GB RAM
- 4 aplikasi berbeda dengan 1 backend yang sama
- Ratusan akun mengakses (web ymsofterp, member apps, POS di outlet)
- Konfigurasi saat ini: Max Children = 24 (TERLALU KECIL)

## Analisis Masalah

### 1. Max Children Terlalu Kecil
- **Saat ini**: 24 workers
- **Kebutuhan**: 4 aplikasi × ~20-25 workers = 80-100 workers
- **Akibat**: Request queue panjang → CPU 100% karena workers overloaded

### 2. Memory Per Process
- **Saat ini**: 256M per process
- **Dengan 24 workers**: 24 × 256M = ~6GB
- **Available RAM**: 16GB - 4GB (system) = 12GB
- **Kesimpulan**: Masih ada ruang untuk lebih banyak workers

### 3. Process Idle Timeout Terlalu Pendek
- **Saat ini**: 10 detik
- **Masalah**: Process terlalu cepat di-kill → overhead spawn/kill tinggi
- **Solusi**: Naikkan ke 30 detik

## Rekomendasi Konfigurasi

### Perubahan Utama:
1. **Max Children**: 24 → **80**
2. **Start Servers**: 12 → **32**
3. **Min Spare Servers**: 8 → **20**
4. **Max Spare Servers**: 12 → **40**
5. **Max Requests**: 100 → **200** (reduce restart overhead)
6. **Process Idle Timeout**: 10s → **30s**
7. **Memory Limit**: 256M → **128M** (allow more workers)
8. **OPcache Memory**: 256M → **512M** (better caching)

## Cara Implementasi

### Opsi 1: Update via cPanel (Recommended)
1. Login ke cPanel
2. Masuk ke **Select PHP Version** atau **MultiPHP INI Editor**
3. Klik **PHP-FPM Settings**
4. Update settings sesuai file `php-fpm-optimized-high-traffic.conf`
5. Klik **Update**
6. Restart PHP-FPM:
   ```bash
   systemctl restart php-fpm
   # atau
   service php82-php-fpm restart
   ```

### Opsi 2: Manual Edit (Jika tidak pakai cPanel)
1. Backup config saat ini:
   ```bash
   cp /etc/php-fpm.d/www.conf /etc/php-fpm.d/www.conf.backup
   ```
2. Edit config:
   ```bash
   nano /etc/php-fpm.d/www.conf
   ```
3. Update settings sesuai file `php-fpm-optimized-high-traffic.conf`
4. Test config:
   ```bash
   php-fpm -t
   ```
5. Restart PHP-FPM:
   ```bash
   systemctl restart php-fpm
   ```

## Monitoring Setelah Update

### 1. Check PHP-FPM Status
```bash
# Check status page
curl http://localhost/status

# Atau via browser: http://yourdomain.com/status
```

### 2. Monitor CPU Usage
```bash
# Real-time CPU monitoring
top
# atau
htop
```

### 3. Monitor PHP-FPM Processes
```bash
# Check active processes
ps aux | grep php-fpm | wc -l

# Check memory usage
ps aux | grep php-fpm | awk '{sum+=$6} END {print sum/1024 " MB"}'
```

### 4. Check Slow Log
```bash
# Monitor slow requests
tail -f /var/log/php-fpm/www-slow.log
```

## Optimasi Tambahan

### 1. Database Connection Pooling
- Pastikan menggunakan connection pooling
- Limit max connections per application
- Monitor database connections

### 2. Redis/Memcached untuk Caching
- Enable Redis untuk session & cache
- Reduce database queries

### 3. Query Optimization
- Review slow queries
- Add database indexes
- Optimize N+1 queries

### 4. CDN untuk Static Assets
- Serve static files via CDN
- Reduce server load

### 5. Separate Pool per Aplikasi (Advanced)
Jika memungkinkan, buat separate PHP-FPM pool untuk setiap aplikasi:
- `ymsofterp.conf` → 30 workers
- `ymsoftapp.conf` → 20 workers
- `pos.conf` → 20 workers
- `justusku.conf` → 10 workers

## Expected Results

Setelah optimasi:
- ✅ CPU usage turun dari 100% ke 60-80%
- ✅ Response time lebih cepat
- ✅ Bisa handle lebih banyak concurrent users
- ✅ No more request queue

## Troubleshooting

### Jika CPU Masih 100%:
1. Check slow queries di database
2. Review application code untuk inefficient queries
3. Check apakah ada infinite loops atau memory leaks
4. Monitor dengan `strace` untuk identify bottleneck

### Jika Out of Memory:
1. Reduce `pm.max_children` ke 60
2. Reduce `php_admin_value[memory_limit]` ke 96M
3. Check memory leaks di application

### Jika Response Time Masih Lambat:
1. Enable OPcache (sudah di config)
2. Enable Redis caching
3. Optimize database queries
4. Check network latency

## Catatan Penting

⚠️ **JANGAN langsung set ke 80 workers tanpa testing!**

**Langkah Incremental:**
1. Set ke 40 workers → monitor 1-2 jam
2. Jika OK, naikkan ke 60 → monitor 1-2 jam
3. Jika OK, naikkan ke 80 → monitor 1-2 jam
4. Adjust sesuai kebutuhan

**Formula Safety:**
- Max workers = (Available RAM - 2GB) / Memory per process
- Dengan 128M per process: (12GB - 2GB) / 128M = ~78 workers
- Jadi 80 workers masih dalam batas aman
