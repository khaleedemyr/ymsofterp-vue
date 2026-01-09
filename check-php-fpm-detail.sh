#!/bin/bash

echo "=========================================="
echo "🔍 DETAILED PHP-FPM DIAGNOSIS"
echo "=========================================="
echo ""

# 1. Check PHP-FPM config
echo "1️⃣ PHP-FPM CONFIG (Max Children):"
echo "----------------------------------------"
find /opt/cpanel/ea-php82/root/etc/php-fpm.d -name "*.conf" -exec grep -H "pm.max_children" {} \; 2>/dev/null | head -5
echo ""

# 2. Check active PHP-FPM processes with details
echo "2️⃣ TOP 10 PHP-FPM PROCESSES (CPU):"
echo "----------------------------------------"
ps aux | grep php-fpm | grep -v grep | sort -k3 -rn | head -10
echo ""

# 3. Check total PHP-FPM processes
echo "3️⃣ TOTAL PHP-FPM PROCESSES:"
echo "----------------------------------------"
TOTAL=$(ps aux | grep php-fpm | grep -v grep | wc -l)
echo "Total: $TOTAL processes"
echo ""

# 4. Check what PHP-FPM processes are doing
echo "4️⃣ PHP-FPM PROCESSES STATE:"
echo "----------------------------------------"
ps aux | grep php-fpm | grep -v grep | awk '{print $8}' | sort | uniq -c
echo ""

# 5. Check active HTTP connections
echo "5️⃣ ACTIVE HTTP/HTTPS CONNECTIONS:"
echo "----------------------------------------"
HTTP_CONN=$(netstat -an | grep :80 | grep ESTABLISHED | wc -l)
HTTPS_CONN=$(netstat -an | grep :443 | grep ESTABLISHED | wc -l)
echo "HTTP (port 80): $HTTP_CONN connections"
echo "HTTPS (port 443): $HTTPS_CONN connections"
echo "Total: $((HTTP_CONN + HTTPS_CONN)) connections"
echo ""

# 6. Check MySQL connections from PHP
echo "6️⃣ MYSQL CONNECTIONS FROM PHP:"
echo "----------------------------------------"
mysql -u root -p -e "
SELECT 
    SUBSTRING_INDEX(host, ':', 1) as host_ip,
    COUNT(*) as connections,
    SUM(CASE WHEN command != 'Sleep' THEN 1 ELSE 0 END) as active_queries
FROM information_schema.processlist 
WHERE user LIKE 'justusku%' OR user LIKE 'mysql.%'
GROUP BY SUBSTRING_INDEX(host, ':', 1)
ORDER BY connections DESC;
" 2>/dev/null | head -10

if [ $? -ne 0 ]; then
    echo "⚠️  Cannot connect to MySQL (need password)"
fi
echo ""

# 7. Check top CPU consumers (all processes)
echo "7️⃣ TOP 10 CPU CONSUMERS (ALL PROCESSES):"
echo "----------------------------------------"
ps aux --sort=-%cpu | head -11 | tail -10
echo ""

# 8. Check if PHP-FPM slow log exists
echo "8️⃣ PHP-FPM SLOW LOG:"
echo "----------------------------------------"
SLOW_LOG="/opt/cpanel/ea-php82/root/var/log/php-fpm-slow.log"
if [ -f "$SLOW_LOG" ]; then
    echo "✅ Slow log exists: $SLOW_LOG"
    echo "Last 5 slow requests:"
    tail -20 "$SLOW_LOG" | grep -A 10 "script_filename" | head -30
else
    echo "⚠️  Slow log not found: $SLOW_LOG"
    echo "   Enable it in PHP-FPM config:"
    echo "   slowlog = $SLOW_LOG"
    echo "   request_slowlog_timeout = 5s"
fi
echo ""

# 9. Check system load
echo "9️⃣ SYSTEM LOAD:"
echo "----------------------------------------"
uptime
echo ""

# 10. Summary
echo "=========================================="
echo "📋 SUMMARY"
echo "=========================================="
echo ""

if [ "$TOTAL" -gt 16 ]; then
    echo "⚠️  WARNING: PHP-FPM processes ($TOTAL) > 16"
    echo "   → Max Children mungkin masih terlalu tinggi"
    echo "   → Recommended: Max Children = 12-16 untuk 8 vCPU"
else
    echo "✅ PHP-FPM processes ($TOTAL) dalam range normal"
fi

echo ""
echo "CPU per PHP-FPM process:"
TOP_CPU=$(ps aux | grep php-fpm | grep -v grep | sort -k3 -rn | head -1 | awk '{print $3}')
if [ -n "$TOP_CPU" ]; then
    if (( $(echo "$TOP_CPU > 10" | bc -l) )); then
        echo "⚠️  WARNING: Top process CPU = ${TOP_CPU}% (ABNORMAL!)"
        echo "   → Normal: < 5% per process"
        echo "   → Kemungkinan ada query berat atau infinite loop"
    else
        echo "✅ Top process CPU = ${TOP_CPU}% (normal)"
    fi
fi

echo ""
echo "=========================================="
