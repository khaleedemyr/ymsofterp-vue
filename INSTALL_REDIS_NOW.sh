#!/bin/bash

echo "=========================================="
echo "🔴 INSTALL PHP REDIS EXTENSION - READY TO GO!"
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
        $PHP_BIN -m | grep redis
        exit 0
    fi
fi
echo "⚠️  PHP Redis extension not found, proceeding with installation..."
echo ""

# Step 2: Download Redis source
echo "2️⃣ DOWNLOADING REDIS SOURCE..."
echo "----------------------------------------"
cd /tmp
REDIS_VERSION="5.3.7"
REDIS_FILE="redis-${REDIS_VERSION}.tgz"
REDIS_DIR="redis-${REDIS_VERSION}"

if [ -d "$REDIS_DIR" ]; then
    echo "✅ Redis source already extracted"
    cd "$REDIS_DIR"
else
    if [ ! -f "$REDIS_FILE" ]; then
        echo "Downloading Redis ${REDIS_VERSION}..."
        wget "https://pecl.php.net/get/${REDIS_FILE}" -O "$REDIS_FILE"
        
        if [ $? -ne 0 ]; then
            echo "❌ Failed to download Redis source"
            exit 1
        fi
        echo "✅ Redis source downloaded"
    else
        echo "✅ Redis source already downloaded"
    fi
    
    echo "Extracting..."
    tar -xzf "$REDIS_FILE"
    if [ $? -ne 0 ]; then
        echo "❌ Failed to extract"
        exit 1
    fi
    cd "$REDIS_DIR"
    echo "✅ Source extracted"
fi
echo ""

# Step 3: Clean previous build (if any)
echo "3️⃣ CLEANING PREVIOUS BUILD..."
echo "----------------------------------------"
make clean 2>/dev/null || true
echo "✅ Clean completed"
echo ""

# Step 4: Run phpize
echo "4️⃣ RUNNING PHPIZE..."
echo "----------------------------------------"
$PHPIZE_BIN
if [ $? -ne 0 ]; then
    echo "❌ phpize failed"
    exit 1
fi
echo "✅ phpize completed"
echo ""

# Step 5: Configure
echo "5️⃣ CONFIGURING..."
echo "----------------------------------------"
./configure --with-php-config="$PHP_CONFIG"
if [ $? -ne 0 ]; then
    echo "❌ configure failed"
    exit 1
fi
echo "✅ configure completed"
echo ""

# Step 6: Compile
echo "6️⃣ COMPILING (this may take a few minutes)..."
echo "----------------------------------------"
make
if [ $? -ne 0 ]; then
    echo "❌ make failed"
    echo "Check error above for details"
    exit 1
fi
echo "✅ compile completed"
echo ""

# Step 7: Install
echo "7️⃣ INSTALLING..."
echo "----------------------------------------"
make install
if [ $? -ne 0 ]; then
    echo "❌ make install failed"
    exit 1
fi
echo "✅ install completed"
echo ""

# Step 8: Check extension file
echo "8️⃣ CHECKING EXTENSION FILE..."
echo "----------------------------------------"
if [ -f "$EXT_DIR/redis.so" ]; then
    echo "✅ Extension file found: $EXT_DIR/redis.so"
    ls -lh "$EXT_DIR/redis.so"
else
    echo "❌ Extension file not found"
    echo "   Expected: $EXT_DIR/redis.so"
    echo "   Check make install output above"
    exit 1
fi
echo ""

# Step 9: Enable extension
echo "9️⃣ ENABLING EXTENSION IN PHP.INI..."
echo "----------------------------------------"
if grep -q "^extension=redis.so" "$PHP_INI"; then
    echo "✅ Extension already enabled in php.ini"
elif grep -q "extension=redis.so" "$PHP_INI"; then
    echo "✅ Extension already enabled in php.ini (commented)"
    # Uncomment if commented
    sed -i 's/;extension=redis.so/extension=redis.so/' "$PHP_INI"
else
    echo "Adding extension=redis.so to php.ini..."
    echo "" >> "$PHP_INI"
    echo "; Redis extension" >> "$PHP_INI"
    echo "extension=redis.so" >> "$PHP_INI"
    echo "✅ Extension enabled in php.ini"
fi
echo ""

# Step 10: Restart PHP-FPM
echo "🔟 RESTARTING PHP-FPM..."
echo "----------------------------------------"
if systemctl restart "ea-php${PHP_VERSION}-php-fpm" 2>/dev/null; then
    echo "✅ PHP-FPM restarted"
    sleep 2
elif systemctl restart php-fpm 2>/dev/null; then
    echo "✅ PHP-FPM restarted"
    sleep 2
else
    echo "⚠️  Could not restart PHP-FPM automatically"
    echo "   Please restart manually: systemctl restart ea-php82-php-fpm"
fi
echo ""

# Step 11: Verify
echo "1️⃣1️⃣ VERIFYING INSTALLATION..."
echo "----------------------------------------"
if [ -f "$PHP_BIN" ]; then
    if $PHP_BIN -m | grep -q redis; then
        echo "✅ PHP Redis extension is installed and loaded"
        echo ""
        echo "Extension details:"
        $PHP_BIN -m | grep redis
    else
        echo "❌ PHP Redis extension not loaded"
        echo ""
        echo "Troubleshooting:"
        echo "1. Check php.ini: grep -i redis $PHP_INI"
        echo "2. Check extension file: ls -la $EXT_DIR/redis.so"
        echo "3. Check PHP error log"
        exit 1
    fi
else
    echo "❌ PHP binary not found at $PHP_BIN"
    exit 1
fi
echo ""

# Step 12: Test Redis connection
echo "1️⃣2️⃣ TESTING REDIS CONNECTION..."
echo "----------------------------------------"
if [ -f "$PHP_BIN" ]; then
    PHP_TEST=$($PHP_BIN -r "try { \$r = new Redis(); \$r->connect('127.0.0.1', 6379); echo 'Connected'; } catch (Exception \$e) { echo 'Failed: ' . \$e->getMessage(); }" 2>&1)
    
    if echo "$PHP_TEST" | grep -q "Connected"; then
        echo "✅ Redis connection successful"
    else
        echo "⚠️  Redis connection test: $PHP_TEST"
        echo ""
        echo "Note: This might be because Redis service is not running."
        echo "Check Redis: systemctl status redis"
        echo "If Redis is running, the extension is installed correctly."
    fi
fi
echo ""

# Summary
echo "=========================================="
echo "📋 SUMMARY"
echo "=========================================="
echo ""
echo "✅ PHP Redis extension: INSTALLED"
echo "✅ PHP version: $($PHP_BIN -v | head -1 | cut -d' ' -f2)"
echo "✅ Extension file: $EXT_DIR/redis.so"
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
echo "   php artisan cache:clear"
echo ""
echo "=========================================="
