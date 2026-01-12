# PowerShell Script untuk cek slow queries di MySQL
# Jalankan dengan: .\check_slow_queries.ps1

# MySQL credentials (sesuaikan dengan environment Anda)
$MYSQL_USER = "root"
$MYSQL_PASS = ""
$MYSQL_HOST = "localhost"

Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "CHECKING MYSQL SLOW QUERIES" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""

Write-Host "1. Checking Slow Query Log Status..." -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray
mysql -u $MYSQL_USER -p$MYSQL_PASS -h $MYSQL_HOST -e "SHOW VARIABLES LIKE 'slow_query_log%';"
mysql -u $MYSQL_USER -p$MYSQL_PASS -h $MYSQL_HOST -e "SHOW VARIABLES LIKE 'long_query_time';"
Write-Host ""

Write-Host "2. Enabling Slow Query Log (if not enabled)..." -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray
mysql -u $MYSQL_USER -p$MYSQL_PASS -h $MYSQL_HOST -e "SET GLOBAL slow_query_log = 'ON';"
mysql -u $MYSQL_USER -p$MYSQL_PASS -h $MYSQL_HOST -e "SET GLOBAL long_query_time = 1;"
mysql -u $MYSQL_USER -p$MYSQL_PASS -h $MYSQL_HOST -e "SET GLOBAL log_queries_not_using_indexes = 'ON';"
Write-Host ""

Write-Host "3. Checking Slow Query Log File Location..." -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray
$slowLogFile = mysql -u $MYSQL_USER -p$MYSQL_PASS -h $MYSQL_HOST -sN -e "SHOW VARIABLES LIKE 'slow_query_log_file';" | ForEach-Object { ($_ -split '\s+')[1] }
Write-Host "Slow Query Log File: $slowLogFile" -ForegroundColor Green
Write-Host ""

Write-Host "4. Checking Performance Schema Status..." -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray
mysql -u $MYSQL_USER -p$MYSQL_PASS -h $MYSQL_HOST -e "SHOW VARIABLES LIKE 'performance_schema';"
Write-Host ""

Write-Host "5. Top 20 Slowest Queries (if performance_schema enabled)..." -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray
mysql -u $MYSQL_USER -p$MYSQL_PASS -h $MYSQL_HOST -e @"
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
"@
Write-Host ""

Write-Host "6. Top 20 Most Executed Queries..." -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray
mysql -u $MYSQL_USER -p$MYSQL_PASS -h $MYSQL_HOST -e @"
SELECT 
    LEFT(DIGEST_TEXT, 100) as query_preview,
    COUNT_STAR as execution_count,
    ROUND(AVG_TIMER_WAIT/1000000000000, 2) as avg_time_seconds
FROM performance_schema.events_statements_summary_by_digest
WHERE DIGEST_TEXT IS NOT NULL
ORDER BY COUNT_STAR DESC
LIMIT 20;
"@
Write-Host ""

Write-Host "7. Queries Scanning Many Rows..." -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray
mysql -u $MYSQL_USER -p$MYSQL_PASS -h $MYSQL_HOST -e @"
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
"@
Write-Host ""

Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "ANALYSIS COMPLETE" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Next Steps:" -ForegroundColor Yellow
Write-Host "1. Review slow queries above"
Write-Host "2. Use EXPLAIN on slow queries"
Write-Host "3. Add indexes where needed"
Write-Host "4. Optimize queries"
Write-Host ""
