# 🚀 Optimasi PHP-FPM untuk Server 8 vCPU / 16GB RAM - CPU 100%

## 📊 Analisis Masalah

Server Anda dengan spesifikasi:
- **8 vCPU**
- **16GB RAM**
- **High Traffic**
- **CPU Usage: 100%**

Masalah utama biasanya:
1. ❌ PHP-FPM `pm.max_children` terlalu tinggi → terlalu banyak proses bersamaan
2. ❌ PHP-FPM `pm.max_requests` terlalu tinggi → memory leak
3. ❌ Process manager strategy tidak optimal
4. ❌ Tidak ada monitoring dan logging

---

## ✅ SOLUSI LENGKAP

### **LANGKAH 1: Optimasi PHP-FPM Configuration**

#### **A. Untuk cPanel (MultiPHP Manager)**

1. Login ke **cPanel**
2. Buka **MultiPHP Manager** atau **Select PHP Version**
3. Klik **PHP-FPM Settings** atau **Configure PHP-FPM**
4. Ubah setting berikut:

| Setting | Nilai Lama (Typical) | Nilai Baru (Optimized) | Alasan |
|---------|---------------------|------------------------|--------|
| **Process Manager** | dynamic | **dynamic** | Tetap dynamic untuk auto-scaling |
| **Max Children** | 40-50 | **24** | Formula: (8 vCPU × 2) + 4 = 20, untuk high traffic: 24 |
| **Start Servers** | 5-10 | **12** | 50% dari max_children untuk quick response |
| **Min Spare Servers** | 2-5 | **8** | 30% dari max_children |
| **Max Spare Servers** | 5-10 | **12** | 50% dari max_children |
| **Max Requests** | 500-1000 | **100** | Restart setiap 100 requests untuk prevent memory leak |
| **Process Idle Timeout** | 10 | **10** | Sudah optimal |

5. Klik **Update** atau **Save**
6. Restart PHP-FPM:
   ```bash
   systemctl restart php-fpm
   # atau via cPanel: MultiPHP Manager → Restart PHP-FPM
   ```

#### **B. Untuk Manual Configuration (Non-cPanel)**

1. Edit file PHP-FPM pool config:
   ```bash
   # Lokasi biasanya:
   # /etc/php-fpm.d/www.conf (CentOS/RHEL)
   # /etc/php/8.2/fpm/pool.d/www.conf (Ubuntu/Debian)
   # /opt/cpanel/ea-php82/root/etc/php-fpm.d/www.conf (cPanel)
   ```

2. Gunakan file `php-fpm-optimized.conf` yang sudah disediakan
   - Copy isi file tersebut ke config PHP-FPM Anda
   - Sesuaikan `user`, `group`, dan `listen` socket sesuai server Anda

3. Restart PHP-FPM:
   ```bash
   systemctl restart php-fpm
   # atau
   service php-fpm restart
   ```

---

### **LANGKAH 2: Perhitungan Resource**

#### **Memory Calculation:**

```
Max Children: 24
Memory per process: ~75MB (average)
Total PHP-FPM memory: 24 × 75MB = 1.8GB

Sisa RAM untuk:
- OS: ~2GB
- Database (MySQL): ~4GB
- Web Server (Nginx/Apache): ~1GB
- Cache (Redis/Memcached): ~2GB
- Other services: ~2GB
- Buffer: ~3.2GB

Total: ~16GB ✓
```

#### **CPU Calculation:**

```
8 vCPU = 8 cores
Optimal workers: (8 × 2) + 4 = 20
Untuk high traffic: 24 (masih aman)
Maximum: 32 (tapi tidak recommended)

Dengan 24 workers:
- 3 workers per core (average)
- Masih ada buffer untuk OS dan services lain
```

---

### **LANGKAH 3: Monitoring & Verification**

#### **Check Current PHP-FPM Status:**

