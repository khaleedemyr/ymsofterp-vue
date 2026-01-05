# ✅ Verifikasi Setting PHP-FPM: Max Children 24

## 🎉 **STATUS: SETTING SUDAH OPTIMAL!**

Setting PHP-FPM Anda sudah sangat baik untuk high traffic:

| Setting | Nilai | Status |
|---------|-------|--------|
| **Max Children** | 24 | ✅ **SEMPURNA** |
| **Max Requests** | 100 | ✅ **SEMPURNA** |
| **Process Idle Timeout** | 10 | ✅ **SEMPURNA** |

---

## ✅ **LANGKAH SELANJUTNYA**

### **LANGKAH 1: Klik "Update" di cPanel**

Setting sudah benar, tapi **pastikan klik "Update"** untuk apply perubahan!

1. Klik tombol **"Update"** di bagian bawah halaman
2. Tunggu sampai muncul konfirmasi "Settings updated successfully"

---

### **LANGKAH 2: Restart PHP-FPM**

Setelah update, restart PHP-FPM:

**Via cPanel:**
1. MultiPHP Manager → **Restart PHP-FPM**
2. Atau klik tombol restart di halaman PHP-FPM Settings

**Atau via terminal:**
```bash
systemctl restart php-fpm
# atau
/scripts/restartsrv_php-fpm
```

---

### **LANGKAH 3: Check Start Servers, Min/Max Spare Servers**

Di halaman PHP-FPM Settings, biasanya ada setting tambahan yang tidak terlihat di screenshot:
- **Start Servers:** Harusnya 12 (50% dari max_children)
- **Min Spare Servers:** Harusnya 8 (30% dari max_children)
- **Max Spare Servers:** Harusnya 12 (50% dari max_children)

**Cara check:**
- Scroll ke bawah di halaman PHP-FPM Settings
- Atau check via terminal:
  ```bash
  php-fpm -tt 2>/dev/null | grep -E "pm\.(start_servers|min_spare|max_spare)"
  ```

**Jika tidak ada atau berbeda, ubah:**
- Start Servers: **12**
- Min Spare Servers: **8**
- Max Spare Servers: **12**

---

### **LANGKAH 4: Verifikasi Setelah Restart**

```bash
# Wait 10 seconds setelah restart
sleep 10

# Check total PHP-FPM processes (harusnya 12-24, bukan 42!)
ps aux | grep php-fpm | grep -v grep | wc -l
```

**Expected:** 12-24 processes (bukan 42!)

```bash
# Check CPU usage (harusnya turun!)
top
```

**Expected:** CPU usage 40-60% (bukan 95%)

---

## 📊 **EXPECTED RESULTS**

Setelah apply setting ini:

| Metric | Sebelum | Sesudah (Expected) |
|--------|---------|-------------------|
| **PHP-FPM Processes** | 42 | 12-24 |
| **CPU Usage** | ~95% | 40-60% |
| **Load Average** | 7.00, 7.57, 13.22 | < 8.0 |
| **Response Time** | Lambat (500-1000ms) | Cepat (200-300ms) |

---

## 📋 **CHECKLIST**

- [x] ✅ Max Children: 24 (sudah benar)
- [x] ✅ Max Requests: 100 (sudah benar)
- [x] ✅ Process Idle Timeout: 10 (sudah benar)
- [ ] **Klik "Update" di cPanel** ⚠️
- [ ] **Restart PHP-FPM** ⚠️
- [ ] Check Start Servers, Min/Max Spare Servers
- [ ] Verifikasi total processes (harusnya 12-24)
- [ ] Monitor CPU usage (harusnya 40-60%)
- [ ] Monitor response time aplikasi
- [ ] Monitor selama 1-2 jam

---

## 🔍 **VERIFIKASI LENGKAP**

Jalankan command ini setelah restart untuk verifikasi:

```bash
echo "=== 1. PHP-FPM Processes (harusnya 12-24) ==="
ps aux | grep php-fpm | grep -v grep | wc -l
echo ""

echo "=== 2. CPU Usage ==="
top -bn1 | grep "Cpu(s)" | sed "s/.*, *\([0-9.]*\)%* id.*/\1/" | awk '{print "CPU Idle: " $1 "% (CPU Used: " (100-$1) "%)"}'
echo ""

echo "=== 3. Load Average ==="
uptime
echo ""

echo "=== 4. PHP-FPM Config ==="
php-fpm -tt 2>/dev/null | grep -E "pm\.(max_children|start_servers|min_spare|max_spare)" | head -4
echo ""

echo "=== 5. Memory Usage ==="
ps aux | grep php-fpm | grep -v grep | awk '{sum+=$6} END {print "Total PHP-FPM Memory: " sum/1024 " MB"}'
echo ""
```

---

## ⚠️ **CATATAN PENTING**

1. **Setting sudah benar, tapi harus di-apply!**
   - Klik **"Update"** di cPanel
   - Restart PHP-FPM

2. **Monitor selama 1-2 jam** setelah perubahan
   - Check CPU usage (harusnya turun)
   - Check response time aplikasi (harusnya lebih cepat)
   - Check error logs (tidak ada error baru)

3. **Jika CPU masih tinggi setelah apply:**
   - Check slow queries di database
   - Check apakah ada process lain yang consume CPU
   - Consider kurangi ke 20 jika perlu

4. **Jika aplikasi jadi lambat:**
   - Naikkan sedikit ke 28 (maksimal untuk 8 vCPU)
   - Check slow queries
   - Optimize database queries

---

## 🎯 **KESIMPULAN**

**Setting PHP-FPM Anda SUDAH OPTIMAL!** ✅

**Yang perlu dilakukan:**
1. ⚠️ **Klik "Update"** di cPanel (penting!)
2. ⚠️ **Restart PHP-FPM** setelah update
3. ✅ Verifikasi total processes (harusnya 12-24)
4. ✅ Monitor CPU usage (harusnya 40-60%)

**Expected:** CPU usage turun dari ~95% ke 40-60%, response time lebih cepat!

---

**Lakukan Langkah 1-2 sekarang: Klik "Update" dan Restart PHP-FPM!** ✅

