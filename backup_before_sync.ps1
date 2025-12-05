# PowerShell script untuk backup sebelum sync dari server
# Jalankan: .\backup_before_sync.ps1

$timestamp = Get-Date -Format 'yyyyMMdd_HHmmss'
$backupDir = "backup_before_sync_$timestamp"

Write-Host "=== BACKUP BEFORE SYNC ===" -ForegroundColor Green
Write-Host "Backup directory: $backupDir" -ForegroundColor Yellow
Write-Host ""

# Create backup directory
New-Item -ItemType Directory -Path $backupDir -Force | Out-Null

# Folders to backup
$foldersToBackup = @(
    "app",
    "routes",
    "bootstrap",
    "config",
    "database"
)

foreach ($folder in $foldersToBackup) {
    if (Test-Path $folder) {
        Write-Host "Backing up $folder..." -ForegroundColor Cyan
        $destPath = Join-Path $backupDir $folder
        Copy-Item -Path $folder -Destination $destPath -Recurse -Force
        Write-Host "  ✓ Backed up: $folder" -ForegroundColor Green
    } else {
        Write-Host "  ⚠ Not found: $folder" -ForegroundColor Yellow
    }
}

# Backup important files
$filesToBackup = @(
    "composer.json",
    "composer.lock",
    ".env"
)

foreach ($file in $filesToBackup) {
    if (Test-Path $file) {
        Write-Host "Backing up $file..." -ForegroundColor Cyan
        Copy-Item -Path $file -Destination (Join-Path $backupDir $file) -Force
        Write-Host "  ✓ Backed up: $file" -ForegroundColor Green
    }
}

Write-Host ""
Write-Host "=== BACKUP COMPLETE ===" -ForegroundColor Green
Write-Host "Backup location: $backupDir" -ForegroundColor Yellow
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Yellow
Write-Host "1. Download files from server (see SYNC_FROM_SERVER.md)" -ForegroundColor White
Write-Host "2. Replace folders: app, routes, bootstrap, config, database" -ForegroundColor White
Write-Host "3. Run: composer install" -ForegroundColor White
Write-Host "4. Run: php artisan config:clear" -ForegroundColor White
Write-Host ""

