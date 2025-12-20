#!/bin/bash
# =====================================================
# Queue Worker Script untuk Member Notification
# =====================================================
# Script ini untuk dijalankan via cron job di cPanel
# Set permission: chmod 755 queue-worker.sh

# Masuk ke folder aplikasi (sesuai dengan command yang sudah ada)
cd /home/ymsuperadmin/public_html

# Jalankan queue worker
# --stop-when-empty: Stop jika tidak ada job (untuk cron job)
# --max-time=3600: Auto-restart setelah 1 jam
# --max-jobs=1000: Auto-restart setelah 1000 jobs
php artisan queue:work \
    --queue=notifications \
    --tries=3 \
    --timeout=300 \
    --sleep=3 \
    --max-jobs=1000 \
    --max-time=3600 \
    --stop-when-empty

