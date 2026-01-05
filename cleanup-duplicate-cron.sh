#!/bin/bash

# ============================================
# Script untuk Cleanup Duplicate Cron Jobs
# ============================================

echo "============================================"
echo "Cleanup Duplicate Cron Jobs"
echo "============================================"
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Path to Laravel app
APP_PATH="/home/ymsuperadmin/public_html"

# Check if app path exists
if [ ! -d "$APP_PATH" ]; then
    echo -e "${RED}ERROR:${NC} App path tidak ditemukan: $APP_PATH"
    echo "Edit script ini dan ubah APP_PATH sesuai path aplikasi Anda"
    exit 1
fi

cd "$APP_PATH" || exit 1

echo "1. Checking current cron jobs..."
echo ""

# Get current cron jobs
CURRENT_CRON=$(crontab -l 2>/dev/null)

if [ -z "$CURRENT_CRON" ]; then
    echo -e "${YELLOW}WARNING:${NC} Tidak ada cron jobs yang ditemukan"
    exit 0
fi

# Count total cron jobs
TOTAL_CRON=$(echo "$CURRENT_CRON" | grep -v "^#" | grep -v "^$" | wc -l)
echo "   Total cron jobs: $TOTAL_CRON"
echo ""

# Check for schedule:run
if echo "$CURRENT_CRON" | grep -q "schedule:run"; then
    echo -e "2. schedule:run: ${GREEN}FOUND${NC} ✅"
else
    echo -e "2. schedule:run: ${RED}NOT FOUND${NC} ❌"
    echo "   Action: Tambahkan ini ke cron:"
    echo "   * * * * * cd $APP_PATH && php artisan schedule:run >> /dev/null 2>&1"
fi
echo ""

# Check for queue worker (every minute)
QUEUE_WORKER_COUNT=$(echo "$CURRENT_CRON" | grep -c "queue:work.*\* \* \* \* \*")
if [ "$QUEUE_WORKER_COUNT" -gt 0 ]; then
    echo -e "3. Queue Worker (setiap menit): ${RED}FOUND${NC} ⚠️"
    echo "   Action: HAPUS ini dan setup dengan supervisor!"
    echo "   Lihat: MIGRASI_CRON_KE_SCHEDULER.md"
else
    echo -e "3. Queue Worker (setiap menit): ${GREEN}NOT FOUND${NC} ✅"
fi
echo ""

# List of duplicate commands to check
DUPLICATE_COMMANDS=(
    "attendance:process-holiday"
    "extra-off:detect"
    "employee-movements:execute"
    "leave:monthly-credit"
    "leave:burn-previous-year"
    "vouchers:distribute-birthday"
    "attendance:cleanup-logs"
    "members:update-tiers"
    "points:expire"
    "member:notify-incomplete-profile"
    "member:notify-incomplete-challenge"
    "member:notify-inactive"
    "member:notify-long-inactive"
    "member:notify-expiring-points"
    "member:notify-monthly-inactive"
    "member:notify-expiring-vouchers"
    "device-tokens:cleanup"
)

echo "4. Checking duplicate cron jobs..."
echo ""

DUPLICATE_COUNT=0
for cmd in "${DUPLICATE_COMMANDS[@]}"; do
    COUNT=$(echo "$CURRENT_CRON" | grep -c "$cmd")
    if [ "$COUNT" -gt 0 ]; then
        echo -e "   ${YELLOW}DUPLICATE:${NC} $cmd (found $COUNT times)"
        DUPLICATE_COUNT=$((DUPLICATE_COUNT + COUNT))
    fi
done

if [ "$DUPLICATE_COUNT" -eq 0 ]; then
    echo -e "   ${GREEN}No duplicates found${NC} ✅"
else
    echo ""
    echo -e "   Total duplicate cron jobs: ${RED}$DUPLICATE_COUNT${NC}"
    echo ""
    echo "   Action: Hapus semua duplicate cron jobs ini"
    echo "   Lihat: CRON_JOBS_TO_DELETE.md untuk daftar lengkap"
fi
echo ""

# Check if Kernel.php has schedule method
echo "5. Checking Laravel Scheduler..."
if [ -f "app/Console/Kernel.php" ]; then
    if grep -q "protected function schedule" app/Console/Kernel.php; then
        SCHEDULE_COUNT=$(grep -c "\$schedule->command" app/Console/Kernel.php)
        echo -e "   Scheduler: ${GREEN}FOUND${NC} ✅"
        echo "   Scheduled tasks: $SCHEDULE_COUNT"
    else
        echo -e "   Scheduler: ${RED}NOT FOUND${NC} ❌"
    fi
else
    echo -e "   Kernel.php: ${RED}NOT FOUND${NC} ❌"
fi
echo ""

# Summary
echo "============================================"
echo "SUMMARY"
echo "============================================"
echo ""

if [ "$DUPLICATE_COUNT" -gt 0 ] || [ "$QUEUE_WORKER_COUNT" -gt 0 ]; then
    echo -e "${RED}ACTION REQUIRED:${NC}"
    echo ""
    
    if [ "$QUEUE_WORKER_COUNT" -gt 0 ]; then
        echo "1. ⚠️  URGENT: Fix queue worker (setup supervisor)"
        echo "   Lihat: MIGRASI_CRON_KE_SCHEDULER.md - Langkah 1"
        echo ""
    fi
    
    if [ "$DUPLICATE_COUNT" -gt 0 ]; then
        echo "2. Hapus $DUPLICATE_COUNT duplicate cron jobs"
        echo "   Lihat: CRON_JOBS_TO_DELETE.md untuk daftar lengkap"
        echo ""
    fi
    
    if ! echo "$CURRENT_CRON" | grep -q "schedule:run"; then
        echo "3. Tambahkan schedule:run ke cron:"
        echo "   * * * * * cd $APP_PATH && php artisan schedule:run >> /dev/null 2>&1"
        echo ""
    fi
    
    echo "Expected result after cleanup:"
    echo "  - Total cron jobs: 1-2 (schedule:run + queue worker via supervisor)"
    echo "  - CPU usage: 30-50% (dari 100%)"
    echo "  - No duplicate execution"
else
    echo -e "${GREEN}All good!${NC} ✅"
    echo "No duplicate cron jobs found."
    echo ""
    
    if ! echo "$CURRENT_CRON" | grep -q "schedule:run"; then
        echo -e "${YELLOW}Note:${NC} Pastikan schedule:run ada di cron"
    fi
fi

echo ""
echo "============================================"
echo "Next Steps:"
echo "============================================"
echo "1. Review: MIGRASI_CRON_KE_SCHEDULER.md"
echo "2. Review: CRON_JOBS_TO_DELETE.md"
echo "3. Fix queue worker (URGENT!)"
echo "4. Hapus duplicate cron jobs"
echo "5. Monitor CPU usage selama 24 jam"
echo ""

