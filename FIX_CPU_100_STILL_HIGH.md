# 🔥 Fix CPU 100% - Masih Tinggi Setelah Restart

## 🎯 **HASIL SETELAH PERUBAHAN**

**Status saat ini:**
- ✅ PHP-FPM processes: **18** (turun dari 26)
- 🔴 **CPU per process: 49-51%** (MASIH SANGAT TINGGI! Normal < 5%)
- 🔴 **Load Average: 17-22** (MASIH SANGAT TINGGI!)
- 🔴 **Total CPU: 94.8%** (MASIH 100%!)

**Kesimpulan:** Setiap PHP-FPM process masih consume CPU sangat tinggi!

---

## 🔍 **ROOT CAUSE ANALYSIS**

**Masalahnya BUKAN hanya jumlah processes, tapi:**
1. 🔴 **Setiap PHP-FPM process consume 49-51% CPU** - ABNORMAL!
2. 🔴 **Ada query berat atau komputasi berat di setiap request**
3. 🔴 **Mungkin ada infinite loop atau heavy computation**

**Normal:**
- PHP-FPM process: **< 5% CPU** per process
- Dengan 18 processes × 5% = **90% CPU** (masih acceptable)

**Saat ini:**
- PHP-FPM process: **49-51% CPU** per process
- Dengan 18 processes × 50% = **900% CPU** (impossible!)
- **Result:** CPU 100%, semua processes compete untuk CPU

---

## ⚡ **SOLUSI URGENT - LAKUKAN SEKARANG!**

### **STEP 1: Kurangi Max Children Lebih Lanjut** (PRIORITAS TINGGI!)

**Karena setiap process consume 50% CPU, kita perlu kurangi jumlah processes lebih agresif:**

**Via cPanel:**
1. Login cPanel → **MultiPHP Manager**
2. Klik **PHP-FPM Settings**
3. Ubah settings:
   - **Max Children: 18 → 8** (atau bahkan 6!)
   - **Start Servers: 8 → 4**
   - **Min Spare Servers: 6 → 3**
   - **Max Spare Servers: 8 → 4**
   - **Max Requests: 100 → 50** (lebih agresif)
   - **Process Idle Timeout: 5 → 3** (kill idle lebih cepat)
4. Klik **Update**
5. Restart PHP-FPM

**Rumus untuk CPU 50% per process:**
- **Max Children = vCPU ÷ 2 = 8 ÷ 2 = 4** (sangat konservatif)
- **Atau: vCPU = 8** (satu process per vCPU)
- **Recommended: 6-8** (balance antara performance dan stability)

---

### **STEP 2: Enable PHP-FPM Slow Log** (URGENT!)

**Untuk melihat apa yang sedang diproses oleh PHP-FPM:**

1. **Find config file:**
   ```bash
   find /opt/cpanel/ea-php82/root/etc/php-fpm.d -name "*.conf" | head -1
   ```

2. **Edit config file:**
   ```bash
   nano /opt/cpanel/ea-php82/root/etc/php-fpm.d/[domain].conf
   ```

3. **Tambahkan di section `[pool]`:**
   ```ini
   ; Slow log
   slowlog = /opt/cpanel/ea-php82/root/var/log/php-fpm-slow.log
   request_slowlog_timeout = 2s
   ```

4. **Create log directory:**
   ```bash
   mkdir -p /opt/cpanel/ea-php82/root/var/log
   chown ymsuperadmin:ymsuperadmin /opt/cpanel/ea-php82/root/var/log
   ```

5. **Restart PHP-FPM:**
   ```bash
   systemctl restart ea-php82-php-fpm
   ```

6. **Monitor slow log:**
   ```bash
   tail -f /opt/cpanel/ea-php82/root/var/log/php-fpm-slow.log
   ```

**Ini akan menunjukkan:**
- Script mana yang sedang diproses
- Berapa lama waktu eksekusi
- Stack trace jika ada

---

### **STEP 3: Check Active Requests** (IMPORTANT!)

**Check apakah ada banyak concurrent requests:**

```bash
# Check active HTTP connections
netstat -an | grep :80 | grep ESTABLISHED | wc -l
netstat -an | grep :443 | grep ESTABLISHED | wc -l

# Check MySQL connections from PHP
mysql -u root -p -e "
SELECT 
    SUBSTRING_INDEX(host, ':', 1) as host_ip,
    COUNT(*) as connections,
    SUM(CASE WHEN command != 'Sleep' THEN 1 ELSE 0 END) as active_queries
FROM information_schema.processlist 
WHERE user LIKE 'justusku%' OR user LIKE 'mysql.%'
GROUP BY SUBSTRING_INDEX(host, ':', 1)
ORDER BY connections DESC;
"
```

**Jika ada banyak concurrent requests:**
- Ini normal untuk aplikasi dengan banyak user
- Tapi setiap request tidak boleh consume 50% CPU
- Perlu optimize aplikasi (bukan hanya server config)

---

