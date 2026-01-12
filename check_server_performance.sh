#!/bin/bash

# =====================================================
# Script untuk Check Server Performance
# =====================================================
# Script ini akan menampilkan:
# 1. CPU Usage
# 2. Memory Usage
# 3. Disk I/O
# 4. MySQL Status
# 5. PHP-FPM Status
# 6. Active Connections
# =====================================================

echo "=========================================="
echo "SERVER PERFORMANCE CHECK"
echo "=========================================="
echo ""

# 1. CPU Usage
echo "1. CPU USAGE:"
echo "----------------------------------------"
top -bn1 | grep "Cpu(s)" | awk '{print "CPU Usage: " $2}'
echo ""

# 2. Memory Usage
echo "2. MEMORY USAGE:"
echo "----------------------------------------"
free -h
echo ""

# 3. Disk Usage
echo "3. DISK USAGE:"
echo "----------------------------------------"
df -h | grep -E "^/dev|Filesystem"
echo ""

# 4. MySQL Status
echo "4. MYSQL STATUS:"
echo "----------------------------------------"
mysql -u root -p -e "SHOW STATUS LIKE 'Threads_connected';" 2>/dev/null || echo "MySQL not accessible"
mysql -u root -p -e "SHOW STATUS LIKE 'Threads_running';" 2>/dev/null || echo ""
mysql -u root -p -e "SHOW STATUS LIKE 'Slow_queries';" 2>/dev/null || echo ""
echo ""

# 5. PHP-FPM Status
echo "5. PHP-FPM STATUS:"
echo "----------------------------------------"
PHP_FPM_PROCESSES=$(ps aux | grep php-fpm | grep -v grep | wc -l)
echo "PHP-FPM Processes: $PHP_FPM_PROCESSES"
ps aux | grep php-fpm | grep -v grep | head -5
echo ""

# 6. Active MySQL Connections
echo "6. ACTIVE MYSQL CONNECTIONS:"
echo "----------------------------------------"
mysql -u root -p -e "SHOW PROCESSLIST;" 2>/dev/null | head -10 || echo "MySQL not accessible"
echo ""

# 7. Top 10 Processes by CPU
echo "7. TOP 10 PROCESSES BY CPU:"
echo "----------------------------------------"
ps aux --sort=-%cpu | head -11
echo ""

# 8. Top 10 Processes by Memory
echo "8. TOP 10 PROCESSES BY MEMORY:"
echo "----------------------------------------"
ps aux --sort=-%mem | head -11
echo ""

# 9. Network Connections
echo "9. NETWORK CONNECTIONS:"
echo "----------------------------------------"
netstat -an | grep ESTABLISHED | wc -l | awk '{print "Established Connections: " $1}'
echo ""

# 10. Laravel Queue Status
echo "10. LARAVEL QUEUE STATUS:"
echo "----------------------------------------"
QUEUE_WORKERS=$(ps aux | grep "queue:work" | grep -v grep | wc -l)
echo "Queue Workers: $QUEUE_WORKERS"
ps aux | grep "queue:work" | grep -v grep | head -5
echo ""

# 11. Scheduled Tasks
echo "11. SCHEDULED TASKS (CRON):"
echo "----------------------------------------"
CRON_JOBS=$(ps aux | grep "schedule:run" | grep -v grep | wc -l)
echo "Running Cron Jobs: $CRON_JOBS"
ps aux | grep "schedule:run" | grep -v grep | head -5
echo ""

echo "=========================================="
echo "CHECK COMPLETED"
echo "=========================================="
