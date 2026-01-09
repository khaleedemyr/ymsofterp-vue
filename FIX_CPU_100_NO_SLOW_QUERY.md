# 🔥 Fix CPU 100% - Bukan Karena Slow Query

## ✅ **HASIL DIAGNOSIS**

**MySQL Process List:**
- ✅ **TIDAK ADA** query yang berjalan > 5 detik
- ✅ Semua query aktif berjalan cepat (Time: 0-2 detik)
- ✅ Ada banyak koneksi idle (normal)

**Kesimpulan:** Masalah CPU 100% **BUKAN** karena slow query yang berjalan lama!

---

## 🎯 **ROOT CAUSE**

**Masalah sebenarnya:**
1. 🔴 **Terlalu banyak PHP-FPM processes** (26 processes)
2. 🔴 **Max Children terlalu tinggi** (26 untuk 8 vCPU)
3. 🔴 **Banyak concurrent requests** yang membuat semua processes aktif
4. 🔴 **Setiap request memakan CPU tinggi** meskipun query cepat

**Rumus yang benar untuk 8 vCPU:**
- **Max Children = vCPU × 2 = 8 × 2 = 16** (maksimal)
- **Atau lebih aman: vCPU × 1.5 = 8 × 1.5 = 12**

**Saat ini:** Max Children = 26 → **TERLALU TINGGI!**

---

## ⚡ **SOLUSI - LAKUKAN SEKARANG!**

### **STEP 1: Restart PHP-FPM** (URGENT!)

**Restart untuk kill hung processes dan reset state:**

```bash
# Restart PHP-FPM
systemctl restart ea-php82-php-fpm
# atau
/scripts/restartsrv_php-fpm

# Check setelah restart
ps aux | grep php-fpm | grep -v grep | wc -l
ps aux | grep php-fpm | grep -v grep | sort -k3 -rn | head -5
top -bn1 | head -20
```

**Expected setelah restart:**
- PHP-FPM processes: **12-16** (bukan 26+)
- CPU per process: **< 5%** (bukan 32%!)
- Load Average: **< 8.0** (bukan 22+)

---

### **STEP 2: Kurangi PHP-FPM Max Children** (PRIORITAS TINGGI!)

**Via cPanel:**
1. Login cPanel → **MultiPHP Manager**
2. Klik **PHP-FPM Settings**
3. Ubah settings:
   - **Max Children: 26 → 16** (atau bahkan 12)
   - **Start Servers: 12 → 8** (atau 6)
   - **Min Spare Servers: 8 → 6** (atau 4)
   - **Max Spare Servers: 12 → 8** (atau 6)
   - **Max Requests: 500 → 100** (lebih agresif untuk recycle)
   - **Process Idle Timeout: 10 → 5** (kill idle lebih cepat)
4. Klik **Update**
5. Restart PHP-FPM

**Atau via command line (jika tahu config file):**
```bash
# Find config file
find /opt/cpanel/ea-php82/root/etc/php-fpm.d -name "*.conf" | head -1

# Edit config (contoh untuk ymsofterp.com)
nano /opt/cpanel/ea-php82/root/etc/php-fpm.d/ymsofterp.com.conf

# Ubah:
pm.max_children = 16
pm.start_servers = 8
pm.min_spare_servers = 6
pm.max_spare_servers = 8
pm.max_requests = 100
pm.process_idle_timeout = 5s

# Restart
systemctl restart ea-php82-php-fpm
```

---

### **STEP 3: Monitor Setelah Perubahan**

**Check setiap 5 menit:**

```bash
# Check PHP-FPM processes
ps aux | grep php-fpm | grep -v grep | wc -l

# Check CPU usage per process
ps aux | grep php-fpm | grep -v grep | sort -k3 -rn | head -5

# Check load average
uptime

# Check total CPU usage
top -bn1 | head -5
```

