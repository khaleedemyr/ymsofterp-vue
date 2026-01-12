# Cara Identifikasi Sumber Masalah Server Lemot

## 🎯 Overview

Server ini digunakan oleh banyak aplikasi:
- **Backend Laravel** (API)
- **Frontend Vue** (Web)
- **ymsoftapp** (Mobile App)
- **ymsoftpos** (Desktop App)
- **Member App** (Mobile App)

Masalah lemot bisa berasal dari berbagai sumber. Script ini akan membantu identifikasi.

---

## 📋 Langkah-Langkah Diagnostic

### Step 1: Jalankan Diagnostic SQL

Jalankan file `DIAGNOSTIC_SERVER_LEMOT.sql` di MySQL:

```bash
mysql -u root -p < DIAGNOSTIC_SERVER_LEMOT.sql
```

Atau copy-paste query satu per satu di MySQL client.

**Hasil yang akan didapat:**
1. Query yang paling lambat (TOP 20)
2. Query yang paling sering dipanggil
3. Tabel yang paling banyak diakses
4. Query yang examine banyak rows (full table scan)
5. Process yang sedang running

### Step 2: Cek Server Performance

**Untuk Linux:**
```bash
bash check_server_performance.sh
```

**Untuk Windows (PowerShell):**
```powershell
.\check_server_performance.ps1
```

**Atau manual:**
```bash
# CPU Usage
top

# Memory Usage
free -h

# MySQL Process
mysql -u root -p -e "SHOW PROCESSLIST;"
```

### Step 3: Analisa Hasil

Berdasarkan hasil diagnostic, identifikasi:

#### A. Jika Banyak Query Lambat di Slow Query Log

**Kemungkinan masalah:**
- Missing index
- Full table scan
- Query tidak optimal

**Solusi:**
- Tambahkan index yang diperlukan
- Optimasi query
- Lihat file `FIX_SLOW_QUERIES.sql` dan `QUICK_FIX_SLOW_QUERIES.sql`

#### B. Jika Banyak Process Running di MySQL

**Kemungkinan masalah:**
- Query yang stuck/lock
- Deadlock
- Too many connections

**Solusi:**
```sql
-- Kill process yang stuck
KILL <process_id>;

-- Cek max connections
SHOW VARIABLES LIKE 'max_connections';
```

#### C. Jika CPU Tinggi

**Kemungkinan masalah:**
- Scheduled tasks (cron jobs) yang berat
- Queue jobs yang banyak
- PHP-FPM workers terlalu banyak

**Cek scheduled tasks:**
```bash
# Cek cron jobs
php artisan schedule:list

# Cek queue workers
ps aux | grep "queue:work"
```

**Scheduled tasks yang mungkin berat:**
- `members:update-tiers` (update semua member tiers - bisa berat jika banyak member)
- `points:expire` (expire points - bisa berat jika banyak points)
- `member:notify-*` (notifikasi ke member - bisa banyak)

#### D. Jika Memory Tinggi

**Kemungkinan masalah:**
- PHP-FPM workers terlalu banyak
- Memory leak di PHP
- Query yang load banyak data ke memory

**Solusi:**
- Cek PHP-FPM configuration
- Restart PHP-FPM
- Optimasi query yang load banyak data

#### E. Jika Banyak Query dari Tabel Tertentu

**Cek di hasil diagnostic:**
- Tabel mana yang paling banyak diakses?
- Apakah ada full table scan?

**Contoh:**
- Jika `member_apps_members` banyak diakses → Cek query login/register
- Jika `orders` banyak diakses → Cek query POS
- Jika `activity_logs` banyak diakses → Cek logging

---

## 🔍 Sumber Masalah yang Mungkin

### 1. Member App Login/Register

**Gejala:**
- Query `member_apps_members` lambat
- Banyak query saat user login/register

**Cek:**
- Apakah ada index di `email` dan `mobile_phone`?
- Apakah query register mengambil semua data?

**Solusi:**
- Lihat `QUICK_FIX_MEMBER_LOGIN.sql`
- Lihat `FIX_MEMBER_REGISTER_NO_CODE_CHANGE.sql`

### 2. POS App (ymsoftpos)

**Gejala:**
- Query `orders` dan `order_items` lambat
- Banyak query saat create order

**Cek:**
- Apakah ada index di `orders`?
- Apakah ada N+1 query problem?

**Solusi:**
- Tambahkan index yang diperlukan
- Optimasi query order

### 3. Scheduled Tasks (Cron Jobs)

