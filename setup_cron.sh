#!/bin/bash

# Employee Movement Execution - Cron Job Setup Script
# Run this script to setup automatic execution of employee movements

echo "=== Employee Movement Execution - Cron Job Setup ==="
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
echo "Testing employee-movements:execute command..."
php artisan employee-movements:execute --help > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "✅ Command 'employee-movements:execute' is available"
else
    echo "❌ Error: Command 'employee-movements:execute' not found"
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
php artisan schedule:list | grep "employee-movements:execute" > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "✅ Employee movement execution is scheduled in Laravel"
    echo "   Schedule: Daily at 08:00"
    echo "   Log: storage/logs/employee-movements-execution.log"
else
    echo "❌ Warning: Employee movement execution not found in Laravel schedule"
    echo "   Please check app/Console/Kernel.php"
fi

echo ""

# Generate crontab entry
echo "=== Crontab Setup ==="
echo "Add this line to your crontab (run 'crontab -e'):"
echo ""
echo "* * * * * cd $PROJECT_PATH && php artisan schedule:run >> /dev/null 2>&1"
echo ""

# Check if crontab already has Laravel scheduler
if crontab -l 2>/dev/null | grep -q "artisan schedule:run"; then
    echo "✅ Laravel scheduler already exists in crontab"
else
    echo "⚠️  Laravel scheduler not found in crontab"
    echo "   You need to add the crontab entry above"
fi

echo ""

# Test manual execution
echo "=== Testing Manual Execution ==="
echo "Running employee-movements:execute command..."
php artisan employee-movements:execute

echo ""
echo "=== Setup Complete ==="
echo ""
echo "Next steps:"
echo "1. Add the crontab entry if not already present"
echo "2. Monitor logs at: storage/logs/employee-movements-execution.log"
echo "3. Test the scheduler: php artisan schedule:run"
echo ""
echo "For more details, see: CRON_JOB_SETUP.md"
