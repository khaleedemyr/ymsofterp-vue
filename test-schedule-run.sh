#!/bin/bash

# Script untuk test dan debug schedule:run
# Usage: bash test-schedule-run.sh

echo "=========================================="
echo "Laravel Schedule:Run Test & Debug"
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

echo "1. Checking Laravel installation..."
if [ ! -f "artisan" ]; then
    echo "   ❌ ERROR: File artisan tidak ditemukan di $APP_PATH"
    exit 1
fi
echo "   ✅ Laravel artisan ditemukan"
echo ""

echo "2. Checking PHP path..."
PHP_PATH=$(which php)
if [ -z "$PHP_PATH" ]; then
    echo "   ❌ ERROR: PHP tidak ditemukan di PATH"
    echo "   Coba cari manual: find /usr -name php 2>/dev/null | head -1"
    exit 1
fi
echo "   ✅ PHP ditemukan di: $PHP_PATH"
echo "   PHP Version: $($PHP_PATH -v | head -1)"
echo ""

echo "3. Testing schedule:run command..."
echo "   Running: $PHP_PATH artisan schedule:run"
echo "   ----------------------------------------"
$PHP_PATH artisan schedule:run
EXIT_CODE=$?
echo "   ----------------------------------------"
if [ $EXIT_CODE -eq 0 ]; then
    echo "   ✅ schedule:run berhasil dijalankan (exit code: $EXIT_CODE)"
else
    echo "   ❌ schedule:run gagal (exit code: $EXIT_CODE)"
    echo "   Check error di atas"
fi
echo ""

echo "4. Testing schedule:list command..."
echo "   Scheduled tasks:"
$PHP_PATH artisan schedule:list
echo ""

echo "5. Checking cron job..."
echo "   Current user: $(whoami)"
echo "   Checking crontab..."
if command -v crontab &> /dev/null; then
    CRON_COUNT=$(crontab -l 2>/dev/null | grep -c "schedule:run" || echo "0")
    if [ $CRON_COUNT -gt 0 ]; then
        echo "   ✅ Cron job schedule:run ditemukan ($CRON_COUNT entry)"
        echo "   Cron entries:"
        crontab -l 2>/dev/null | grep "schedule:run"
    else
        echo "   ❌ Cron job schedule:run TIDAK ditemukan!"
        echo "   Anda perlu menambahkan cron job berikut:"
        echo ""
        echo "   * * * * * cd $APP_PATH && $PHP_PATH artisan schedule:run >> storage/logs/schedule.log 2>&1"
    fi
else
    echo "   ⚠️  crontab command tidak ditemukan"
fi
echo ""

echo "6. Checking schedule log..."
if [ -f "storage/logs/schedule.log" ]; then
    echo "   ✅ Schedule log ditemukan"
    echo "   Last 10 lines:"
    tail -10 storage/logs/schedule.log
else
    echo "   ⚠️  Schedule log belum ada (akan dibuat saat pertama kali run)"
fi
echo ""

echo "7. Checking Laravel log for errors..."
if [ -f "storage/logs/laravel.log" ]; then
    echo "   Last 5 errors (if any):"
    grep -i "error\|exception\|fatal" storage/logs/laravel.log | tail -5 || echo "   (No errors found)"
else
    echo "   ⚠️  Laravel log belum ada"
fi
echo ""

echo "=========================================="
echo "Recommendations:"
echo "=========================================="
echo ""

# Check if cron exists
if [ $CRON_COUNT -eq 0 ]; then
    echo "1. TAMBAHKAN cron job berikut di cPanel atau via crontab -e:"
    echo ""
    echo "   * * * * * cd $APP_PATH && $PHP_PATH artisan schedule:run >> $APP_PATH/storage/logs/schedule.log 2>&1"
    echo ""
    echo "   Atau dengan full path (lebih aman):"
    echo "   * * * * * cd $APP_PATH && /usr/bin/php artisan schedule:run >> $APP_PATH/storage/logs/schedule.log 2>&1"
    echo ""
fi

echo "2. Pastikan cron service berjalan:"
echo "   systemctl status crond    # CentOS/RHEL"
echo "   systemctl status cron     # Ubuntu/Debian"
echo ""

echo "3. Test cron job manual:"
echo "   cd $APP_PATH"
echo "   $PHP_PATH artisan schedule:run"
echo ""

echo "4. Monitor schedule log:"
echo "   tail -f $APP_PATH/storage/logs/schedule.log"
echo ""

echo "5. Check apakah schedule:run berjalan setiap menit:"
echo "   watch -n 1 'ps aux | grep schedule:run | grep -v grep'"
echo ""