### **STEP 4: Check MySQL Queries yang Dipanggil Berulang** (IMPORTANT!)

**Meskipun tidak ada slow query yang berjalan lama, mungkin ada query yang dipanggil berkali-kali:**

```bash
# Check MySQL slow query log untuk query yang sering dipanggil
mysqldumpslow -s c -t 10 /var/lib/mysql/YMServer-slow.log | head -30
```

**Atau check query yang paling sering dipanggil:**
```bash
# Enable general log sementara (HATI-HATI! Bisa besar sekali)
mysql -u root -p -e "SET GLOBAL general_log = 'ON';"
# Tunggu 1 menit
sleep 60
# Check log
tail -1000 /var/lib/mysql/YMServer.log | grep -i "select\|update\|insert\|delete" | sort | uniq -c | sort -rn | head -20
# Disable general log
mysql -u root -p -e "SET GLOBAL general_log = 'OFF';"
```

---

### **STEP 5: Check Aplikasi untuk Infinite Loop atau Heavy Computation**

**Kemungkinan ada masalah di aplikasi:**
1. **Infinite loop** di controller atau service
2. **Heavy computation** tanpa caching
3. **Query N+1** yang dipanggil berkali-kali
4. **Large data processing** tanpa pagination

**Check Laravel logs:**
```bash
tail -100 /path/to/laravel/storage/logs/laravel.log | grep -i "error\|exception\|fatal"
```

**Check apakah ada job yang stuck:**
```bash
# Check queue jobs
php /path/to/laravel/artisan queue:work --help
# Check failed jobs
php /path/to/laravel/artisan queue:failed
```

---

## 📊 **EXPECTED RESULTS SETELAH FIX**

| Metric | Saat Ini | Expected |
|--------|----------|----------|
| **Max Children** | 18 | 6-8 |
| **PHP-FPM Processes** | 18 | 6-8 |
| **CPU per Process** | 49-51% | < 10% |
| **Load Average** | 17-22 | < 4.0 |
| **Total CPU Usage** | 94.8% | 40-60% |

---

## ⚠️ **KENAPA PERLU KURANGI KE 6-8?**

**Dengan CPU 50% per process:**
- **8 processes × 50% = 400% CPU** (masih tinggi, tapi manageable)
- **6 processes × 50% = 300% CPU** (lebih aman)
- **4 processes × 50% = 200% CPU** (sangat aman, tapi mungkin lambat)

**Trade-off:**
- ✅ CPU usage turun
- ⚠️ Aplikasi mungkin lebih lambat jika ada banyak concurrent requests
- ✅ Tapi lebih baik lambat daripada server crash

**Solusi jangka panjang:**
- Optimize aplikasi untuk mengurangi CPU usage per request
- Setelah optimize, bisa naikkan Max Children lagi

---

## 🔧 **TROUBLESHOOTING**

### **Setelah kurangi ke 6-8, CPU masih tinggi?**

1. **Check apakah ada process lain yang consume CPU:**
   ```bash
   ps aux --sort=-%cpu | head -20
   ```

2. **Check apakah ada MySQL query yang berat:**
   ```bash
   mysql -u root -p -e "SHOW PROCESSLIST;" | grep -v Sleep
   ```

3. **Kurangi lebih lanjut ke 4:**
   - Max Children: 8 → 4
   - Monitor CPU usage

### **Aplikasi jadi sangat lambat setelah kurangi ke 6-8?**

- **Ini expected** jika ada banyak concurrent requests
- **Solusi jangka pendek:** Naikkan sedikit ke 10, tapi pastikan CPU tidak 100%
- **Solusi jangka panjang:** Optimize aplikasi untuk mengurangi CPU usage per request

---

## ✅ **ACTION ITEMS (URUTAN PRIORITAS)**

1. 🔴 **URGENT:** Kurangi Max Children dari 18 → 8 (atau 6)
2. 🔴 **URGENT:** Enable PHP-FPM slow log untuk monitoring
3. ⚠️ **IMPORTANT:** Check active HTTP connections
4. ⚠️ **IMPORTANT:** Check MySQL queries yang dipanggil berulang
5. ⚠️ **IMPORTANT:** Check aplikasi untuk infinite loop atau heavy computation
6. ✅ **MONITOR:** Check CPU usage setiap 5 menit setelah perubahan

---

## 🎯 **KESIMPULAN**

**Root Cause:** Setiap PHP-FPM process consume 50% CPU → terlalu tinggi!

**Solusi:**
1. ✅ Kurangi Max Children ke 6-8 (bukan 18)
2. ✅ Enable PHP-FPM slow log untuk monitoring
3. ✅ Check dan optimize aplikasi untuk mengurangi CPU usage per request

**Status:** 🔴 **URGENT - LAKUKAN STEP 1-2 SEKARANG!**

**Expected:** CPU usage akan turun dari 100% ke 40-60% setelah fix.

**Catatan:** Setelah CPU turun, perlu optimize aplikasi untuk mengurangi CPU usage per request agar bisa naikkan Max Children lagi.
