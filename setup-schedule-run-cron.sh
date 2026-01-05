#!/bin/bash

# Script untuk setup cron job schedule:run
# Usage: bash setup-schedule-run-cron.sh

echo "=========================================="
echo "Setup Schedule:Run Cron Job"
echo "=========================================="
echo ""

APP_PATH="/home/ymsuperadmin/public_html"

# Detect PHP path
echo "1. Detecting PHP path..."
PHP_PATH=$(which php 2>/dev/null)
if [ -z "$PHP_PATH" ]; then
    PHP_PATH=$(find /usr -name php 2>/dev/null | grep -E "bin/php$" | head -1)
    if [ -z "$PHP_PATH" ]; then
        echo "   ⚠️  PHP path tidak ditemukan otomatis"
        read -p "   Masukkan PHP path (contoh: /usr/bin/php): " PHP_PATH
    fi
fi

if [ ! -f "$PHP_PATH" ]; then
    echo "   ❌ ERROR: PHP tidak ditemukan di: $PHP_PATH"
    exit 1
fi

echo "   ✅ PHP ditemukan di: $PHP_PATH"
echo "   PHP Version: $($PHP_PATH -v | head -1)"
echo ""

# Check Laravel installation
echo "2. Checking Laravel installation..."
if [ ! -d "$APP_PATH" ]; then
    echo "   ⚠️  Path aplikasi tidak ditemukan: $APP_PATH"
    read -p "   Masukkan path aplikasi Laravel: " APP_PATH
fi

if [ ! -f "$APP_PATH/artisan" ]; then
    echo "   ❌ ERROR: File artisan tidak ditemukan di $APP_PATH"
    exit 1
fi

echo "   ✅ Laravel ditemukan di: $APP_PATH"
echo ""

# Test schedule:run
echo "3. Testing schedule:run..."
cd $APP_PATH
$PHP_PATH artisan schedule:run > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "   ✅ schedule:run berfungsi dengan baik"
else
    echo "   ⚠️  schedule:run ada warning/error (check manual dengan: php artisan schedule:run)"
fi
echo ""

# Check current crontab
echo "4. Checking current crontab..."
if command -v crontab &> /dev/null; then
    CURRENT_USER=$(whoami)
    echo "   User: $CURRENT_USER"
    
    # Backup crontab
    BACKUP_FILE="/tmp/crontab_backup_$(date +%Y%m%d_%H%M%S).txt"
    crontab -l > $BACKUP_FILE 2>/dev/null
    if [ $? -eq 0 ]; then
        echo "   ✅ Backup crontab dibuat: $BACKUP_FILE"
    fi
    
    # Check if schedule:run exists
    if crontab -l 2>/dev/null | grep -q "schedule:run"; then
        echo "   ⚠️  Cron job schedule:run sudah ada"
        echo "   Current entry:"
        crontab -l 2>/dev/null | grep "schedule:run"
        echo ""
        read -p "   Apakah Anda ingin mengganti dengan yang baru? (y/n) " -n 1 -r
        echo ""
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            echo "   ❌ Dibatalkan oleh user"
            exit 0
        fi
    fi
    
    # Create new crontab
    echo ""
    echo "5. Creating/Updating cron job..."
    
    # Remove old schedule:run entries
    crontab -l 2>/dev/null | grep -v "schedule:run" > /tmp/crontab_new.txt 2>/dev/null
    
    # Add new schedule:run entry
    CRON_ENTRY="* * * * * cd $APP_PATH && $PHP_PATH artisan schedule:run >> $APP_PATH/storage/logs/schedule.log 2>&1"
    echo "$CRON_ENTRY" >> /tmp/crontab_new.txt
    
    # Install new crontab
    crontab /tmp/crontab_new.txt
    rm /tmp/crontab_new.txt
    
    if [ $? -eq 0 ]; then
        echo "   ✅ Cron job schedule:run berhasil ditambahkan/diperbarui"
        echo ""
        echo "   New cron entry:"
        crontab -l | grep "schedule:run"
    else
        echo "   ❌ ERROR: Gagal menambahkan cron job"
        exit 1
    fi
else
    echo "   ⚠️  crontab command tidak ditemukan"
    echo "   Anda perlu menambahkan cron job manual di cPanel:"
    echo ""
    echo "   Minute: *"
    echo "   Hour: *"
    echo "   Day: *"
    echo "   Month: *"
    echo "   Weekday: *"
    echo "   Command: cd $APP_PATH && $PHP_PATH artisan schedule:run >> $APP_PATH/storage/logs/schedule.log 2>&1"
fi
echo ""

# Create log file
echo "6. Creating schedule log file..."
mkdir -p $APP_PATH/storage/logs
touch $APP_PATH/storage/logs/schedule.log
chmod 644 $APP_PATH/storage/logs/schedule.log
echo "   ✅ Schedule log: $APP_PATH/storage/logs/schedule.log"
echo ""

# Test execution
echo "7. Testing cron execution..."
$PHP_PATH artisan schedule:run >> $APP_PATH/storage/logs/schedule.log 2>&1
if [ $? -eq 0 ]; then
    echo "   ✅ Test execution berhasil"
    echo "   Check log: tail -f $APP_PATH/storage/logs/schedule.log"
else
    echo "   ⚠️  Ada warning/error, check log di atas"
fi
echo ""

echo "=========================================="
echo "Setup Selesai!"
echo "=========================================="
echo ""
echo "Next Steps:"
echo "1. Monitor schedule log:"
echo "   tail -f $APP_PATH/storage/logs/schedule.log"
echo ""
echo "2. List scheduled tasks:"
echo "   cd $APP_PATH && $PHP_PATH artisan schedule:list"
echo ""
echo "3. Test manual:"
echo "   cd $APP_PATH && $PHP_PATH artisan schedule:run"
echo ""
echo "4. HAPUS semua cron jobs individual di cPanel"
echo "   (kecuali queue:work yang sudah diperbaiki)"
echo ""
echo "5. Monitor selama 24 jam untuk memastikan semua tasks berjalan"
echo ""

