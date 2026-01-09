# 🔥 Fix CPU 100% - Masih Tinggi Meski Query Sudah Cepat

## 🎯 **MASALAH**

**User report:** "skrg web dan app ga lemot tapi cpu msh 100% knp ya"

**Status:**
- ✅ Query sudah cepat (tidak ada slow queries)
- ✅ Web dan app sudah tidak lemot
- ❌ **CPU masih 100%**

**Kesimpulan:** Masalah bukan di query, tapi di **resource management** (PHP-FPM, Queue Workers, dll)

---

## 🔍 **PENYEBAB CPU 100%**

Kemungkinan penyebab CPU masih 100% meski query sudah cepat:

### **1. PHP-FPM Max Children Terlalu Tinggi** ⚠️

**Masalah:**
- PHP-FPM `max_children` masih terlalu tinggi (mungkin 40+)
- Setiap PHP-FPM process consume CPU
- Total processes terlalu banyak → CPU 100%

**Check:**
```bash
# Check berapa banyak PHP-FPM processes
ps aux | grep php-fpm | grep -v grep | wc -l

# Check CPU usage per process
ps aux | grep php-fpm | grep -v grep | awk '{sum+=$3} END {print sum "%"}'
```

**Expected:**
- Total processes: **12-20** (bukan 30+)
- Total CPU: **< 50%** (bukan 100%)

---

### **2. Queue Workers Terlalu Banyak** ⚠️

**Masalah:**
- Queue worker berjalan terlalu banyak (mungkin 10+)
- Setiap worker consume CPU
- Total workers terlalu banyak → CPU 100%

**Check:**
```bash
# Check berapa banyak queue workers
ps aux | grep 'queue:work' | grep -v grep | wc -l

# Check CPU usage
ps aux | grep 'queue:work' | grep -v grep | awk '{sum+=$3} END {print sum "%"}'
```

**Expected:**
- Total workers: **2** (via Supervisor)
- Total CPU: **< 10%**

---

### **3. Background Processes Lain** ⚠️

**Masalah:**
- Cron jobs yang berat
- Scheduled tasks yang berjalan terlalu sering
- Process lain yang consume CPU tinggi

**Check:**
```bash
# Top 10 processes by CPU
ps aux --sort=-%cpu | head -11

# Check system load
uptime
```

---

## ✅ **SOLUSI**

### **LANGKAH 1: Check Status Server**

Jalankan script untuk check:
```bash
chmod +x check-cpu-100-causes.sh
./check-cpu-100-causes.sh
```

Atau manual:
```bash
# Check PHP-FPM processes
ps aux | grep php-fpm | grep -v grep | wc -l

# Check Queue Workers
ps aux | grep 'queue:work' | grep -v grep | wc -l

# Check top processes
ps aux --sort=-%cpu | head -11

# Check system load
uptime
```

---

### **LANGKAH 2: Fix PHP-FPM Max Children**

**Jika PHP-FPM processes > 30:**

1. **Via cPanel:**
   - Login cPanel → **MultiPHP Manager**
   - Klik **PHP-FPM Settings**
   - Ubah **Max Children: 40 → 20** (atau 24)
   - Ubah **Max Requests: 500 → 100**
   - Klik **Update**
   - Restart PHP-FPM

2. **Via Terminal:**
   ```bash
   # Edit config (sesuai path di server)
   nano /opt/cpanel/ea-php82/root/etc/php-fpm.d/www.conf
   
   # Ubah:
   pm.max_children = 20
   pm.start_servers = 10
   pm.min_spare_servers = 5
   pm.max_spare_servers = 10
   pm.max_requests = 100
   
   # Restart PHP-FPM
   systemctl restart ea-php82-php-fpm
   # atau
   /scripts/restartsrv_php-fpm
   ```

**Rumus Max Children:**
- Untuk 8 vCPU: **(vCPU × 2) + 4 = (8 × 2) + 4 = 20**
- Atau lebih konservatif: **vCPU × 2 = 8 × 2 = 16**

---

### **LANGKAH 3: Fix Queue Workers**

**Jika Queue Workers > 5:**

1. **Check cron jobs:**
   ```bash
   crontab -l | grep queue:work
   ```

2. **Hapus cron job queue worker yang berjalan setiap menit**

3. **Gunakan Supervisor** (RECOMMENDED):
   ```bash
   # Buat config: /etc/supervisor/conf.d/ymsofterp-queue.conf
   [program:ymsofterp-queue-worker]
   process_name=%(program_name)s_%(process_num)02d
   command=php /home/ymsuperadmin/public_html/artisan queue:work --queue=notifications --tries=3 --timeout=300 --sleep=3 --max-jobs=1000
   autostart=true
   autorestart=true
   stopasgroup=true
   killasgroup=true
   user=ymsuperadmin
   numprocs=2
   redirect_stderr=true
   stdout_logfile=/home/ymsuperadmin/public_html/storage/logs/queue-worker.log
   stopwaitsecs=3600
   
   # Aktifkan
   supervisorctl reread
   supervisorctl update
   supervisorctl start ymsofterp-queue-worker:*
   ```

