#!/bin/bash

echo "=========================================="
echo "🔴 QUICK INSTALL PHP REDIS VIA PECL"
echo "=========================================="
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo "⚠️  Please run as root"
    exit 1
fi

PHP_VERSION="82"
PHP_BIN="/opt/cpanel/ea-php${PHP_VERSION}/root/usr/bin/php"
PECL_BIN="/opt/cpanel/ea-php${PHP_VERSION}/root/usr/bin/pecl"
PHP_INI="/opt/cpanel/ea-php${PHP_VERSION}/root/etc/php.ini"
EXT_DIR="/opt/cpanel/ea-php${PHP_VERSION}/root/usr/lib64/php/modules"

# Step 1: Check if already installed
echo "1️⃣ CHECKING PHP REDIS EXTENSION..."
echo "----------------------------------------"
if [ -f "$PHP_BIN" ]; then
    if $PHP_BIN -m | grep -q redis; then
        echo "✅ PHP Redis extension already installed"
        exit 0
    fi
else
    if php -m | grep -q redis; then
        echo "✅ PHP Redis extension already installed"
        exit 0
    fi
fi
echo "⚠️  PHP Redis extension not found"
echo ""

# Step 2: Install dependencies
echo "2️⃣ INSTALLING DEPENDENCIES..."
echo "----------------------------------------"
echo "Installing: php-pear, php-devel, gcc, make, autoconf..."

if [ -f "$PHP_BIN" ]; then
    # For cPanel
    dnf install -y ea-php${PHP_VERSION}-php-pear ea-php${PHP_VERSION}-php-devel gcc make autoconf 2>/dev/null || \
    dnf install -y php-pear php-devel gcc make autoconf 2>/dev/null
else
    dnf install -y php-pear php-devel gcc make autoconf 2>/dev/null
fi

if [ $? -eq 0 ]; then
    echo "✅ Dependencies installed"
else
    echo "⚠️  Some dependencies may not be installed, continuing..."
fi
echo ""

# Step 3: Check PECL
echo "3️⃣ CHECKING PECL..."
echo "----------------------------------------"
if [ -f "$PECL_BIN" ]; then
    echo "✅ PECL found: $PECL_BIN"
elif command -v pecl &> /dev/null; then
    PECL_BIN="pecl"
    echo "✅ PECL found: $PECL_BIN"
else
    echo "❌ PECL not found"
    echo "   Installing PECL..."
    if [ -f "$PHP_BIN" ]; then
        dnf install -y ea-php${PHP_VERSION}-php-pear 2>/dev/null || dnf install -y php-pear 2>/dev/null
    else
        dnf install -y php-pear 2>/dev/null
    fi
    
    if [ -f "$PECL_BIN" ]; then
        echo "✅ PECL installed"
    elif command -v pecl &> /dev/null; then
        PECL_BIN="pecl"
        echo "✅ PECL installed"
    else
        echo "❌ Failed to install PECL"
        exit 1
    fi
fi
echo ""

# Step 4: Install Redis extension via PECL
echo "4️⃣ INSTALLING REDIS EXTENSION VIA PECL..."
echo "----------------------------------------"
echo "This may take a few minutes..."

if [ -f "$PECL_BIN" ]; then
    echo "yes" | $PECL_BIN install redis 2>&1 | tee /tmp/pecl_redis_install.log
    PECL_EXIT=$?
elif command -v pecl &> /dev/null; then
    echo "yes" | pecl install redis 2>&1 | tee /tmp/pecl_redis_install.log
    PECL_EXIT=$?
else
    echo "❌ PECL not available"
    exit 1
fi

if [ $PECL_EXIT -eq 0 ]; then
    echo "✅ Redis extension installed via PECL"
else
    echo "⚠️  PECL install may have issues, checking extension file..."
    if [ -f "$EXT_DIR/redis.so" ]; then
        echo "✅ Extension file found: $EXT_DIR/redis.so"
    else
        echo "❌ Extension file not found"
        echo "   Check log: /tmp/pecl_redis_install.log"
        exit 1
    fi
fi
echo ""

# Step 5: Enable extension in php.ini
echo "5️⃣ ENABLING EXTENSION IN PHP.INI..."
echo "----------------------------------------"
if [ -f "$PHP_INI" ]; then
    if grep -q "extension=redis.so" "$PHP_INI"; then
        echo "✅ Extension already enabled in php.ini"
    else
        echo "extension=redis.so" >> "$PHP_INI"
        echo "✅ Extension enabled in php.ini"
    fi
else
    echo "⚠️  php.ini not found at $PHP_INI"
    echo "   Please add 'extension=redis.so' manually"
fi
echo ""

# Step 6: Restart PHP-FPM
echo "6️⃣ RESTARTING PHP-FPM..."
echo "----------------------------------------"
if systemctl restart "ea-php${PHP_VERSION}-php-fpm" 2>/dev/null; then
    echo "✅ PHP-FPM restarted"
elif systemctl restart php-fpm 2>/dev/null; then
    echo "✅ PHP-FPM restarted"
else
    echo "⚠️  Could not restart PHP-FPM automatically"
    echo "   Please restart manually: systemctl restart php-fpm"
fi
echo ""

# Step 7: Verify installation
echo "7️⃣ VERIFYING INSTALLATION..."
echo "----------------------------------------"
sleep 2

if [ -f "$PHP_BIN" ]; then
    if $PHP_BIN -m | grep -q redis; then
        echo "✅ PHP Redis extension is installed and loaded"
    else
        echo "❌ PHP Redis extension not loaded"
        echo "   Check: $PHP_INI"
        echo "   Check: $EXT_DIR/redis.so"
        exit 1
    fi
else
    if php -m | grep -q redis; then
        echo "✅ PHP Redis extension is installed and loaded"
    else
        echo "❌ PHP Redis extension not loaded"
        exit 1
    fi
fi
echo ""

# Step 8: Test Redis connection
echo "8️⃣ TESTING REDIS CONNECTION..."
echo "----------------------------------------"
if [ -f "$PHP_BIN" ]; then
    PHP_TEST=$($PHP_BIN -r "try { \$r = new Redis(); \$r->connect('127.0.0.1', 6379); echo 'Connected'; } catch (Exception \$e) { echo 'Failed: ' . \$e->getMessage(); }" 2>&1)
else
    PHP_TEST=$(php -r "try { \$r = new Redis(); \$r->connect('127.0.0.1', 6379); echo 'Connected'; } catch (Exception \$e) { echo 'Failed: ' . \$e->getMessage(); }" 2>&1)
fi

if echo "$PHP_TEST" | grep -q "Connected"; then
    echo "✅ Redis connection successful"
else
    echo "⚠️  Redis connection test: $PHP_TEST"
    echo "   Make sure Redis service is running: systemctl status redis"
fi
echo ""

# Summary
echo "=========================================="
echo "📋 SUMMARY"
echo "=========================================="
echo ""
echo "✅ PHP Redis extension: Installed"
if [ -f "$PHP_BIN" ]; then
    echo "✅ PHP version: $($PHP_BIN -v | head -1)"
else
    echo "✅ PHP version: $(php -v | head -1)"
fi
echo ""

echo "=========================================="
echo "📝 NEXT STEPS"
echo "=========================================="
echo ""
echo "1. Test via Laravel Tinker:"
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
echo ""
echo "=========================================="
