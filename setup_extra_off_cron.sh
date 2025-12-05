#!/bin/bash

# Setup script for Extra Off Detection Cron Job
# This script sets up the cron job for automatic extra off detection

echo "=== Extra Off Detection Cron Job Setup ==="
echo ""

# Get the current directory (project root)
PROJECT_DIR=$(pwd)
echo "Project directory: $PROJECT_DIR"

# Check if Laravel project
if [ ! -f "artisan" ]; then
    echo "Error: This doesn't appear to be a Laravel project (artisan file not found)"
    exit 1
fi

# Create cron job entry
CRON_ENTRY="0 7 * * * cd $PROJECT_DIR && php artisan extra-off:detect >> storage/logs/extra-off-detection.log 2>&1"
CRON_ENTRY2="30 23 * * * cd $PROJECT_DIR && php artisan extra-off:detect >> storage/logs/extra-off-detection.log 2>&1"

echo "Adding cron jobs for extra off detection..."
echo ""

# Add cron jobs
(crontab -l 2>/dev/null; echo "$CRON_ENTRY") | crontab -
(crontab -l 2>/dev/null; echo "$CRON_ENTRY2") | crontab -

echo "✅ Cron jobs added successfully!"
echo ""
echo "Cron jobs added:"
echo "1. Daily at 7:00 AM - Detect extra off for yesterday"
echo "2. Daily at 11:30 PM - Detect extra off for today (late scans)"
echo ""
echo "Log file: storage/logs/extra-off-detection.log"
echo ""
echo "To view current cron jobs: crontab -l"
echo "To remove cron jobs: crontab -e (then delete the lines)"
echo ""
echo "=== Setup Complete ==="
