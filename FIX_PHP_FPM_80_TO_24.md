# 🚨 FIX: PHP-FPM Max Children 80 → 24

## ⚠️ **MASALAH**

Max Children saat ini: **80** (TERLALU TINGGI!)
- Akan menyebabkan CPU overload
- Context switching berlebihan
- Response time lambat

**Rekomendasi untuk 8 vCPU + 16GB RAM: 24**

---

## ✅ **SOLUSI: Turunkan Max Children ke 24**

### **LANGKAH 1: Ubah di cPanel**

1. Login ke **cPanel**
2. Buka **MultiPHP Manager** atau **Select PHP Version**
3. Klik **PHP-FPM Settings** atau **Configure PHP-FPM**
4. Ubah setting berikut:

| Setting | Dari | Ke | Alasan |
|---------|------|-----|--------|
| **Max Children** | 80 | **24** | Optimal untuk 8 vCPU |
| **Max Requests** | 200 | **100** | Prevent memory leak |
| **Process Idle Timeout** | 30 | **10** | Standard optimal |

5. Klik **Update**
6. **Restart PHP-FPM** (biasanya otomatis, atau via terminal)

---

## 🔧 **LANGKAH 2: Set Start Servers, Min Spare, Max Spare (Jika Tidak Terlihat di cPanel)**

Jika di cPanel tidak ada setting untuk Start Servers, Min Spare, Max Spare, bisa di-set via terminal:

### **A. Cari File Config PHP-FPM**

```bash
# Check cPanel PHP-FPM config
find /opt/cpanel/ea-php82/root/etc/php-fpm.d -name "*.conf" | head -1

# Atau standard location
ls -la /etc/php-fpm.d/www.conf
ls -la /etc/php/8.2/fpm/pool.d/www.conf
```

### **B. Edit Config File**

```bash
# Edit config file (sesuai path yang ditemukan)
nano /opt/cpanel/ea-php82/root/etc/php-fpm.d/[domain].conf
# atau
nano /etc/php-fpm.d/www.conf
```

### **C. Tambahkan/Edit Setting**

Cari atau tambahkan baris berikut:

```ini
pm = dynamic
pm.max_children = 24
pm.start_servers = 12
pm.min_spare_servers = 8
pm.max_spare_servers = 12
pm.max_requests = 100
pm.process_idle_timeout = 10s
```

**Penjelasan:**
- `pm.max_children = 24` → Maksimal 24 processes
- `pm.start_servers = 12` → Start dengan 12 processes (50% dari max)
- `pm.min_spare_servers = 8` → Minimal 8 processes idle (30% dari max)
- `pm.max_spare_servers = 12` → Maksimal 12 processes idle (50% dari max)
- `pm.max_requests = 100` → Restart setiap 100 requests (prevent memory leak)

### **D. Restart PHP-FPM**

```bash
# Restart PHP-FPM
systemctl restart php-fpm
# atau
/scripts/restartsrv_php-fpm
```

---

## 🔍 **LANGKAH 3: Verifikasi**

### **Check PHP-FPM Processes**

```bash
# Check total processes
ps aux | grep php-fpm | grep -v grep | wc -l
# Expected: 12-24 (bukan 80!)
```

### **Check CPU Usage**

```bash
top
# Expected: CPU usage turun ke 30-50% (bukan 100%)
```

### **Check Config**

```bash
# Check apakah setting sudah ter-apply
ps aux | grep php-fpm | head -5
# Atau check config file
grep -E "pm.max_children|pm.start_servers" /opt/cpanel/ea-php82/root/etc/php-fpm.d/*.conf
```

---

## 📊 **EXPECTED RESULTS**

Setelah fix:

| Metric | Sebelum (80) | Sesudah (24) |
|--------|--------------|--------------|
| **PHP-FPM Processes** | 80+ | 12-24 |
| **CPU Usage** | 100% | 30-50% |
| **Response Time** | Lambat (500-1000ms) | Cepat (200-300ms) |
| **Load Average** | > 8.0 | < 8.0 |

---

## ⚠️ **CATATAN PENTING**

1. **Jangan langsung turun ke 16** - untuk ratusan user, 24 lebih aman
2. **Monitor selama 1-2 jam** setelah perubahan
3. **Jika masih lemot setelah 24**, check:
   - Slow queries di database
   - Queue workers (harusnya hanya 2 via supervisor)
   - Memory usage
4. **Jika CPU masih tinggi**, mungkin ada masalah lain (bukan hanya PHP-FPM)

---

## 🔧 **TROUBLESHOOTING**

### **Setting Tidak Ter-Apply?**

1. **Check apakah config file benar:**
   ```bash
   cat /opt/cpanel/ea-php82/root/etc/php-fpm.d/[domain].conf | grep pm.max_children
   ```

2. **Restart PHP-FPM lagi:**
   ```bash
   systemctl restart php-fpm
   ```

3. **Check cPanel PHP-FPM Settings** - mungkin override config file

### **Aplikasi Jadi Lambat Setelah Turun ke 24?**

1. **Naikkan sedikit ke 28** (maksimal untuk 8 vCPU)
2. **Check apakah ada slow queries**
3. **Check memory usage** - mungkin perlu upgrade RAM

---

## 📋 **CHECKLIST**

- [ ] Ubah Max Children: 80 → 24 di cPanel
- [ ] Ubah Max Requests: 200 → 100
- [ ] Set Start Servers: 12 (via config file jika tidak ada di cPanel)
- [ ] Set Min Spare: 8 (via config file jika tidak ada di cPanel)
- [ ] Set Max Spare: 12 (via config file jika tidak ada di cPanel)
- [ ] Restart PHP-FPM
- [ ] Verifikasi processes: 12-24 (bukan 80)
- [ ] Monitor CPU usage: harusnya turun ke 30-50%
- [ ] Monitor selama 1-2 jam

---

**Lakukan sekarang! Max Children 80 terlalu tinggi dan menyebabkan CPU overload!** ⚠️
