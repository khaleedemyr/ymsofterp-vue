#!/bin/bash
# =====================================================
# Queue Worker Script untuk Member Notification
# =====================================================
# Script ini untuk dijalankan via cron job di cPanel
# Set permission: chmod 755 queue-worker.sh

# Ganti path ini dengan path folder aplikasi Anda
# Contoh: /home/username/public_html/ymsofterp
APP_PATH="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Ganti path PHP jika berbeda
# Untuk cek path PHP: which php (via SSH)
# Atau cek di cPanel → Select PHP Version
PHP_PATH="/usr/bin/php"

# Masuk ke folder aplikasi
cd "$APP_PATH"

# Jalankan queue worker
# --stop-when-empty: Stop jika tidak ada job (untuk cron job)
# --max-time=3600: Auto-restart setelah 1 jam
# --max-jobs=1000: Auto-restart setelah 1000 jobs
$PHP_PATH artisan queue:work \
    --queue=notifications \
    --tries=3 \
    --timeout=300 \
    --sleep=3 \
    --max-jobs=1000 \
    --max-time=3600 \
    --stop-when-empty

# Log output (optional)
# Uncomment baris di bawah jika ingin log output
# >> storage/logs/queue-worker.log 2>&1

