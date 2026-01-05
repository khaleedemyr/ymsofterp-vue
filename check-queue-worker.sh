#!/bin/bash

# Script untuk check queue worker yang berjalan
# Usage: bash check-queue-worker.sh

echo "=========================================="
echo "Queue Worker Status Check"
echo "=========================================="
echo ""

echo "1. Checking running queue workers..."
echo "   ----------------------------------------"
QUEUE_COUNT=$(ps aux | grep 'queue:work' | grep -v grep | wc -l)
echo "   Total queue workers running: $QUEUE_COUNT"
echo ""

if [ $QUEUE_COUNT -gt 0 ]; then
    echo "   Queue worker processes:"
    ps aux | grep 'queue:work' | grep -v grep | awk '{printf "   PID: %-8s | CPU: %5s%% | MEM: %5s%% | Started: %s | CMD: ", $2, $3, $4, $9; for(i=11;i<=NF;i++) printf "%s ", $i; print ""}'
    echo ""
    
    if [ $QUEUE_COUNT -gt 5 ]; then
        echo "   ⚠️  WARNING: Too many queue workers detected!"
        echo "   This is likely causing high CPU usage."
        echo "   Recommended: Only 1-2 queue workers should run"
    else
        echo "   ✅ Queue worker count looks normal"
    fi
else
    echo "   ⚠️  No queue workers running"
    echo "   Queue jobs will not be processed"
fi
echo ""

echo "2. Checking queue jobs status..."
if [ -f "/home/ymsuperadmin/public_html/artisan" ]; then
    cd /home/ymsuperadmin/public_html
    PHP_PATH=$(which php 2>/dev/null || echo "/usr/bin/php")
    
    echo "   Checking jobs table..."
    echo "   ----------------------------------------"
    
    # Check if queue connection is database
    QUEUE_CONNECTION=$(grep -E "QUEUE_CONNECTION" .env 2>/dev/null | cut -d'=' -f2 | tr -d ' ' || echo "database")
    echo "   Queue connection: $QUEUE_CONNECTION"
    echo ""
    
    if [ "$QUEUE_CONNECTION" = "database" ]; then
        # Try to get job count from database
        echo "   Pending jobs:"
        $PHP_PATH artisan tinker --execute="echo DB::table('jobs')->count() . ' jobs in queue';" 2>/dev/null || echo "   (Could not check - need database access)"
        echo ""
        
        echo "   Failed jobs:"
        $PHP_PATH artisan tinker --execute="echo DB::table('failed_jobs')->count() . ' failed jobs';" 2>/dev/null || echo "   (Could not check - need database access)"
        echo ""
    fi
    
    echo "   Queue monitor (if available):"
    $PHP_PATH artisan queue:monitor notifications 2>&1 | head -10 || echo "   (Queue monitor not available)"
else
    echo "   ⚠️  Laravel artisan not found"
fi
echo ""

echo "3. Checking queue worker log..."
LOG_FILE="/home/ymsuperadmin/public_html/storage/logs/queue-worker.log"
if [ -f "$LOG_FILE" ]; then
    echo "   ✅ Queue worker log found: $LOG_FILE"
    echo "   Last 10 lines:"
    tail -10 "$LOG_FILE" | sed 's/^/   /'
    echo ""
    echo "   Log file size: $(du -h "$LOG_FILE" | cut -f1)"
    echo "   Last modified: $(stat -c %y "$LOG_FILE" 2>/dev/null | cut -d'.' -f1)"
else
    echo "   ⚠️  Queue worker log not found"
fi
echo ""

echo "4. Checking cron job for queue worker..."
if command -v crontab &> /dev/null; then
    CRON_COUNT=$(crontab -l 2>/dev/null | grep -c "queue:work" || echo "0")
    if [ $CRON_COUNT -gt 0 ]; then
        echo "   ✅ Cron job for queue worker found ($CRON_COUNT entry)"
        echo "   Cron entries:"
        crontab -l 2>/dev/null | grep "queue:work" | sed 's/^/   /'
    else
        echo "   ⚠️  No cron job for queue worker found"
    fi
else
    echo "   ⚠️  crontab command not found"
fi
echo ""

echo "5. Top CPU consumers (queue related)..."
echo "   ----------------------------------------"
ps aux --sort=-%cpu | grep -E "queue|php.*artisan" | grep -v grep | head -5 | awk '{printf "   PID: %-8s | CPU: %5s%% | MEM: %5s%% | CMD: ", $2, $3, $4; for(i=11;i<=NF;i++) printf "%s ", $i; print ""}'
echo ""

echo "=========================================="
echo "Recommendations:"
echo "=========================================="
echo ""

if [ $QUEUE_COUNT -gt 5 ]; then
    echo "⚠️  URGENT: Too many queue workers!"
    echo ""
    echo "1. Kill excess queue workers:"
    echo "   pkill -f 'queue:work'"
    echo ""
    echo "2. Fix queue worker setup (see fix-queue-worker.sh)"
    echo ""
    echo "3. Use Supervisor for proper queue worker management"
    echo ""
elif [ $QUEUE_COUNT -eq 0 ]; then
    echo "⚠️  No queue workers running!"
    echo ""
    echo "1. Start queue worker:"
    echo "   cd /home/ymsuperadmin/public_html"
    echo "   php artisan queue:work --queue=notifications --tries=3 --timeout=300"
    echo ""
    echo "2. Or setup Supervisor (recommended)"
    echo ""
else
    echo "✅ Queue worker status looks good"
    echo ""
    echo "To monitor queue worker:"
    echo "  watch -n 5 'ps aux | grep queue:work | grep -v grep'"
    echo ""
    echo "To check queue jobs:"
    echo "  cd /home/ymsuperadmin/public_html"
    echo "  php artisan queue:monitor notifications"
    echo ""
fi

echo "=========================================="

