# 🔍 Cara Cek Queue Worker yang Berjalan

## ✅ Cara 1: Via Command Line (RECOMMENDED)

### **Check Queue Worker Processes**

```bash
# Check berapa banyak queue worker yang berjalan
ps aux | grep 'queue:work' | grep -v grep | wc -l

# Lihat detail queue worker yang berjalan
ps aux | grep 'queue:work' | grep -v grep

# Atau dengan format yang lebih rapi
ps aux | grep 'queue:work' | grep -v grep | awk '{print "PID: "$2" | CPU: "$3"% | MEM: "$4"% | CMD: "$11" "$12" "$13" "$14" "$15}'
```

**Expected output:**
- Jika normal: 1-2 queue workers
- Jika bermasalah: 10+ queue workers (ini yang menyebabkan CPU 100%)

---

### **Check Queue Worker dengan Detail**

```bash
# Lihat semua informasi queue worker
ps aux | grep 'queue:work' | grep -v grep
```

**Output akan menampilkan:**
- PID (Process ID)
- CPU usage (%)
- Memory usage (%)
- Start time
- Command yang dijalankan

---

## ✅ Cara 2: Via Script Helper

Gunakan script yang sudah dibuat:

```bash
cd /home/ymsuperadmin/public_html
chmod +x check-queue-worker.sh
bash check-queue-worker.sh
```

Script ini akan menampilkan:
- ✅ Jumlah queue worker yang berjalan
- ✅ Detail setiap queue worker (PID, CPU, MEM)
- ✅ Status queue jobs
- ✅ Queue worker log
- ✅ Cron job untuk queue worker
- ✅ Top CPU consumers

---

## ✅ Cara 3: Check Queue Jobs Status

### **Check Pending Jobs**

```bash
cd /home/ymsuperadmin/public_html

# Check jumlah jobs di queue
php artisan tinker --execute="echo DB::table('jobs')->count() . ' jobs in queue';"

# Check failed jobs
php artisan tinker --execute="echo DB::table('failed_jobs')->count() . ' failed jobs';"
```

### **Check Queue Monitor**

```bash
php artisan queue:monitor notifications
```

---

## ✅ Cara 4: Check Queue Worker Log

```bash
# Lihat log queue worker
tail -f /home/ymsuperadmin/public_html/storage/logs/queue-worker.log

# Atau lihat 20 baris terakhir
tail -20 /home/ymsuperadmin/public_html/storage/logs/queue-worker.log
```

---

## ✅ Cara 5: Monitor Real-Time

### **Monitor Queue Worker Processes**

```bash
# Update setiap 5 detik
watch -n 5 'ps aux | grep queue:work | grep -v grep'

# Atau dengan count
watch -n 5 'echo "Queue Workers: $(ps aux | grep queue:work | grep -v grep | wc -l)"'
```

### **Monitor CPU Usage**

```bash
# Monitor CPU usage dari queue worker
top -p $(pgrep -d',' -f 'queue:work')
```

---

## 📊 Interpretasi Hasil

### **Normal (1-2 Queue Workers)**
```
PID: 12345 | CPU:  2.5% | MEM:  1.2% | CMD: php artisan queue:work
```
✅ **Status:** Normal, tidak ada masalah

### **Bermasalah (10+ Queue Workers)**
```
PID: 12345 | CPU: 15.0% | MEM:  1.2% | CMD: php artisan queue:work
PID: 12346 | CPU: 14.5% | MEM:  1.1% | CMD: php artisan queue:work
PID: 12347 | CPU: 14.8% | MEM:  1.3% | CMD: php artisan queue:work
... (dan seterusnya, bisa sampai 60+)
```
❌ **Status:** Bermasalah! Ini yang menyebabkan CPU 100%

---

## 🔧 Troubleshooting

### Problem 1: Terlalu Banyak Queue Workers

**Gejala:** CPU 100%, banyak queue worker berjalan

**Solusi:**
```bash
# Kill semua queue worker
pkill -f 'queue:work'

# Verifikasi sudah tidak ada
ps aux | grep 'queue:work' | grep -v grep

# Fix setup queue worker (lihat fix-queue-worker.sh)
```

### Problem 2: Tidak Ada Queue Worker

**Gejala:** Queue jobs tidak diproses

**Solusi:**
```bash
# Start queue worker manual
cd /home/ymsuperadmin/public_html
php artisan queue:work --queue=notifications --tries=3 --timeout=300

# Atau setup Supervisor (recommended)
```

### Problem 3: Queue Worker Mati Terus

**Gejala:** Queue worker start lalu langsung mati

**Solusi:**
1. Check error log: `tail -50 storage/logs/queue-worker.log`
2. Check Laravel log: `tail -50 storage/logs/laravel.log`
3. Check PHP error: `php artisan queue:work -v`

---

## 📋 Quick Commands

```bash
# Count queue workers
ps aux | grep 'queue:work' | grep -v grep | wc -l

# List queue workers
ps aux | grep 'queue:work' | grep -v grep

# Kill all queue workers
pkill -f 'queue:work'

# Check queue jobs
cd /home/ymsuperadmin/public_html && php artisan queue:monitor notifications

# Check queue worker log
tail -f storage/logs/queue-worker.log
```

---

## ✅ Checklist

- [ ] Check jumlah queue worker (harusnya 1-2, bukan 10+)
- [ ] Check CPU usage per queue worker (harusnya < 5% per worker)
- [ ] Check memory usage (harusnya < 2% per worker)
- [ ] Check queue jobs status (pending vs failed)
- [ ] Check queue worker log untuk error
- [ ] Check cron job untuk queue worker

---

## 🎯 Expected Results

**Normal:**
- ✅ 1-2 queue workers berjalan
- ✅ CPU usage per worker: < 5%
- ✅ Memory usage per worker: < 2%
- ✅ Queue jobs diproses dengan baik
- ✅ Tidak ada error di log

**Bermasalah:**
- ❌ 10+ queue workers berjalan
- ❌ CPU usage total: 100%
- ❌ Memory usage tinggi
- ❌ Queue jobs tidak diproses atau duplicate

---

## 🔗 File Terkait

- `check-queue-worker.sh` - Script untuk check queue worker
- `fix-queue-worker.sh` - Script untuk fix queue worker
- `check-server-status.sh` - Script untuk check status server lengkap

