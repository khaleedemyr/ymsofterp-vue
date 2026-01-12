#!/bin/bash

# Script untuk cek slow queries di MySQL
# Jalankan dengan: bash check_slow_queries.sh

echo "=========================================="
echo "CHECKING MYSQL SLOW QUERIES"
echo "=========================================="
echo ""

# MySQL credentials (sesuaikan dengan environment Anda)
MYSQL_USER="root"
MYSQL_PASS=""
MYSQL_HOST="localhost"

echo "1. Checking Slow Query Log Status..."
echo "----------------------------------------"
mysql -u $MYSQL_USER -p$MYSQL_PASS -h $MYSQL_HOST -e "SHOW VARIABLES LIKE 'slow_query_log%';"
mysql -u $MYSQL_USER -p$MYSQL_PASS -h $MYSQL_HOST -e "SHOW VARIABLES LIKE 'long_query_time';"
echo ""

echo "2. Enabling Slow Query Log (if not enabled)..."
echo "----------------------------------------"
mysql -u $MYSQL_USER -p$MYSQL_PASS -h $MYSQL_HOST -e "SET GLOBAL slow_query_log = 'ON';"
mysql -u $MYSQL_USER -p$MYSQL_PASS -h $MYSQL_HOST -e "SET GLOBAL long_query_time = 1;"
mysql -u $MYSQL_USER -p$MYSQL_PASS -h $MYSQL_HOST -e "SET GLOBAL log_queries_not_using_indexes = 'ON';"
echo ""

echo "3. Checking Slow Query Log File Location..."
echo "----------------------------------------"
SLOW_LOG_FILE=$(mysql -u $MYSQL_USER -p$MYSQL_PASS -h $MYSQL_HOST -sN -e "SHOW VARIABLES LIKE 'slow_query_log_file';" | awk '{print $2}')
echo "Slow Query Log File: $SLOW_LOG_FILE"
echo ""

echo "4. Checking Performance Schema Status..."
echo "----------------------------------------"
mysql -u $MYSQL_USER -p$MYSQL_PASS -h $MYSQL_HOST -e "SHOW VARIABLES LIKE 'performance_schema';"
echo ""

echo "5. Top 20 Slowest Queries (if performance_schema enabled)..."
echo "----------------------------------------"
mysql -u $MYSQL_USER -p$MYSQL_PASS -h $MYSQL_HOST -e "
SELECT 
    LEFT(DIGEST_TEXT, 100) as query_preview,
    COUNT_STAR as execution_count,
    ROUND(SUM_TIMER_WAIT/1000000000000, 2) as total_time_seconds,
    ROUND(AVG_TIMER_WAIT/1000000000000, 2) as avg_time_seconds,
    ROUND(MAX_TIMER_WAIT/1000000000000, 2) as max_time_seconds
FROM performance_schema.events_statements_summary_by_digest
WHERE DIGEST_TEXT IS NOT NULL
  AND AVG_TIMER_WAIT/1000000000000 > 1
ORDER BY AVG_TIMER_WAIT DESC
LIMIT 20;
"
echo ""

echo "6. Top 20 Most Executed Queries..."
echo "----------------------------------------"
mysql -u $MYSQL_USER -p$MYSQL_PASS -h $MYSQL_HOST -e "
SELECT 
    LEFT(DIGEST_TEXT, 100) as query_preview,
    COUNT_STAR as execution_count,
    ROUND(AVG_TIMER_WAIT/1000000000000, 2) as avg_time_seconds
FROM performance_schema.events_statements_summary_by_digest
WHERE DIGEST_TEXT IS NOT NULL
ORDER BY COUNT_STAR DESC
LIMIT 20;
"
echo ""

echo "7. Queries Scanning Many Rows..."
echo "----------------------------------------"
mysql -u $MYSQL_USER -p$MYSQL_PASS -h $MYSQL_HOST -e "
SELECT 
    LEFT(DIGEST_TEXT, 100) as query_preview,
    COUNT_STAR as execution_count,
    SUM_ROWS_EXAMINED as total_rows_examined,
    SUM_ROWS_SENT as total_rows_sent,
    ROUND(AVG_TIMER_WAIT/1000000000000, 2) as avg_time_seconds
FROM performance_schema.events_statements_summary_by_digest
WHERE DIGEST_TEXT IS NOT NULL
  AND SUM_ROWS_EXAMINED > 10000
ORDER BY SUM_ROWS_EXAMINED DESC
LIMIT 20;
"
echo ""

echo "=========================================="
echo "ANALYSIS COMPLETE"
echo "=========================================="
echo ""
echo "Next Steps:"
echo "1. Review slow queries above"
echo "2. Use EXPLAIN on slow queries"
echo "3. Add indexes where needed"
echo "4. Optimize queries"
echo ""
