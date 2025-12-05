@echo off
echo Setting up Holiday Attendance Automatic Processing for Windows...

echo.
echo ========================================
echo  HOLIDAY ATTENDANCE AUTOMATIC SETUP
echo ========================================
echo.

echo [1/4] Creating scheduled task for automatic processing...
echo.

REM Create a PowerShell script for the scheduled task
echo $Action = New-ScheduledTaskAction -Execute "php" -Argument "artisan attendance:process-holiday" -WorkingDirectory "%CD%" > setup_task.ps1
echo $Trigger = New-ScheduledTaskTrigger -Daily -At 6:00AM >> setup_task.ps1
echo $Trigger2 = New-ScheduledTaskTrigger -Daily -At 11:00PM >> setup_task.ps1
echo $Settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries >> setup_task.ps1
echo Register-ScheduledTask -Action $Action -Trigger $Trigger,$Trigger2 -Settings $Settings -TaskName "HolidayAttendanceProcessor" -Description "Automatic Holiday Attendance Processing" >> setup_task.ps1

echo [2/4] Running PowerShell script to create scheduled task...
powershell -ExecutionPolicy Bypass -File setup_task.ps1

echo.
echo [3/4] Testing manual command...
php artisan attendance:process-holiday --help

echo.
echo [4/4] Setup completed!
echo.
echo ========================================
echo  SETUP SUMMARY
echo ========================================
echo.
echo ✅ Scheduled task created: HolidayAttendanceProcessor
echo 📅 Runs daily at: 6:00 AM and 11:00 PM
echo 🎯 Command: php artisan attendance:process-holiday
echo 📝 Logs: storage/logs/holiday-attendance.log
echo.
echo ========================================
echo  MANUAL COMMANDS
echo ========================================
echo.
echo Test manual processing:
echo   php artisan attendance:process-holiday 2024-01-15
echo.
echo Check scheduled tasks:
echo   schtasks /query /tn "HolidayAttendanceProcessor"
echo.
echo View logs:
echo   type storage\logs\holiday-attendance.log
echo.
echo ========================================
echo  NEXT STEPS
echo ========================================
echo.
echo 1. ✅ Create database table: create_holiday_attendance_compensations_table.sql
echo 2. ✅ Run migration: php artisan migrate
echo 3. ✅ Insert menu permissions: insert_holiday_attendance_menu_permissions.sql
echo 4. ✅ Test with real data
echo.
echo 🎉 The system will now automatically process holiday attendance!
echo.

REM Cleanup
del setup_task.ps1

pause
