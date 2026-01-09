# ⚡ Quick Start: Aplikasi Lancar untuk Semua User

## 🎯 **TUJUAN**

**Semua user bisa merasakan seluruh aplikasi berjalan lancar, tidak lemot.**

---

## ⚡ **ACTION PLAN - 3 PHASE**

### **PHASE 1: Stabilkan Server (30 Menit)** 🔴 URGENT

**Tujuan:** Mencegah server crash, aplikasi masih bisa digunakan.

#### **1. Kurangi Max Children ke 14**

**Via cPanel:**
1. Login cPanel → **MultiPHP Manager**
2. Klik **PHP-FPM Settings**
3. Ubah:
   - **Max Children: 18 → 14**
   - **Start Servers: 12 → 10**
   - **Min Spare Servers: 8 → 7**
   - **Max Spare Servers: 12 → 10**
   - **Max Requests: 500 → 200**
   - **Process Idle Timeout: 10 → 5**
4. Klik **Update**
5. Restart PHP-FPM

#### **2. Monitor 30 Menit**

```bash
# Check CPU
watch -n 5 'top -bn1 | head -5'

# Check PHP-FPM processes
watch -n 5 'ps aux | grep php-fpm | grep -v grep | wc -l'
```

**Expected:**
- CPU: 100% → 85-90%
- Aplikasi: Masih bisa digunakan

---

### **PHASE 2: Optimize Aplikasi (2-4 Jam)** ⚠️ PRIORITAS UTAMA

**Tujuan:** Kurangi CPU usage per request dari 50% → 5-10%

#### **1. Enable PHP-FPM Slow Log**

```bash
# Edit PHP-FPM config
nano /opt/cpanel/ea-php82/root/etc/php-fpm.d/ymsofterp.com.conf

# Tambahkan:
slowlog = /opt/cpanel/ea-php82/root/var/log/php-fpm-slow.log
request_slowlog_timeout = 2s

# Create log directory
mkdir -p /opt/cpanel/ea-php82/root/var/log
chown ymsuperadmin:ymsuperadmin /opt/cpanel/ea-php82/root/var/log

# Restart
systemctl restart ea-php82-php-fpm

# Monitor
tail -f /opt/cpanel/ea-php82/root/var/log/php-fpm-slow.log
```

#### **2. Enable Caching (Redis)**

```bash
# Install Redis (via cPanel atau command line)

# Update .env
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

**Cache master data di Controller:**
```php
// Before
$outlets = Outlet::all();

// After
$outlets = Cache::remember('outlets', 3600, function () {
    return Outlet::all();
});
```

#### **3. Fix N+1 Queries**

```php
// Before
$users = User::all();
foreach ($users as $user) {
    echo $user->outlet->name; // Query setiap loop!
}

// After
$users = User::with('outlet')->get();
foreach ($users as $user) {
    echo $user->outlet->name; // No additional query!
}
```

#### **4. Enable OPcache**

**Edit php.ini:**
```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0
```

**Restart PHP-FPM.**

---

### **PHASE 3: Monitor dan Naikkan Max Children (Ongoing)** ✅

**Setelah CPU per process turun ke 5-10%:**

1. **Naikkan Max Children: 14 → 16**
2. **Monitor 30 menit**
3. **Jika CPU masih < 80%:** Naikkan ke 18-20

**Expected:**
- CPU per process: 5-10%
- Total CPU: 50-70%
- Response time: < 2 detik
- **Aplikasi lancar untuk semua user!** ✅

---

## 📊 **EXPECTED RESULTS**

| Phase | Max Children | CPU/Process | Total CPU | Response Time | Status |
|-------|-------------|-------------|-----------|---------------|--------|
| **Before** | 18 | 50% | 100% | 5-10 detik | 🔴 LEMOT |
| **Phase 1** | 14 | 50% | 85-90% | 2-5 detik | ⚠️ MASIH BISA |
| **Phase 2** | 14 | 5-10% | 40-60% | < 2 detik | ✅ LANCAR |
| **Phase 3** | 16-20 | 5-10% | 50-70% | < 2 detik | ✅ **SANGAT LANCAR** |

---

## ✅ **CHECKLIST**

### **URGENT (30 Menit):**
- [ ] Kurangi Max Children: 18 → 14
- [ ] Monitor CPU dan response time

### **IMPORTANT (2-4 Jam):**
- [ ] Enable PHP-FPM slow log
- [ ] Enable caching (Redis)
- [ ] Fix N+1 queries
- [ ] Enable OPcache

### **ONGOING:**
- [ ] Monitor CPU per process (target: 5-10%)
- [ ] Monitor response time (target: < 2 detik)
- [ ] Naikkan Max Children kembali (setelah optimize)

---

## 🎯 **KESIMPULAN**

**Untuk aplikasi lancar:**
1. ✅ Kurangi Max Children secara minimal (14) → Mencegah crash
2. ✅ **Optimize aplikasi** (prioritas utama!) → Kurangi CPU usage
3. ✅ Setelah optimize: Naikkan Max Children kembali (16-20)

**FOKUS PADA OPTIMIZE APLIKASI, bukan hanya kurangi Max Children!**
