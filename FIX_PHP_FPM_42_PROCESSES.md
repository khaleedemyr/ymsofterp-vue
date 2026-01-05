# 🚨 Fix PHP-FPM: 42 Processes (Terlalu Banyak!)

## 🚨 **MASALAH DITEMUKAN**

**Total PHP-FPM processes: 42** ❌

**Masalah:**
- Terlalu banyak untuk server 8 vCPU
- Seharusnya hanya 12-24 processes
- Setiap process consume 20-25% CPU
- Total: 42 × 20% = ~840% CPU (overload!)

**Penyebab:**
- PHP-FPM `max_children` masih terlalu tinggi (mungkin 40+)
- Atau settings belum di-apply dengan benar

---

## ✅ **SOLUSI CEPAT**

### **LANGKAH 1: Check PHP-FPM Settings Saat Ini**

**Via cPanel:**
1. Login cPanel → **MultiPHP Manager**
2. Klik **PHP-FPM Settings**
3. Check **Max Children** saat ini

**Atau via terminal:**
```bash
# Check PHP-FPM config
php-fpm -tt 2>/dev/null | grep "pm.max_children"
# atau
grep "pm.max_children" /etc/php-fpm.d/www.conf
# atau untuk cPanel
grep "pm.max_children" /opt/cpanel/ea-php82/root/etc/php-fpm.d/www.conf
```

---

### **LANGKAH 2: Kurangi Max Children ke 16**

**Via cPanel (RECOMMENDED):**
1. Login cPanel → **MultiPHP Manager**
2. Klik **PHP-FPM Settings**
3. Ubah setting berikut:
   - **Max Children:** 42 → **16**
   - **Start Servers:** (auto) → **8**
   - **Min Spare Servers:** (auto) → **4**
   - **Max Spare Servers:** (auto) → **8**
   - **Max Requests:** 500 → **100** (jika masih 500)
4. Klik **Update**
5. **Restart PHP-FPM:**
   - Via cPanel: MultiPHP Manager → Restart PHP-FPM
   - Atau via terminal: `systemctl restart php-fpm`

---

### **LANGKAH 3: Verifikasi Setelah Restart**

```bash
# Wait 10 seconds setelah restart
sleep 10

# Check total PHP-FPM processes
ps aux | grep php-fpm | grep -v grep | wc -l
```

**Expected:** 8-16 processes (bukan 42!)

```bash
# Check CPU usage
top
```

**Expected:** CPU usage turun ke 30-50%

---

## 📊 **PERHITUNGAN**

### **Saat Ini:**
- PHP-FPM processes: 42
- CPU per process: ~20-25%
- Total CPU: ~840% (overload!)

### **Setelah Fix:**
- PHP-FPM processes: 8-16
- CPU per process: ~5-10%
- Total CPU: ~80-160% (normal untuk 8 vCPU)

---

## ⚠️ **JIKA MASIH TINGGI SETELAH KURANGI KE 16**

Kurangi lebih agresif ke **12**:

**Via cPanel:**
1. MultiPHP Manager → PHP-FPM Settings
2. Ubah **Max Children: 16 → 12**
3. **Start Servers:** 6
4. **Min Spare Servers:** 3
5. **Max Spare Servers:** 6
6. Klik **Update** dan **Restart PHP-FPM**

---

## 📋 **CHECKLIST**

- [ ] Check PHP-FPM settings saat ini (Max Children)
- [ ] Kurangi Max Children: 42 → 16
- [ ] Update Start Servers: 8
- [ ] Update Min/Max Spare Servers: 4/8
- [ ] Restart PHP-FPM
- [ ] Verifikasi total processes (harusnya 8-16)
- [ ] Monitor CPU usage (harusnya turun)

---

## 🎯 **EXPECTED RESULTS**

Setelah fix:

| Metric | Sebelum | Sesudah |
|--------|---------|---------|
| **PHP-FPM Processes** | 42 | 8-16 |
| **CPU Usage** | ~95% | 30-50% |
| **Load Average** | 7.00, 7.57, 13.22 | < 8.0 |
| **Max Children** | 40+ | 16 |

---

## 🔍 **TROUBLESHOOTING**

### **Masih 42 processes setelah restart?**

1. **Check apakah settings sudah di-apply:**
   ```bash
   php-fpm -tt 2>/dev/null | grep "pm.max_children"
   ```

2. **Kill semua PHP-FPM processes dan restart:**
   ```bash
   # Hati-hati! Hanya jika perlu
   systemctl stop php-fpm
   pkill -9 php-fpm
   systemctl start php-fpm
   ```

3. **Check apakah ada multiple PHP-FPM pools:**
   ```bash
   ls -la /etc/php-fpm.d/
   # atau untuk cPanel
   ls -la /opt/cpanel/ea-php82/root/etc/php-fpm.d/
   ```

### **Aplikasi jadi lambat setelah kurangi?**

- Naikkan sedikit ke 20 (jika CPU dan memory masih OK)
- Check apakah ada slow queries
- Check apakah OPcache enabled

---

## ⚠️ **CATATAN PENTING**

1. **Lakukan perubahan di waktu low traffic** jika memungkinkan
2. **Monitor selama 1-2 jam** setelah perubahan
3. **Jika aplikasi jadi lambat**, naikkan sedikit ke 20
4. **Jika CPU masih tinggi**, kurangi lebih agresif ke 12

---

## 📚 **DOKUMENTASI TERKAIT**

- `OPTIMASI_PHP_FPM_CPU_100.md` - Optimasi PHP-FPM lengkap
- `ANALISIS_SETTING_PHP_FPM.md` - Analisis setting PHP-FPM
- `ANALISIS_CPU_MASIH_TINGGI.md` - Analisis CPU masih tinggi

---

**Lakukan Langkah 1-2 sekarang! Kurangi Max Children dari 42 ke 16.** ✅

Setelah itu, CPU usage harusnya turun drastis dari ~95% ke 30-50%.

