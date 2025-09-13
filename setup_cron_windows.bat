@echo off
REM Employee Movement Execution - Cron Job Setup Script for Windows
REM Run this script to setup automatic execution of employee movements

echo === Employee Movement Execution - Cron Job Setup ===
echo.

REM Get current directory
set PROJECT_PATH=%CD%
echo Project Path: %PROJECT_PATH%
echo.

REM Check if Laravel project
if not exist "artisan" (
    echo ❌ Error: This doesn't appear to be a Laravel project (artisan file not found)
    pause
    exit /b 1
)

echo ✅ Laravel project detected
echo.

REM Check if command exists
echo Testing employee-movements:execute command...
php artisan employee-movements:execute --help >nul 2>&1
if %errorlevel% equ 0 (
    echo ✅ Command 'employee-movements:execute' is available
) else (
    echo ❌ Error: Command 'employee-movements:execute' not found
    echo Make sure the command is properly registered
    pause
    exit /b 1
)

echo.

REM Create log directory if not exists
if not exist "storage\logs" mkdir storage\logs
echo ✅ Log directory ready: storage\logs\

REM Test Laravel scheduler
echo.
echo Testing Laravel scheduler...
php artisan schedule:list | findstr "employee-movements:execute" >nul 2>&1
if %errorlevel% equ 0 (
    echo ✅ Employee movement execution is scheduled in Laravel
    echo    Schedule: Daily at 08:00
    echo    Log: storage\logs\employee-movements-execution.log
) else (
    echo ❌ Warning: Employee movement execution not found in Laravel schedule
    echo    Please check app\Console\Kernel.php
)

echo.

REM Test manual execution
echo === Testing Manual Execution ===
echo Running employee-movements:execute command...
php artisan employee-movements:execute

echo.
echo === Windows Task Scheduler Setup ===
echo.
echo To setup automatic execution on Windows:
echo.
echo 1. Open Task Scheduler (taskschd.msc)
echo 2. Click "Create Basic Task"
echo 3. Name: Laravel Employee Movement Execution
echo 4. Trigger: Daily
echo 5. Start time: 08:00:00
echo 6. Action: Start a program
echo 7. Program: php
echo 8. Arguments: artisan employee-movements:execute
echo 9. Start in: %PROJECT_PATH%
echo.

echo === Setup Complete ===
echo.
echo Next steps:
echo 1. Setup Windows Task Scheduler as described above
echo 2. Monitor logs at: storage\logs\employee-movements-execution.log
echo 3. Test the scheduler: php artisan schedule:run
echo.
echo For more details, see: CRON_JOB_SETUP.md
echo.
pause
