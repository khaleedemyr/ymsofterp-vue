#!/bin/bash

# ============================================
# Script untuk Check PHP-FPM Status
# Server: 8 vCPU / 16GB RAM
# ============================================

echo "============================================"
echo "PHP-FPM Status Check"
echo "============================================"
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 1. Check PHP-FPM Processes Count
echo "1. PHP-FPM Processes Count:"
PHP_FPM_COUNT=$(ps aux | grep php-fpm | grep -v grep | wc -l)
echo "   Total PHP-FPM processes: $PHP_FPM_COUNT"

if [ $PHP_FPM_COUNT -gt 32 ]; then
    echo -e "   Status: ${RED}TOO HIGH (>32)${NC} - Recommended: 20-24"
elif [ $PHP_FPM_COUNT -gt 24 ]; then
    echo -e "   Status: ${YELLOW}HIGH (24-32)${NC} - Recommended: 20-24"
elif [ $PHP_FPM_COUNT -ge 12 ] && [ $PHP_FPM_COUNT -le 24 ]; then
    echo -e "   Status: ${GREEN}OPTIMAL (12-24)${NC}"
else
    echo -e "   Status: ${YELLOW}LOW (<12)${NC} - Might need more for high traffic"
fi
echo ""

# 2. Check PHP-FPM Memory Usage
echo "2. PHP-FPM Memory Usage:"
PHP_FPM_MEMORY=$(ps aux | grep php-fpm | grep -v grep | awk '{sum+=$6} END {print sum/1024}')
echo "   Total Memory: ${PHP_FPM_MEMORY} MB"

# Calculate percentage of 16GB
MEMORY_PERCENT=$(echo "scale=2; ($PHP_FPM_MEMORY * 100) / 16384" | bc)
echo "   Memory Usage: ${MEMORY_PERCENT}% of 16GB"

if (( $(echo "$MEMORY_PERCENT > 25" | bc -l) )); then
    echo -e "   Status: ${RED}HIGH (>25%)${NC} - Consider reducing max_children"
elif (( $(echo "$MEMORY_PERCENT > 15" | bc -l) )); then
    echo -e "   Status: ${YELLOW}MODERATE (15-25%)${NC}"
else
    echo -e "   Status: ${GREEN}GOOD (<15%)${NC}"
fi
echo ""

# 3. Check CPU Usage
echo "3. CPU Usage:"
CPU_USAGE=$(top -bn1 | grep "Cpu(s)" | sed "s/.*, *\([0-9.]*\)%* id.*/\1/" | awk '{print 100 - $1}')
echo "   Current CPU Usage: ${CPU_USAGE}%"

if (( $(echo "$CPU_USAGE > 80" | bc -l) )); then
    echo -e "   Status: ${RED}CRITICAL (>80%)${NC} - Server overloaded"
elif (( $(echo "$CPU_USAGE > 60" | bc -l) )); then
    echo -e "   Status: ${YELLOW}HIGH (60-80%)${NC} - Monitor closely"
elif (( $(echo "$CPU_USAGE > 40" | bc -l) )); then
    echo -e "   Status: ${YELLOW}MODERATE (40-60%)${NC}"
else
    echo -e "   Status: ${GREEN}GOOD (<40%)${NC}"
fi
echo ""

# 4. Check Load Average
echo "4. Load Average:"
LOAD_AVG=$(uptime | awk -F'load average:' '{print $2}' | awk '{print $1}' | sed 's/,//')
echo "   Load Average (1 min): $LOAD_AVG"
echo "   vCPU Count: 8"
echo "   Recommended: < 8.0"

if (( $(echo "$LOAD_AVG > 8" | bc -l) )); then
    echo -e "   Status: ${RED}CRITICAL (>8.0)${NC} - Server overloaded"
elif (( $(echo "$LOAD_AVG > 6" | bc -l) )); then
    echo -e "   Status: ${YELLOW}HIGH (6-8)${NC} - Monitor closely"
elif (( $(echo "$LOAD_AVG > 4" | bc -l) )); then
    echo -e "   Status: ${YELLOW}MODERATE (4-6)${NC}"
else
    echo -e "   Status: ${GREEN}GOOD (<4)${NC}"
fi
echo ""

