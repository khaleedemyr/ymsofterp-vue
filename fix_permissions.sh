#!/bin/bash
# Script untuk fix file permissions di server
# Jalankan di server: bash fix_permissions.sh

echo "=== FIXING FILE PERMISSIONS ==="
echo ""

# Get current directory
CURRENT_DIR=$(pwd)
echo "Current directory: $CURRENT_DIR"
echo ""

# Set permissions untuk storage dan cache
echo "1. Setting permissions for storage directory..."
chmod -R 775 storage
chmod -R 775 bootstrap/cache

echo "2. Setting ownership (adjust user:group sesuai server Anda)..."
# Ganti www-data:www-data dengan user:group yang sesuai di server Anda
# Untuk cPanel biasanya: ymsuperadmin:ymsuperadmin atau ymsuperadmin:nobody
chown -R ymsuperadmin:ymsuperadmin storage bootstrap/cache 2>/dev/null || chown -R ymsuperadmin:nobody storage bootstrap/cache 2>/dev/null || echo "Warning: Could not change ownership. You may need to run this as root or with sudo."

echo "3. Creating storage subdirectories if they don't exist..."
mkdir -p storage/app/public
mkdir -p storage/framework/cache
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs
mkdir -p bootstrap/cache

echo "4. Setting specific permissions for log file..."
touch storage/logs/laravel.log 2>/dev/null || echo "Warning: Could not create laravel.log"
chmod 664 storage/logs/laravel.log 2>/dev/null || echo "Warning: Could not set permission for laravel.log"
chown ymsuperadmin:ymsuperadmin storage/logs/laravel.log 2>/dev/null || chown ymsuperadmin:nobody storage/logs/laravel.log 2>/dev/null || echo "Warning: Could not change ownership of laravel.log"

echo ""
echo "=== VERIFYING PERMISSIONS ==="
echo ""
echo "Storage directory permissions:"
ls -la storage/ | head -5
echo ""
echo "Storage/logs permissions:"
ls -la storage/logs/ | head -5
echo ""
echo "Bootstrap/cache permissions:"
ls -la bootstrap/cache/ | head -5
echo ""

# Test write permission
echo "5. Testing write permission..."
if touch storage/logs/test_write.log 2>/dev/null; then
    echo "✓ Write permission OK"
    rm -f storage/logs/test_write.log
else
    echo "✗ Write permission FAILED"
    echo "You may need to run: chmod -R 777 storage bootstrap/cache (less secure but works)"
fi

echo ""
echo "=== DONE ==="
echo ""
echo "If permissions are still not working, try:"
echo "  chmod -R 777 storage bootstrap/cache"
echo "  (Less secure but ensures write access)"
echo ""

