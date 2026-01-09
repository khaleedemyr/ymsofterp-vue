#!/bin/bash

echo "=========================================="
echo "🔴 INSTALL PHP REDIS EXTENSION MANUAL"
echo "=========================================="
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo "⚠️  Please run as root"
    exit 1
fi

PHP_VERSION="82"
PHP_BIN="/opt/cpanel/ea-php${PHP_VERSION}/root/usr/bin/php"
PHPIZE_BIN="/opt/cpanel/ea-php${PHP_VERSION}/root/usr/bin/phpize"
PHP_CONFIG="/opt/cpanel/ea-php${PHP_VERSION}/root/usr/bin/php-config"
PHP_INI="/opt/cpanel/ea-php${PHP_VERSION}/root/etc/php.ini"
EXT_DIR="/opt/cpanel/ea-php${PHP_VERSION}/root/usr/lib64/php/modules"

# Step 1: Check if already installed
echo "1️⃣ CHECKING IF ALREADY INSTALLED..."
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

# Step 2: Check dependencies
echo "2️⃣ CHECKING DEPENDENCIES..."
echo "----------------------------------------"
MISSING_DEPS=0

if ! command -v gcc &> /dev/null; then
    echo "❌ gcc not found"
    MISSING_DEPS=1
else
    echo "✅ gcc found"
fi

if ! command -v make &> /dev/null; then
    echo "❌ make not found"
    MISSING_DEPS=1
else
    echo "✅ make found"
fi

if ! command -v autoconf &> /dev/null; then
    echo "❌ autoconf not found"
    MISSING_DEPS=1
else
    echo "✅ autoconf found"
fi

if [ ! -f "$PHPIZE_BIN" ]; then
    echo "❌ phpize not found at $PHPIZE_BIN"
    MISSING_DEPS=1
else
    echo "✅ phpize found"
fi

if [ $MISSING_DEPS -eq 1 ]; then
    echo ""
    echo "❌ Missing dependencies. Please install them first."
    exit 1
fi
echo ""

# Step 3: Download Redis source
echo "3️⃣ DOWNLOADING REDIS SOURCE..."
echo "----------------------------------------"
cd /tmp
REDIS_VERSION="5.3.7"
REDIS_FILE="redis-${REDIS_VERSION}.tgz"

if [ -f "$REDIS_FILE" ]; then
    echo "✅ Redis source already downloaded"
else
    echo "Downloading Redis ${REDIS_VERSION}..."
    wget "https://pecl.php.net/get/${REDIS_FILE}" -O "$REDIS_FILE"
    
    if [ $? -ne 0 ]; then
        echo "❌ Failed to download Redis source"
        exit 1
    fi
    echo "✅ Redis source downloaded"
fi
echo ""

# Step 4: Extract source
echo "4️⃣ EXTRACTING SOURCE..."
echo "----------------------------------------"
if [ -d "redis-${REDIS_VERSION}" ]; then
    echo "✅ Source already extracted"
    cd "redis-${REDIS_VERSION}"
else
    echo "Extracting..."
    tar -xzf "$REDIS_FILE"
    if [ $? -ne 0 ]; then
        echo "❌ Failed to extract"
        exit 1
    fi
    cd "redis-${REDIS_VERSION}"
    echo "✅ Source extracted"
fi
echo ""

# Step 5: Run phpize
echo "5️⃣ RUNNING PHPIZE..."
echo "----------------------------------------"
$PHPIZE_BIN
if [ $? -ne 0 ]; then
    echo "❌ phpize failed"
    exit 1
fi
echo "✅ phpize completed"
echo ""

# Step 6: Configure
echo "6️⃣ CONFIGURING..."
echo "----------------------------------------"
./configure --with-php-config="$PHP_CONFIG"
if [ $? -ne 0 ]; then
    echo "❌ configure failed"
    exit 1
fi
echo "✅ configure completed"
echo ""

# Step 7: Compile
echo "7️⃣ COMPILING..."
echo "----------------------------------------"
make
if [ $? -ne 0 ]; then
    echo "❌ make failed"
    exit 1
fi
echo "✅ compile completed"
echo ""

# Step 8: Install
echo "8️⃣ INSTALLING..."
echo "----------------------------------------"
make install
if [ $? -ne 0 ]; then
    echo "❌ make install failed"
    exit 1
fi
echo "✅ install completed"
echo ""

# Step 9: Check extension file
echo "9️⃣ CHECKING EXTENSION FILE..."
echo "----------------------------------------"
if [ -f "$EXT_DIR/redis.so" ]; then
    echo "✅ Extension file found: $EXT_DIR/redis.so"
else
    echo "❌ Extension file not found"
    echo "   Expected: $EXT_DIR/redis.so"
    exit 1
fi
echo ""

# Step 10: Enable extension
echo "🔟 ENABLING EXTENSION..."
echo "----------------------------------------"
if grep -q "extension=redis.so" "$PHP_INI"; then
    echo "✅ Extension already enabled in php.ini"
else
    echo "extension=redis.so" >> "$PHP_INI"
    echo "✅ Extension enabled in php.ini"
fi
echo ""

# Step 11: Restart PHP-FPM
echo "1️⃣1️⃣ RESTARTING PHP-FPM..."
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

# Step 12: Verify
echo "1️⃣2️⃣ VERIFYING INSTALLATION..."
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

# Step 13: Test Redis connection
echo "1️⃣3️⃣ TESTING REDIS CONNECTION..."
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
