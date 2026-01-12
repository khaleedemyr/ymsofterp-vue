#!/bin/bash

# =====================================================
# Real-Time Server Performance Monitor
# =====================================================
# Script ini akan monitor server performance secara real-time
# =====================================================

INTERVAL=${1:-5}  # Default 5 detik
DURATION=${2:-60}  # Default 60 detik

echo "=========================================="
echo "REAL-TIME SERVER PERFORMANCE MONITOR"
echo "=========================================="
echo "Interval: $INTERVAL seconds"
echo "Duration: $DURATION seconds"
echo "Press Ctrl+C to stop"
echo "=========================================="
echo ""

END_TIME=$(($(date +%s) + DURATION))
ITERATION=0

while [ $(date +%s) -lt $END_TIME ]; do
    ITERATION=$((ITERATION + 1))
    echo "=== Iteration #$ITERATION - $(date '+%Y-%m-%d %H:%M:%S') ==="
    echo ""

    # 1. MySQL Processes
    echo "1. ACTIVE MYSQL PROCESSES:"
    echo "----------------------------------------"
    mysql -u root -p -e "
        SELECT 
            id,
            user,
            db,
            command,
            time,
            state,
            LEFT(info, 80) as query
        FROM information_schema.processlist
        WHERE command != 'Sleep'
        ORDER BY time DESC
        LIMIT 5;
    " 2>/dev/null || echo "MySQL not accessible"
    echo ""

    # 2. MySQL Status
    echo "2. MYSQL STATUS:"
    echo "----------------------------------------"
    mysql -u root -p -e "
        SHOW STATUS WHERE Variable_name IN (
            'Threads_connected',
            'Threads_running',
            'Slow_queries',
            'Questions'
        );
    " 2>/dev/null || echo "MySQL not accessible"
    echo ""

    # 3. CPU Usage
    echo "3. CPU USAGE:"
    echo "----------------------------------------"
    top -bn1 | grep "Cpu(s)" | awk '{print "CPU: " $2}'
    echo ""

    # 4. Memory Usage
    echo "4. MEMORY USAGE:"
    echo "----------------------------------------"
    free -h | grep -E "^Mem|^Swap"
    echo ""

    # 5. Top 5 Processes by CPU
    echo "5. TOP 5 PROCESSES BY CPU:"
    echo "----------------------------------------"
    ps aux --sort=-%cpu | head -6 | tail -5
    echo ""

    # 6. PHP-FPM Processes
    echo "6. PHP-FPM PROCESSES:"
    echo "----------------------------------------"
    PHP_FPM_COUNT=$(ps aux | grep php-fpm | grep -v grep | wc -l)
    echo "PHP-FPM Count: $PHP_FPM_COUNT"
    ps aux | grep php-fpm | grep -v grep | head -3
    echo ""

    # 7. Queue Workers
    echo "7. QUEUE WORKERS:"
    echo "----------------------------------------"
    QUEUE_COUNT=$(ps aux | grep "queue:work" | grep -v grep | wc -l)
    echo "Queue Workers: $QUEUE_COUNT"
    ps aux | grep "queue:work" | grep -v grep | head -3
    echo ""

    # 8. Network Connections
    echo "8. NETWORK CONNECTIONS:"
    echo "----------------------------------------"
    ESTABLISHED=$(netstat -an | grep ESTABLISHED | wc -l)
    echo "Established: $ESTABLISHED"
    echo ""

    echo "=========================================="
    echo ""

    sleep $INTERVAL
done

echo "Monitoring completed!"
