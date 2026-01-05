#!/bin/bash

# Script untuk check scheduler yang ada di Kernel.php
# Usage: bash check-scheduler.sh

echo "=========================================="
echo "Laravel Scheduler Check"
echo "=========================================="
echo ""

APP_PATH="/home/ymsuperadmin/public_html"

# Check if path exists
if [ ! -d "$APP_PATH" ]; then
    echo "❌ ERROR: Path tidak ditemukan: $APP_PATH"
    echo "   Silakan ubah APP_PATH di script ini sesuai path aplikasi Anda"
    exit 1
fi

cd $APP_PATH

# Check Laravel installation
if [ ! -f "artisan" ]; then
    echo "❌ ERROR: File artisan tidak ditemukan di $APP_PATH"
    exit 1
fi

# Detect PHP path
PHP_PATH=$(which php 2>/dev/null)
if [ -z "$PHP_PATH" ]; then
    PHP_PATH=$(find /usr -name php 2>/dev/null | grep -E "bin/php$" | head -1)
    if [ -z "$PHP_PATH" ]; then
        PHP_PATH="/usr/bin/php"
        echo "⚠️  PHP path tidak ditemukan, menggunakan default: $PHP_PATH"
    fi
fi

echo "1. Checking Laravel installation..."
echo "   Path: $APP_PATH"
echo "   PHP: $PHP_PATH"
echo "   PHP Version: $($PHP_PATH -v | head -1)"
echo ""

echo "2. Checking Kernel.php scheduler configuration..."
if [ -f "app/Console/Kernel.php" ]; then
    echo "   ✅ Kernel.php ditemukan"
    
    # Count scheduled tasks
    SCHEDULE_COUNT=$(grep -c "\$schedule->command" app/Console/Kernel.php 2>/dev/null || echo "0")
    echo "   Total scheduled tasks ditemukan: $SCHEDULE_COUNT"
    echo ""
    
    echo "   Scheduled tasks di Kernel.php:"
    echo "   ----------------------------------------"
    grep "\$schedule->command" app/Console/Kernel.php | sed 's/^[[:space:]]*//' | head -20
    if [ $SCHEDULE_COUNT -gt 20 ]; then
        echo "   ... dan $((SCHEDULE_COUNT - 20)) lebih"
    fi
    echo "   ----------------------------------------"
else
    echo "   ❌ Kernel.php tidak ditemukan"
fi
echo ""

echo "3. Listing scheduled tasks via Laravel..."
echo "   Running: $PHP_PATH artisan schedule:list"
echo "   ----------------------------------------"
$PHP_PATH artisan schedule:list 2>&1
echo "   ----------------------------------------"
echo ""

echo "4. Testing schedule:run (dry run)..."
echo "   Running: $PHP_PATH artisan schedule:run -v"
echo "   ----------------------------------------"
$PHP_PATH artisan schedule:run -v 2>&1 | head -30
echo "   ----------------------------------------"
echo ""

echo "5. Checking schedule log..."
if [ -f "storage/logs/schedule.log" ]; then
    echo "   ✅ Schedule log ditemukan"
    echo "   Last 10 lines:"
    tail -10 storage/logs/schedule.log | sed 's/^/   /'
    echo ""
    echo "   Log file size: $(du -h storage/logs/schedule.log | cut -f1)"
    echo "   Last modified: $(stat -c %y storage/logs/schedule.log 2>/dev/null | cut -d'.' -f1)"
else
    echo "   ⚠️  Schedule log belum ada (akan dibuat saat pertama kali run)"
fi
echo ""

echo "6. Checking cron job for schedule:run..."
if command -v crontab &> /dev/null; then
    CRON_COUNT=$(crontab -l 2>/dev/null | grep -c "schedule:run" || echo "0")
    if [ $CRON_COUNT -gt 0 ]; then
        echo "   ✅ Cron job schedule:run ditemukan ($CRON_COUNT entry)"
        echo "   Cron entries:"
        crontab -l 2>/dev/null | grep "schedule:run" | sed 's/^/   /'
    else
        echo "   ❌ Cron job schedule:run TIDAK ditemukan!"
        echo "   Anda perlu menambahkan cron job berikut:"
        echo ""
        echo "   * * * * * cd $APP_PATH && $PHP_PATH artisan schedule:run >> $APP_PATH/storage/logs/schedule.log 2>&1"
    fi
else
    echo "   ⚠️  crontab command tidak ditemukan"
fi
echo ""

echo "7. Summary of scheduled tasks..."
echo "   ----------------------------------------"
$PHP_PATH artisan schedule:list 2>/dev/null | grep -E "Command|Next Due" | head -40
echo "   ----------------------------------------"
echo ""

echo "=========================================="
echo "Recommendations:"
echo "=========================================="
echo ""

# Check if cron exists
if [ $CRON_COUNT -eq 0 ]; then
    echo "1. TAMBAHKAN cron job untuk schedule:run"
    echo "   (lihat file CRON_JOB_SCHEDULE_RUN.txt)"
    echo ""
fi

echo "2. Untuk melihat detail semua scheduled tasks:"
echo "   cd $APP_PATH"
echo "   $PHP_PATH artisan schedule:list"
echo ""

echo "3. Untuk test run scheduler:"
echo "   cd $APP_PATH"
echo "   $PHP_PATH artisan schedule:run -v"
echo ""

echo "4. Untuk monitor scheduler log:"
echo "   tail -f $APP_PATH/storage/logs/schedule.log"
echo ""

echo "5. Untuk check apakah scheduler berjalan setiap menit:"
echo "   watch -n 1 'ps aux | grep schedule:run | grep -v grep'"
echo ""

