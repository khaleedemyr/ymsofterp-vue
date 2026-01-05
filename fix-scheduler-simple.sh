#!/bin/bash
cd /home/ymsuperadmin/public_html
PHP_PATH=$(which php 2>/dev/null || echo "/usr/bin/php")
echo "Clearing cache..."
$PHP_PATH artisan config:clear
$PHP_PATH artisan cache:clear
echo "Rebuilding cache..."
$PHP_PATH artisan config:cache
echo "Testing schedule:list..."
$PHP_PATH artisan schedule:list
echo "Done!"

