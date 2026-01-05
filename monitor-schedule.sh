#!/bin/bash

# Script untuk monitor apakah schedule:run berjalan dengan baik
# Usage: bash monitor-schedule.sh [duration_in_minutes]
# Example: bash monitor-schedule.sh 5  (monitor selama 5 menit)

DURATION=${1:-5}  # Default 5 menit
APP_PATH="/home/ymsuperadmin/public_html"
LOG_FILE="$APP_PATH/storage/logs/schedule.log"

echo "=========================================="
echo "Schedule:Run Monitor"
echo "Monitoring selama $DURATION menit..."
echo "=========================================="
echo ""

# Function to check schedule:run
check_schedule() {
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    
    # Check if process is running
    local process_count=$(ps aux | grep 'schedule:run' | grep -v grep | wc -l)
    
    # Check last log entry
    if [ -f "$LOG_FILE" ]; then
        local last_log=$(tail -1 "$LOG_FILE" 2>/dev/null | head -c 100)
        local log_time=$(stat -c %y "$LOG_FILE" 2>/dev/null | cut -d'.' -f1)
    else
        local last_log="(log file tidak ada)"
        local log_time="N/A"
    fi
    
    # Check if log was updated in last 2 minutes
    local log_updated=false
    if [ -f "$LOG_FILE" ]; then
        local log_age=$(($(date +%s) - $(stat -c %Y "$LOG_FILE" 2>/dev/null || echo 0)))
        if [ $log_age -lt 120 ]; then
            log_updated=true
        fi
    fi
    
    echo "[$timestamp]"
    echo "  Process running: $process_count"
    echo "  Log file: $LOG_FILE"
    echo "  Log last updated: $log_time"
    echo "  Log recently updated: $([ "$log_updated" = true ] && echo "✅ Yes" || echo "❌ No")"
    if [ ! -z "$last_log" ] && [ "$last_log" != "(log file tidak ada)" ]; then
        echo "  Last log entry: $last_log"
    fi
    echo ""
}

# Initial check
echo "Initial Status:"
check_schedule
echo ""

# Monitor loop
echo "Starting monitoring (check setiap 30 detik)..."
echo "Press Ctrl+C to stop"
echo ""

for i in $(seq 1 $((DURATION * 2))); do
    sleep 30
    check_schedule
done

echo "=========================================="
echo "Monitoring selesai"
echo "=========================================="
echo ""
echo "Summary:"
echo "- Check log file: tail -20 $LOG_FILE"
echo "- Check cron job: crontab -l | grep schedule:run"
echo "- Test manual: cd $APP_PATH && php artisan schedule:run"
echo ""

