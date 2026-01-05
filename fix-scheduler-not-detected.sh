#!/bin/bash

# Script untuk fix scheduler yang tidak terdeteksi
# Usage: bash fix-scheduler-not-detected.sh

echo "=========================================="
echo "Fix Scheduler Not Detected"
echo "=========================================="
echo ""

APP_PATH="/home/ymsuperadmin/public_html"
cd $APP_PATH

PHP_PATH=$(which php 2>/dev/null || echo "/usr/bin/php")

echo "1. Checking Laravel installation..."
if [ ! -f "artisan" ]; then
    echo "   ❌ ERROR: File artisan tidak ditemukan"
    exit 1
fi
echo "   ✅ Laravel artisan ditemukan"
echo ""

echo "2. Checking Kernel.php..."
if [ ! -f "app/Console/Kernel.php" ]; then
    echo "   ❌ ERROR: Kernel.php tidak ditemukan di app/Console/Kernel.php"
    exit 1
fi
echo "   ✅ Kernel.php ditemukan"
echo ""

echo "3. Checking scheduled tasks in Kernel.php..."
SCHEDULE_COUNT=$(grep -c "\$schedule->command" app/Console/Kernel.php 2>/dev/null || echo "0")
echo "   Found $SCHEDULE_COUNT scheduled tasks in Kernel.php"
if [ $SCHEDULE_COUNT -eq 0 ]; then
    echo "   ⚠️  WARNING: Tidak ada scheduled tasks ditemukan di Kernel.php"
    echo "   Check file: app/Console/Kernel.php"
else
    echo "   ✅ Scheduled tasks ditemukan di code"
fi
echo ""

echo "4. Checking for syntax errors..."
$PHP_PATH artisan list 2>&1 | grep -i "error\|fatal\|exception" > /dev/null
if [ $? -eq 0 ]; then
    echo "   ❌ ERROR: Ada error di Laravel"
    echo "   Running: $PHP_PATH artisan list"
    $PHP_PATH artisan list 2>&1 | head -20
else
    echo "   ✅ Tidak ada syntax error"
fi
echo ""

echo "5. Clearing Laravel cache..."
$PHP_PATH artisan config:clear 2>&1
$PHP_PATH artisan cache:clear 2>&1
$PHP_PATH artisan route:clear 2>&1
echo "   ✅ Cache cleared"
echo ""

echo "6. Rebuilding cache..."
$PHP_PATH artisan config:cache 2>&1
echo "   ✅ Config cached"
echo ""

echo "7. Testing schedule:list again..."
echo "   Running: $PHP_PATH artisan schedule:list"
echo "   ----------------------------------------"
$PHP_PATH artisan schedule:list 2>&1
echo "   ----------------------------------------"
echo ""

echo "8. Checking Kernel.php method schedule()..."
if grep -q "protected function schedule" app/Console/Kernel.php; then
    echo "   ✅ Method schedule() ditemukan"
    
    # Check if schedule method has content
    if grep -A 5 "protected function schedule" app/Console/Kernel.php | grep -q "\$schedule->"; then
        echo "   ✅ Method schedule() memiliki content"
    else
        echo "   ⚠️  WARNING: Method schedule() kosong atau tidak ada scheduled tasks"
    fi
else
    echo "   ❌ ERROR: Method schedule() tidak ditemukan di Kernel.php"
fi
echo ""

echo "9. Checking namespace and class..."
if grep -q "namespace App\\Console" app/Console/Kernel.php && grep -q "class Kernel" app/Console/Kernel.php; then
    echo "   ✅ Namespace dan class benar"
else
    echo "   ⚠️  WARNING: Check namespace dan class di Kernel.php"
fi
echo ""

echo "10. Checking autoload..."
if [ -f "composer.json" ]; then
    echo "   ✅ composer.json ditemukan"
    if command -v composer &> /dev/null; then
        echo "   Running: composer dump-autoload"
        composer dump-autoload 2>&1 | tail -5
    else
        echo "   ⚠️  Composer tidak ditemukan, skip autoload"
    fi
else
    echo "   ⚠️  composer.json tidak ditemukan"
fi
echo ""

echo "=========================================="
echo "Troubleshooting Steps:"
echo "=========================================="
echo ""

if [ $SCHEDULE_COUNT -eq 0 ]; then
    echo "❌ MASALAH: Tidak ada scheduled tasks di Kernel.php"
    echo ""
    echo "Solusi:"
    echo "1. Check file: app/Console/Kernel.php"
    echo "2. Pastikan method schedule() ada dan berisi \$schedule->command(...)"
    echo "3. Pastikan tidak ada syntax error"
    echo ""
else
    echo "✅ Scheduled tasks ada di code ($SCHEDULE_COUNT tasks)"
    echo ""
    echo "Jika schedule:list masih kosong, coba:"
    echo "1. Clear cache: php artisan config:clear && php artisan cache:clear"
    echo "2. Rebuild cache: php artisan config:cache"
    echo "3. Dump autoload: composer dump-autoload"
    echo "4. Check file permission: chmod 644 app/Console/Kernel.php"
    echo "5. Restart PHP-FPM: systemctl restart php-fpm"
    echo ""
fi

echo "Test commands:"
echo "  php artisan schedule:list"
echo "  php artisan schedule:run -v"
echo "  php artisan list | grep schedule"
echo ""

