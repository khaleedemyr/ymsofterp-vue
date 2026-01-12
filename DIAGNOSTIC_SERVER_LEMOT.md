# Diagnostic: Identifikasi Sumber Masalah Server Lemot

## 🎯 Tujuan

Mengidentifikasi sumber masalah lemot di server yang digunakan oleh:
- **Backend Laravel** (API)
- **Frontend Vue** (Web)
- **ymsoftapp** (Mobile App)
- **ymsoftpos** (Desktop App)
- **Member App** (Mobile App)

---

## 📊 Langkah Diagnostic

### Step 1: Analisa Slow Query Log

Jalankan script `DIAGNOSTIC_SERVER_LEMOT.sql` untuk melihat:

1. **Query yang paling lambat** (TOP 20)
2. **Query yang paling sering dipanggil** (TOP 20)
3. **Tabel yang paling banyak diakses**
4. **Query yang examine banyak rows** (kemungkinan full table scan)
5. **Query yang tidak pakai index**

### Step 2: Cek Process yang Sedang Running

```sql
SHOW PROCESSLIST;
```

Cek apakah ada:
- Query yang stuck/lock
- Process yang running lama
- Deadlock atau lock wait

### Step 3: Cek Resource Usage

```bash
# CPU Usage
top -bn1 | grep "Cpu(s)" | awk '{print $2}'

# Memory Usage
free -h

# Disk I/O
iostat -x 1 5

# MySQL Process
ps aux | grep mysql
```

### Step 4: Cek Scheduled Tasks (Cron Jobs)

```bash
# Cek cron jobs yang sedang running
ps aux | grep -E "php.*artisan|schedule:run"

# Cek scheduled tasks di Laravel
php artisan schedule:list
```

### Step 5: Cek Queue Jobs

```bash
# Cek queue yang sedang running
ps aux | grep "queue:work"

# Cek jumlah job di queue
php artisan queue:monitor
```

### Step 6: Cek API Endpoints yang Paling Sering Dipanggil

Cek log Laravel untuk melihat endpoint mana yang paling sering dipanggil:

```bash
# Cek access log
tail -f storage/logs/laravel.log | grep "GET\|POST\|PUT\|DELETE"

# Atau cek nginx/apache access log
tail -f /var/log/nginx/access.log
```

---

## 🔍 Sumber Masalah yang Mungkin

### 1. Database Queries

**Gejala:**
- Query lambat di slow query log
- Banyak full table scan
- Missing index

**Solusi:**
- Tambahkan index yang diperlukan
- Optimasi query
- Cek N+1 query problems

### 2. Scheduled Tasks (Cron Jobs)

**Gejala:**
- CPU tinggi saat cron job running
- Process yang running lama
- Overlap cron jobs

**Solusi:**
- Cek `app/Console/Kernel.php`
- Pastikan cron job tidak overlap
- Optimasi scheduled tasks

### 3. Queue Jobs

**Gejala:**
- Banyak job menumpuk di queue
- Queue worker stuck
- Memory leak di queue worker

**Solusi:**
- Restart queue worker
- Cek job yang stuck
- Optimasi queue processing

### 4. API Endpoints yang Overloaded

**Gejala:**
- Endpoint tertentu sangat lambat
- Banyak request ke endpoint yang sama
- Timeout di client

**Solusi:**
- Identifikasi endpoint yang bermasalah
- Optimasi endpoint tersebut
- Tambahkan caching jika perlu

### 5. Memory Leak

**Gejala:**
- Memory usage terus naik
- PHP-FPM workers restart terus
- Server crash

**Solusi:**
- Cek PHP memory limit
- Cek memory leak di code
- Restart PHP-FPM secara berkala

### 6. Concurrent Users

**Gejala:**
- Server lemot saat banyak user login
- CPU tinggi saat peak hours
- Timeout saat banyak request

**Solusi:**
- Scale up server
- Optimasi PHP-FPM configuration
- Optimasi database connection pool

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
- [ ] Cek nginx/apache access log
- [ ] Cek PHP-FPM status
- [ ] Cek MySQL status

---

## 🎯 Prioritas Fix

Berdasarkan hasil diagnostic, prioritaskan fix:

1. **CRITICAL**: Query yang sangat lambat (>10 detik) dan sering dipanggil
2. **HIGH**: Full table scan pada tabel besar (>50k records)
3. **MEDIUM**: Missing index pada kolom yang sering di-query
4. **LOW**: Optimasi query yang sudah cukup cepat (<1 detik)

---

## 📊 Monitoring Tools

### Real-time Monitoring

```bash
# Monitor MySQL queries real-time
mysqladmin -u root -p processlist

# Monitor PHP-FPM status
watch -n 1 'ps aux | grep php-fpm | wc -l'

# Monitor server resources
htop
```

### Log Analysis

```bash
# Analisa slow query log
mysqldumpslow /var/log/mysql/slow-query.log

# Analisa Laravel log
tail -f storage/logs/laravel.log | grep -E "ERROR|WARNING|slow"
```

---

## 🚀 Quick Fixes

### 1. Restart Services

```bash
# Restart PHP-FPM
sudo systemctl restart php-fpm

# Restart MySQL
sudo systemctl restart mysql

# Restart Nginx
sudo systemctl restart nginx
```

### 2. Clear Cache

```bash
# Clear Laravel cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Clear OPcache
php artisan opcache:clear
```

### 3. Kill Stuck Processes

```sql
-- Kill process yang stuck
KILL <process_id>;
```

---

## 📈 Expected Results

Setelah diagnostic, Anda akan tahu:

1. **Query mana yang paling bermasalah**
2. **Tabel mana yang paling banyak diakses**
3. **Endpoint mana yang paling lambat**
4. **Scheduled task mana yang paling berat**
5. **Resource bottleneck** (CPU, Memory, Disk I/O)

Dengan informasi ini, Anda bisa fokus fix pada masalah yang paling critical.
