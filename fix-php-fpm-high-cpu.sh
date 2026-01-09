#!/bin/bash

echo "=========================================="
echo "🔥 FIX PHP-FPM HIGH CPU"
echo "=========================================="
echo ""

# 1. Check current status
echo "1️⃣ CURRENT STATUS:"
echo "----------------------------------------"
PHP_FPM_COUNT=$(ps aux | grep php-fpm | grep -v grep | wc -l)
PHP_FPM_CPU=$(ps aux | grep php-fpm | grep -v grep | awk '{sum+=$3} END {printf "%.1f", sum}')
LOAD_AVG=$(uptime | awk -F'load average:' '{print $2}')

echo "PHP-FPM processes: $PHP_FPM_COUNT"
echo "Total CPU usage: ${PHP_FPM_CPU}%"
echo "Load Average: $LOAD_AVG"
echo ""

# 2. Check top PHP-FPM processes
echo "2️⃣ TOP 5 PHP-FPM PROCESSES (by CPU):"
echo "----------------------------------------"
ps aux | grep php-fpm | grep -v grep | sort -k3 -rn | head -5 | awk '{printf "PID: %-6s CPU: %5s%% TIME: %s\n", $2, $3, $10}'
echo ""

# 3. Check MySQL running queries
echo "3️⃣ MYSQL RUNNING QUERIES:"
echo "----------------------------------------"
mysql -u root -p -e "SHOW PROCESSLIST;" 2>/dev/null | head -10
echo ""

# 4. Check PHP-FPM config
echo "4️⃣ PHP-FPM CONFIG:"
echo "----------------------------------------"
if [ -f "/opt/cpanel/ea-php82/root/etc/php-fpm.d/www.conf" ]; then
    MAX_CHILDREN=$(grep "pm.max_children" /opt/cpanel/ea-php82/root/etc/php-fpm.d/www.conf | head -1 | awk '{print $3}' | tr -d ';')
    echo "Max Children: $MAX_CHILDREN"
    
    if [ -n "$MAX_CHILDREN" ] && [ "$MAX_CHILDREN" -gt 20 ]; then
        echo "⚠️  WARNING: Max Children terlalu tinggi! ($MAX_CHILDREN)"
        echo "   → Kurangi ke 16 via cPanel"
    fi
else
    echo "⚠️  Config file tidak ditemukan"
    echo "   → Check via cPanel: MultiPHP Manager → PHP-FPM Settings"
fi
echo ""

# 5. Recommendations
echo "=========================================="
echo "📋 RECOMMENDATIONS"
echo "=========================================="
echo ""

if [ "$PHP_FPM_COUNT" -gt 20 ]; then
    echo "🔴 ACTION REQUIRED:"
    echo ""
    echo "1. Restart PHP-FPM untuk kill hung processes:"
    echo "   systemctl restart ea-php82-php-fpm"
    echo ""
    echo "2. Kurangi Max Children ke 16:"
    echo "   → Via cPanel: MultiPHP Manager → PHP-FPM Settings"
    echo "   → Set Max Children: 16"
    echo "   → Set Max Requests: 50"
    echo "   → Restart PHP-FPM"
    echo ""
    echo "3. Check MySQL running queries:"
    echo "   mysql -u root -p -e 'SHOW PROCESSLIST;'"
    echo ""
    echo "4. Monitor setelah perubahan:"
    echo "   watch -n 5 'ps aux | grep php-fpm | grep -v grep | wc -l'"
    echo ""
else
    echo "✅ PHP-FPM processes sudah OK"
    echo ""
    echo "Jika CPU masih tinggi, check:"
    echo "1. MySQL running queries"
    echo "2. Background processes lain"
    echo "3. Memory usage"
    echo ""
fi

echo "=========================================="
