#!/bin/bash

echo "=========================================="
echo "🔍 CHECK PHP-FPM & SUPERVISOR STATUS"
echo "=========================================="
echo ""

# 1. Check PHP-FPM Processes
echo "=== 1. PHP-FPM PROCESSES ==="
PHP_FPM_COUNT=$(ps aux | grep php-fpm | grep -v grep | wc -l)
echo "Total PHP-FPM processes: $PHP_FPM_COUNT"
echo "Expected: 12-24 (bukan 80!)"
echo ""

# 2. Check PHP-FPM Config (cPanel)
echo "=== 2. PHP-FPM CONFIG (cPanel) ==="
if [ -d "/opt/cpanel/ea-php82/root/etc/php-fpm.d" ]; then
    echo "Checking cPanel PHP-FPM config..."
    CONFIG_FILE=$(find /opt/cpanel/ea-php82/root/etc/php-fpm.d -name "*.conf" | head -1)
    if [ -n "$CONFIG_FILE" ]; then
        echo "Config file: $CONFIG_FILE"
        echo ""
        echo "Current settings:"
        grep -E "pm.max_children|pm.start_servers|pm.min_spare_servers|pm.max_spare_servers|pm.max_requests" "$CONFIG_FILE" 2>/dev/null || echo "Settings not found in config file (might be managed by cPanel)"
    else
        echo "Config file not found"
    fi
else
    echo "cPanel PHP-FPM config directory not found"
    echo "Trying standard location..."
    if [ -f "/etc/php-fpm.d/www.conf" ]; then
        echo "Config file: /etc/php-fpm.d/www.conf"
        echo ""
        echo "Current settings:"
        grep -E "pm.max_children|pm.start_servers|pm.min_spare_servers|pm.max_spare_servers|pm.max_requests" /etc/php-fpm.d/www.conf
    elif [ -f "/etc/php/8.2/fpm/pool.d/www.conf" ]; then
        echo "Config file: /etc/php/8.2/fpm/pool.d/www.conf"
        echo ""
        echo "Current settings:"
        grep -E "pm.max_children|pm.start_servers|pm.min_spare_servers|pm.max_spare_servers|pm.max_requests" /etc/php/8.2/fpm/pool.d/www.conf
    else
        echo "PHP-FPM config file not found in standard locations"
    fi
fi
echo ""

# 3. Check Supervisor Status
echo "=== 3. SUPERVISOR STATUS ==="
if command -v supervisorctl &> /dev/null; then
    echo "Supervisor is installed"
    echo ""
    echo "Supervisor service status:"
    systemctl status supervisord --no-pager -l | head -5
    echo ""
    echo "Queue workers via supervisor:"
    supervisorctl status 2>/dev/null || echo "Supervisor not running or no programs configured"
else
    echo "❌ Supervisor is NOT installed"
    echo "Install with: dnf install supervisor -y"
fi
echo ""

# 4. Check Queue Workers
echo "=== 4. QUEUE WORKERS ==="
QUEUE_WORKER_COUNT=$(ps aux | grep 'queue:work' | grep -v grep | wc -l)
echo "Total queue workers: $QUEUE_WORKER_COUNT"
echo "Expected: 2 (via supervisor), bukan 60+!"
if [ "$QUEUE_WORKER_COUNT" -gt 10 ]; then
    echo "⚠️  WARNING: Too many queue workers! Check cron jobs."
fi
echo ""

# 5. Check CPU Usage
echo "=== 5. CPU USAGE ==="
CPU_IDLE=$(top -bn1 | grep "Cpu(s)" | sed "s/.*, *\([0-9.]*\)%* id.*/\1/" | awk '{print $1}')
CPU_USED=$(echo "100 - $CPU_IDLE" | bc)
echo "CPU Usage: ${CPU_USED}%"
echo "CPU Idle: ${CPU_IDLE}%"
if (( $(echo "$CPU_USED > 80" | bc -l) )); then
    echo "⚠️  WARNING: CPU usage is high! (>80%)"
fi
echo ""

# 6. Check Load Average
echo "=== 6. LOAD AVERAGE ==="
uptime
LOAD_1=$(uptime | awk -F'load average:' '{print $2}' | awk '{print $1}' | sed 's/,//')
if (( $(echo "$LOAD_1 > 8.0" | bc -l) )); then
    echo "⚠️  WARNING: Load average is high! (>8.0 for 8 vCPU)"
fi
echo ""

# 7. Check Memory Usage
echo "=== 7. MEMORY USAGE ==="
free -h
echo ""

# 8. Recommendations
echo "=========================================="
echo "📋 RECOMMENDATIONS"
echo "=========================================="
echo ""

if [ "$PHP_FPM_COUNT" -gt 40 ]; then
    echo "❌ PHP-FPM processes too high ($PHP_FPM_COUNT)"
    echo "   → Reduce Max Children to 24 in cPanel PHP-FPM Settings"
fi

if [ "$QUEUE_WORKER_COUNT" -gt 10 ]; then
    echo "❌ Too many queue workers ($QUEUE_WORKER_COUNT)"
    echo "   → Check supervisor status: supervisorctl status"
    echo "   → Remove queue worker from cron: crontab -e"
fi

if (( $(echo "$CPU_USED > 80" | bc -l) )); then
    echo "❌ CPU usage too high (${CPU_USED}%)"
    echo "   → Reduce PHP-FPM Max Children to 24"
    echo "   → Check for slow queries"
fi

echo ""
echo "=========================================="
echo "✅ Check complete!"
echo "=========================================="
