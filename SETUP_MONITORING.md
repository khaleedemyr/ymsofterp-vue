# Setup Sistem Monitoring Server

## 🎯 Tujuan

Membuat sistem monitoring untuk mengidentifikasi sumber masalah lemot secara real-time.

---

## 📋 Langkah Setup

### Step 1: Enable Slow Query Log

Jalankan script untuk enable slow query log:

```bash
mysql -u root -p < ENABLE_SLOW_QUERY_LOG.sql
```

Atau manual:
```sql
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 1; -- Log query > 1 detik
SET GLOBAL log_queries_not_using_indexes = 'ON';
```

**Verifikasi:**
```sql
SHOW VARIABLES LIKE 'slow_query_log';
SHOW VARIABLES LIKE 'long_query_time';
```

### Step 2: Jalankan Monitoring Real-Time

#### Opsi A: Menggunakan Laravel Command (Recommended)

```bash
# Monitor selama 60 detik, update setiap 5 detik
php artisan monitor:server-performance --interval=5 --duration=60

# Monitor selama 5 menit, update setiap 10 detik
php artisan monitor:server-performance --interval=10 --duration=300
```

**Output akan menampilkan:**
- Active MySQL processes
- Recent slow queries
- MySQL status (connections, threads, etc)
- Warning jika ada long-running queries

#### Opsi B: Menggunakan Shell Script (Linux)

```bash
# Berikan permission execute
chmod +x monitor_real_time.sh

# Jalankan monitoring (interval 5 detik, durasi 60 detik)
./monitor_real_time.sh 5 60

# Atau dengan durasi lebih lama (5 menit)
./monitor_real_time.sh 5 300
```

#### Opsi C: Menggunakan PowerShell (Windows)

```powershell
# Jalankan monitoring (interval 5 detik, durasi 60 detik)
.\monitor_real_time.ps1 -Interval 5 -Duration 60

# Atau dengan durasi lebih lama (5 menit)
.\monitor_real_time.ps1 -Interval 5 -Duration 300
```

### Step 3: Analisa Hasil Monitoring

Saat monitoring berjalan, perhatikan:

1. **MySQL Processes yang Running Lama**
   - Jika ada query yang running >5 detik, itu kemungkinan masalah
   - Catat query-nya dan analisa

2. **Threads Running Tinggi**
   - Jika Threads_running > 10, berarti banyak query concurrent
   - Bisa jadi ada bottleneck

3. **Slow Queries yang Muncul**
   - Perhatikan query mana yang sering muncul
   - Query mana yang paling lambat

4. **CPU/Memory Usage**
   - Jika CPU tinggi saat ada query tertentu, query itu bermasalah
   - Jika memory tinggi, bisa jadi ada memory leak

---

## 🔍 Cara Menggunakan

### Scenario 1: Monitor Saat Server Lemot

1. **Jalankan monitoring:**
```bash
php artisan monitor:server-performance --interval=3 --duration=300
```

2. **Lakukan aktivitas yang membuat server lemot:**
   - Login di member app
   - Create order di POS
   - Akses endpoint tertentu
   - Dll

3. **Perhatikan output monitoring:**
   - Query mana yang muncul saat server lemot?
   - Process mana yang running lama?
   - Resource mana yang tinggi?

### Scenario 2: Monitor Scheduled Tasks

1. **Cek jadwal scheduled tasks:**
```bash
php artisan schedule:list
```

2. **Jalankan monitoring saat scheduled task running:**
```bash
# Misalnya, monitor saat members:update-tiers running
php artisan monitor:server-performance --interval=5 --duration=600
```

3. **Perhatikan:**
   - Query apa yang dijalankan?
   - Berapa lama query tersebut?
   - Berapa banyak rows yang di-examine?

### Scenario 3: Monitor API Endpoints

1. **Jalankan monitoring:**
```bash
php artisan monitor:server-performance --interval=2 --duration=120
```

2. **Akses endpoint tertentu berulang kali:**
   - Login endpoint
   - Order endpoint
   - Member endpoint
   - Dll

3. **Perhatikan:**
   - Query apa yang muncul saat akses endpoint tersebut?
   - Apakah query tersebut lambat?
   - Apakah ada full table scan?

---

