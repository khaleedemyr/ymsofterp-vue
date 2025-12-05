@echo off
REM Script untuk setup cron job membersihkan debug logs di Windows
REM Jalankan script ini untuk mengatur task scheduler

echo === Setup Log Cleanup Cron Job for Windows ===
echo.

REM Get current directory
set CURRENT_DIR=%CD%
set SCRIPT_PATH=%CURRENT_DIR%\clear_debug_logs_cron.php

echo Current directory: %CURRENT_DIR%
echo Script path: %SCRIPT_PATH%
echo.

REM Check if script exists
if not exist "%SCRIPT_PATH%" (
    echo Error: Script %SCRIPT_PATH% not found!
    pause
    exit /b 1
)

echo Script found: %SCRIPT_PATH%
echo.

REM Create batch file for task scheduler
set BATCH_FILE=%CURRENT_DIR%\run_log_cleanup.bat
echo @echo off > "%BATCH_FILE%"
echo cd /d "%CURRENT_DIR%" >> "%BATCH_FILE%"
echo php "%SCRIPT_PATH%" ^>^> "%CURRENT_DIR%\storage\logs\cron_cleanup.log" 2^>^&1 >> "%BATCH_FILE%"

echo Created batch file: %BATCH_FILE%
echo.

REM Create task scheduler command
set TASK_NAME="LogCleanupTask"
set TASK_COMMAND=schtasks /create /tn %TASK_NAME% /tr "%BATCH_FILE%" /sc daily /st 02:00 /f

echo Task scheduler command:
echo %TASK_COMMAND%
echo.

REM Execute task scheduler command
echo Creating Windows Task Scheduler task...
%TASK_COMMAND%

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ✓ Task created successfully!
    echo.
    echo Task will run daily at 2:00 AM
    echo Logs will be saved to: %CURRENT_DIR%\storage\logs\cron_cleanup.log
    echo.
    echo To view tasks: schtasks /query /tn %TASK_NAME%
    echo To delete task: schtasks /delete /tn %TASK_NAME% /f
    echo.
    echo To test the script manually:
    echo php "%SCRIPT_PATH%"
) else (
    echo.
    echo ✗ Failed to create task
    echo Make sure you're running as Administrator
)

echo.
pause
