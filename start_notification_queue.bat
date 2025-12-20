@echo off
REM =====================================================
REM Start Queue Worker untuk Member Notification
REM =====================================================
REM Script ini untuk menjalankan queue worker di Windows
REM Queue worker akan memproses notifikasi member secara background

echo Starting Laravel Queue Worker for Notifications...
echo.
echo Queue: notifications
echo Tries: 3
echo Timeout: 300 seconds (5 minutes)
echo.
echo Press Ctrl+C to stop the queue worker
echo.

cd /d "%~dp0"

REM Jalankan queue worker
php artisan queue:work --queue=notifications --tries=3 --timeout=300 --sleep=3 --max-jobs=1000 --max-time=3600

pause