**Gejala:**
- CPU tinggi pada waktu tertentu
- Server lemot saat cron job running

**Cek scheduled tasks yang berat:**
- `members:update-tiers` - Update semua member tiers (bisa berat)
- `points:expire` - Expire points (bisa berat)
- `member:notify-*` - Notifikasi ke member (bisa banyak)

**Solusi:**
- Optimasi scheduled tasks
- Jadwalkan di waktu yang tidak peak
- Pastikan tidak overlap

### 4. Queue Jobs

**Gejala:**
- Banyak job menumpuk di queue
- Queue worker stuck

**Cek:**
```bash
# Cek queue workers
ps aux | grep "queue:work"

# Cek jumlah job di queue
php artisan queue:monitor
```

**Solusi:**
- Restart queue worker
- Cek job yang stuck
- Optimasi queue processing

### 5. API Endpoints yang Overloaded

**Gejala:**
- Endpoint tertentu sangat lambat
- Banyak request ke endpoint yang sama

**Cek:**
- Analisa Laravel log
- Cek nginx/apache access log
- Identifikasi endpoint yang bermasalah

**Solusi:**
- Optimasi endpoint tersebut
- Tambahkan caching jika perlu
- Rate limiting jika perlu

---

## 📊 Prioritas Fix

Berdasarkan hasil diagnostic, prioritaskan:

1. **CRITICAL**: Query yang sangat lambat (>10 detik) dan sering dipanggil
2. **HIGH**: Full table scan pada tabel besar (>50k records)
3. **MEDIUM**: Missing index pada kolom yang sering di-query
4. **LOW**: Optimasi query yang sudah cukup cepat (<1 detik)

---

## 🚀 Quick Actions

### Jika Server Sangat Lemot Sekarang:

1. **Restart Services:**
```bash
sudo systemctl restart php-fpm
sudo systemctl restart mysql
sudo systemctl restart nginx
```

2. **Kill Stuck Processes:**
```sql
-- Cek process yang stuck
SHOW PROCESSLIST;

-- Kill process yang stuck
KILL <process_id>;
```

3. **Clear Cache:**
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

4. **Restart Queue Workers:**
```bash
# Stop queue workers
pkill -f "queue:work"

# Start queue workers
php artisan queue:work --daemon
```

---

## 📝 Checklist Diagnostic

- [ ] Jalankan `DIAGNOSTIC_SERVER_LEMOT.sql`
- [ ] Cek slow query log (TOP 20 query)
- [ ] Cek process yang sedang running
- [ ] Cek resource usage (CPU, Memory, Disk I/O)
- [ ] Cek scheduled tasks (cron jobs)
- [ ] Cek queue jobs
- [ ] Cek API endpoints yang paling sering dipanggil
- [ ] Cek log Laravel untuk error/warning
- [ ] Identifikasi sumber masalah utama
- [ ] Buat plan untuk fix

---

## 🎯 Expected Results

Setelah menjalankan diagnostic, Anda akan tahu:

1. **Query mana yang paling bermasalah** (lambat dan sering dipanggil)
2. **Tabel mana yang paling banyak diakses** (kemungkinan perlu index)
3. **Endpoint mana yang paling lambat** (perlu optimasi)
4. **Scheduled task mana yang paling berat** (perlu optimasi)
5. **Resource bottleneck** (CPU, Memory, Disk I/O)

Dengan informasi ini, Anda bisa fokus fix pada masalah yang paling critical.

---

## 📞 Next Steps

Setelah mendapatkan hasil diagnostic:

1. **Identifikasi masalah utama** (query, endpoint, atau scheduled task)
2. **Prioritaskan fix** berdasarkan impact dan frequency
3. **Implementasi fix** (tambahkan index, optimasi query, dll)
4. **Monitor** setelah fix untuk memastikan improvement
5. **Document** perubahan yang dilakukan

---

## 🔗 File Terkait

- `DIAGNOSTIC_SERVER_LEMOT.sql` - Script SQL untuk diagnostic
- `DIAGNOSTIC_SERVER_LEMOT.md` - Dokumentasi lengkap
- `check_server_performance.sh` - Script check performance (Linux)
- `check_server_performance.ps1` - Script check performance (Windows)
- `FIX_SLOW_QUERIES.sql` - Fix untuk slow queries
- `QUICK_FIX_SLOW_QUERIES.sql` - Quick fix untuk slow queries
- `QUICK_FIX_MEMBER_LOGIN.sql` - Fix untuk member login
- `FIX_MEMBER_REGISTER_NO_CODE_CHANGE.sql` - Fix untuk member register
