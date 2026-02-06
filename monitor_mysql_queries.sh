#!/bin/bash

# Script untuk monitor dan kill query MySQL yang lama
# Usage: ./monitor_mysql_queries.sh

# Configuration
MAX_QUERY_TIME=300  # 5 menit (300 detik)
CHECK_INTERVAL=30   # Check setiap 30 detik
MYSQL_USER="root"   # Ganti dengan user MySQL Anda
MYSQL_PASS=""       # Ganti dengan password MySQL Anda
MYSQL_HOST="localhost"

echo "=== MySQL Query Monitor ==="
echo "Max query time: ${MAX_QUERY_TIME} seconds"
echo "Check interval: ${CHECK_INTERVAL} seconds"
echo "Press Ctrl+C to stop"
echo ""

while true; do
    CURRENT_TIME=$(date '+%Y-%m-%d %H:%M:%S')
    
    # Get list of long-running queries
    LONG_QUERIES=$(mysql -u${MYSQL_USER} -p${MYSQL_PASS} -h${MYSQL_HOST} -e "
        SELECT 
            Id,
            User,
            Host,
            db,
            Time,
            State,
            LEFT(Info, 100) as Query_Preview
        FROM information_schema.PROCESSLIST
        WHERE Command = 'Execute'
          AND State IN ('Creating sort index', 'Sending data', 'Sorting result')
          AND Time > ${MAX_QUERY_TIME}
          AND User NOT IN ('system user', 'event_scheduler')
        ORDER BY Time DESC;
    " 2>/dev/null)
    
    if [ ! -z "$LONG_QUERIES" ]; then
        echo "[$CURRENT_TIME] Found long-running queries:"
        echo "$LONG_QUERIES"
        echo ""
        
        # Get process IDs
        PROCESS_IDS=$(mysql -u${MYSQL_USER} -p${MYSQL_PASS} -h${MYSQL_HOST} -N -e "
            SELECT Id 
            FROM information_schema.PROCESSLIST
            WHERE Command = 'Execute'
              AND State IN ('Creating sort index', 'Sending data', 'Sorting result')
              AND Time > ${MAX_QUERY_TIME}
              AND User NOT IN ('system user', 'event_scheduler');
        " 2>/dev/null)
        
        # Kill each process
        for PID in $PROCESS_IDS; do
            echo "[$CURRENT_TIME] Killing process ID: $PID"
            mysql -u${MYSQL_USER} -p${MYSQL_PASS} -h${MYSQL_HOST} -e "KILL $PID;" 2>/dev/null
            
            if [ $? -eq 0 ]; then
                echo "[$CURRENT_TIME] Successfully killed process $PID"
            else
                echo "[$CURRENT_TIME] Failed to kill process $PID"
            fi
        done
        echo ""
    else
        echo "[$CURRENT_TIME] No long-running queries found"
    fi
    
    # Wait before next check
    sleep $CHECK_INTERVAL
done
