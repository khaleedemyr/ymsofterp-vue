# 🚨 SOLUSI CEPAT: CPU 100% - Server 8 vCPU / 16GB RAM

## ⚡ LANGKAH CEPAT (Lakukan SEKARANG)

### **1. Optimasi PHP-FPM (PRIORITAS TINGGI)**

**Via cPanel:**
1. Login cPanel → **MultiPHP Manager** atau **Select PHP Version**
2. Klik **PHP-FPM Settings** atau **Configure PHP-FPM**
3. Ubah setting berikut:

```
Max Children: 40 → 24
Max Requests: 500 → 100
Start Servers: (auto) → 12
Min Spare Servers: (auto) → 8
Max Spare Servers: (auto) → 12
```

4. Klik **Update** / **Save**
5. Restart PHP-FPM

**Hasil yang diharapkan:**
- CPU turun dari 100% ke 30-50%
- PHP-FPM processes: dari 40+ ke 12-24

---

### **2. Check Queue Workers (PENTING!)**

Jalankan di terminal:
```bash
ps aux | grep 'queue:work' | grep -v grep | wc -l
```

**Jika hasilnya > 2:**
- ⚠️ **MASALAH:** Terlalu banyak queue worker
- ✅ **SOLUSI:** Lihat `SOLUSI_LEMOT_SERVER.md` bagian "Fix Queue Worker"

**Harusnya hanya 1-2 queue worker yang berjalan.**

---

### **3. Check Status Server**

Jalankan script:
```bash
bash check-php-fpm-status.sh
```

Atau manual:
```bash
# Check PHP-FPM processes
ps aux | grep php-fpm | grep -v grep | wc -l

# Check CPU
top

# Check memory
free -h
```

---

## 📊 SETTING YANG DIREKOMENDASIKAN

### **PHP-FPM Settings untuk 8 vCPU / 16GB RAM:**

| Setting | Nilai |
|---------|-------|
| Process Manager | dynamic |
| **Max Children** | **24** |
| Start Servers | 12 |
| Min Spare Servers | 8 |
| Max Spare Servers | 12 |
| **Max Requests** | **100** |
| Process Idle Timeout | 10 |

**Rumus:**
- Max Children = (vCPU × 2) + 4 = (8 × 2) + 4 = 20
- Untuk high traffic: 24 (masih aman)

---

## 🔍 TROUBLESHOOTING

### **CPU masih 100% setelah optimasi PHP-FPM?**

1. **Check queue workers:**
   ```bash
   ps aux | grep 'queue:work' | grep -v grep
   ```
   Jika > 2, ini penyebabnya! Fix dulu.

2. **Check cron jobs:**
   ```bash
   crontab -l
   ```
   Hapus duplicate cron jobs (lihat `SOLUSI_LEMOT_SERVER.md`)

3. **Check scheduled tasks:**
   ```bash
   ps aux | grep 'schedule:run' | grep -v grep
   ```

### **Aplikasi jadi lambat setelah kurangi Max Children?**

- Naikkan sedikit ke 28
- Check apakah OPcache enabled
- Check slow queries di database

### **Memory masih tinggi?**

- Kurangi Max Children ke 20
- Kurangi memory_limit per process ke 192M

---

## 📋 CHECKLIST

- [ ] Ubah PHP-FPM Max Children: 40 → 24
- [ ] Ubah PHP-FPM Max Requests: 500 → 100
- [ ] Restart PHP-FPM
- [ ] Check queue workers (harusnya 1-2)
- [ ] Monitor CPU selama 1 jam (harusnya turun)
- [ ] Check response time aplikasi

---

## 📚 DOKUMENTASI LENGKAP

1. **`OPTIMASI_PHP_FPM_CPU_100.md`** - Panduan lengkap optimasi PHP-FPM
2. **`SOLUSI_LEMOT_SERVER.md`** - Solusi lengkap server lemot (termasuk queue workers)
3. **`php-fpm-optimized.conf`** - File config lengkap untuk manual setup
4. **`check-php-fpm-status.sh`** - Script untuk check status PHP-FPM

---

## ⚠️ PENTING!

1. **Lakukan perubahan bertahap** - jangan sekaligus
2. **Monitor selama 24 jam** setelah perubahan
3. **Backup config** sebelum edit manual
4. **Test di waktu low traffic** jika memungkinkan

---

## 🎯 EXPECTED RESULTS

| Metric | Sebelum | Sesudah |
|--------|---------|---------|
| CPU Usage | 100% | 30-50% |
| PHP-FPM Processes | 40+ | 12-24 |
| Memory Usage | 80-90% | 60-70% |
| Response Time | Lambat | Lebih cepat |

---

**Mulai dari Langkah 1 sekarang!** ⚡

