# =====================================================
# Real-Time Server Performance Monitor (PowerShell)
# =====================================================
# Script ini akan monitor server performance secara real-time
# =====================================================

param(
    [int]$Interval = 5,  # Default 5 detik
    [int]$Duration = 60  # Default 60 detik
)

Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "REAL-TIME SERVER PERFORMANCE MONITOR" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "Interval: $Interval seconds" -ForegroundColor Yellow
Write-Host "Duration: $Duration seconds" -ForegroundColor Yellow
Write-Host "Press Ctrl+C to stop" -ForegroundColor Yellow
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""

$EndTime = (Get-Date).AddSeconds($Duration)
$Iteration = 0

while ((Get-Date) -lt $EndTime) {
    $Iteration++
    Write-Host "=== Iteration #$Iteration - $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') ===" -ForegroundColor Green
    Write-Host ""

    # 1. CPU Usage
    Write-Host "1. CPU USAGE:" -ForegroundColor Yellow
    Write-Host "----------------------------------------" -ForegroundColor Gray
    $cpu = Get-Counter '\Processor(_Total)\% Processor Time'
    Write-Host "CPU: $([math]::Round($cpu.CounterSamples[0].CookedValue, 2))%"
    Write-Host ""

    # 2. Memory Usage
    Write-Host "2. MEMORY USAGE:" -ForegroundColor Yellow
    Write-Host "----------------------------------------" -ForegroundColor Gray
    $mem = Get-CimInstance Win32_OperatingSystem
    $totalMem = [math]::Round($mem.TotalVisibleMemorySize / 1MB, 2)
    $freeMem = [math]::Round($mem.FreePhysicalMemory / 1MB, 2)
    $usedMem = [math]::Round($totalMem - $freeMem, 2)
    $memPercent = [math]::Round(($usedMem / $totalMem) * 100, 2)
    Write-Host "Total: $totalMem GB | Used: $usedMem GB ($memPercent%) | Free: $freeMem GB"
    Write-Host ""

    # 3. Top 5 Processes by CPU
    Write-Host "3. TOP 5 PROCESSES BY CPU:" -ForegroundColor Yellow
    Write-Host "----------------------------------------" -ForegroundColor Gray
    Get-Process | Sort-Object CPU -Descending | Select-Object -First 5 ProcessName, Id, @{Name="CPU(s)";Expression={$_.CPU}}, @{Name="Memory(MB)";Expression={[math]::Round($_.WorkingSet/1MB,2)}} | Format-Table -AutoSize
    Write-Host ""

    # 4. PHP Processes
    Write-Host "4. PHP PROCESSES:" -ForegroundColor Yellow
    Write-Host "----------------------------------------" -ForegroundColor Gray
    $phpProcesses = Get-Process | Where-Object { $_.ProcessName -like "*php*" }
    Write-Host "PHP Processes: $($phpProcesses.Count)"
    $phpProcesses | Select-Object -First 3 ProcessName, Id, CPU, @{Name="Memory(MB)";Expression={[math]::Round($_.WorkingSet/1MB,2)}} | Format-Table -AutoSize
    Write-Host ""

    # 5. MySQL Processes
    Write-Host "5. MYSQL PROCESSES:" -ForegroundColor Yellow
    Write-Host "----------------------------------------" -ForegroundColor Gray
    $mysqlProcesses = Get-Process | Where-Object { $_.ProcessName -like "*mysql*" }
    Write-Host "MySQL Processes: $($mysqlProcesses.Count)"
    $mysqlProcesses | Select-Object -First 3 ProcessName, Id, CPU, @{Name="Memory(MB)";Expression={[math]::Round($_.WorkingSet/1MB,2)}} | Format-Table -AutoSize
    Write-Host ""

    # 6. Network Connections
    Write-Host "6. NETWORK CONNECTIONS:" -ForegroundColor Yellow
    Write-Host "----------------------------------------" -ForegroundColor Gray
    $connections = Get-NetTCPConnection | Where-Object { $_.State -eq "Established" }
    Write-Host "Established Connections: $($connections.Count)"
    Write-Host ""

    Write-Host "==========================================" -ForegroundColor Cyan
    Write-Host ""

    Start-Sleep -Seconds $Interval
}

Write-Host "Monitoring completed!" -ForegroundColor Green
