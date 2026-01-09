#!/bin/bash

echo "=========================================="
echo "🔧 FIX PHP-FPM CONFIG ERROR"
echo "=========================================="
echo ""

# 1. Check PHP-FPM status
echo "=== 1. PHP-FPM STATUS ==="
systemctl status ea-php82-php-fpm --no-pager -l | head -20
echo ""

# 2. Test config syntax
echo "=== 2. TEST CONFIG SYNTAX ==="
/opt/cpanel/ea-php82/root/usr/sbin/php-fpm -t 2>&1
echo ""

# 3. List all config files
echo "=== 3. LIST ALL CONFIG FILES ==="
echo "Config files in /opt/cpanel/ea-php82/root/etc/php-fpm.d/:"
ls -lah /opt/cpanel/ea-php82/root/etc/php-fpm.d/*.conf 2>/dev/null | while read line; do
    echo "$line"
done
echo ""

# 4. Find recently modified files (within last 10 minutes)
echo "=== 4. RECENTLY MODIFIED FILES (Last 10 minutes) ==="
find /opt/cpanel/ea-php82/root/etc/php-fpm.d -name "*.conf" -mmin -10 2>/dev/null | while read file; do
    echo "⚠️  Recently modified: $file"
    echo "   First 5 lines:"
    head -5 "$file" | sed 's/^/   /'
    echo ""
done

# 5. Check for files with syntax errors
echo "=== 5. CHECK FOR FILES WITH 'pm' AT LINE 1 (POSSIBLE ERROR) ==="
for file in /opt/cpanel/ea-php82/root/etc/php-fpm.d/*.conf; do
    if [ -f "$file" ]; then
        first_line=$(head -1 "$file" | tr -d '[:space:]')
        if [[ "$first_line" == "pm"* ]] && [[ "$first_line" != "[*" ]]; then
            echo "⚠️  Possible error in: $file"
            echo "   First line: $(head -1 "$file")"
            echo "   This file might need fixing!"
            echo ""
        fi
    fi
done

# 6. Show main config file
echo "=== 6. MAIN CONFIG FILE ==="
if [ -f "/opt/cpanel/ea-php82/root/etc/php-fpm.conf" ]; then
    echo "Main config includes:"
    grep -E "include|include_path" /opt/cpanel/ea-php82/root/etc/php-fpm.conf | head -5
else
    echo "Main config file not found"
fi
echo ""

# 7. Recommendations
echo "=========================================="
echo "📋 RECOMMENDATIONS"
echo "=========================================="
echo ""

echo "1. Find the error file:"
echo "   Check files listed in 'Recently Modified Files' above"
echo ""

echo "2. Fix options:"
echo "   A. Delete the error file (if it's a new file you created):"
echo "      rm /opt/cpanel/ea-php82/root/etc/php-fpm.d/[nama-file-error].conf"
echo ""
echo "   B. Or edit via cPanel (safer):"
echo "      - Login cPanel → MultiPHP Manager → PHP-FPM Settings"
echo "      - Change Max Children: 80 → 24"
echo "      - Click Update"
echo ""

echo "3. After fix, restart PHP-FPM:"
echo "   systemctl restart ea-php82-php-fpm"
echo "   systemctl status ea-php82-php-fpm"
echo ""

echo "=========================================="
echo "✅ Check complete!"
echo "=========================================="
