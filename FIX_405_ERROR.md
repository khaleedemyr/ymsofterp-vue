3# Fix: 405 Method Not Allowed Error

## 🔍 Masalah:
Error: `405 Method Not Allowed`

Ini berarti route sudah ditemukan, tapi **HTTP method yang digunakan salah**.

## ✅ Solusi:

### 1. Pastikan Method Benar

**Route `/auth/me`:**
- ✅ **Method: GET** (bukan POST)
- ✅ **URL:** `https://ymsofterp.com/api/mobile/member/auth/me`
- ✅ **Headers:**
  ```
  Authorization: Bearer {TOKEN}
  Accept: application/json
  ```

**Route `/auth/test-token` (debug):**
- ✅ **Method: GET** (bukan POST)
- ✅ **URL:** `https://ymsofterp.com/api/mobile/member/auth/test-token`
- ✅ **Headers:**
  ```
  Authorization: Bearer {TOKEN}
  Accept: application/json
  ```

### 2. Test dengan PowerShell (Windows):

**⚠️ PENTING:** Di PowerShell, `curl` adalah alias untuk `Invoke-WebRequest` yang punya syntax berbeda!

**Opsi A: Gunakan `curl.exe` (Paling Mudah):**
```powershell
# Test /auth/me (GET)
curl.exe -X GET "https://ymsofterp.com/api/mobile/member/auth/me" `
  -H "Authorization: Bearer {TOKEN}" `
  -H "Accept: application/json"
```

**Opsi B: Gunakan `Invoke-RestMethod` (PowerShell Native):**
```powershell
$headers = @{
    Authorization = "Bearer {TOKEN}"
    Accept = "application/json"
}

$profile = Invoke-RestMethod -Uri "https://ymsofterp.com/api/mobile/member/auth/me" `
    -Method GET `
    -Headers $headers

$profile | ConvertTo-Json -Depth 10
```

**Opsi C: Gunakan Script PowerShell (Paling Mudah):**
Jalankan file `test-auth.ps1` yang sudah disediakan:
```powershell
.\test-auth.ps1
```

**PENTING:** 
- Gunakan backtick `` ` `` untuk line continuation (bukan `\`)
- Atau gunakan `curl.exe` dengan `.exe` di akhir

### 3. Test dengan Postman:

1. **Method:** Pilih **GET** (bukan POST)
2. **URL:** `https://ymsofterp.com/api/mobile/member/auth/me`
3. **Headers:**
   - `Authorization`: `Bearer {TOKEN}`
   - `Accept`: `application/json`
   - `Content-Type`: `application/json`

### 4. Test dengan Browser:

**TIDAK BISA** test langsung di browser karena:
- Browser tidak bisa set `Authorization` header
- Browser hanya support GET tanpa auth

**Solusi:** Gunakan Postman atau curl

---

## 🚀 Langkah Test Lengkap:

### Step 1: Login untuk dapat token

**PowerShell:**
```powershell
$loginBody = @{
    email = "email_member@example.com"
    password = "password_member"
} | ConvertTo-Json

$loginResponse = Invoke-RestMethod -Uri "https://ymsofterp.com/api/mobile/member/auth/login" `
    -Method POST `
    -ContentType "application/json" `
    -Body $loginBody

$token = $loginResponse.data.token
Write-Host "Token: $token"
```

**Atau dengan curl.exe:**
```powershell
curl.exe -X POST "https://ymsofterp.com/api/mobile/member/auth/login" `
  -H "Content-Type: application/json" `
  -H "Accept: application/json" `
  -d '{\"email\": \"email_member@example.com\", \"password\": \"password_member\"}'
```

**Response:**
```json
{
  "success": true,
  "data": {
    "token": "1|abc123def456..."  // <-- COPY INI
  }
}
```

### Step 2: Test /auth/me dengan token

**PowerShell:**
```powershell
$headers = @{
    Authorization = "Bearer $token"
    Accept = "application/json"
}

$profile = Invoke-RestMethod -Uri "https://ymsofterp.com/api/mobile/member/auth/me" `
    -Method GET `
    -Headers $headers

$profile | ConvertTo-Json -Depth 10
```

**Atau dengan curl.exe:**
```powershell
curl.exe -X GET "https://ymsofterp.com/api/mobile/member/auth/me" `
  -H "Authorization: Bearer $token" `
  -H "Accept: application/json"
```

**Jika berhasil (200 OK):**
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

**Jika gagal (401 Unauthorized):**
```json
{
  "message": "Unauthenticated."
}
```
→ Token tidak valid, login ulang

**Jika masih 405:**
→ Pastikan method adalah **GET**, bukan POST

---

## 📝 Checklist:

- [ ] Method adalah **GET** (bukan POST)
- [ ] URL benar: `https://ymsofterp.com/api/mobile/member/auth/me`
- [ ] Header `Authorization: Bearer {TOKEN}` ada
- [ ] Header `Accept: application/json` ada
- [ ] Token dari response login (format: `{id}|{hash}`)
- [ ] Tidak ada space di akhir URL atau token

---

## 🔧 Alternative: Test dengan Endpoint yang Tidak Butuh Auth

Jika masih error, test dengan endpoint public dulu:

```bash
# Test endpoint public (tidak butuh auth)
curl -X GET "https://ymsofterp.com/api/mobile/member/brands" \
  -H "Accept: application/json"
```

Jika ini berhasil, berarti masalahnya di authentication, bukan routing.

