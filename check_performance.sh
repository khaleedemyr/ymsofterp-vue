#!/bin/bash

# Script untuk cek performa server
# Jalankan dengan: bash check_performance.sh

echo "=========================================="
echo "DIAGNOSTIC: SERVER PERFORMANCE CHECK"
echo "=========================================="
echo ""

echo "1. Checking MySQL Processes..."
echo "----------------------------------------"
mysql -u root -p -e "SELECT ID, USER, HOST, DB, COMMAND, TIME, STATE, LEFT(INFO, 100) as QUERY FROM information_schema.PROCESSLIST WHERE COMMAND != 'Sleep' ORDER BY TIME DESC LIMIT 10;"
echo ""

echo "2. Checking Stuck Processes (> 30 seconds)..."
echo "----------------------------------------"
mysql -u root -p -e "SELECT ID, USER, HOST, DB, COMMAND, TIME as TIME_SECONDS, STATE, LEFT(INFO, 200) as QUERY FROM information_schema.PROCESSLIST WHERE TIME > 30 ORDER BY TIME DESC;"
echo ""

echo "3. Checking MySQL Connections..."
echo "----------------------------------------"
mysql -u root -p -e "SHOW STATUS LIKE 'Threads_connected'; SHOW STATUS LIKE 'Threads_running'; SHOW STATUS LIKE 'Max_used_connections'; SHOW VARIABLES LIKE 'max_connections';"
echo ""

echo "4. Checking Queue Workers..."
echo "----------------------------------------"
ps aux | grep "queue:work" | grep -v grep
echo ""

echo "5. Checking Scheduled Tasks..."
echo "----------------------------------------"
ps aux | grep "schedule:run" | grep -v grep
echo ""

echo "6. Checking PHP-FPM Processes..."
echo "----------------------------------------"
ps aux | grep php-fpm | wc -l
echo "PHP-FPM processes count: $(ps aux | grep php-fpm | wc -l)"
echo ""

echo "7. Checking Recent Laravel Errors..."
echo "----------------------------------------"
if [ -f "storage/logs/laravel.log" ]; then
    tail -20 storage/logs/laravel.log | grep -i "error\|exception\|timeout" || echo "No recent errors found"
else
    echo "Laravel log file not found"
fi
echo ""

echo "8. Checking System Resources..."
echo "----------------------------------------"
echo "CPU Usage:"
top -bn1 | grep "Cpu(s)" | sed "s/.*, *\([0-9.]*\)%* id.*/\1/" | awk '{print "CPU Idle: " 100 - $1"%"}'
echo ""
echo "Memory Usage:"
free -h
echo ""

echo "9. Checking Disk Usage..."
echo "----------------------------------------"
df -h | head -5
echo ""

echo "=========================================="
echo "DIAGNOSTIC COMPLETE"
echo "=========================================="
