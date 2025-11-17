# ============================================
# Script Test Authentication dengan Debug Route Auth
# ============================================

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  TEST AUTHENTICATION + DEBUG AUTH" -ForegroundColor Cyan
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
    
    $token = $token.Trim()
    $authHeader = "Bearer $token"
    
    # ============================================
    # STEP 2: TEST DEBUG ROUTE (NO AUTH)
    # ============================================
    Write-Host "=== STEP 2: TEST DEBUG ROUTE /auth/test-token (NO AUTH) ===" -ForegroundColor Green
    
    $uri = "https://ymsofterp.com/api/mobile/member/auth/test-token"
    
    try {
        $request = [System.Net.WebRequest]::Create($uri)
        $request.Method = "GET"
        $request.Headers.Add("Authorization", $authHeader)
        $request.Accept = "application/json"
        $request.ContentType = "application/json"
        
        $response = $request.GetResponse()
        $reader = New-Object System.IO.StreamReader($response.GetResponseStream())
        $responseBody = $reader.ReadToEnd()
        $reader.Close()
        $response.Close()
        
        $debugResponse = $responseBody | ConvertFrom-Json
        
        Write-Host "Debug Response (NO AUTH):" -ForegroundColor Yellow
        Write-Host "  has_token: $($debugResponse.has_token)" -ForegroundColor Cyan
        Write-Host "  db_token_found: $($debugResponse.db_token_found)" -ForegroundColor Cyan
        Write-Host "  member_found: $($debugResponse.member_found)" -ForegroundColor Cyan
        Write-Host ""
    } catch {
        Write-Host "Debug route failed: $($_.Exception.Message)" -ForegroundColor Red
    }
    
    # ============================================
    # STEP 3: TEST DEBUG ROUTE AUTH (WITH AUTH MIDDLEWARE)
    # ============================================
    Write-Host "=== STEP 3: TEST DEBUG ROUTE /auth/test-token-auth (WITH AUTH) ===" -ForegroundColor Green
    
    $uri = "https://ymsofterp.com/api/mobile/member/auth/test-token-auth"
    
    try {
        $request = [System.Net.WebRequest]::Create($uri)
        $request.Method = "GET"
        $request.Headers.Add("Authorization", $authHeader)
        $request.Accept = "application/json"
        $request.ContentType = "application/json"
        
        $response = $request.GetResponse()
        $reader = New-Object System.IO.StreamReader($response.GetResponseStream())
        $responseBody = $reader.ReadToEnd()
        $reader.Close()
        $response.Close()
        
        $debugAuthResponse = $responseBody | ConvertFrom-Json
        
        Write-Host "Debug Auth Response (WITH AUTH):" -ForegroundColor Yellow
        Write-Host "  has_token: $($debugAuthResponse.has_token)" -ForegroundColor Cyan
        Write-Host "  user_authenticated: $($debugAuthResponse.user_authenticated)" -ForegroundColor Cyan
        Write-Host "  user_id: $($debugAuthResponse.user_id)" -ForegroundColor Cyan
        Write-Host "  user_email: $($debugAuthResponse.user_email)" -ForegroundColor Cyan
        Write-Host ""
        
        if ($debugAuthResponse.user_authenticated) {
            Write-Host "✅ Sanctum middleware BERHASIL authenticate!" -ForegroundColor Green
        } else {
            Write-Host "❌ Sanctum middleware GAGAL authenticate!" -ForegroundColor Red
        }
    } catch {
        Write-Host "Debug auth route failed: $($_.Exception.Message)" -ForegroundColor Red
        if ($_.Exception.Response) {
            $statusCode = $_.Exception.Response.StatusCode.value__
            Write-Host "Status Code: $statusCode" -ForegroundColor Red
        }
    }
    
    # ============================================
    # STEP 4: TEST /auth/me
    # ============================================
    Write-Host "=== STEP 4: TEST /auth/me ===" -ForegroundColor Green
    
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
            
            try {
                $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
                $responseBody = $reader.ReadToEnd()
                Write-Host "Response Body: $responseBody" -ForegroundColor Yellow
            } catch {
                Write-Host "Could not read response body" -ForegroundColor Yellow
            }
        }
        
        Write-Host "`n========================================" -ForegroundColor Cyan
        Write-Host "  AUTHENTICATION TEST: FAILED" -ForegroundColor Red
        Write-Host "========================================" -ForegroundColor Cyan
    }
    
} catch {
    Write-Host "Login FAILED!" -ForegroundColor Red
    Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""
Write-Host "Press any key to exit..."
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")

