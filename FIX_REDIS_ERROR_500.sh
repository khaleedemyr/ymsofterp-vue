#!/bin/bash

echo "=========================================="
echo "🔴 FIX REDIS ERROR 500"
echo "=========================================="
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo "⚠️  Please run as root"
    exit 1
fi

# Step 1: Check PHP Redis extension
echo "1️⃣ CHECKING PHP REDIS EXTENSION..."
echo "----------------------------------------"
PHP_VERSION="82"  # Adjust if needed
PHP_BIN="/opt/cpanel/ea-php${PHP_VERSION}/root/usr/bin/php"

if [ -f "$PHP_BIN" ]; then
    if $PHP_BIN -m | grep -q redis; then
        echo "✅ PHP Redis extension already installed"
    else
        echo "🔴 PHP Redis extension NOT FOUND!"
        echo "Installing PHP Redis extension..."
        
        # Try to install via dnf first (cPanel)
        if dnf install -y "ea-php${PHP_VERSION}-php-redis" 2>/dev/null; then
            echo "✅ PHP Redis extension installed via dnf (cPanel)"
        else
            # Try to install via dnf (standard PHP)
            if dnf install -y php-redis 2>/dev/null; then
                echo "✅ PHP Redis extension installed via dnf"
            else
                echo "❌ Failed to install PHP Redis extension via dnf"
                echo "   Please install manually:"
                echo "   dnf install -y php-redis"
                echo "   or"
                echo "   dnf install -y ea-php82-php-redis"
                exit 1
            fi
        fi
        
        # Restart PHP-FPM
        echo "Restarting PHP-FPM..."
        if systemctl restart "ea-php${PHP_VERSION}-php-fpm" 2>/dev/null; then
            echo "✅ PHP-FPM restarted"
        elif systemctl restart php-fpm 2>/dev/null; then
            echo "✅ PHP-FPM restarted"
        else
            echo "⚠️  Could not restart PHP-FPM automatically"
        fi
        
        # Verify
        sleep 2
        if $PHP_BIN -m | grep -q redis; then
            echo "✅ PHP Redis extension installed successfully"
        else
            echo "❌ PHP Redis extension installed but not detected"
            echo "   Please restart PHP-FPM manually and check again"
            exit 1
        fi
    fi
else
    echo "⚠️  PHP ${PHP_VERSION} not found, checking default PHP..."
    if php -m | grep -q redis; then
        echo "✅ PHP Redis extension already installed"
    else
        echo "🔴 PHP Redis extension NOT FOUND!"
        echo "Installing PHP Redis extension..."
        if dnf install -y php-redis 2>/dev/null; then
            echo "✅ PHP Redis extension installed"
            systemctl restart php-fpm 2>/dev/null || systemctl restart "ea-php${PHP_VERSION}-php-fpm" 2>/dev/null
        else
            echo "❌ Failed to install PHP Redis extension"
            exit 1
        fi
    fi
fi
echo ""

# Step 2: Check Redis service
echo "2️⃣ CHECKING REDIS SERVICE..."
echo "----------------------------------------"
if systemctl is-active --quiet redis; then
    echo "✅ Redis service is running"
else
    echo "🔴 Redis service is NOT running!"
    echo "Starting Redis service..."
    systemctl start redis
    systemctl enable redis
    
    if systemctl is-active --quiet redis; then
        echo "✅ Redis service started"
    else
        echo "❌ Failed to start Redis service"
        exit 1
    fi
fi
echo ""

# Step 3: Test Redis connection
echo "3️⃣ TESTING REDIS CONNECTION..."
echo "----------------------------------------"
if redis-cli ping | grep -q PONG; then
    echo "✅ Redis server is responding"
else
    echo "❌ Redis server is not responding"
    exit 1
fi
echo ""

# Step 4: Test PHP Redis connection
echo "4️⃣ TESTING PHP REDIS CONNECTION..."
echo "----------------------------------------"
if [ -f "$PHP_BIN" ]; then
    PHP_TEST=$($PHP_BIN -r "try { \$r = new Redis(); \$r->connect('127.0.0.1', 6379); echo 'Connected'; } catch (Exception \$e) { echo 'Failed: ' . \$e->getMessage(); }" 2>&1)
    if echo "$PHP_TEST" | grep -q "Connected"; then
        echo "✅ PHP Redis connection successful"
    else
        echo "❌ PHP Redis connection failed: $PHP_TEST"
        exit 1
    fi
else
    PHP_TEST=$(php -r "try { \$r = new Redis(); \$r->connect('127.0.0.1', 6379); echo 'Connected'; } catch (Exception \$e) { echo 'Failed: ' . \$e->getMessage(); }" 2>&1)
    if echo "$PHP_TEST" | grep -q "Connected"; then
        echo "✅ PHP Redis connection successful"
    else
        echo "❌ PHP Redis connection failed: $PHP_TEST"
        exit 1
    fi
fi
echo ""

# Summary
echo "=========================================="
echo "📋 SUMMARY"
echo "=========================================="
echo ""
echo "✅ PHP Redis extension: Installed"
echo "✅ Redis service: Running"
echo "✅ Redis connection: Working"
echo "✅ PHP Redis connection: Working"
echo ""

echo "=========================================="
echo "📝 NEXT STEPS"
echo "=========================================="
echo ""
echo "1. Test Redis via Laravel Tinker:"
echo "   cd /path/to/laravel"
echo "   php artisan tinker"
echo "   >>> Redis::connection()->ping();"
echo "   >>> Cache::store('redis')->put('test', 'Hello', 60);"
echo "   >>> Cache::store('redis')->get('test');"
echo ""
echo "2. Jika semua test berhasil, update .env:"
echo "   CACHE_DRIVER=redis"
echo ""
echo "3. Clear config cache:"
echo "   php artisan config:clear"
echo "   php artisan cache:clear"
echo ""
echo "4. Test aplikasi lagi"
echo ""
echo "=========================================="
