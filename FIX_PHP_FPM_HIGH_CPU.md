# 🔥 FIX: PHP-FPM Processes Consume 32-33% CPU Each

## 🎯 **MASALAH DITEMUKAN**

**Dari diagnosis:**
- ✅ Queue Workers: 2 (OK)
- ⚠️ PHP-FPM Processes: 26 (bisa dikurangi)
- 🔴 **MASALAH UTAMA:** Setiap PHP-FPM process consume **32-33% CPU**!
- 🔴 Load Average: **22.58, 22.73, 22.94** (SANGAT TINGGI!)

**Kesimpulan:** PHP-FPM processes sedang menjalankan query berat atau hang!

---

## 🔍 **DIAGNOSIS LEBIH LANJUT**

### **1. Check Apakah Ada PHP-FPM Processes yang Hang**

```bash
# Check PHP-FPM processes yang consume CPU tinggi
ps aux | grep php-fpm | grep -v grep | sort -k3 -rn | head -10

# Check apakah ada process yang berjalan terlalu lama
ps aux | grep php-fpm | grep -v grep | awk '{if ($10 > 5) print $0}'
# (Process yang berjalan > 5 menit mungkin hang)
```

### **2. Check MySQL Running Queries**

```bash
# Check apakah ada query yang berjalan lama
mysql -u root -p -e "SHOW PROCESSLIST;" | grep -E "Query|Time" | head -20

# Check query yang berjalan > 5 detik
mysql -u root -p -e "SHOW PROCESSLIST;" | awk '$6 > 5 {print $0}'
```

### **3. Check PHP-FPM Slow Log**

```bash
# Check PHP-FPM slow log (jika ada)
tail -50 /var/log/php-fpm/www-slow.log
# atau
tail -50 /opt/cpanel/ea-php82/root/var/log/php-fpm-slow.log
```

---

## ✅ **SOLUSI URGENT**

### **LANGKAH 1: Restart PHP-FPM (Kill Hung Processes)**

**Jika ada PHP-FPM processes yang hang:**

```bash
# Restart PHP-FPM untuk kill hung processes
systemctl restart ea-php82-php-fpm
# atau
/scripts/restartsrv_php-fpm

# Check setelah restart
ps aux | grep php-fpm | grep -v grep | wc -l
```

**Expected:**
- PHP-FPM processes: **12-20** (bukan 26+)
- CPU per process: **< 5%** (bukan 32%!)

---

### **LANGKAH 2: Kurangi PHP-FPM Max Children**

**Via cPanel:**
1. Login cPanel → **MultiPHP Manager**
2. Klik **PHP-FPM Settings**
3. Ubah:
   - **Max Children: 26 → 16** (atau 20)
   - **Max Requests: 500 → 50** (lebih agresif untuk kill hung processes)
   - **Process Idle Timeout: 10 → 5** (kill idle processes lebih cepat)
4. Klik **Update**
5. Restart PHP-FPM

**Rumus untuk 8 vCPU:**
- Konservatif: **vCPU × 2 = 8 × 2 = 16**
- Atau: **(vCPU × 2) + 4 = 20**

---

### **LANGKAH 3: Check MySQL Running Queries**

**Jika ada query yang berjalan lama:**

```bash
# Kill query yang berjalan > 30 detik
mysql -u root -p -e "
SELECT CONCAT('KILL ', id, ';') 
FROM information_schema.processlist 
WHERE command != 'Sleep' 
AND time > 30 
AND user != 'system user';
" | grep KILL | mysql -u root -p
```

**Atau check manual:**
```bash
mysql -u root -p -e "SHOW PROCESSLIST;" | head -20
```

---

### **LANGKAH 4: Enable PHP-FPM Slow Log**

**Untuk monitoring:**

1. **Edit PHP-FPM config:**
   ```bash
   nano /opt/cpanel/ea-php82/root/etc/php-fpm.d/www.conf
   ```

2. **Tambahkan:**
   ```ini
   ; Slow log
   slowlog = /opt/cpanel/ea-php82/root/var/log/php-fpm-slow.log
   request_slowlog_timeout = 5s
   ```

3. **Restart PHP-FPM:**
   ```bash
   systemctl restart ea-php82-php-fpm
   ```

4. **Monitor:**
   ```bash
   tail -f /opt/cpanel/ea-php82/root/var/log/php-fpm-slow.log
   ```

---

## 📊 **EXPECTED RESULTS**

| Metric | Saat Ini | Expected |
|--------|----------|----------|
| **CPU per PHP-FPM process** | 32-33% | < 5% |
| **Total PHP-FPM processes** | 26 | 12-16 |
| **Load Average** | 22+ | < 8.0 |
| **Total CPU Usage** | 100% | 30-50% |

---

## ⚠️ **CATATAN PENTING**

1. **PHP-FPM process consume 32% CPU = ABNORMAL!**
   - Normal: < 5% per process
   - 32% = process sedang menjalankan query berat atau hang

2. **Load Average 22+ = SANGAT TINGGI!**
   - Untuk 8 vCPU, load average harus < 8.0
   - 22+ = sistem sangat overloaded

3. **Solusi:**
   - Restart PHP-FPM untuk kill hung processes
   - Kurangi Max Children ke 16
   - Check dan kill slow queries di MySQL

---

## 🔧 **TROUBLESHOOTING**

### **Setelah restart PHP-FPM, CPU masih tinggi?**

1. **Check apakah ada query yang berjalan lama:**
   ```bash
   mysql -u root -p -e "SHOW PROCESSLIST;" | grep -E "Query|Time"
   ```

2. **Kill hung PHP-FPM processes:**
   ```bash
   # Hati-hati! Hanya jika perlu
   pkill -9 php-fpm
   systemctl start ea-php82-php-fpm
   ```

3. **Kurangi Max Children lebih lanjut:**
   - Dari 16 → 12
   - Monitor CPU usage

### **Aplikasi jadi lambat setelah kurangi Max Children?**

- Naikkan sedikit ke 20
- Tapi pastikan tidak ada hung processes
- Monitor memory usage

---

## ✅ **ACTION ITEMS**

1. ✅ **Restart PHP-FPM** - untuk kill hung processes
2. ✅ **Kurangi Max Children ke 16** - via cPanel
3. ✅ **Check MySQL running queries** - kill yang berjalan lama
4. ✅ **Monitor CPU usage** - check setiap 5 menit setelah perubahan

**Status:** 🔴 **URGENT - Restart PHP-FPM dan kurangi Max Children SEKARANG!**
