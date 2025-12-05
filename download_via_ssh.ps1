# PowerShell script untuk download file dari server via SSH/SCP
# Edit credentials di bawah sebelum menjalankan

param(
    [string]$ServerHost = "your-server-ip-or-domain",
    [string]$ServerUser = "ymsuperadmin",
    [string]$RemotePath = "/home/ymsuperadmin/public_html",
    [string]$LocalPath = "D:\Gawean\YM\web\ymsofterp"
)

Write-Host "=== DOWNLOAD VIA SSH ===" -ForegroundColor Green
Write-Host "Server: $ServerUser@$ServerHost" -ForegroundColor Yellow
Write-Host "Remote: $RemotePath" -ForegroundColor Yellow
Write-Host "Local: $LocalPath" -ForegroundColor Yellow
Write-Host ""

# Folders to download
$folders = @("app", "routes", "bootstrap", "config", "database")

# Test SSH connection first
Write-Host "Testing SSH connection..." -ForegroundColor Cyan
$testConnection = ssh -o ConnectTimeout=5 -o BatchMode=yes "$ServerUser@$ServerHost" "echo 'connected'" 2>&1

if ($LASTEXITCODE -ne 0) {
    Write-Host "⚠ SSH connection test failed. You may need to enter password manually." -ForegroundColor Yellow
    Write-Host "Continue anyway? (Y/N): " -ForegroundColor Yellow -NoNewline
    $continue = Read-Host
    if ($continue -ne "Y" -and $continue -ne "y") {
        Write-Host "Aborted." -ForegroundColor Red
        exit
    }
} else {
    Write-Host "✓ SSH connection OK" -ForegroundColor Green
}

Write-Host ""

foreach ($folder in $folders) {
    Write-Host "Downloading $folder..." -ForegroundColor Cyan
    
    $remoteFolder = "$RemotePath/$folder"
    $localFolder = "$LocalPath\$folder"
    
    # Backup dulu jika folder sudah ada
    if (Test-Path $localFolder) {
        $backupFolder = "$localFolder.backup.$(Get-Date -Format 'yyyyMMdd_HHmmss')"
        Write-Host "  Creating backup: $backupFolder" -ForegroundColor Gray
        try {
            Copy-Item -Path $localFolder -Destination $backupFolder -Recurse -Force -ErrorAction Stop
            Write-Host "  ✓ Backup created" -ForegroundColor Gray
        } catch {
            Write-Host "  ⚠ Backup failed: $_" -ForegroundColor Yellow
        }
    }
    
    # Download via SCP
    Write-Host "  Downloading from server..." -ForegroundColor Gray
    scp -r "${ServerUser}@${ServerHost}:${remoteFolder}" $LocalPath
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host "  ✓ Downloaded: $folder" -ForegroundColor Green
    } else {
        Write-Host "  ✗ Failed: $folder" -ForegroundColor Red
        Write-Host "  You may need to enter password manually" -ForegroundColor Yellow
    }
    
    Write-Host ""
}

Write-Host "=== DOWNLOAD COMPLETE ===" -ForegroundColor Green
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Yellow
Write-Host "1. Run: composer install" -ForegroundColor White
Write-Host "2. Run: php artisan config:clear" -ForegroundColor White
Write-Host "3. Run: php artisan route:clear" -ForegroundColor White
Write-Host "4. Test: php artisan serve" -ForegroundColor White
Write-Host ""

