#!/bin/bash

# Script untuk setup cron job membersihkan debug logs
# Jalankan script ini untuk mengatur cron job otomatis

echo "=== Setup Log Cleanup Cron Job ==="
echo ""

# Get current directory
CURRENT_DIR=$(pwd)
SCRIPT_PATH="$CURRENT_DIR/clear_debug_logs_cron.php"

echo "Current directory: $CURRENT_DIR"
echo "Script path: $SCRIPT_PATH"
echo ""

# Check if script exists
if [ ! -f "$SCRIPT_PATH" ]; then
    echo "Error: Script $SCRIPT_PATH not found!"
    exit 1
fi

# Make script executable
chmod +x "$SCRIPT_PATH"

echo "✓ Script is executable"
echo ""

# Create cron job entry
CRON_ENTRY="0 2 * * * cd $CURRENT_DIR && php $SCRIPT_PATH >> $CURRENT_DIR/storage/logs/cron_cleanup.log 2>&1"

echo "Cron job entry:"
echo "$CRON_ENTRY"
echo ""

# Add to crontab
echo "Adding cron job to crontab..."
(crontab -l 2>/dev/null; echo "$CRON_ENTRY") | crontab -

if [ $? -eq 0 ]; then
    echo "✓ Cron job added successfully!"
    echo ""
    echo "Cron job will run daily at 2:00 AM"
    echo "Logs will be saved to: $CURRENT_DIR/storage/logs/cron_cleanup.log"
    echo ""
    echo "To view current crontab: crontab -l"
    echo "To remove cron job: crontab -e (then delete the line)"
    echo ""
    echo "To test the script manually:"
    echo "php $SCRIPT_PATH"
else
    echo "✗ Failed to add cron job"
    exit 1
fi
