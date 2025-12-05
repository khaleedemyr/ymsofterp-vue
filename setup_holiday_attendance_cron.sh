#!/bin/bash

# Setup Holiday Attendance Automatic Processing
# This script sets up the cron job for automatic holiday attendance processing

echo "Setting up Holiday Attendance Automatic Processing..."

# Get the current directory (Laravel project root)
PROJECT_PATH=$(pwd)

# Create the cron job entry
CRON_ENTRY="0 6,23 * * * cd $PROJECT_PATH && php artisan attendance:process-holiday >> /dev/null 2>&1"

# Add to crontab if not already exists
if ! crontab -l 2>/dev/null | grep -q "attendance:process-holiday"; then
    (crontab -l 2>/dev/null; echo "$CRON_ENTRY") | crontab -
    echo "✅ Cron job added successfully!"
    echo "📅 Holiday attendance will be processed automatically at 6:00 AM and 11:00 PM daily"
else
    echo "⚠️  Cron job already exists for holiday attendance processing"
fi

# Show current crontab
echo ""
echo "Current crontab entries:"
crontab -l

echo ""
echo "🎯 Setup completed! The system will now automatically:"
echo "   • Check for employees who worked on holidays"
echo "   • Give extra off days or bonuses based on their level"
echo "   • Process at 6:00 AM and 11:00 PM daily"
echo ""
echo "📝 Logs will be saved to: storage/logs/holiday-attendance.log"
echo ""
echo "🔧 Manual commands available:"
echo "   • php artisan attendance:process-holiday [date]"
echo "   • php artisan attendance:cleanup-logs"
