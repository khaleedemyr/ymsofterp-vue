#!/bin/bash

# CRM Dashboard Monitoring Script
# Usage: bash monitor_crm.sh

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

clear
echo "================================="
echo "📊 CRM Dashboard Monitoring"
echo "================================="
echo ""

# Function to check CPU usage
check_cpu() {
    echo -e "${BLUE}🔍 Top 10 CPU Consumers:${NC}"
    echo "-----------------------------------"
    ps aux --sort=-%cpu | head -11
    echo ""
}

# Function to check PHP-FPM processes
check_phpfpm() {
    echo -e "${BLUE}🔍 PHP-FPM Processes:${NC}"
    echo "-----------------------------------"
    ps aux --sort=-%cpu | grep php-fpm | grep -v grep | head -10
    
    PHP_COUNT=$(ps aux | grep php-fpm | grep -v grep | wc -l)
    echo ""
    echo "Total PHP-FPM processes: $PHP_COUNT"
    
    # Get average CPU usage of php-fpm
    PHP_CPU=$(ps aux | grep php-fpm | grep -v grep | awk '{sum+=$3} END {print sum}')
    echo "Total PHP-FPM CPU usage: ${PHP_CPU}%"
    echo ""
}

# Function to check MySQL processes
check_mysql() {
    echo -e "${BLUE}🔍 MySQL Active Queries:${NC}"
    echo "-----------------------------------"
    read -sp "Enter MySQL root password: " MYSQL_PASSWORD
    echo ""
    
    mysql -u root -p"$MYSQL_PASSWORD" -e "SHOW FULL PROCESSLIST\G" | grep -v "Sleep" | head -50
    echo ""
}

# Function to tail Laravel logs
check_logs() {
    echo -e "${BLUE}🔍 Latest Laravel Logs (last 20 lines):${NC}"
    echo "-----------------------------------"
    
    LOG_FILE="/var/www/ymsofterp/storage/logs/laravel-$(date +%Y-%m-%d).log"
    
    if [ -f "$LOG_FILE" ]; then
        tail -20 "$LOG_FILE"
    else
        echo "No log file found for today"
        # Try to find latest log
        LATEST_LOG=$(ls -t /var/www/ymsofterp/storage/logs/laravel-*.log 2>/dev/null | head -1)
        if [ -n "$LATEST_LOG" ]; then
            echo "Latest log file: $LATEST_LOG"
            tail -20 "$LATEST_LOG"
        fi
    fi
    echo ""
}

# Function to check PHP-FPM error logs
check_phpfpm_errors() {
    echo -e "${BLUE}🔍 PHP-FPM Error Logs (last 20 lines):${NC}"
    echo "-----------------------------------"
    
    if [ -f "/var/log/php-fpm/ymsofterp_com-error.log" ]; then
        tail -20 /var/log/php-fpm/ymsofterp_com-error.log
    elif [ -f "/var/log/php-fpm/error.log" ]; then
        tail -20 /var/log/php-fpm/error.log
    else
        echo "PHP-FPM error log not found"
    fi
    echo ""
}

# Function to check system resources
check_system() {
    echo -e "${BLUE}🔍 System Resources:${NC}"
    echo "-----------------------------------"
    
    # CPU Load
    echo "CPU Load Average:"
    uptime
    echo ""
    
    # Memory
    echo "Memory Usage:"
    free -h
    echo ""
    
    # Disk
    echo "Disk Usage:"
    df -h | grep -E "Filesystem|/dev/"
    echo ""
}

# Function to test CRM Dashboard
test_dashboard() {
    echo -e "${BLUE}🔍 Testing CRM Dashboard Response Time:${NC}"
    echo "-----------------------------------"
    
    # Get the domain from nginx config or use default
    DOMAIN="localhost"
    
    echo "Testing: http://$DOMAIN/crm/dashboard"
    
    # Test response time with curl
    RESPONSE_TIME=$(curl -o /dev/null -s -w '%{time_total}\n' "http://$DOMAIN/crm/dashboard" 2>&1)
    HTTP_CODE=$(curl -o /dev/null -s -w '%{http_code}\n' "http://$DOMAIN/crm/dashboard" 2>&1)
    
    echo "HTTP Status Code: $HTTP_CODE"
    echo "Response Time: ${RESPONSE_TIME}s"
    
    # Evaluate performance
    if [ "$HTTP_CODE" == "200" ]; then
        echo -e "${GREEN}✅ Status: OK${NC}"
        
        # Check if response time is acceptable
        if (( $(echo "$RESPONSE_TIME < 5" | bc -l) )); then
            echo -e "${GREEN}✅ Performance: GOOD (< 5s)${NC}"
        elif (( $(echo "$RESPONSE_TIME < 10" | bc -l) )); then
            echo -e "${YELLOW}⚠️  Performance: ACCEPTABLE (5-10s)${NC}"
        else
            echo -e "${RED}❌ Performance: SLOW (> 10s)${NC}"
        fi
    else
        echo -e "${RED}❌ Status: ERROR${NC}"
    fi
    echo ""
}

# Menu
while true; do
    echo ""
    echo "================================="
    echo "Select monitoring option:"
    echo "================================="
    echo "1. Check CPU Usage"
    echo "2. Check PHP-FPM Processes"
    echo "3. Check MySQL Queries"
    echo "4. Check Laravel Logs"
    echo "5. Check PHP-FPM Error Logs"
    echo "6. Check System Resources"
    echo "7. Test Dashboard Response"
    echo "8. Run All Checks"
    echo "9. Real-time CPU Monitor (top)"
    echo "0. Exit"
    echo ""
    read -p "Enter choice [0-9]: " choice
    
    echo ""
    
    case $choice in
        1)
            check_cpu
            ;;
        2)
            check_phpfpm
            ;;
        3)
            check_mysql
            ;;
        4)
            check_logs
            ;;
        5)
            check_phpfpm_errors
            ;;
        6)
            check_system
            ;;
        7)
            test_dashboard
            ;;
        8)
            check_system
            check_cpu
            check_phpfpm
            check_logs
            test_dashboard
            ;;
        9)
            echo "Starting real-time monitor (Press 'q' to quit)..."
            sleep 2
            top
            ;;
        0)
            echo "Exiting..."
            exit 0
            ;;
        *)
            echo -e "${RED}Invalid choice. Please try again.${NC}"
            ;;
    esac
    
    read -p "Press Enter to continue..."
    clear
    echo "================================="
    echo "📊 CRM Dashboard Monitoring"
    echo "================================="
done
