@echo off
REM Setup script for Extra Off Detection Cron Job (Windows)
REM This script sets up the scheduled task for automatic extra off detection

echo === Extra Off Detection Scheduled Task Setup ===
echo.

REM Get the current directory (project root)
set PROJECT_DIR=%CD%
echo Project directory: %PROJECT_DIR%

REM Check if Laravel project
if not exist "artisan" (
    echo Error: This doesn't appear to be a Laravel project (artisan file not found)
    pause
    exit /b 1
)

echo Creating scheduled tasks for extra off detection...
echo.

REM Create scheduled task for 7:00 AM detection
schtasks /create /tn "ExtraOffDetection_Morning" /tr "cd /d \"%PROJECT_DIR%\" && php artisan extra-off:detect" /sc daily /st 07:00 /f

REM Create scheduled task for 11:30 PM detection
schtasks /create /tn "ExtraOffDetection_Evening" /tr "cd /d \"%PROJECT_DIR%\" && php artisan extra-off:detect" /sc daily /st 23:30 /f

echo ✅ Scheduled tasks created successfully!
echo.
echo Scheduled tasks created:
echo 1. ExtraOffDetection_Morning - Daily at 7:00 AM
echo 2. ExtraOffDetection_Evening - Daily at 11:30 PM
echo.
echo Log file: storage/logs/extra-off-detection.log
echo.
echo To view scheduled tasks: schtasks /query /tn "ExtraOffDetection_*"
echo To delete scheduled tasks: schtasks /delete /tn "ExtraOffDetection_Morning" /f
echo To delete scheduled tasks: schtasks /delete /tn "ExtraOffDetection_Evening" /f
echo.
echo === Setup Complete ===
pause
