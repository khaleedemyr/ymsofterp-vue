# 🔍 Analisis CPU Masih Tinggi

## 📊 **Status Saat Ini**

✅ **Queue Workers:** Sudah baik (2 workers via supervisor)
✅ **Cron Jobs:** Sudah benar (hanya 1: schedule:run)
⚠️ **CPU Usage:** Masih tinggi (~95% used, 5.1% idle)
⚠️ **Load Average:** 7.00, 7.57, 13.22 (masih tinggi untuk 8 vCPU)

---

## 🔍 **ANALISIS MASALAH**

### **Masalah Utama: PHP-FPM Processes**

Dari `top` output:
- Banyak `php-fpm` processes consume CPU tinggi (20-25% each)
- Total CPU usage: 88.9% user + 4.2% system = ~93% used
- Load average: 7.00, 7.57, 13.22 (harusnya < 8.0)

**Penyebab:**
1. PHP-FPM `max_children` mungkin masih terlalu tinggi
2. Atau PHP-FPM settings belum di-apply
3. Atau ada slow queries/process yang membuat PHP-FPM processes hang

---

## ✅ **SOLUSI**

### **LANGKAH 1: Check PHP-FPM Settings**

1. **Check berapa banyak PHP-FPM processes:**
   ```bash
   ps aux | grep php-fpm | grep -v grep | wc -l
   ```

2. **Check PHP-FPM config:**
   ```bash
   # Via cPanel: MultiPHP Manager → PHP-FPM Settings
   # Atau check file config
   ```

3. **Verifikasi settings:**
   - Max Children: **24** (bukan 40+)
   - Max Requests: **100** (bukan 500+)

---

### **LANGKAH 2: Kurangi PHP-FPM Max Children**

Jika masih banyak PHP-FPM processes, kurangi `max_children`:

**Via cPanel:**
1. Login cPanel → **MultiPHP Manager**
2. Klik **PHP-FPM Settings**
3. Ubah **Max Children: 24 → 16**
4. Klik **Update**
5. Restart PHP-FPM

**Atau via terminal:**
```bash
# Edit PHP-FPM config (sesuai path di server)
nano /etc/php-fpm.d/www.conf
# atau
nano /opt/cpanel/ea-php82/root/etc/php-fpm.d/www.conf

# Ubah:
pm.max_children = 16
pm.start_servers = 8
pm.min_spare_servers = 4
pm.max_spare_servers = 8

# Restart PHP-FPM
systemctl restart php-fpm
# atau
/scripts/restartsrv_php-fpm
```

---

### **LANGKAH 3: Check Slow Queries**

PHP-FPM processes mungkin hang karena slow queries:

```bash
# Check MySQL slow queries
mysql -u root -p -e "SHOW PROCESSLIST;" | grep -i select

# Check MySQL slow query log (jika enabled)
tail -f /var/log/mysql/slow-query.log
```

**Jika ada slow queries:**
- Optimize queries
- Add indexes
- Check database performance

---

### **LANGKAH 4: Check Process yang Consume CPU**

```bash
# Top 10 processes by CPU
ps aux --sort=-%cpu | head -11

# Check apakah ada process lain yang consume CPU
top -o %CPU
```

**Jika ada process lain yang consume CPU tinggi:**
- Identifikasi process tersebut
- Check apakah perlu atau bisa di-optimize

---

### **LANGKAH 5: Monitor PHP-FPM Processes**

```bash
# Check total PHP-FPM processes
ps aux | grep php-fpm | grep -v grep | wc -l

# Check memory per process
ps aux | grep php-fpm | grep -v grep | awk '{sum+=$6} END {print sum/1024 " MB"}'

# Check CPU per process
ps aux | grep php-fpm | grep -v grep | awk '{sum+=$3} END {print sum "%"}'
```

**Expected:**
- Total processes: 12-16 (bukan 40+)
- Memory: < 2GB total
- CPU: < 50% total

---

## 📋 **CHECKLIST**

- [ ] Check PHP-FPM settings (Max Children harus 16-24)
- [ ] Check total PHP-FPM processes (harusnya 12-16, bukan 40+)
- [ ] Check slow queries di database
- [ ] Check process lain yang consume CPU
- [ ] Kurangi Max Children jika perlu (24 → 16)
- [ ] Restart PHP-FPM setelah ubah settings
- [ ] Monitor CPU usage setelah perubahan

---

## 🎯 **EXPECTED RESULTS**

Setelah optimasi PHP-FPM:

| Metric | Saat Ini | Expected |
|--------|----------|----------|
| **CPU Usage** | ~95% | 30-50% |
| **Load Average** | 7.00, 7.57, 13.22 | < 8.0 |
| **PHP-FPM Processes** | Banyak (20-25% each) | 12-16 (total) |
| **PHP-FPM Max Children** | ? | 16-24 |

---

## ⚠️ **CATATAN PENTING**

1. **PHP-FPM settings mungkin belum di-apply** - check dan apply ulang
2. **Jika masih tinggi setelah kurangi Max Children** - check slow queries
3. **Monitor selama 1-2 jam** setelah perubahan
4. **Jika masih tinggi** - mungkin ada masalah di aplikasi (slow queries, memory leak, dll)

---

## 🔍 **TROUBLESHOOTING**

### **CPU masih tinggi setelah kurangi Max Children?**

1. **Check slow queries:**
   ```bash
   mysql -u root -p -e "SHOW PROCESSLIST;" | head -20
   ```

2. **Check PHP-FPM slow log:**
   ```bash
   tail -f /var/log/php-fpm/www-slow.log
   ```

3. **Check Laravel log:**
   ```bash
   tail -f /home/ymsuperadmin/public_html/storage/logs/laravel.log
   ```

### **Banyak PHP-FPM processes hang?**

1. **Restart PHP-FPM:**
   ```bash
   systemctl restart php-fpm
   ```

2. **Kill hung processes:**
   ```bash
   # Hati-hati! Hanya jika perlu
   pkill -9 php-fpm
   systemctl start php-fpm
   ```

---

## 📚 **DOKUMENTASI TERKAIT**

- `OPTIMASI_PHP_FPM_CPU_100.md` - Optimasi PHP-FPM lengkap
- `ANALISIS_SETTING_PHP_FPM.md` - Analisis setting PHP-FPM
- `php-fpm-optimized.conf` - Config file lengkap

---

**Lakukan Langkah 1-5 untuk menurunkan CPU usage lebih lanjut!** ✅

