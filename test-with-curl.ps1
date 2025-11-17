# ============================================
# Script Test dengan curl.exe
# ============================================

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  TEST AUTHENTICATION dengan curl.exe" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# ============================================
# STEP 1: LOGIN
# ============================================
Write-Host "=== STEP 1: LOGIN ===" -ForegroundColor Green

$email = "hendiroom@gmail.com"
$password = "Justus123!!"

$loginBody = @{
    email = $email
    password = $password
} | ConvertTo-Json

Write-Host "Email: $email" -ForegroundColor Yellow
Write-Host "Sending login request..." -ForegroundColor Yellow

try {
    $loginResponse = Invoke-RestMethod -Uri "https://ymsofterp.com/api/mobile/member/auth/login" `
        -Method POST `
        -ContentType "application/json" `
        -Body $loginBody
    
    Write-Host "Login SUCCESS!" -ForegroundColor Green
    $token = $loginResponse.data.token
    Write-Host "Token: $token" -ForegroundColor Cyan
    Write-Host ""
    
    # ============================================
    # STEP 2: TEST /auth/me dengan curl.exe
    # ============================================
    Write-Host "=== STEP 2: TEST /auth/me dengan curl.exe ===" -ForegroundColor Green
    
    $token = $token.Trim()
    
    Write-Host "Token: $token" -ForegroundColor Cyan
    Write-Host "Sending request dengan curl.exe..." -ForegroundColor Yellow
    
    # Gunakan curl.exe (bukan alias PowerShell)
    $curlCommand = "curl.exe -X GET `"https://ymsofterp.com/api/mobile/member/auth/me`" -H `"Authorization: Bearer $token`" -H `"Accept: application/json`""
    
    Write-Host "Command: $curlCommand" -ForegroundColor Gray
    Write-Host ""
    
    $response = Invoke-Expression $curlCommand
    
    Write-Host "Response:" -ForegroundColor Green
    Write-Host $response
    
    # Try to parse as JSON
    try {
        $jsonResponse = $response | ConvertFrom-Json
        if ($jsonResponse.success) {
            Write-Host "`n========================================" -ForegroundColor Cyan
            Write-Host "  AUTHENTICATION TEST: SUCCESS!" -ForegroundColor Green
            Write-Host "========================================" -ForegroundColor Cyan
            $jsonResponse | ConvertTo-Json -Depth 10
        } else {
            Write-Host "`n========================================" -ForegroundColor Cyan
            Write-Host "  AUTHENTICATION TEST: FAILED" -ForegroundColor Red
            Write-Host "========================================" -ForegroundColor Cyan
        }
    } catch {
        Write-Host "Response is not JSON or error occurred" -ForegroundColor Yellow
    }
    
} catch {
    Write-Host "Login FAILED!" -ForegroundColor Red
    Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""
Write-Host "Press any key to exit..."
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")