# 5. Check PHP-FPM Configuration (if accessible)
echo "5. PHP-FPM Configuration Check:"
PHP_FPM_CONFIG=$(php-fpm -tt 2>/dev/null || php-fpm82 -tt 2>/dev/null || echo "")

if [ -n "$PHP_FPM_CONFIG" ]; then
    echo "   Configuration test: OK"
    MAX_CHILDREN=$(echo "$PHP_FPM_CONFIG" | grep "pm.max_children" | awk '{print $3}' | head -1)
    if [ -n "$MAX_CHILDREN" ]; then
        echo "   Max Children: $MAX_CHILDREN"
        if [ "$MAX_CHILDREN" -gt 32 ]; then
            echo -e "   Status: ${RED}TOO HIGH (>32)${NC} - Recommended: 20-24"
        elif [ "$MAX_CHILDREN" -gt 24 ]; then
            echo -e "   Status: ${YELLOW}HIGH (24-32)${NC} - Recommended: 20-24"
        else
            echo -e "   Status: ${GREEN}OK${NC}"
        fi
    fi
else
    echo "   Configuration: Cannot access (might need root/sudo)"
    echo "   Check manually: php-fpm -tt or check config file"
fi
echo ""

# 6. Check Queue Workers (Laravel)
echo "6. Laravel Queue Workers:"
QUEUE_WORKERS=$(ps aux | grep 'queue:work' | grep -v grep | wc -l)
echo "   Queue Workers: $QUEUE_WORKERS"

if [ $QUEUE_WORKERS -gt 5 ]; then
    echo -e "   Status: ${RED}TOO MANY (>5)${NC} - Should be 1-2"
    echo "   Action: Check cron jobs, might be running every minute"
elif [ $QUEUE_WORKERS -gt 2 ]; then
    echo -e "   Status: ${YELLOW}MODERATE (3-5)${NC} - Should be 1-2"
else
    echo -e "   Status: ${GREEN}OK (1-2)${NC}"
fi
echo ""

# 7. Check Slow Log (if exists)
echo "7. PHP-FPM Slow Log:"
SLOW_LOG="/var/log/php-fpm/www-slow.log"
if [ -f "$SLOW_LOG" ]; then
    SLOW_COUNT=$(tail -n 100 "$SLOW_LOG" | grep -c "script_filename" 2>/dev/null || echo "0")
    echo "   Slow requests (last 100 lines): $SLOW_COUNT"
    if [ "$SLOW_COUNT" -gt 10 ]; then
        echo -e "   Status: ${YELLOW}HIGH${NC} - Check slow log for optimization"
    else
        echo -e "   Status: ${GREEN}OK${NC}"
    fi
else
    echo "   Slow log: Not found or not accessible"
fi
echo ""

# 8. Recommendations
echo "============================================"
echo "RECOMMENDATIONS:"
echo "============================================"

if [ $PHP_FPM_COUNT -gt 24 ]; then
    echo "1. ${RED}URGENT:${NC} Reduce PHP-FPM max_children to 20-24"
    echo "   Current: ~$PHP_FPM_COUNT processes"
    echo "   Recommended: 20-24"
    echo ""
fi

if (( $(echo "$CPU_USAGE > 80" | bc -l) )); then
    echo "2. ${RED}URGENT:${NC} CPU usage is critical"
    echo "   Check: queue workers, cron jobs, database queries"
    echo "   See: SOLUSI_LEMOT_SERVER.md"
    echo ""
fi

if [ $QUEUE_WORKERS -gt 2 ]; then
    echo "3. ${RED}URGENT:${NC} Too many queue workers"
    echo "   Should be 1-2, not $QUEUE_WORKERS"
    echo "   Check cron jobs and fix queue worker setup"
    echo ""
fi

if (( $(echo "$LOAD_AVG > 8" | bc -l) )); then
    echo "4. ${RED}URGENT:${NC} Load average is critical"
    echo "   Server is overloaded, reduce processes"
    echo ""
fi

echo "============================================"
echo "Next Steps:"
echo "============================================"
echo "1. Review: OPTIMASI_PHP_FPM_CPU_100.md"
echo "2. Apply PHP-FPM settings from: php-fpm-optimized.conf"
echo "3. Check: SOLUSI_LEMOT_SERVER.md for complete solution"
echo "4. Monitor for 24 hours after changes"
echo ""

