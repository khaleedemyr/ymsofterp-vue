# ============================================
# Script Test Token Debug
# ============================================

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  TEST TOKEN DEBUG" -ForegroundColor Cyan
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
    # STEP 2: TEST DEBUG ROUTE
    # ============================================
    Write-Host "=== STEP 2: TEST DEBUG ROUTE /auth/test-token ===" -ForegroundColor Green
    
    $token = $token.Trim()
    $authHeader = "Bearer $token"
    
    Write-Host "Token: $token" -ForegroundColor Cyan
    Write-Host "Auth Header: $authHeader" -ForegroundColor Cyan
    
    # Gunakan WebRequest untuk kontrol lebih baik
    $uri = "https://ymsofterp.com/api/mobile/member/auth/test-token"
    
    Write-Host "Sending debug request to: $uri" -ForegroundColor Yellow
    
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
        
        Write-Host "Debug Response (SUCCESS):" -ForegroundColor Green
        $debugResponse | ConvertTo-Json -Depth 10
        
        Write-Host "`n========================================" -ForegroundColor Cyan
        Write-Host "  TOKEN DEBUG: SUCCESS!" -ForegroundColor Green
        Write-Host "========================================" -ForegroundColor Cyan
    } catch {
        Write-Host "Debug FAILED!" -ForegroundColor Red
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
        Write-Host "  TOKEN DEBUG: FAILED" -ForegroundColor Red
        Write-Host "========================================" -ForegroundColor Cyan
    }
    
} catch {
    Write-Host "Login FAILED!" -ForegroundColor Red
    Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""
Write-Host "Press any key to exit..."
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")

