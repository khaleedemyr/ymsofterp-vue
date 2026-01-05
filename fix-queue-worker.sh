#!/bin/bash

# Script untuk fix queue worker yang berjalan terlalu banyak
# Usage: bash fix-queue-worker.sh

echo "=========================================="
echo "Queue Worker Fix Script"
echo "=========================================="
echo ""

# Check current queue workers
echo "1. Checking current queue workers..."
QUEUE_COUNT=$(ps aux | grep 'queue:work' | grep -v grep | wc -l)
echo "   Found $QUEUE_COUNT queue worker(s) running"
echo ""

if [ $QUEUE_COUNT -gt 5 ]; then
    echo "⚠️  WARNING: Too many queue workers detected!"
    echo "   This is likely causing high CPU usage."
    echo ""
    read -p "Do you want to kill all existing queue workers? (y/n) " -n 1 -r
    echo ""
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        echo "   Killing all queue workers..."
        pkill -f 'queue:work'
        sleep 2
        echo "   Done!"
    fi
fi

echo ""
echo "2. Checking if Supervisor is installed..."
if command -v supervisorctl &> /dev/null; then
    echo "   ✅ Supervisor is installed"
    echo ""
    echo "   Creating supervisor config..."
    
    SUPERVISOR_CONF="/etc/supervisor/conf.d/ymsofterp-queue.conf"
    cat > $SUPERVISOR_CONF << 'EOF'
[program:ymsofterp-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /home/ymsuperadmin/public_html/artisan queue:work --queue=notifications --tries=3 --timeout=300 --sleep=3 --max-jobs=1000
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=ymsuperadmin
numprocs=2
redirect_stderr=true
stdout_logfile=/home/ymsuperadmin/public_html/storage/logs/queue-worker.log
stopwaitsecs=3600
EOF
    
    echo "   Config created at: $SUPERVISOR_CONF"
    echo ""
    echo "   To activate, run:"
    echo "   sudo supervisorctl reread"
    echo "   sudo supervisorctl update"
    echo "   sudo supervisorctl start ymsofterp-queue-worker:*"
    
else
    echo "   ❌ Supervisor is not installed"
    echo ""
    echo "   Option 1: Install Supervisor"
    echo "   - CentOS/RHEL: sudo yum install supervisor"
    echo "   - Ubuntu/Debian: sudo apt-get install supervisor"
    echo ""
    echo "   Option 2: Use single long-running process"
    echo "   Replace your cron job with:"
    echo "   @reboot cd /home/ymsuperadmin/public_html && nohup php artisan queue:work --queue=notifications --tries=3 --timeout=300 --sleep=3 --max-jobs=1000 > storage/logs/queue-worker.log 2>&1 &"
    echo ""
    echo "   Or use this cron (checks if worker exists before starting):"
    echo "   */5 * * * * cd /home/ymsuperadmin/public_html && [ \$(ps aux | grep 'queue:work' | grep -v grep | wc -l) -eq 0 ] && php artisan queue:work --queue=notifications --tries=3 --timeout=300 --sleep=3 --max-jobs=1000 --max-time=300 >> storage/logs/queue-worker.log 2>&1"
fi

echo ""
echo "=========================================="
echo "Next Steps:"
echo "1. Remove the old queue worker cron job that runs every minute"
echo "2. Implement one of the solutions above"
echo "3. Monitor CPU usage: top"
echo "4. Check queue workers: ps aux | grep queue:work"
echo "=========================================="

