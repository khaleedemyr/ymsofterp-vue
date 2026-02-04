#!/bin/bash

# Quick Deploy Script - Fix CRM Dashboard 503 Error
# Usage: bash deploy_crm_fix.sh

echo "================================="
echo "🚀 Deploying CRM Dashboard Fix"
echo "================================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Step 1: Check if on server
if [ ! -d "/var/www/ymsofterp" ]; then
    echo -e "${RED}❌ Error: Not on server. Please run this script on production server.${NC}"
    exit 1
fi

echo -e "${YELLOW}📂 Changing directory to /var/www/ymsofterp${NC}"
cd /var/www/ymsofterp

# Step 2: Pull latest code
echo ""
echo -e "${YELLOW}📥 Pulling latest code from Git...${NC}"
git pull origin main

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Failed to pull from Git${NC}"
    exit 1
fi
echo -e "${GREEN}✅ Code pulled successfully${NC}"

# Step 3: Apply database indexes (if SQL file exists)
if [ -f "fix_crm_dashboard_503.sql" ]; then
    echo ""
    echo -e "${YELLOW}💾 Applying database indexes...${NC}"
    read -sp "Enter MySQL root password: " MYSQL_PASSWORD
    echo ""
    
    mysql -u root -p"$MYSQL_PASSWORD" < fix_crm_dashboard_503.sql
    
    if [ $? -ne 0 ]; then
        echo -e "${RED}❌ Failed to apply database indexes${NC}"
        echo -e "${YELLOW}⚠️  You may need to apply indexes manually${NC}"
    else
        echo -e "${GREEN}✅ Database indexes applied successfully${NC}"
    fi
else
    echo -e "${YELLOW}⚠️  SQL file not found, skipping database indexes${NC}"
fi

# Step 4: Clear Laravel cache
echo ""
echo -e "${YELLOW}🗑️  Clearing Laravel cache...${NC}"
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
echo -e "${GREEN}✅ Cache cleared${NC}"

# Step 5: Restart PHP-FPM
echo ""
echo -e "${YELLOW}🔄 Restarting PHP-FPM...${NC}"
systemctl restart php-fpm

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Failed to restart PHP-FPM${NC}"
    exit 1
fi
echo -e "${GREEN}✅ PHP-FPM restarted${NC}"

# Step 6: Check PHP-FPM status
echo ""
echo -e "${YELLOW}🔍 Checking PHP-FPM status...${NC}"
systemctl status php-fpm --no-pager | head -n 5

# Step 7: Monitor CPU for 5 seconds
echo ""
echo -e "${YELLOW}📊 Monitoring CPU usage (5 seconds)...${NC}"
echo -e "${YELLOW}Press Ctrl+C to stop${NC}"
sleep 2
ps aux --sort=-%cpu | head -10

echo ""
echo "================================="
echo -e "${GREEN}✅ Deployment completed!${NC}"
echo "================================="
echo ""
echo "Next steps:"
echo "1. Test dashboard at: https://your-domain.com/crm/dashboard"
echo "2. Monitor logs: tail -f storage/logs/laravel.log"
echo "3. Monitor CPU: top"
echo ""
echo -e "${YELLOW}If still getting 503, check:${NC}"
echo "  - PHP-FPM error log: /var/log/php-fpm/ymsofterp_com-error.log"
echo "  - MySQL slow query log: /var/log/mysql/slow-query.log"
echo "  - Laravel log: storage/logs/laravel.log"
echo ""
