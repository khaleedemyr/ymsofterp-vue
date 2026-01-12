# =====================================================
# Script untuk Check Server Performance (PowerShell)
# =====================================================
# Script ini akan menampilkan:
# 1. CPU Usage
# 2. Memory Usage
# 3. Disk I/O
# 4. MySQL Status
# 5. PHP-FPM Status
# 6. Active Connections
# =====================================================

Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "SERVER PERFORMANCE CHECK" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""

# 1. CPU Usage
Write-Host "1. CPU USAGE:" -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray
$cpu = Get-Counter '\Processor(_Total)\% Processor Time'
Write-Host "CPU Usage: $($cpu.CounterSamples[0].CookedValue)%"
Write-Host ""

# 2. Memory Usage
Write-Host "2. MEMORY USAGE:" -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray
$mem = Get-CimInstance Win32_OperatingSystem
$totalMem = [math]::Round($mem.TotalVisibleMemorySize / 1MB, 2)
$freeMem = [math]::Round($mem.FreePhysicalMemory / 1MB, 2)
$usedMem = [math]::Round($totalMem - $freeMem, 2)
$memPercent = [math]::Round(($usedMem / $totalMem) * 100, 2)
Write-Host "Total Memory: $totalMem GB"
Write-Host "Used Memory: $usedMem GB ($memPercent%)"
Write-Host "Free Memory: $freeMem GB"
Write-Host ""

# 3. Disk Usage
Write-Host "3. DISK USAGE:" -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray
Get-PSDrive -PSProvider FileSystem | Where-Object { $_.Used -gt 0 } | Format-Table Name, @{Label="Used(GB)";Expression={[math]::Round($_.Used/1GB,2)}}, @{Label="Free(GB)";Expression={[math]::Round($_.Free/1GB,2)}}, @{Label="Total(GB)";Expression={[math]::Round(($_.Used+$_.Free)/1GB,2)}}
Write-Host ""

# 4. PHP-FPM / PHP Processes
Write-Host "4. PHP PROCESSES:" -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray
$phpProcesses = Get-Process | Where-Object { $_.ProcessName -like "*php*" } | Measure-Object
Write-Host "PHP Processes: $($phpProcesses.Count)"
Get-Process | Where-Object { $_.ProcessName -like "*php*" } | Select-Object ProcessName, Id, CPU, WorkingSet | Format-Table -AutoSize
Write-Host ""

# 5. MySQL Processes
Write-Host "5. MYSQL PROCESSES:" -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray
$mysqlProcesses = Get-Process | Where-Object { $_.ProcessName -like "*mysql*" } | Measure-Object
Write-Host "MySQL Processes: $($mysqlProcesses.Count)"
Get-Process | Where-Object { $_.ProcessName -like "*mysql*" } | Select-Object ProcessName, Id, CPU, WorkingSet | Format-Table -AutoSize
Write-Host ""

# 6. Top 10 Processes by CPU
Write-Host "6. TOP 10 PROCESSES BY CPU:" -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray
Get-Process | Sort-Object CPU -Descending | Select-Object -First 10 ProcessName, Id, @{Name="CPU(s)";Expression={$_.CPU}}, @{Name="Memory(MB)";Expression={[math]::Round($_.WorkingSet/1MB,2)}} | Format-Table -AutoSize
Write-Host ""

# 7. Top 10 Processes by Memory
Write-Host "7. TOP 10 PROCESSES BY MEMORY:" -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray
Get-Process | Sort-Object WorkingSet -Descending | Select-Object -First 10 ProcessName, Id, @{Name="CPU(s)";Expression={$_.CPU}}, @{Name="Memory(MB)";Expression={[math]::Round($_.WorkingSet/1MB,2)}} | Format-Table -AutoSize
Write-Host ""

# 8. Network Connections
Write-Host "8. NETWORK CONNECTIONS:" -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray
$connections = Get-NetTCPConnection | Where-Object { $_.State -eq "Established" } | Measure-Object
Write-Host "Established Connections: $($connections.Count)"
Write-Host ""

# 9. Laravel Queue Workers
Write-Host "9. LARAVEL QUEUE WORKERS:" -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray
$queueWorkers = Get-Process | Where-Object { $_.CommandLine -like "*queue:work*" } | Measure-Object
Write-Host "Queue Workers: $($queueWorkers.Count)"
Write-Host ""

# 10. Scheduled Tasks
Write-Host "10. SCHEDULED TASKS:" -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray
$scheduledTasks = Get-ScheduledTask | Where-Object { $_.State -eq "Running" } | Measure-Object
Write-Host "Running Scheduled Tasks: $($scheduledTasks.Count)"
Write-Host ""

Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "CHECK COMPLETED" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
