# ============================================
# Script Test Authentication
# ============================================

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  TEST AUTHENTICATION API" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# ============================================
# STEP 1: LOGIN
# ============================================
Write-Host "=== STEP 1: LOGIN ===" -ForegroundColor Green

# EMAIL DAN PASSWORD SUDAH DIISI
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
    Write-Host "Response:" -ForegroundColor Yellow
    $loginResponse | ConvertTo-Json -Depth 5
    
    if ($loginResponse.success -and $loginResponse.data.token) {
        $token = $loginResponse.data.token
        Write-Host "`nToken: $token" -ForegroundColor Cyan
        Write-Host ""
        
        # ============================================
        # STEP 2: TEST /auth/me
        # ============================================
        Write-Host "=== STEP 2: TEST /auth/me ===" -ForegroundColor Green
        
        # Pastikan token tidak ada space
        $token = $token.Trim()
        $authHeader = "Bearer $token"
        
        Write-Host "Token (first 30 chars): $($token.Substring(0, [Math]::Min(30, $token.Length)))..." -ForegroundColor Cyan
        Write-Host "Authorization Header: $($authHeader.Substring(0, [Math]::Min(50, $authHeader.Length)))..." -ForegroundColor Cyan
        
        $headers = @{
            "Authorization" = $authHeader
            "Accept" = "application/json"
            "Content-Type" = "application/json"
        }
        
        Write-Host "Sending profile request..." -ForegroundColor Yellow
        
        try {
            $profile = Invoke-RestMethod -Uri "https://ymsofterp.com/api/mobile/member/auth/me" `
                -Method GET `
                -Headers $headers `
                -ErrorAction Stop
            
            Write-Host "Profile SUCCESS!" -ForegroundColor Green
            Write-Host "Profile Data:" -ForegroundColor Yellow
            $profile | ConvertTo-Json -Depth 10
            
            Write-Host "`n========================================" -ForegroundColor Cyan
            Write-Host "  AUTHENTICATION TEST: SUCCESS!" -ForegroundColor Green
            Write-Host "========================================" -ForegroundColor Cyan
        } catch {
            Write-Host "Profile FAILED!" -ForegroundColor Red
            Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
            
            if ($_.Exception.Response) {
                $statusCode = $_.Exception.Response.StatusCode.value__
                Write-Host "Status Code: $statusCode" -ForegroundColor Red
                
                # Try to read response body
                try {
                    $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
                    $responseBody = $reader.ReadToEnd()
                    Write-Host "Response Body: $responseBody" -ForegroundColor Yellow
                } catch {
                    Write-Host "Could not read response body" -ForegroundColor Yellow
                }
                
                if ($statusCode -eq 401) {
                    Write-Host "`nToken tidak valid atau expired!" -ForegroundColor Yellow
                    Write-Host "Mungkin ada masalah dengan Sanctum configuration di server." -ForegroundColor Yellow
                    Write-Host "Silakan cek:" -ForegroundColor Yellow
                    Write-Host "  1. Token sudah tersimpan di database (table personal_access_tokens)" -ForegroundColor Yellow
                    Write-Host "  2. Sanctum middleware sudah terkonfigurasi dengan benar" -ForegroundColor Yellow
                    Write-Host "  3. Model MemberAppsMember menggunakan HasApiTokens trait" -ForegroundColor Yellow
                } elseif ($statusCode -eq 405) {
                    Write-Host "`nMethod tidak diizinkan!" -ForegroundColor Yellow
                    Write-Host "Pastikan menggunakan GET method." -ForegroundColor Yellow
                }
            }
            
            Write-Host "`n========================================" -ForegroundColor Cyan
            Write-Host "  AUTHENTICATION TEST: FAILED" -ForegroundColor Red
            Write-Host "========================================" -ForegroundColor Cyan
        }
    } else {
        Write-Host "Login response tidak berisi token!" -ForegroundColor Red
        Write-Host "Response: $($loginResponse | ConvertTo-Json)" -ForegroundColor Red
    }
} catch {
    Write-Host "Login FAILED!" -ForegroundColor Red
    Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
    
    if ($_.Exception.Response) {
        $statusCode = $_.Exception.Response.StatusCode.value__
        Write-Host "Status Code: $statusCode" -ForegroundColor Red
        
        $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
        $responseBody = $reader.ReadToEnd()
        Write-Host "Response Body: $responseBody" -ForegroundColor Red
    }
    
    Write-Host "`n========================================" -ForegroundColor Cyan
    Write-Host "  LOGIN TEST: FAILED" -ForegroundColor Red
    Write-Host "========================================" -ForegroundColor Cyan
}

Write-Host ""
Write-Host "Press any key to exit..."
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")

