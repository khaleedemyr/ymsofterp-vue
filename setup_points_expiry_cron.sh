#!/bin/bash

# Point Expiry - Cron Job Setup Script
# Run this script to setup automatic point expiry

echo "=== Point Expiry - Cron Job Setup ==="
echo ""

# Get current directory
PROJECT_PATH=$(pwd)
echo "Project Path: $PROJECT_PATH"
echo ""

# Check if Laravel project
if [ ! -f "artisan" ]; then
    echo "❌ Error: This doesn't appear to be a Laravel project (artisan file not found)"
    exit 1
fi

echo "✅ Laravel project detected"
echo ""

# Check if command exists
echo "Testing points:expire command..."
php artisan points:expire --help > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "✅ Command 'points:expire' is available"
else
    echo "❌ Error: Command 'points:expire' not found"
    echo "Make sure the command is properly registered"
    exit 1
fi

echo ""

# Create log directory if not exists
mkdir -p storage/logs
echo "✅ Log directory ready: storage/logs/"

# Test Laravel scheduler
echo ""
echo "Testing Laravel scheduler..."
php artisan schedule:list | grep "points:expire" > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "✅ Point expiry is scheduled in Laravel"
    echo "   Schedule: Daily at 00:00 (midnight)"
    echo "   Log: storage/logs/points-expiry.log"
else
    echo "❌ Warning: Point expiry not found in Laravel schedule"
    echo "   Please check app/Console/Kernel.php"
fi

echo ""

# Generate crontab entry
echo "=== Crontab Setup ==="
echo "IMPORTANT: Laravel uses a single scheduler that runs every minute."
echo "If you already have 'schedule:run' in your crontab, you don't need to add another one."
echo ""
echo "Add this line to your crontab (run 'crontab -e'):"
echo ""
echo "* * * * * cd $PROJECT_PATH && php artisan schedule:run >> /dev/null 2>&1"
echo ""

# Check if crontab already has Laravel scheduler
if crontab -l 2>/dev/null | grep -q "artisan schedule:run"; then
    echo "✅ Laravel scheduler already exists in crontab"
    echo "   Point expiry will run automatically at 00:00 daily"
else
    echo "⚠️  Laravel scheduler not found in crontab"
    echo "   You need to add the crontab entry above"
    echo "   This single entry will run ALL scheduled commands including:"
    echo "   - Point expiry (daily at 00:00)"
    echo "   - Member tier update (monthly on 1st)"
    echo "   - Employee movements (daily at 08:00)"
    echo "   - And other scheduled commands"
fi

echo ""

# Test manual execution (dry run)
echo "=== Testing Manual Execution (Dry Run) ==="
echo "Running points:expire command with --dry-run option..."
php artisan points:expire --dry-run

echo ""
echo "=== Setup Complete ==="
echo ""
echo "Next steps:"
echo "1. Add the crontab entry if not already present (only ONE entry needed for all scheduled commands)"
echo "2. Monitor logs at: storage/logs/points-expiry.log"
echo "3. Test the scheduler: php artisan schedule:run"
echo "4. Test point expiry manually: php artisan points:expire --dry-run"
echo ""
echo "For more details, see: POINTS_EXPIRY_CRON_SETUP.md"
echo ""
echo "📝 Note: Point expiry will run automatically every day at midnight (00:00)"
echo "   The command will:"
echo "   - Find all expired point transactions"
echo "   - Reduce member point balance"
echo "   - Mark transactions as expired"
echo "   - Create tracking records for audit"