**Expected:**
- ✅ PHP-FPM processes: **12-16** (bukan 26+)
- ✅ CPU per process: **< 5%** (bukan 32%!)
- ✅ Load Average: **< 8.0** (bukan 22+)
- ✅ Total CPU Usage: **30-50%** (bukan 100%)

---

## 📊 **BEFORE vs AFTER**

| Metric | BEFORE | AFTER (Expected) |
|--------|--------|-------------------|
| **Max Children** | 26 | 16 (atau 12) |
| **PHP-FPM Processes** | 26+ | 12-16 |
| **CPU per Process** | 32-33% | < 5% |
| **Load Average** | 22+ | < 8.0 |
| **Total CPU Usage** | 100% | 30-50% |

---

## ⚠️ **KENAPA MAX CHILDREN 26 TERLALU TINGGI?**

**Rumus yang benar:**
- **Max Children = vCPU × 2** (untuk PHP-FPM)
- **8 vCPU × 2 = 16** (maksimal)
- **Lebih aman: 8 vCPU × 1.5 = 12**

**Dengan Max Children 26:**
- 26 processes × 32% CPU = **832% CPU usage** (impossible!)
- Server hanya punya 8 vCPU = **800% max** (100% × 8)
- **Result:** CPU 100%, semua processes compete untuk CPU
- **Load Average:** 22+ (sangat tinggi!)

**Dengan Max Children 16:**
- 16 processes × 5% CPU = **80% CPU usage** (normal)
- Masih ada buffer untuk MySQL, system, dll
- **Load Average:** < 8.0 (normal untuk 8 vCPU)

---

## 🔧 **TROUBLESHOOTING**

### **Setelah restart dan kurangi Max Children, CPU masih tinggi?**

1. **Check apakah ada banyak concurrent requests:**
   ```bash
   # Check active connections
   netstat -an | grep :80 | grep ESTABLISHED | wc -l
   netstat -an | grep :443 | grep ESTABLISHED | wc -l
   ```

2. **Check apakah ada banyak PHP-FPM processes lagi:**
   ```bash
   ps aux | grep php-fpm | grep -v grep | wc -l
   ```

3. **Kurangi Max Children lebih lanjut:**
   - Dari 16 → 12
   - Monitor CPU usage

### **Aplikasi jadi lambat setelah kurangi Max Children?**

- **Ini normal** jika ada banyak concurrent requests
- **Solusi:** Naikkan sedikit ke 20, tapi pastikan CPU tidak 100%
- **Atau:** Optimize aplikasi untuk mengurangi CPU usage per request

---

## ✅ **ACTION ITEMS (URUTAN PRIORITAS)**

1. 🔴 **URGENT:** Restart PHP-FPM - untuk kill hung processes
2. 🔴 **URGENT:** Kurangi Max Children dari 26 → 16 (atau 12)
3. ⚠️ **IMPORTANT:** Adjust Start Servers, Min/Max Spare Servers
4. ⚠️ **IMPORTANT:** Set Max Requests ke 100 (lebih agresif)
5. ✅ **MONITOR:** Check CPU usage setiap 5 menit setelah perubahan

---

## 🎯 **KESIMPULAN**

**Root Cause:** Max Children terlalu tinggi (26 untuk 8 vCPU) → terlalu banyak concurrent processes → CPU 100%

**Solusi:**
1. ✅ Restart PHP-FPM
2. ✅ Kurangi Max Children ke 16 (atau 12)
3. ✅ Adjust PHP-FPM settings lainnya
4. ✅ Monitor setelah perubahan

**Status:** 🔴 **URGENT - LAKUKAN STEP 1-2 SEKARANG!**

**Expected:** CPU usage akan turun dari 100% ke 30-50% setelah fix.

---

## 📝 **NOTES**

- **TIDAK ADA** slow query yang berjalan lama di MySQL
- Masalah murni karena **terlalu banyak PHP-FPM processes**
- Dengan Max Children 16, server masih bisa handle ratusan concurrent users
- Jika masih lambat, perlu optimize aplikasi (bukan server config)
