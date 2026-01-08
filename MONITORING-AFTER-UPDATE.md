# Monitoring Setelah Update PHP-FPM ke 80 Workers

## Checklist Update
- ✅ Max Requests: 200
- ✅ Max Children: 80
- ⚠️ Process Idle Timeout: 10 → **UPDATE KE 30**

## Langkah Monitoring

### 1. Restart PHP-FPM
```bash
# Via SSH
systemctl restart php-fpm
# atau
service php82-php-fpm restart

# Atau via cPanel: WHM → Service Configuration → Service Manager → Restart PHP-FPM
```

### 2. Monitor Real-time (5-10 menit pertama)

```bash
# Check processes count (harusnya naik dari 43 ke ~50-80)
watch -n 2 'ps aux | grep php-fpm | wc -l'

# Check load average (harusnya turun dari 38 ke < 20)
watch -n 2 'uptime'

# Check CPU usage
top
```

### 3. Monitor Extended (1-2 jam)

```bash
# Check processes
ps aux | grep php-fpm | wc -l

# Check load average
uptime

# Check memory usage per process
ps aux | grep php-fpm | awk '{sum+=$6} END {print sum/1024 " MB"}'

# Check PHP-FPM status (jika enabled)
curl http://localhost/status
```

## Expected Results

### Immediate (5-10 menit):
- ✅ Processes: 43 → 50-80
- ✅ Load average: 38 → 25-30
- ✅ CPU: 100% → 80-90%

### After 1-2 hours:
- ✅ Load average: 25-30 → 15-20
- ✅ CPU: 80-90% → 60-80%
- ✅ Response time: Lebih cepat

## Warning Signs

### ⚠️ Jika Load Average Masih > 30:
- Ada masalah di application code
- Perlu check slow queries
- Perlu optimize database

### ⚠️ Jika CPU Masih 100%:
- Ada bottleneck lain (database, network, dll)
- Perlu check MySQL slow query log
- Perlu review application code

### ⚠️ Jika Memory Habis:
- Reduce max_children ke 60
- Reduce memory_limit per process
- Check memory leaks

## Commands untuk Troubleshooting

### Check Slow Queries
```bash
# MySQL slow query log
tail -f /var/log/mysql/slow-query.log

# Laravel log
tail -f storage/logs/laravel.log | grep -i "slow\|query"
```

### Check PHP-FPM Slow Log
```bash
tail -f /var/log/php-fpm/www-slow.log
```

### Check Database Connections
```bash
mysql -u root -p -e "SHOW PROCESSLIST;"
mysql -u root -p -e "SHOW STATUS LIKE 'Threads_connected';"
```

### Check Application Errors
```bash
tail -f storage/logs/laravel.log
```

## Next Steps

1. **Monitor 2-3 jam** setelah update
2. **Jika hasilnya baik** (load < 20, CPU < 80%):
   - ✅ Konfigurasi sudah optimal
   - Lanjutkan monitoring harian
3. **Jika masih ada masalah**:
   - Check slow queries
   - Review application code
   - Optimize database indexes
