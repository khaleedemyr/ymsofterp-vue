# ⚡ QUICK FIX: CPU 100% Meski Query Sudah Cepat

## 🎯 **MASALAH**

**Status:**
- ✅ Query sudah cepat (tidak ada slow queries)
- ✅ Web dan app sudah tidak lemot
- ❌ **CPU masih 100%**

**Kesimpulan:** Masalah di **resource management**, bukan query!

---

## 🔍 **DIAGNOSIS CEPAT**

Jalankan command berikut untuk check:

```bash
# 1. Check PHP-FPM processes
echo "PHP-FPM: $(ps aux | grep php-fpm | grep -v grep | wc -l) processes"

# 2. Check Queue Workers
echo "Queue Workers: $(ps aux | grep 'queue:work' | grep -v grep | wc -l) workers"

# 3. Check Top 5 processes by CPU
echo "Top 5 processes:"
ps aux --sort=-%cpu | head -6

# 4. Check System Load
uptime
```

---

## ✅ **SOLUSI CEPAT**

### **Jika PHP-FPM Processes > 30:**

**Via cPanel:**
1. Login cPanel → **MultiPHP Manager**
2. Klik **PHP-FPM Settings**
3. Ubah:
   - **Max Children: 40 → 20** (atau 24)
   - **Max Requests: 500 → 100**
4. Klik **Update**
5. Restart PHP-FPM

**Expected:**
- PHP-FPM processes: **12-20** (bukan 30+)
- CPU usage: **30-50%** (bukan 100%)

---

### **Jika Queue Workers > 5:**

1. **Check cron jobs:**
   ```bash
   crontab -l | grep queue:work
   ```

2. **Hapus cron job queue worker yang berjalan setiap menit**

3. **Gunakan Supervisor** (2 workers saja):
   ```bash
   # Check supervisor status
   supervisorctl status
   
   # Jika belum ada, setup supervisor
   # (lihat SOLUSI_LEMOT_SERVER.md untuk detail)
   ```

**Expected:**
- Queue Workers: **2** (bukan 10+)
- CPU usage: **< 10%** untuk queue workers

---

## 📊 **CHECKLIST**

- [ ] Check PHP-FPM processes (harusnya 12-20, bukan 30+)
- [ ] Check Queue Workers (harusnya 2, bukan 10+)
- [ ] Check top processes by CPU
- [ ] Kurangi PHP-FPM Max Children ke 20-24
- [ ] Fix Queue Workers (hanya 2 via Supervisor)
- [ ] Monitor CPU usage setelah perubahan

---

## 🎯 **EXPECTED RESULTS**

| Metric | Sebelum | Sesudah |
|--------|---------|---------|
| **CPU Usage** | 100% | 30-50% |
| **PHP-FPM Processes** | 30+ | 12-20 |
| **Queue Workers** | 10+ | 2 |
| **Load Average** | > 8.0 | < 8.0 |

---

## ⚠️ **PENTING**

1. **Lakukan diagnosis dulu** - jalankan command di atas
2. **Fix PHP-FPM dulu** - ini biasanya penyebab utama
3. **Fix Queue Workers** - jika masih tinggi
4. **Monitor** - check CPU setiap 5-10 menit setelah perubahan

---

**Status:** ⏳ **TUNGGU DIAGNOSIS - Jalankan command di atas untuk check!**
