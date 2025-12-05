#!/bin/bash

# Setup Cron Jobs untuk Leave Management System
# Script ini akan menambahkan cron jobs untuk sistem cuti

echo "🕐 Setting up Leave Management Cron Jobs..."

# Path ke Laravel project
LARAVEL_PATH="/home/ymsuperadmin/public_html"

# Backup existing crontab
echo "📋 Backing up existing crontab..."
crontab -l > crontab_backup_$(date +%Y%m%d_%H%M%S).txt

# Add Leave Management cron jobs
echo "➕ Adding Leave Management cron jobs..."

# Monthly Credit - Setiap tanggal 1, jam 00:00
(crontab -l 2>/dev/null; echo "0 0 1 * * cd $LARAVEL_PATH && php artisan leave:monthly-credit >> storage/logs/leave-monthly-credit.log 2>&1") | crontab -

# Leave Burning - Setiap tanggal 1 Maret, jam 00:00  
(crontab -l 2>/dev/null; echo "0 0 1 3 * cd $LARAVEL_PATH && php artisan leave:burn-previous-year >> storage/logs/leave-burning.log 2>&1") | crontab -

echo "✅ Leave Management cron jobs added successfully!"

# Display current crontab
echo "📋 Current cron jobs:"
crontab -l

echo ""
echo "🎯 Added Jobs:"
echo "1. Monthly Credit: 0 0 1 * * (Setiap tanggal 1, jam 00:00)"
echo "2. Leave Burning: 0 0 1 3 * (Setiap tanggal 1 Maret, jam 00:00)"
echo ""
echo "📁 Log files will be created at:"
echo "- $LARAVEL_PATH/storage/logs/leave-monthly-credit.log"
echo "- $LARAVEL_PATH/storage/logs/leave-burning.log"
echo ""
echo "🔧 To test manually:"
echo "cd $LARAVEL_PATH && php artisan leave:monthly-credit"
echo "cd $LARAVEL_PATH && php artisan leave:burn-previous-year"
