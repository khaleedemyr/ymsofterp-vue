#!/bin/bash

echo "=========================================="
echo "🔴 QUICK INSTALL REDIS - ALMALINUX 9"
echo "=========================================="
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo "⚠️  Please run as root"
    exit 1
fi

# Check OS
if [ ! -f /etc/almalinux-release ]; then
    echo "⚠️  This script is for AlmaLinux 9"
    read -p "Continue anyway? (y/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

# Step 1: Check if Redis already installed
echo "1️⃣ CHECKING REDIS INSTALLATION..."
echo "----------------------------------------"
if command -v redis-cli &> /dev/null; then
    echo "✅ Redis server already installed"
    redis-cli ping
else
    echo "⚠️  Redis server not found, installing..."
    
    # Install EPEL (if not installed)
    if ! rpm -q epel-release &> /dev/null; then
        echo "Installing EPEL repository..."
        dnf install -y epel-release
    fi
    
    # Install Redis
    echo "Installing Redis server..."
    dnf install -y redis
    
    # Start and enable Redis
    systemctl start redis
    systemctl enable redis
    
    # Test
    if redis-cli ping &> /dev/null; then
        echo "✅ Redis server installed and running"
    else
        echo "❌ Failed to start Redis"
        exit 1
    fi
fi
echo ""

# Step 2: Check PHP Redis extension
echo "2️⃣ CHECKING PHP REDIS EXTENSION..."
echo "----------------------------------------"
PHP_VERSION="82"  # Adjust if needed
PHP_BIN="/opt/cpanel/ea-php${PHP_VERSION}/root/usr/bin/php"

if [ -f "$PHP_BIN" ]; then
    if $PHP_BIN -m | grep -q redis; then
        echo "✅ PHP Redis extension already installed"
    else
        echo "⚠️  PHP Redis extension not found, installing..."
        
        # Try to install via dnf first (cPanel)
        if dnf install -y "ea-php${PHP_VERSION}-php-redis" 2>/dev/null; then
            echo "✅ PHP Redis extension installed via dnf (cPanel)"
        else
            # Try to install via dnf (standard PHP)
            if dnf install -y php-redis 2>/dev/null; then
                echo "✅ PHP Redis extension installed via dnf"
            else
                echo "⚠️  Installing via PECL..."
                PECL_BIN="/opt/cpanel/ea-php${PHP_VERSION}/root/usr/bin/pecl"
                if [ -f "$PECL_BIN" ]; then
                    $PECL_BIN install redis <<< "yes"
                    echo "✅ PHP Redis extension installed via PECL"
                else
                    # Try standard pecl
                    if command -v pecl &> /dev/null; then
                        pecl install redis <<< "yes"
                        echo "✅ PHP Redis extension installed via PECL"
                    else
                        echo "❌ PECL not found, please install manually"
                        echo "   Run: dnf install -y php-pear php-devel gcc"
                        exit 1
                    fi
                fi
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
        if [ -f "$PHP_BIN" ]; then
            if $PHP_BIN -m | grep -q redis; then
                echo "✅ PHP Redis extension installed successfully"
            else
                echo "⚠️  PHP Redis extension installed but not detected"
                echo "   Please restart PHP-FPM manually"
            fi
        else
            if php -m | grep -q redis; then
                echo "✅ PHP Redis extension installed successfully"
            else
                echo "⚠️  PHP Redis extension installed but not detected"
                echo "   Please restart PHP-FPM manually"
            fi
        fi
    fi
else
    echo "⚠️  PHP ${PHP_VERSION} not found, checking default PHP..."
    if php -m | grep -q redis; then
        echo "✅ PHP Redis extension already installed"
    else
        echo "⚠️  Installing PHP Redis extension..."
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

# Step 3: Configure Redis
echo "3️⃣ CONFIGURING REDIS..."
echo "----------------------------------------"
REDIS_CONF="/etc/redis.conf"
if [ ! -f "$REDIS_CONF" ]; then
    REDIS_CONF="/etc/redis/redis.conf"
fi

