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

### 2. Test dengan curl (Paling Mudah):

```bash
# Test /auth/me (GET)
curl -X GET "https://ymsofterp.com/api/mobile/member/auth/me" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json"
```

**PENTING:** Gunakan `-X GET` atau tidak perlu `-X` sama sekali (default GET)

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
```bash
curl -X POST "https://ymsofterp.com/api/mobile/member/auth/login" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "email_member@example.com",
    "password": "password_member"
  }'
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
```bash
curl -X GET "https://ymsofterp.com/api/mobile/member/auth/me" \
  -H "Authorization: Bearer 1|abc123def456..." \
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

