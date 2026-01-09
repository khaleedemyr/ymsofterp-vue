#!/bin/bash

echo "=========================================="
echo "🔍 CHECK SLOW QUERIES & DATABASE PERFORMANCE"
echo "=========================================="
echo ""

# Get MySQL credentials (you may need to adjust this)
MYSQL_USER="root"
MYSQL_PASS=""
DB_NAME="justusku_cms"

# Function to run MySQL command
mysql_cmd() {
    if [ -z "$MYSQL_PASS" ]; then
        mysql -u "$MYSQL_USER" -e "$1" 2>/dev/null
    else
        mysql -u "$MYSQL_USER" -p"$MYSQL_PASS" -e "$1" 2>/dev/null
    fi
}

# 1. Check Slow Query Log Status
echo "=== 1. SLOW QUERY LOG STATUS ==="
mysql_cmd "SHOW VARIABLES LIKE 'slow_query_log';"
mysql_cmd "SHOW VARIABLES LIKE 'long_query_time';"
mysql_cmd "SHOW VARIABLES LIKE 'slow_query_log_file';"
echo ""

# 2. Check Queries Currently Running (> 5 seconds)
echo "=== 2. QUERIES RUNNING > 5 SECONDS ==="
mysql_cmd "SELECT id, user, host, db, command, time, state, LEFT(info, 100) as query FROM information_schema.processlist WHERE time > 5 AND command != 'Sleep' ORDER BY time DESC;" 2>/dev/null || echo "No queries running > 5 seconds"
echo ""

# 3. Check All Running Queries
echo "=== 3. ALL RUNNING QUERIES (Non-Sleep) ==="
mysql_cmd "SELECT id, user, db, command, time, state, LEFT(info, 80) as query FROM information_schema.processlist WHERE command != 'Sleep' ORDER BY time DESC LIMIT 10;" 2>/dev/null || echo "No queries running"
echo ""

# 4. Check Queries with Locks
echo "=== 4. QUERIES WITH LOCKS ==="
mysql_cmd "SELECT id, user, db, command, time, state, LEFT(info, 80) as query FROM information_schema.processlist WHERE state LIKE '%lock%' ORDER BY time DESC;" 2>/dev/null || echo "No queries with locks"
echo ""

# 5. Check Slow Query Log File (if exists)
echo "=== 5. SLOW QUERY LOG FILE (Last 5 entries) ==="
SLOW_LOG=$(mysql_cmd "SHOW VARIABLES LIKE 'slow_query_log_file';" | grep slow_query_log_file | awk '{print $2}')
if [ -n "$SLOW_LOG" ] && [ -f "$SLOW_LOG" ]; then
    echo "Log file: $SLOW_LOG"
    echo "Last 5 slow queries:"
    tail -50 "$SLOW_LOG" | grep -A 5 "Query_time" | tail -30
else
    echo "Slow query log file not found or not enabled"
    echo "Enable with: SET GLOBAL slow_query_log = 'ON';"
fi
echo ""

# 6. Check Table Sizes
echo "=== 6. TOP 10 LARGEST TABLES ==="
mysql_cmd "SELECT TABLE_NAME, ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) AS 'Size (MB)', TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$DB_NAME' ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC LIMIT 10;" 2>/dev/null || echo "Cannot check table sizes"
echo ""

# 7. Check Indexes
echo "=== 7. TABLES WITHOUT PRIMARY KEY (Potential Issue) ==="
mysql_cmd "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$DB_NAME' AND TABLE_TYPE = 'BASE TABLE' AND TABLE_NAME NOT IN (SELECT DISTINCT TABLE_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = '$DB_NAME' AND INDEX_NAME = 'PRIMARY') LIMIT 10;" 2>/dev/null || echo "Cannot check indexes"
echo ""

# 8. Check MySQL Status
echo "=== 8. MYSQL STATUS ==="
mysql_cmd "SHOW STATUS LIKE 'Slow_queries';"
mysql_cmd "SHOW STATUS LIKE 'Threads_connected';"
mysql_cmd "SHOW STATUS LIKE 'Threads_running';"
mysql_cmd "SHOW STATUS LIKE 'Max_used_connections';"
echo ""

# 9. Recommendations
echo "=========================================="
echo "📋 RECOMMENDATIONS"
echo "=========================================="
echo ""

# Check if slow query log is enabled
SLOW_LOG_ENABLED=$(mysql_cmd "SHOW VARIABLES LIKE 'slow_query_log';" | grep slow_query_log | awk '{print $2}')
if [ "$SLOW_LOG_ENABLED" != "ON" ]; then
    echo "❌ Slow query log is NOT enabled"
    echo "   Enable with: SET GLOBAL slow_query_log = 'ON';"
    echo "   Set threshold: SET GLOBAL long_query_time = 1;"
    echo ""
fi

# Check for long running queries
LONG_QUERIES=$(mysql_cmd "SELECT COUNT(*) FROM information_schema.processlist WHERE time > 5 AND command != 'Sleep';" 2>/dev/null | tail -1)
if [ -n "$LONG_QUERIES" ] && [ "$LONG_QUERIES" -gt 0 ]; then
    echo "⚠️  Found $LONG_QUERIES queries running > 5 seconds"
    echo "   Check queries above and optimize them"
    echo ""
fi

# Check connections
CONNECTIONS=$(mysql_cmd "SHOW STATUS LIKE 'Threads_connected';" | grep Threads_connected | awk '{print $2}')
MAX_CONNECTIONS=$(mysql_cmd "SHOW VARIABLES LIKE 'max_connections';" | grep max_connections | awk '{print $2}')
if [ -n "$CONNECTIONS" ] && [ -n "$MAX_CONNECTIONS" ]; then
    PERCENTAGE=$((CONNECTIONS * 100 / MAX_CONNECTIONS))
    if [ "$PERCENTAGE" -gt 80 ]; then
        echo "⚠️  MySQL connections high: $CONNECTIONS/$MAX_CONNECTIONS ($PERCENTAGE%)"
        echo "   Consider increasing max_connections or optimizing queries"
        echo ""
    fi
fi

echo "=========================================="
echo "✅ Check complete!"
echo "=========================================="
echo ""
echo "Next steps:"
echo "1. Enable slow query log if not enabled"
echo "2. Monitor slow query log for 1-2 hours"
echo "3. Analyze slow queries and add indexes"
echo "4. Optimize queries that are running > 5 seconds"
