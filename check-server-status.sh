#!/bin/bash

# Script untuk check status server dan proses yang berjalan
# Usage: bash check-server-status.sh

echo "=========================================="
echo "Server Status Check"
echo "=========================================="
echo ""

# CPU Usage
echo "1. CPU Usage:"
echo "   $(top -bn1 | grep "Cpu(s)" | sed "s/.*, *\([0-9.]*\)%* id.*/\1/" | awk '{print 100 - $1"%"}')"
echo ""

# Memory Usage
echo "2. Memory Usage:"
free -h | grep -E "Mem|Swap"
echo ""

# PHP-FPM Processes
echo "3. PHP-FPM Processes:"
PHPFPM_COUNT=$(ps aux | grep 'php-fpm' | grep -v grep | wc -l)
echo "   Total: $PHPFPM_COUNT processes"
ps aux | grep 'php-fpm' | grep -v grep | head -5 | awk '{print "   PID: "$2" | CPU: "$3"% | MEM: "$4"% | CMD: "$11" "$12" "$13}'
if [ $PHPFPM_COUNT -gt 5 ]; then
    echo "   ... and $((PHPFPM_COUNT - 5)) more"
fi
echo ""

# Queue Workers
echo "4. Queue Workers:"
QUEUE_COUNT=$(ps aux | grep 'queue:work' | grep -v grep | wc -l)
echo "   Total: $QUEUE_COUNT worker(s)"
if [ $QUEUE_COUNT -gt 0 ]; then
    ps aux | grep 'queue:work' | grep -v grep | awk '{print "   PID: "$2" | CPU: "$3"% | MEM: "$4"% | Started: "$9" | CMD: "$11" "$12" "$13" "$14" "$15}'
fi
if [ $QUEUE_COUNT -gt 5 ]; then
    echo "   ⚠️  WARNING: Too many queue workers! This is likely causing high CPU."
fi
echo ""

# Laravel Schedule Processes
echo "5. Laravel Schedule Processes:"
SCHEDULE_COUNT=$(ps aux | grep 'schedule:run' | grep -v grep | wc -l)
echo "   Total: $SCHEDULE_COUNT process(es)"
if [ $SCHEDULE_COUNT -gt 0 ]; then
    ps aux | grep 'schedule:run' | grep -v grep | awk '{print "   PID: "$2" | CPU: "$3"% | MEM: "$4"%"}'
fi
echo ""

# Top CPU Consumers
echo "6. Top 10 CPU Consumers:"
echo "   PID    CPU%   MEM%   COMMAND"
ps aux --sort=-%cpu | head -11 | tail -10 | awk '{printf "   %-6s %-6s %-6s %s\n", $2, $3"%", $4"%", $11" "$12" "$13" "$14" "$15}'
echo ""

# Top Memory Consumers
echo "7. Top 10 Memory Consumers:"
echo "   PID    CPU%   MEM%   COMMAND"
ps aux --sort=-%mem | head -11 | tail -10 | awk '{printf "   %-6s %-6s %-6s %s\n", $2, $3"%", $4"%", $11" "$12" "$13" "$14" "$15}'
echo ""

# Queue Status (if Laravel is accessible)
echo "8. Queue Status (Laravel):"
if [ -f "/home/ymsuperadmin/public_html/artisan" ]; then
    cd /home/ymsuperadmin/public_html
    QUEUE_JOBS=$(php artisan queue:monitor notifications 2>/dev/null | grep -E "jobs|waiting" | head -1)
    if [ ! -z "$QUEUE_JOBS" ]; then
        echo "   $QUEUE_JOBS"
    else
        echo "   (Run 'php artisan queue:monitor notifications' for details)"
    fi
else
    echo "   (Laravel artisan not found)"
fi
echo ""

echo "=========================================="
echo "Recommendations:"
if [ $QUEUE_COUNT -gt 5 ]; then
    echo "⚠️  Fix queue worker - too many instances running"
fi
if [ $PHPFPM_COUNT -gt 30 ]; then
    echo "⚠️  Consider reducing PHP-FPM Max Children"
fi
echo "=========================================="

