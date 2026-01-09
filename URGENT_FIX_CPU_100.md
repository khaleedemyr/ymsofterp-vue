# 🔥 URGENT: Fix CPU 100% - PHP-FPM Processes Consume 32-33% CPU Each

## 🎯 **MASALAH KRITIS**

**Dari diagnosis:**
- 🔴 **Setiap PHP-FPM process consume 32-33% CPU** - ABNORMAL!
- 🔴 **Load Average: 22.58, 22.73, 22.94** - SANGAT TINGGI!
- 🔴 **26 PHP-FPM processes** - terlalu banyak
- ✅ Queue Workers: 2 (OK)

**Kesimpulan:** PHP-FPM processes sedang menjalankan query berat atau HANG!

---

## ⚡ **ACTION URGENT - LAKUKAN SEKARANG!**

### **STEP 1: Check MySQL Running Queries** (PRIORITAS TINGGI!)

**Kemungkinan besar ada query yang berjalan lama dan membuat PHP-FPM processes hang.**

```bash
# Check semua running queries
mysql -u root -p -e "SHOW PROCESSLIST;" | head -20

# Check query yang berjalan > 5 detik
mysql -u root -p -e "
SELECT 
    id,
    user,
    host,
    db,
    command,
    time,
    state,
    LEFT(info, 100) as query_preview
FROM information_schema.processlist 
WHERE command != 'Sleep' 
AND time > 5
ORDER BY time DESC;
"
```

**Jika ada query yang berjalan > 30 detik:**
```bash
# Kill query yang berjalan lama (HATI-HATI!)
mysql -u root -p -e "
SELECT CONCAT('KILL ', id, ';') 
FROM information_schema.processlist 
WHERE command != 'Sleep' 
AND time > 30 
AND user != 'system user';
" | grep KILL | mysql -u root -p
```

---

### **STEP 2: Restart PHP-FPM** (URGENT!)

**Restart untuk kill hung processes:**

```bash
# Restart PHP-FPM
systemctl restart ea-php82-php-fpm
# atau
/scripts/restartsrv_php-fpm

# Check setelah restart
ps aux | grep php-fpm | grep -v grep | wc -l
ps aux | grep php-fpm | grep -v grep | sort -k3 -rn | head -5
```

**Expected setelah restart:**
- PHP-FPM processes: **12-16** (bukan 26+)
- CPU per process: **< 5%** (bukan 32%!)
- Load Average: **< 8.0** (bukan 22+)

---

### **STEP 3: Kurangi PHP-FPM Max Children**

**Via cPanel:**
1. Login cPanel → **MultiPHP Manager**
2. Klik **PHP-FPM Settings**
3. Ubah:
   - **Max Children: 26 → 16** (atau bahkan 12)
   - **Max Requests: 500 → 50** (lebih agresif)
   - **Process Idle Timeout: 10 → 5** (kill idle lebih cepat)
4. Klik **Update**
5. Restart PHP-FPM

**Rumus untuk 8 vCPU:**
- **Konservatif: vCPU × 2 = 8 × 2 = 16**
- **Atau lebih aman: vCPU × 1.5 = 8 × 1.5 = 12**

---

### **STEP 4: Enable PHP-FPM Slow Log** (Untuk Monitoring)

**Enable slow log untuk monitoring:**

1. **Check config file:**
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
   request_slowlog_timeout = 5s
   ```

4. **Create log directory (jika belum ada):**
   ```bash
   mkdir -p /opt/cpanel/ea-php82/root/var/log
   chown ymsuperadmin:ymsuperadmin /opt/cpanel/ea-php82/root/var/log
   ```

5. **Restart PHP-FPM:**
   ```bash
   systemctl restart ea-php82-php-fpm
   ```

6. **Monitor:**
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

## ⚠️ **KENAPA PHP-FPM PROCESS CONSUME 32% CPU?**

**Normal:** PHP-FPM process consume < 5% CPU  
**Abnormal:** 32% CPU = process sedang:
1. ⚠️ Menjalankan query yang sangat berat
2. ⚠️ Hang karena query timeout
3. ⚠️ Infinite loop di aplikasi
4. ⚠️ Memory leak atau process stuck

**Solusi:**
1. ✅ Check dan kill slow queries di MySQL
2. ✅ Restart PHP-FPM untuk kill hung processes
3. ✅ Kurangi Max Children untuk limit concurrent processes
4. ✅ Enable slow log untuk monitoring

---

## 🔧 **TROUBLESHOOTING**

### **Setelah restart PHP-FPM, CPU masih tinggi?**

1. **Check MySQL queries lagi:**
   ```bash
   mysql -u root -p -e "SHOW PROCESSLIST;" | grep -E "Query|Time"
   ```

2. **Kill semua hung PHP-FPM processes:**
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

## ✅ **ACTION ITEMS (URUTAN PRIORITAS)**

1. 🔴 **URGENT:** Check MySQL running queries - kill yang berjalan lama
2. 🔴 **URGENT:** Restart PHP-FPM - untuk kill hung processes
3. ⚠️ **IMPORTANT:** Kurangi Max Children ke 16 (atau 12)
4. ⚠️ **IMPORTANT:** Enable PHP-FPM slow log untuk monitoring
5. ✅ **MONITOR:** Check CPU usage setiap 5 menit setelah perubahan

---

## 🎯 **KESIMPULAN**

**Root Cause:** PHP-FPM processes hang karena query yang berjalan lama di MySQL.

**Solusi:**
1. ✅ Kill slow queries di MySQL
2. ✅ Restart PHP-FPM
3. ✅ Kurangi Max Children
4. ✅ Enable slow log

**Status:** 🔴 **URGENT - LAKUKAN STEP 1-3 SEKARANG!**

**Expected:** CPU usage akan turun dari 100% ke 30-50% setelah fix.