4. **Kill queue workers yang berjalan:**
   ```bash
   # Hati-hati! Hanya jika perlu
   pkill -f 'queue:work'
   ```

---

### **LANGKAH 4: Check Background Processes**

**Check apakah ada process lain yang consume CPU tinggi:**
```bash
# Top 10 processes by CPU
ps aux --sort=-%cpu | head -11

# Check cron jobs
crontab -l

# Check scheduled tasks
php artisan schedule:list
```

**Jika ada process yang consume CPU tinggi:**
- Identifikasi process tersebut
- Check apakah perlu atau bisa di-optimize
- Pertimbangkan untuk disable atau kurangi frequency

---

### **LANGKAH 5: Monitor Setelah Perubahan**

**Setelah fix PHP-FPM dan Queue Workers:**
```bash
# Monitor CPU usage
top

# Monitor PHP-FPM processes
watch -n 1 'ps aux | grep php-fpm | grep -v grep | wc -l'

# Monitor Queue Workers
watch -n 1 'ps aux | grep queue:work | grep -v grep | wc -l'
```

**Expected Results:**
- CPU usage: **30-50%** (bukan 100%)
- PHP-FPM processes: **12-20** (bukan 30+)
- Queue Workers: **2** (bukan 10+)
- Load Average: **< 8.0** (untuk 8 vCPU)

---

## 📊 **DIAGNOSIS CHECKLIST**

Jalankan command berikut untuk diagnosis:

```bash
# 1. Check PHP-FPM
echo "=== PHP-FPM ==="
ps aux | grep php-fpm | grep -v grep | wc -l
ps aux | grep php-fpm | grep -v grep | awk '{sum+=$3} END {print "CPU: " sum "%"}'

# 2. Check Queue Workers
echo "=== Queue Workers ==="
ps aux | grep 'queue:work' | grep -v grep | wc -l
ps aux | grep 'queue:work' | grep -v grep | awk '{sum+=$3} END {print "CPU: " sum "%"}'

# 3. Check Top Processes
echo "=== Top 5 Processes ==="
ps aux --sort=-%cpu | head -6

# 4. Check System Load
echo "=== System Load ==="
uptime

# 5. Check MySQL
echo "=== MySQL ==="
mysql -u root -p -e "SHOW PROCESSLIST;" | head -10
```

---

## ⚠️ **CATATAN PENTING**

1. **PHP-FPM Max Children:**
   - Jangan terlalu tinggi (max 24 untuk 8 vCPU)
   - Setiap process consume memory dan CPU
   - Terlalu banyak = CPU 100%

2. **Queue Workers:**
   - Hanya perlu 2 workers (via Supervisor)
   - Jangan jalankan via cron setiap menit
   - Setiap worker consume CPU

3. **Monitor:**
   - Check CPU usage setiap 5-10 menit setelah perubahan
   - Pastikan tidak ada process yang hang
   - Restart PHP-FPM jika perlu

---

## 🔧 **TROUBLESHOOTING**

### **CPU masih 100% setelah kurangi Max Children?**

1. **Check apakah ada process lain:**
   ```bash
   ps aux --sort=-%cpu | head -11
   ```

2. **Check MySQL:**
   ```bash
   mysql -u root -p -e "SHOW PROCESSLIST;"
   ```

3. **Check PHP-FPM slow log:**
   ```bash
   tail -f /var/log/php-fpm/www-slow.log
   ```

4. **Kurangi Max Children lebih lanjut:**
   - Dari 20 → 16
   - Monitor CPU usage

### **Aplikasi jadi lambat setelah kurangi Max Children?**

- Naikkan sedikit ke 24
- Monitor memory usage
- Pastikan tidak ada memory leak

---

## ✅ **KESIMPULAN**

**Root Cause:** CPU 100% bukan karena slow queries, tapi karena:
1. ⚠️ PHP-FPM Max Children terlalu tinggi
2. ⚠️ Queue Workers terlalu banyak
3. ⚠️ Background processes lain

**Solusi:**
1. ✅ Kurangi PHP-FPM Max Children ke 20-24
2. ✅ Fix Queue Workers (hanya 2 via Supervisor)
3. ✅ Monitor dan optimize background processes

**Status:** ⏳ **TUNGGU DIAGNOSIS - Jalankan script check-cpu-100-causes.sh**
