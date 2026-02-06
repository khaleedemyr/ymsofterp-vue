# PowerShell script untuk monitor dan kill query MySQL yang lama
# Usage: .\monitor_mysql_queries.ps1

# Configuration
$MAX_QUERY_TIME = 300  # 5 menit (300 detik)
$CHECK_INTERVAL = 30   # Check setiap 30 detik
$MYSQL_USER = "root"   # Ganti dengan user MySQL Anda
$MYSQL_PASS = ""       # Ganti dengan password MySQL Anda (kosongkan jika tidak ada password)
$MYSQL_HOST = "localhost"
$MYSQL_BIN = "C:\xampp\mysql\bin\mysql.exe"  # Sesuaikan path MySQL

Write-Host "=== MySQL Query Monitor ===" -ForegroundColor Green
Write-Host "Max query time: $MAX_QUERY_TIME seconds"
Write-Host "Check interval: $CHECK_INTERVAL seconds"
Write-Host "Press Ctrl+C to stop"
Write-Host ""

while ($true) {
    $currentTime = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    
    # Build MySQL connection string
    $mysqlArgs = @("-u$MYSQL_USER", "-h$MYSQL_HOST")
    if ($MYSQL_PASS) {
        $mysqlArgs += "-p$MYSQL_PASS"
    }
    
    # Get list of long-running queries
    $query = @"
SELECT 
    Id,
    User,
    Host,
    db,
    Time,
    State,
    LEFT(IFNULL(Info, ''), 100) as Query_Preview
FROM information_schema.PROCESSLIST
WHERE Command = 'Execute'
  AND State IN ('Creating sort index', 'Sending data', 'Sorting result')
  AND Time > $MAX_QUERY_TIME
  AND User NOT IN ('system user', 'event_scheduler')
ORDER BY Time DESC;
"@
    
    try {
        $longQueries = & $MYSQL_BIN $mysqlArgs -e $query 2>$null
        
        if ($longQueries -and $longQueries.Count -gt 1) {
            Write-Host "[$currentTime] Found long-running queries:" -ForegroundColor Yellow
            Write-Host $longQueries
            Write-Host ""
            
            # Get process IDs
            $pidQuery = @"
SELECT Id 
FROM information_schema.PROCESSLIST
WHERE Command = 'Execute'
  AND State IN ('Creating sort index', 'Sending data', 'Sorting result')
  AND Time > $MAX_QUERY_TIME
  AND User NOT IN ('system user', 'event_scheduler');
"@
            
            $processIds = & $MYSQL_BIN $mysqlArgs -N -e $pidQuery 2>$null
            
            # Kill each process
            foreach ($pid in $processIds) {
                if ($pid -and $pid.Trim()) {
                    Write-Host "[$currentTime] Killing process ID: $pid" -ForegroundColor Red
                    $killQuery = "KILL $pid;"
                    & $MYSQL_BIN $mysqlArgs -e $killQuery 2>$null
                    
                    if ($LASTEXITCODE -eq 0) {
                        Write-Host "[$currentTime] Successfully killed process $pid" -ForegroundColor Green
                    } else {
                        Write-Host "[$currentTime] Failed to kill process $pid" -ForegroundColor Red
                    }
                }
            }
            Write-Host ""
        } else {
            Write-Host "[$currentTime] No long-running queries found" -ForegroundColor Gray
        }
    }
    catch {
        Write-Host "[$currentTime] Error checking queries: $_" -ForegroundColor Red
    }
    
    # Wait before next check
    Start-Sleep -Seconds $CHECK_INTERVAL
}
