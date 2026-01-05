#!/bin/bash

# Script untuk fix schedule:run agar berjalan terus
# Usage: bash fix-schedule-run.sh

echo "=========================================="
echo "Fix Schedule:Run - Memastikan Berjalan Terus"
echo "=========================================="
echo ""

APP_PATH="/home/ymsuperadmin/public_html"
PHP_PATH=$(which php 2>/dev/null || echo "/usr/bin/php")

# Detect PHP path if not found
if [ ! -f "$PHP_PATH" ]; then
    echo "Mencari PHP path..."
    PHP_PATH=$(find /usr -name php 2>/dev/null | grep -E "bin/php$" | head -1)
    if [ -z "$PHP_PATH" ]; then
        PHP_PATH="/usr/bin/php"
        echo "⚠️  PHP path tidak ditemukan, menggunakan default: $PHP_PATH"
    else
        echo "✅ PHP ditemukan di: $PHP_PATH"
    fi
fi

cd $APP_PATH 2>/dev/null || {
    echo "❌ ERROR: Tidak bisa akses $APP_PATH"
    echo "   Pastikan path benar atau ubah APP_PATH di script ini"
    exit 1
}

echo "1. Testing schedule:run..."
$PHP_PATH artisan schedule:run > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "   ✅ schedule:run berfungsi dengan baik"
else
    echo "   ❌ schedule:run ada error, check dengan: php artisan schedule:run"
fi
echo ""

echo "2. Checking current cron jobs..."
if command -v crontab &> /dev/null; then
    CURRENT_USER=$(whoami)
    echo "   User: $CURRENT_USER"
    
    # Check if schedule:run exists in crontab
    if crontab -l 2>/dev/null | grep -q "schedule:run"; then
        echo "   ✅ Cron job schedule:run sudah ada"
        echo "   Current entry:"
        crontab -l 2>/dev/null | grep "schedule:run"
        echo ""
        read -p "   Apakah Anda ingin memperbaiki/mengganti cron job? (y/n) " -n 1 -r
        echo ""
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            UPDATE_CRON=true
        else
            UPDATE_CRON=false
        fi
    else
        echo "   ❌ Cron job schedule:run TIDAK ditemukan"
        UPDATE_CRON=true
    fi
    
    if [ "$UPDATE_CRON" = true ]; then
        echo ""
        echo "3. Creating/Updating cron job..."
        
        # Backup existing crontab
        crontab -l > /tmp/crontab_backup_$(date +%Y%m%d_%H%M%S).txt 2>/dev/null
        echo "   ✅ Backup crontab dibuat"
        
        # Remove old schedule:run entries
        crontab -l 2>/dev/null | grep -v "schedule:run" > /tmp/crontab_new.txt
        
        # Add new schedule:run entry with proper logging
        echo "* * * * * cd $APP_PATH && $PHP_PATH artisan schedule:run >> $APP_PATH/storage/logs/schedule.log 2>&1" >> /tmp/crontab_new.txt
        
        # Install new crontab
        crontab /tmp/crontab_new.txt
        rm /tmp/crontab_new.txt
        
        echo "   ✅ Cron job schedule:run ditambahkan/diperbarui"
        echo ""
        echo "   New cron entry:"
        crontab -l | grep "schedule:run"
    fi
else
    echo "   ⚠️  crontab command tidak ditemukan"
    echo "   Anda perlu menambahkan cron job manual di cPanel:"
    echo ""
    echo "   * * * * * cd $APP_PATH && $PHP_PATH artisan schedule:run >> $APP_PATH/storage/logs/schedule.log 2>&1"
fi
echo ""

echo "4. Creating schedule log file (if not exists)..."
mkdir -p storage/logs
touch storage/logs/schedule.log
chmod 644 storage/logs/schedule.log
echo "   ✅ Schedule log: $APP_PATH/storage/logs/schedule.log"
echo ""

echo "5. Testing cron execution..."
echo "   Menjalankan schedule:run sekali untuk test..."
$PHP_PATH artisan schedule:run >> storage/logs/schedule.log 2>&1
if [ $? -eq 0 ]; then
    echo "   ✅ Test berhasil"
    echo "   Check log: tail -f storage/logs/schedule.log"
else
    echo "   ⚠️  Ada error, check log di atas"
fi
echo ""

echo "6. Verifying cron service..."
if command -v systemctl &> /dev/null; then
    if systemctl is-active --quiet crond 2>/dev/null || systemctl is-active --quiet cron 2>/dev/null; then
        echo "   ✅ Cron service berjalan"
    else
        echo "   ⚠️  Cron service mungkin tidak berjalan"
        echo "   Start dengan: sudo systemctl start crond (CentOS) atau sudo systemctl start cron (Ubuntu)"
    fi
else
    echo "   ⚠️  systemctl tidak ditemukan, skip check"
fi
echo ""

echo "=========================================="
echo "Next Steps:"
echo "=========================================="
echo "1. Monitor schedule log:"
echo "   tail -f $APP_PATH/storage/logs/schedule.log"
echo ""
echo "2. Check apakah schedule:run berjalan setiap menit:"
echo "   watch -n 1 'ps aux | grep schedule:run | grep -v grep'"
echo ""
echo "3. Test manual setiap menit selama 5 menit:"
echo "   for i in {1..5}; do echo \"Run \$i:\"; $PHP_PATH artisan schedule:run; sleep 60; done"
echo ""
echo "4. Jika masih tidak jalan, check:"
echo "   - Cron service status"
echo "   - File permission (storage/logs harus writable)"
echo "   - PHP path benar"
echo "   - Laravel .env file ada dan benar"
echo ""

