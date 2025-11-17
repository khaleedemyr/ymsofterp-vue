# Test Auth dengan PowerShell

## ⚠️ Masalah:
Di PowerShell, `curl` adalah alias untuk `Invoke-WebRequest` yang punya syntax berbeda.

## ✅ Solusi:

### Opsi 1: Gunakan `curl.exe` (Paling Mudah)

PowerShell punya `curl.exe` yang asli. Gunakan dengan `.exe`:

```powershell
# Login
curl.exe -X POST "https://ymsofterp.com/api/mobile/member/auth/login" `
  -H "Content-Type: application/json" `
  -H "Accept: application/json" `
  -d '{\"email\": \"email_member@example.com\", \"password\": \"password_member\"}'
```

**PENTING:** 
- Gunakan `curl.exe` (bukan `curl`)
- Gunakan backtick `` ` `` untuk line continuation (bukan `\`)

### Opsi 2: Gunakan `Invoke-WebRequest` (PowerShell Native)

```powershell
# Login
$body = @{
    email = "email_member@example.com"
    password = "password_member"
} | ConvertTo-Json

$response = Invoke-WebRequest -Uri "https://ymsofterp.com/api/mobile/member/auth/login" `
    -Method POST `
    -ContentType "application/json" `
    -Body $body

$response.Content
```

### Opsi 3: Gunakan `Invoke-RestMethod` (Lebih Mudah untuk JSON)

```powershell
# Login
$body = @{
    email = "email_member@example.com"
    password = "password_member"
} | ConvertTo-Json

$response = Invoke-RestMethod -Uri "https://ymsofterp.com/api/mobile/member/auth/login" `
    -Method POST `
    -ContentType "application/json" `
    -Body $body

# Token akan otomatis di-parse sebagai object
$token = $response.data.token
Write-Host "Token: $token"

# Test /auth/me
$headers = @{
    Authorization = "Bearer $token"
    Accept = "application/json"
}

$profile = Invoke-RestMethod -Uri "https://ymsofterp.com/api/mobile/member/auth/me" `
    -Method GET `
    -Headers $headers

$profile | ConvertTo-Json -Depth 10
```

---

## 🚀 Script Lengkap (Copy-Paste):

```powershell
# ============================================
# STEP 1: LOGIN
# ============================================
Write-Host "=== LOGIN ===" -ForegroundColor Green

$loginBody = @{
    email = "email_member@example.com"  # GANTI DENGAN EMAIL MEMBER
    password = "password_member"          # GANTI DENGAN PASSWORD MEMBER
} | ConvertTo-Json

$loginResponse = Invoke-RestMethod -Uri "https://ymsofterp.com/api/mobile/member/auth/login" `
    -Method POST `
    -ContentType "application/json" `
    -Body $loginBody

Write-Host "Login Response:" -ForegroundColor Yellow
$loginResponse | ConvertTo-Json -Depth 5

$token = $loginResponse.data.token
Write-Host "`nToken: $token" -ForegroundColor Cyan

# ============================================
# STEP 2: TEST /auth/me
# ============================================
Write-Host "`n=== TEST /auth/me ===" -ForegroundColor Green

$headers = @{
    Authorization = "Bearer $token"
    Accept = "application/json"
}

try {
    $profile = Invoke-RestMethod -Uri "https://ymsofterp.com/api/mobile/member/auth/me" `
        -Method GET `
        -Headers $headers
    
    Write-Host "Profile Response (SUCCESS):" -ForegroundColor Green
    $profile | ConvertTo-Json -Depth 10
} catch {
    Write-Host "Error: $_" -ForegroundColor Red
    Write-Host "Status Code: $($_.Exception.Response.StatusCode.value__)" -ForegroundColor Red
    Write-Host "Response: $($_.Exception.Response)" -ForegroundColor Red
}
```

---

## 📝 Cara Pakai:

1. **Buka PowerShell**
2. **Copy script di atas**
3. **Edit email dan password** di bagian `$loginBody`
4. **Paste dan Enter**

---

## 🔍 Interpretasi Hasil:

### Jika Berhasil:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "member_id": "UD-123456",
    "email": "...",
    "nama_lengkap": "..."
  }
}
```
✅ **Authentication bekerja!**

### Jika Error 401:
```
Error: The remote server returned an error: (401) Unauthorized.
```
❌ **Token tidak valid** → Login ulang

### Jika Error 405:
```
Error: The remote server returned an error: (405) Method Not Allowed.
```
❌ **Method salah** → Pastikan menggunakan GET untuk /auth/me

---

## 💡 Tips:

- **Gunakan `Invoke-RestMethod`** karena otomatis parse JSON
- **Gunakan backtick `` ` ``** untuk line continuation di PowerShell
- **Gunakan `curl.exe`** jika lebih familiar dengan curl syntax