```bash
# Check berapa banyak PHP-FPM processes yang berjalan
ps aux | grep php-fpm | grep -v grep | wc -l
# Harusnya sekitar 12-24, bukan 40+

# Check memory usage per process
ps aux | grep php-fpm | grep -v grep | awk '{sum+=$6} END {print sum/1024 " MB"}'

# Check CPU usage per process
top -p $(pgrep -d',' -f 'php-fpm')

# Check PHP-FPM status page (jika enabled)
curl http://localhost/status?full
# atau
curl http://localhost/status?json
```

#### **Check Server Resources:**

```bash
# CPU usage
top
# atau
htop

# Memory usage
free -h

# Load average (harusnya < 8 untuk 8 vCPU)
uptime
```

#### **Check PHP-FPM Logs:**

```bash
# Error log
tail -f /var/log/php-fpm/www-error.log

# Slow log (requests > 60s)
tail -f /var/log/php-fpm/www-slow.log

# Access log
tail -f /var/log/php-fpm/www-access.log
```

---

### **LANGKAH 4: Fine-Tuning Berdasarkan Traffic**

#### **Jika CPU masih tinggi (>80%):**

1. **Kurangi Max Children:**
   ```
   pm.max_children = 20  (dari 24)
   pm.start_servers = 10
   pm.min_spare_servers = 6
   pm.max_spare_servers = 10
   ```

2. **Cek apakah ada process lain yang consume CPU:**
   ```bash
   top -o %CPU
   # Cek queue workers, cron jobs, dll
   ```

3. **Enable OPcache** (jika belum):
   - Sudah ada di `php.ini`, pastikan enabled

#### **Jika Memory masih tinggi (>80%):**

1. **Kurangi Max Children:**
   ```
   pm.max_children = 20
   ```

2. **Kurangi Memory Limit per process:**
   ```
   php_admin_value[memory_limit] = 192M  (dari 256M)
   ```

3. **Lower Max Requests:**
   ```
   pm.max_requests = 50  (dari 100)
   ```

#### **Jika Response Time masih lambat:**

1. **Naikkan Max Children (jika CPU dan Memory masih OK):**
   ```
   pm.max_children = 28  (dari 24)
   pm.start_servers = 14
   pm.min_spare_servers = 10
   pm.max_spare_servers = 14
   ```

2. **Enable OPcache** (pastikan sudah enabled)

3. **Check database queries** (mungkin ada slow queries)

---

### **LANGKAH 5: Additional Optimizations**

#### **1. Enable OPcache (Pastikan Sudah Enabled)**

File `php.ini` sudah ada, pastikan:
```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.revalidate_freq=0
opcache.validate_timestamps=0
```

#### **2. Optimasi Database**

- Pastikan ada indexes untuk queries yang sering digunakan
- Check slow query log
- Optimize tables secara berkala

#### **3. Enable Gzip Compression**

Di Nginx/Apache config:
```nginx
gzip on;
gzip_types text/plain text/css application/json application/javascript;
gzip_min_length 1000;
```

#### **4. Setup Caching (Redis/Memcached)**

Jika belum ada, pertimbangkan untuk setup Redis untuk:
- Session storage
- Cache storage
- Queue driver

---

## 📋 CHECKLIST IMPLEMENTASI

### **Immediate Actions (Lakukan Sekarang):**

- [ ] **Backup current PHP-FPM config** (jika manual edit)
- [ ] **Ubah Max Children dari 40 ke 24** (via cPanel atau manual)
- [ ] **Ubah Max Requests dari 500 ke 100**
- [ ] **Restart PHP-FPM**
- [ ] **Monitor CPU usage selama 1 jam**
- [ ] **Check PHP-FPM processes count**

### **Monitoring (24 Jam Pertama):**

- [ ] **Monitor CPU usage** (harusnya turun ke 30-50%)
- [ ] **Monitor Memory usage** (harusnya < 80%)
- [ ] **Check response time aplikasi** (harusnya lebih cepat)
- [ ] **Check error logs** (pastikan tidak ada error baru)
- [ ] **Check slow log** (identifikasi slow requests)

