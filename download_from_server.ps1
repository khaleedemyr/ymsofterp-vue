# PowerShell script untuk download file dari server via FTP
# Edit credentials di bawah sebelum menjalankan

param(
    [string]$ServerHost = "your-server-ip-or-domain",
    [string]$Username = "ymsuperadmin",
    [string]$Password = "your-password",
    [string]$RemotePath = "/public_html",
    [string]$LocalPath = "D:\Gawean\YM\web\ymsofterp"
)

Write-Host "=== DOWNLOAD FILES FROM SERVER ===" -ForegroundColor Green
Write-Host ""

# Install WinSCP .NET assembly jika belum
$WinSCPPath = "C:\Program Files (x86)\WinSCP\WinSCPnet.dll"
if (-not (Test-Path $WinSCPPath)) {
    Write-Host "WinSCP not found. Please install WinSCP or use manual download." -ForegroundColor Yellow
    Write-Host "Alternative: Use FileZilla or cPanel File Manager" -ForegroundColor Yellow
    exit
}

Add-Type -Path $WinSCPPath

try {
    # Setup session options
    $sessionOptions = New-Object WinSCP.SessionOptions -Property @{
        Protocol = [WinSCP.Protocol]::Ftp
        HostName = $ServerHost
        UserName = $Username
        Password = $Password
    }

    $session = New-Object WinSCP.Session

    try {
        # Connect
        Write-Host "Connecting to server..." -ForegroundColor Yellow
        $session.Open($sessionOptions)

        # Folders to download
        $foldersToDownload = @(
            "app",
            "routes",
            "bootstrap",
            "config",
            "database"
        )

        foreach ($folder in $foldersToDownload) {
            $remoteFolder = "$RemotePath/$folder"
            $localFolder = "$LocalPath\$folder"

            Write-Host "Downloading $folder..." -ForegroundColor Cyan
            
            # Create backup first
            if (Test-Path $localFolder) {
                $backupFolder = "$localFolder.backup.$(Get-Date -Format 'yyyyMMdd_HHmmss')"
                Write-Host "  Creating backup: $backupFolder" -ForegroundColor Gray
                Copy-Item -Path $localFolder -Destination $backupFolder -Recurse -Force
            }

            # Download
            $transferOptions = New-Object WinSCP.TransferOptions
            $transferOptions.TransferMode = [WinSCP.TransferMode]::Binary
            
            $session.GetFiles($remoteFolder, $localFolder, $False, $transferOptions).Check()
            
            Write-Host "  ✓ Downloaded: $folder" -ForegroundColor Green
        }

        Write-Host ""
        Write-Host "=== DOWNLOAD COMPLETE ===" -ForegroundColor Green
        Write-Host ""
        Write-Host "Next steps:" -ForegroundColor Yellow
        Write-Host "1. Run: composer install" -ForegroundColor White
        Write-Host "2. Run: php artisan config:clear" -ForegroundColor White
        Write-Host "3. Run: php artisan route:clear" -ForegroundColor White
        Write-Host "4. Test your application" -ForegroundColor White

    }
    finally {
        $session.Dispose()
    }
}
catch {
    Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host ""
    Write-Host "Alternative methods:" -ForegroundColor Yellow
    Write-Host "1. Use FileZilla (GUI FTP client)" -ForegroundColor White
    Write-Host "2. Use cPanel File Manager -> Download as ZIP" -ForegroundColor White
    Write-Host "3. Use SCP if SSH is available" -ForegroundColor White
}

