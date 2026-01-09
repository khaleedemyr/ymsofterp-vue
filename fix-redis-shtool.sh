#!/bin/bash

echo "=========================================="
echo "🔴 FIX REDIS SHTOOL ERROR"
echo "=========================================="
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo "⚠️  Please run as root"
    exit 1
fi

PHP_VERSION="82"
PHPIZE_BIN="/opt/cpanel/ea-php${PHP_VERSION}/root/usr/bin/phpize"
PHP_CONFIG="/opt/cpanel/ea-php${PHP_VERSION}/root/usr/bin/php-config"
PHP_INI="/opt/cpanel/ea-php${PHP_VERSION}/root/etc/php.ini"
EXT_DIR="/opt/cpanel/ea-php${PHP_VERSION}/root/usr/lib64/php/modules"

# Step 1: Clean directory
echo "1️⃣ CLEANING DIRECTORY..."
echo "----------------------------------------"
cd /tmp
rm -rf redis-5.3.7 redis-5.3.7.tgz redis-latest.tgz redis-* 2>/dev/null
echo "✅ Directory cleaned"
echo ""

# Step 2: Download Redis source
echo "2️⃣ DOWNLOADING REDIS SOURCE..."
echo "----------------------------------------"
REDIS_VERSION="5.3.7"
REDIS_FILE="redis-${REDIS_VERSION}.tgz"

wget "https://pecl.php.net/get/${REDIS_FILE}" -O "$REDIS_FILE"

if [ $? -ne 0 ]; then
    echo "❌ Failed to download Redis source"
    exit 1
fi
echo "✅ Redis source downloaded"
echo ""

# Step 3: Extract
echo "3️⃣ EXTRACTING SOURCE..."
echo "----------------------------------------"
tar -xzf "$REDIS_FILE"
if [ $? -ne 0 ]; then
    echo "❌ Failed to extract"
    exit 1
fi

cd "redis-${REDIS_VERSION}"
echo "✅ Source extracted"
echo ""

# Step 4: Set permissions
echo "4️⃣ SETTING PERMISSIONS..."
echo "----------------------------------------"
# Create build directory if not exists
mkdir -p build 2>/dev/null

# Set permissions
chmod +x build/shtool 2>/dev/null || true
chmod +x configure 2>/dev/null || true
chmod +x config.guess 2>/dev/null || true
chmod +x config.sub 2>/dev/null || true

echo "✅ Permissions set"
echo ""

# Step 5: Run phpize
echo "5️⃣ RUNNING PHPIZE..."
echo "----------------------------------------"
$PHPIZE_BIN

if [ $? -ne 0 ]; then
    echo "⚠️  phpize had warnings, but continuing..."
fi
echo ""

# Step 6: Check shtool
echo "6️⃣ CHECKING SHTOOL..."
echo "----------------------------------------"
if [ -f "build/shtool" ]; then
    chmod +x build/shtool
    echo "✅ shtool found and made executable"
else
    echo "⚠️  shtool not found, trying to create it..."
    
    # Try to install shtool
    if command -v shtool &> /dev/null; then
        echo "✅ shtool command available"
    else
        echo "⚠️  Installing shtool..."
        dnf install -y shtool 2>/dev/null || yum install -y shtool 2>/dev/null
        
        if [ $? -eq 0 ]; then
            echo "✅ shtool installed"
        else
            echo "⚠️  Could not install shtool via package manager"
            echo "   Continuing anyway..."
        fi
    fi
fi
echo ""

# Step 7: Configure
echo "7️⃣ CONFIGURING..."
echo "----------------------------------------"
./configure --with-php-config="$PHP_CONFIG"

if [ $? -ne 0 ]; then
    echo "❌ configure failed"
    echo ""
    echo "Troubleshooting:"
    echo "1. Check if php-config exists: $PHP_CONFIG"
    echo "2. Check if phpize worked: ls -la configure"
    exit 1
fi
echo "✅ configure completed"
echo ""

# Step 8: Make
echo "8️⃣ COMPILING (this may take a few minutes)..."
echo "----------------------------------------"
make

if [ $? -ne 0 ]; then
    echo "❌ make failed"
    echo ""
    echo "Check error above for details"
    exit 1
fi
echo "✅ compile completed"
echo ""

# Step 9: Install
echo "9️⃣ INSTALLING..."
echo "----------------------------------------"
make install

if [ $? -ne 0 ]; then
    echo "❌ make install failed"
    exit 1
fi
echo "✅ install completed"
echo ""

# Step 10: Check extension file
echo "🔟 CHECKING EXTENSION FILE..."
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

# Step 11: Enable extension
echo "1️⃣1️⃣ ENABLING EXTENSION..."
echo "----------------------------------------"
if grep -q "^extension=redis.so" "$PHP_INI"; then
    echo "✅ Extension already enabled"
elif grep -q "extension=redis.so" "$PHP_INI"; then
    echo "✅ Extension found (may be commented), uncommenting..."
    sed -i 's/;extension=redis.so/extension=redis.so/' "$PHP_INI"
else
    echo "Adding extension=redis.so to php.ini..."
    echo "" >> "$PHP_INI"
    echo "; Redis extension" >> "$PHP_INI"
    echo "extension=redis.so" >> "$PHP_INI"
    echo "✅ Extension enabled"
fi
echo ""

# Step 12: Restart PHP-FPM
echo "1️⃣2️⃣ RESTARTING PHP-FPM..."
echo "----------------------------------------"
if systemctl restart "ea-php${PHP_VERSION}-php-fpm" 2>/dev/null; then
    echo "✅ PHP-FPM restarted"
    sleep 2
else
    echo "⚠️  Could not restart PHP-FPM automatically"
    echo "   Please restart manually: systemctl restart ea-php82-php-fpm"
fi
echo ""

# Step 13: Verify
echo "1️⃣3️⃣ VERIFYING INSTALLATION..."
echo "----------------------------------------"
PHP_BIN="/opt/cpanel/ea-php${PHP_VERSION}/root/usr/bin/php"

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
        exit 1
    fi
else
    echo "❌ PHP binary not found"
    exit 1
fi
echo ""

# Summary
echo "=========================================="
echo "📋 SUMMARY"
echo "=========================================="
echo ""
echo "✅ PHP Redis extension: INSTALLED"
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
echo ""
echo "2. Jika berhasil, update .env:"
echo "   CACHE_DRIVER=redis"
echo ""
echo "=========================================="