### **Fine-Tuning (Setelah 24 Jam):**

- [ ] **Adjust Max Children** jika perlu (naik/turun)
- [ ] **Adjust Max Requests** jika perlu
- [ ] **Optimize slow queries** yang ditemukan di slow log
- [ ] **Setup monitoring tools** (optional: New Relic, Datadog, dll)

---

## 🎯 EXPECTED RESULTS

Setelah optimasi:

| Metric | Sebelum | Sesudah |
|--------|---------|---------|
| **CPU Usage** | 100% | 30-50% |
| **PHP-FPM Processes** | 40+ | 12-24 |
| **Memory Usage** | 80-90% | 60-70% |
| **Response Time** | Lambat | Lebih cepat |
| **Server Stability** | Tidak stabil | Stabil |

---

## ⚠️ PENTING!

1. **Jangan ubah semua setting sekaligus** - ubah bertahap dan monitor
2. **Backup config sebelum edit** - jika manual edit
3. **Monitor selama 24-48 jam** setelah perubahan
4. **Test di waktu low traffic** jika memungkinkan
5. **Jika aplikasi jadi lambat** setelah kurangi Max Children, naikkan sedikit ke 28

---

## 🔧 TROUBLESHOOTING

### **CPU masih 100% setelah optimasi PHP-FPM?**

1. **Check apakah ada process lain yang consume CPU:**
   ```bash
   top -o %CPU
   # Cek: queue workers, cron jobs, database, dll
   ```

2. **Check queue workers** (mungkin terlalu banyak):
   ```bash
   ps aux | grep 'queue:work' | grep -v grep | wc -l
   # Harusnya hanya 1-2, bukan 60+
   ```

3. **Check scheduled tasks** (mungkin overlap):
   ```bash
   ps aux | grep 'artisan schedule' | grep -v grep
   ```

4. **Lihat dokumentasi:** `SOLUSI_LEMOT_SERVER.md` untuk solusi lengkap

### **Aplikasi jadi lambat setelah kurangi Max Children?**

1. **Naikkan sedikit:**
   ```
   pm.max_children = 28
   ```

2. **Check apakah ada slow queries** di database

3. **Check apakah OPcache enabled**

4. **Check memory usage** - mungkin perlu upgrade RAM

### **Memory masih tinggi setelah optimasi?**

1. **Kurangi Max Children:**
   ```
   pm.max_children = 20
   ```

2. **Kurangi Memory Limit:**
   ```
   php_admin_value[memory_limit] = 192M
   ```

3. **Lower Max Requests:**
   ```
   pm.max_requests = 50
   ```

4. **Check memory leaks** di aplikasi

---

## 📞 Bantuan Tambahan

Jika masih ada masalah:

1. **Check logs:**
   - `/var/log/php-fpm/www-error.log`
   - `/var/log/php-fpm/www-slow.log`
   - `storage/logs/laravel.log`

2. **Monitor dengan tools:**
   - `top`, `htop`, `glances`
   - `php-fpm status page`
   - Server monitoring tools (cPanel, CloudLinux, dll)

3. **Dokumentasi terkait:**
   - `SOLUSI_LEMOT_SERVER.md` - Solusi lengkap server lemot
   - `PERFORMANCE_OPTIMIZATION.md` - Optimasi performa umum
   - `php-fpm-optimized.conf` - Config file lengkap

---

## 📚 Referensi

- [PHP-FPM Official Documentation](https://www.php.net/manual/en/install.fpm.configuration.php)
- [cPanel PHP-FPM Settings](https://docs.cpanel.net/knowledge-base/web-services/how-to-configure-php-fpm-settings/)
- [PHP-FPM Tuning Guide](https://www.php.net/manual/en/install.fpm.configuration.php)

---

**File Helper:**
- `php-fpm-optimized.conf` - Configuration file lengkap
- `SOLUSI_LEMOT_SERVER.md` - Solusi lengkap server lemot
- `PERFORMANCE_OPTIMIZATION.md` - Optimasi performa umum