if [ -f "$REDIS_CONF" ]; then
    # Backup config
    cp "$REDIS_CONF" "${REDIS_CONF}.backup.$(date +%Y%m%d_%H%M%S)"
    
    # Set max memory (1GB)
    if ! grep -q "^maxmemory" "$REDIS_CONF"; then
        echo "maxmemory 1gb" >> "$REDIS_CONF"
        echo "✅ Added maxmemory setting"
    fi
    
    # Set maxmemory policy
    if ! grep -q "^maxmemory-policy" "$REDIS_CONF"; then
        echo "maxmemory-policy allkeys-lru" >> "$REDIS_CONF"
        echo "✅ Added maxmemory-policy setting"
    fi
    
    # Ensure bind to localhost
    if grep -q "^bind" "$REDIS_CONF"; then
        sed -i 's/^bind .*/bind 127.0.0.1/' "$REDIS_CONF"
        echo "✅ Updated bind setting to 127.0.0.1"
    else
        echo "bind 127.0.0.1" >> "$REDIS_CONF"
        echo "✅ Added bind setting"
    fi
    
    # Restart Redis
    systemctl restart redis
    echo "✅ Redis configured"
else
    echo "⚠️  Redis config file not found at $REDIS_CONF"
fi
echo ""

# Step 4: Test Redis
echo "4️⃣ TESTING REDIS..."
echo "----------------------------------------"
if redis-cli ping | grep -q PONG; then
    echo "✅ Redis server is running"
    REDIS_VERSION=$(redis-cli info server | grep redis_version | cut -d: -f2 | tr -d '\r')
    REDIS_MEMORY=$(redis-cli info memory | grep used_memory_human | cut -d: -f2 | tr -d '\r')
    echo "Redis version: $REDIS_VERSION"
    echo "Memory used: $REDIS_MEMORY"
else
    echo "❌ Redis server is not responding"
    exit 1
fi
echo ""

# Step 5: Test PHP Redis
echo "5️⃣ TESTING PHP REDIS..."
echo "----------------------------------------"
if [ -f "$PHP_BIN" ]; then
    PHP_TEST=$($PHP_BIN -r "try { \$r = new Redis(); \$r->connect('127.0.0.1', 6379); echo 'Connected'; } catch (Exception \$e) { echo 'Failed: ' . \$e->getMessage(); }" 2>&1)
    if echo "$PHP_TEST" | grep -q "Connected"; then
        echo "✅ PHP Redis extension is working"
    else
        echo "⚠️  PHP Redis extension test: $PHP_TEST"
    fi
else
    PHP_TEST=$(php -r "try { \$r = new Redis(); \$r->connect('127.0.0.1', 6379); echo 'Connected'; } catch (Exception \$e) { echo 'Failed: ' . \$e->getMessage(); }" 2>&1)
    if echo "$PHP_TEST" | grep -q "Connected"; then
        echo "✅ PHP Redis extension is working"
    else
        echo "⚠️  PHP Redis extension test: $PHP_TEST"
    fi
fi
echo ""

# Summary
echo "=========================================="
echo "📋 SUMMARY"
echo "=========================================="
echo ""
echo "✅ Redis server: $(systemctl is-active redis)"
echo "✅ Redis port: $(ss -tlnp 2>/dev/null | grep 6379 | awk '{print $4}' || netstat -tlnp 2>/dev/null | grep 6379 | awk '{print $4}' || echo 'Not listening')"
echo ""

if [ -f "$PHP_BIN" ]; then
    if $PHP_BIN -m | grep -q redis; then
        echo "✅ PHP Redis extension: Installed"
    else
        echo "❌ PHP Redis extension: Not installed"
    fi
else
    if php -m | grep -q redis; then
        echo "✅ PHP Redis extension: Installed"
    else
        echo "❌ PHP Redis extension: Not installed"
    fi
fi
echo ""

echo "=========================================="
echo "📝 NEXT STEPS"
echo "=========================================="
echo ""
echo "1. Update .env file:"
echo "   CACHE_DRIVER=redis"
echo "   SESSION_DRIVER=redis"
echo "   QUEUE_CONNECTION=redis"
echo ""
echo "2. Clear Laravel config cache:"
echo "   php artisan config:clear"
echo "   php artisan cache:clear"
echo ""
echo "3. Test Redis via Laravel:"
echo "   php artisan tinker"
echo "   >>> Cache::put('test', 'Hello Redis', 60);"
echo "   >>> Cache::get('test');"
echo ""
echo "=========================================="
