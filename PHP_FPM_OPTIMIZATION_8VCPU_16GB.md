# Optimasi PHP-FPM untuk Server 8 vCPU, 16GB RAM

## 🔴 Masalah dengan Settingan Saat Ini

### Settingan Current:
- **Max Children: 24** (default 5)
- **Max Requests: 100** (default 20)
- **Process Idle Timeout: 30** (default 10)

### Analisa Masalah:

1. **Max Children 24 terlalu tinggi untuk 8 vCPU**
   - Dengan 8 vCPU, idealnya Max Children = (8 vCPU × 2) = 16
   - 24 children bisa menyebabkan context switching berlebihan
   - Setiap child process bisa consume 50-200MB RAM
   - 24 × 150MB = ~3.6GB RAM hanya untuk PHP-FPM
   - Plus overhead database connections, cache, dll

2. **Max Requests 100 terlalu tinggi**
   - Laravel dengan banyak query bisa menyebabkan memory leak
   - Setelah 50-70 requests, memory usage per process bisa naik 20-30%
   - Dengan 100 requests, memory leak bisa signifikan

3. **Process Idle Timeout 30 detik terlalu lama**
   - Idle process tetap consume memory
   - Dengan 24 children idle, bisa waste ~3.6GB RAM

## ✅ Rekomendasi Settingan untuk 8 vCPU, 16GB RAM

### Formula Perhitungan:
```
Max Children = (vCPU × 2) + overhead
Max Children = (8 × 2) + 2 = 18 children

Start Servers = Max Children × 0.5 = 9
Min Spare Servers = Max Children × 0.3 = 5-6
Max Spare Servers = Max Children × 0.5 = 9

Max Requests = 50 (untuk prevent memory leak)
Process Idle Timeout = 10 detik (default, cukup)
```

### Settingan Optimal:

```
[www]
user = nobody
group = nobody
listen = /var/run/php-fpm/php-fpm.sock
listen.owner = nobody
listen.group = nobody
listen.mode = 0660

; Process Manager
pm = dynamic
pm.max_children = 18
pm.start_servers = 9
pm.min_spare_servers = 5
pm.max_spare_servers = 9
pm.max_requests = 50
pm.process_idle_timeout = 10s

; Performance Tuning
pm.status_path = /php-fpm-status
ping.path = /php-fpm-ping

; Logging
access.log = /var/log/php-fpm/access.log
access.format = "%R - %u %t \"%m %r%Q%q\" %s %f %{mili}d %{kilo}M %C%%"
slowlog = /var/log/php-fpm/slow.log
request_slowlog_timeout = 10s

; Security
security.limit_extensions = .php
```

## 📊 Perbandingan Resource Usage

### Settingan Current (24 children):
- **Memory Usage**: ~3.6GB (24 × 150MB)
- **CPU Context Switching**: Tinggi (terlalu banyak process)
- **Risk**: CPU 100% karena terlalu banyak concurrent process

### Settingan Optimal (18 children):
- **Memory Usage**: ~2.7GB (18 × 150MB)
- **CPU Context Switching**: Optimal
- **Risk**: Lebih rendah, lebih stabil

## 🛠️ Langkah Implementasi

### Step 1: Backup Settingan Current
```bash
# Backup current PHP-FPM config
cp /etc/php-fpm.d/www.conf /etc/php-fpm.d/www.conf.backup.$(date +%Y%m%d)
```

### Step 2: Update Settingan di cPanel
Masuk ke cPanel → System PHP-FPM Settings:
1. **Max Children**: Ubah dari 24 ke **18**
2. **Max Requests**: Ubah dari 100 ke **50**
3. **Process Idle Timeout**: Ubah dari 30 ke **10**

### Step 3: Restart PHP-FPM
```bash
# Restart PHP-FPM
service php-fpm restart
# atau
systemctl restart php-fpm
```

### Step 4: Monitor Hasil
```bash
# Monitor PHP-FPM status
watch -n 2 'curl -s http://localhost/php-fpm-status | grep -E "active processes|idle processes|max active processes"'

# Monitor CPU usage
top -bn1 | grep "Cpu(s)"
```

## 🔍 Monitoring & Tuning Lanjutan

### 1. Monitor PHP-FPM Status
Setup PHP-FPM status page untuk monitoring:
```nginx
location ~ ^/(php-fpm-status|php-fpm-ping)$ {
    access_log off;
    allow 127.0.0.1;
    deny all;
    include fastcgi_params;
    fastcgi_pass unix:/var/run/php-fpm/php-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}
```

Akses: `http://your-server/php-fpm-status`

### 2. Monitor Memory per Process
```bash
# Cek memory usage per PHP-FPM process
ps aux | grep php-fpm | awk '{sum+=$6} END {print "Total Memory: " sum/1024 " MB"}'
ps aux | grep php-fpm | wc -l  # Jumlah process
```

### 3. Tuning Berdasarkan Load
Jika masih CPU 100%:
- **Kurangi Max Children** ke 16 atau 14
- **Kurangi Max Requests** ke 30-40
- **Cek apakah ada query yang lambat** (slow query log)

Jika CPU masih rendah (< 50%):
- Bisa naikkan Max Children ke 20 (tapi hati-hati)
- Monitor memory usage

## ⚠️ Catatan Penting

1. **Jangan langsung ubah semua settingan sekaligus**
   - Ubah satu per satu dan monitor hasilnya
   - Mulai dari Max Children dulu

2. **Perhatikan Memory Usage**
   - 16GB RAM harus cukup untuk:
     - PHP-FPM: ~3GB
     - MySQL: ~4-6GB
     - System + Cache: ~2GB
     - Buffer: ~5GB

3. **Monitor Database Connections**
   - Setiap PHP-FPM process bisa buka 1-2 database connections
   - 18 children × 2 = 36 connections
   - Pastikan MySQL max_connections cukup (minimal 100)

4. **Perhatikan Laravel Queue Workers**
   - Queue workers juga consume CPU dan memory
   - Pastikan tidak terlalu banyak queue workers running

## 📈 Expected Results

Setelah optimasi, Anda seharusnya melihat:
- ✅ CPU usage turun dari 100% ke 60-80% (normal untuk high traffic)
- ✅ Load average turun dari 15-16 ke 8-12
- ✅ Response time lebih cepat
- ✅ Memory usage lebih stabil
- ✅ Tidak ada process yang stuck

## 🔄 Jika Masih CPU 100%

Jika setelah optimasi PHP-FPM masih CPU 100%, kemungkinan masalahnya di:

1. **Database Queries yang Lambat**
   - Cek slow query log
   - Optimize query yang berat
   - Tambahkan index yang diperlukan

2. **Laravel Queue Workers**
   - Cek apakah ada queue workers yang stuck
   - Restart queue workers
   - Monitor failed jobs

3. **Scheduled Tasks**
   - Cek scheduled tasks yang berat
   - Pastikan tidak overlap
   - Spread out execution time

4. **Application Code**
   - N+1 query problems
   - Missing indexes
   - Heavy computations tanpa caching
