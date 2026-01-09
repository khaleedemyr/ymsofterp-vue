#!/bin/bash

echo "=========================================="
echo "🔍 CHECK PENYEBAB CPU 100%"
echo "=========================================="
echo ""

# 1. Check PHP-FPM Processes
echo "1️⃣ PHP-FPM PROCESSES:"
echo "----------------------------------------"
PHP_FPM_COUNT=$(ps aux | grep php-fpm | grep -v grep | wc -l)
PHP_FPM_CPU=$(ps aux | grep php-fpm | grep -v grep | awk '{sum+=$3} END {printf "%.1f", sum}')
PHP_FPM_MEM=$(ps aux | grep php-fpm | grep -v grep | awk '{sum+=$6} END {printf "%.0f", sum/1024}')

echo "Total PHP-FPM processes: $PHP_FPM_COUNT"
echo "Total CPU usage: ${PHP_FPM_CPU}%"
echo "Total Memory: ${PHP_FPM_MEM} MB"
echo ""

if [ "$PHP_FPM_COUNT" -gt 30 ]; then
    echo "⚠️  WARNING: Terlalu banyak PHP-FPM processes! (> 30)"
    echo "   → Kurangi Max Children di PHP-FPM settings"
elif [ "$PHP_FPM_COUNT" -gt 20 ]; then
    echo "⚠️  WARNING: PHP-FPM processes agak banyak (> 20)"
    echo "   → Pertimbangkan kurangi Max Children"
else
    echo "✅ PHP-FPM processes: OK"
fi
echo ""

# 2. Check Top PHP-FPM Processes by CPU
echo "2️⃣ TOP 5 PHP-FPM PROCESSES (by CPU):"
echo "----------------------------------------"
ps aux | grep php-fpm | grep -v grep | sort -k3 -rn | head -5 | awk '{printf "PID: %-6s CPU: %5s%% MEM: %6s MB\n", $2, $3, $6}'
echo ""

# 3. Check Queue Workers
echo "3️⃣ QUEUE WORKERS:"
echo "----------------------------------------"
QUEUE_COUNT=$(ps aux | grep 'queue:work' | grep -v grep | wc -l)
QUEUE_CPU=$(ps aux | grep 'queue:work' | grep -v grep | awk '{sum+=$3} END {printf "%.1f", sum}')

echo "Total Queue Workers: $QUEUE_COUNT"
echo "Total CPU usage: ${QUEUE_CPU}%"
echo ""

if [ "$QUEUE_COUNT" -gt 5 ]; then
    echo "⚠️  WARNING: Terlalu banyak Queue Workers! (> 5)"
    echo "   → Hapus cron job queue worker yang berjalan setiap menit"
    echo "   → Gunakan Supervisor untuk manage queue workers"
elif [ "$QUEUE_COUNT" -gt 2 ]; then
    echo "⚠️  WARNING: Queue Workers agak banyak (> 2)"
    echo "   → Idealnya hanya 2 workers via Supervisor"
else
    echo "✅ Queue Workers: OK"
fi
echo ""

# 4. Check MySQL Processes
echo "4️⃣ MYSQL PROCESSES:"
echo "----------------------------------------"
MYSQL_COUNT=$(ps aux | grep mysql | grep -v grep | wc -l)
MYSQL_CPU=$(ps aux | grep mysql | grep -v grep | awk '{sum+=$3} END {printf "%.1f", sum}')

echo "Total MySQL processes: $MYSQL_COUNT"
echo "Total CPU usage: ${MYSQL_CPU}%"
echo ""

# Check running queries
echo "Running queries:"
mysql -u root -p -e "SHOW PROCESSLIST;" 2>/dev/null | head -10
echo ""

# 5. Check System Load
echo "5️⃣ SYSTEM LOAD:"
echo "----------------------------------------"
LOAD_AVG=$(uptime | awk -F'load average:' '{print $2}')
CPU_COUNT=$(nproc)
echo "Load Average: $LOAD_AVG"
echo "CPU Cores: $CPU_COUNT"
echo ""

# 6. Check Top 10 Processes by CPU
echo "6️⃣ TOP 10 PROCESSES (by CPU):"
echo "----------------------------------------"
ps aux --sort=-%cpu | head -11 | awk '{printf "%-8s %5s%% %6s MB %s\n", $2, $3, $6, $11}'
echo ""

# 7. Check PHP-FPM Config (if accessible)
echo "7️⃣ PHP-FPM CONFIG CHECK:"
echo "----------------------------------------"
if [ -f "/opt/cpanel/ea-php82/root/etc/php-fpm.d/www.conf" ]; then
    MAX_CHILDREN=$(grep "pm.max_children" /opt/cpanel/ea-php82/root/etc/php-fpm.d/www.conf | head -1 | awk '{print $3}' | tr -d ';')
    echo "Max Children: $MAX_CHILDREN"
    
    if [ -n "$MAX_CHILDREN" ] && [ "$MAX_CHILDREN" -gt 30 ]; then
        echo "⚠️  WARNING: Max Children terlalu tinggi! ($MAX_CHILDREN)"
        echo "   → Kurangi ke 20-24 untuk 8 vCPU"
    elif [ -n "$MAX_CHILDREN" ] && [ "$MAX_CHILDREN" -gt 24 ]; then
        echo "⚠️  WARNING: Max Children agak tinggi ($MAX_CHILDREN)"
        echo "   → Pertimbangkan kurangi ke 20-24"
    else
        echo "✅ Max Children: OK"
    fi
else
    echo "⚠️  Config file tidak ditemukan"
    echo "   → Check via cPanel: MultiPHP Manager → PHP-FPM Settings"
fi
echo ""

# 8. Summary & Recommendations
echo "=========================================="
echo "📋 SUMMARY & RECOMMENDATIONS"
echo "=========================================="
echo ""

if [ "$PHP_FPM_COUNT" -gt 30 ] || [ "$QUEUE_COUNT" -gt 5 ]; then
    echo "🔴 ACTION REQUIRED:"
    echo ""
    
    if [ "$PHP_FPM_COUNT" -gt 30 ]; then
        echo "1. Kurangi PHP-FPM Max Children:"
        echo "   → Via cPanel: MultiPHP Manager → PHP-FPM Settings"
        echo "   → Set Max Children: 20-24 (untuk 8 vCPU)"
        echo "   → Set Max Requests: 100"
        echo "   → Restart PHP-FPM"
        echo ""
    fi
    
    if [ "$QUEUE_COUNT" -gt 5 ]; then
        echo "2. Fix Queue Workers:"
        echo "   → Hapus cron job queue worker yang berjalan setiap menit"
        echo "   → Gunakan Supervisor untuk manage queue workers (2 workers)"
        echo ""
    fi
    
    echo "3. Monitor setelah perubahan:"
    echo "   → Jalankan script ini lagi setelah 5-10 menit"
    echo "   → Check CPU usage: top"
    echo ""
else
    echo "✅ Semua sudah optimal!"
    echo ""
    echo "Jika CPU masih 100%, check:"
    echo "1. Apakah ada process lain yang consume CPU tinggi?"
    echo "2. Apakah ada slow queries di database?"
    echo "3. Apakah ada memory leak di aplikasi?"
    echo ""
fi

echo "=========================================="