## 📊 Interpretasi Hasil

### Jika Ada Long-Running Queries (>5 detik)

**Kemungkinan masalah:**
- Query tidak optimal
- Missing index
- Full table scan
- Lock wait

**Action:**
1. Catat query-nya
2. Jalankan EXPLAIN untuk analisa
3. Tambahkan index jika perlu
4. Optimasi query

### Jika Threads Running Tinggi (>10)

**Kemungkinan masalah:**
- Banyak concurrent requests
- Query yang lambat membuat queue
- Bottleneck di database

**Action:**
1. Identifikasi query yang paling lambat
2. Optimasi query tersebut
3. Pertimbangkan untuk scale up database

### Jika Slow Queries Sering Muncul

**Kemungkinan masalah:**
- Query yang sama sering dipanggil dan lambat
- Missing index
- Query tidak optimal

**Action:**
1. Identifikasi query yang paling sering muncul
2. Optimasi query tersebut
3. Tambahkan index jika perlu

### Jika CPU Tinggi

**Kemungkinan masalah:**
- Query yang berat
- Scheduled tasks yang berat
- PHP-FPM workers terlalu banyak

**Action:**
1. Identifikasi process yang menggunakan CPU tinggi
2. Optimasi query/task tersebut
3. Adjust PHP-FPM configuration

---

## 🚀 Best Practices

1. **Monitor secara berkala:**
   - Monitor saat peak hours
   - Monitor saat scheduled tasks running
   - Monitor saat ada keluhan user

2. **Document hasil:**
   - Catat query yang bermasalah
   - Catat waktu kejadian
   - Catat impact (berapa lama, berapa user affected)

3. **Prioritaskan fix:**
   - Fix query yang paling lambat dan sering dipanggil
   - Fix query yang menyebabkan bottleneck
   - Fix query yang paling banyak rows examined

---

## 📝 Checklist

- [ ] Enable slow query log
- [ ] Test monitoring command/script
- [ ] Monitor saat server normal (baseline)
- [ ] Monitor saat server lemot
- [ ] Identifikasi masalah utama
- [ ] Buat plan untuk fix
- [ ] Implementasi fix
- [ ] Monitor lagi setelah fix
- [ ] Verifikasi improvement

---

## 🔗 File Terkait

- `ENABLE_SLOW_QUERY_LOG.sql` - Enable slow query log
- `app/Console/Commands/MonitorServerPerformance.php` - Laravel command untuk monitoring
- `monitor_real_time.sh` - Shell script untuk monitoring (Linux)
- `monitor_real_time.ps1` - PowerShell script untuk monitoring (Windows)
- `DIAGNOSTIC_SERVER_LEMOT.sql` - Diagnostic queries
- `check_server_performance.sh` - Check performance sekali
- `check_server_performance.ps1` - Check performance sekali (Windows)

---

## 💡 Tips

1. **Jalankan monitoring di background:**
```bash
# Linux
nohup php artisan monitor:server-performance --interval=5 --duration=600 > monitoring.log 2>&1 &

# Windows (PowerShell)
Start-Process powershell -ArgumentList "-File", "monitor_real_time.ps1", "-Interval", "5", "-Duration", "600"
```

2. **Simpan hasil monitoring:**
```bash
php artisan monitor:server-performance --interval=5 --duration=300 > monitoring_$(date +%Y%m%d_%H%M%S).log
```

3. **Monitor saat peak hours:**
   - Pagi hari (08:00-10:00)
   - Siang hari (12:00-14:00)
   - Sore hari (17:00-19:00)

4. **Monitor saat scheduled tasks:**
   - Saat `members:update-tiers` running (tanggal 1 setiap bulan)
   - Saat `points:expire` running (setiap hari 00:00)
   - Saat notification tasks running

---

## 🎯 Expected Results

Setelah setup monitoring, Anda akan bisa:

1. **Lihat query yang sedang running** secara real-time
2. **Identifikasi query yang lambat** saat terjadi
3. **Lihat resource usage** (CPU, Memory) saat masalah terjadi
4. **Track slow queries** yang muncul
5. **Identifikasi bottleneck** dengan lebih akurat

Dengan informasi ini, Anda bisa fix masalah dengan lebih tepat dan cepat!
