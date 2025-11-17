# Cara Test Authentication

## 📱 Cara 1: Test via Mobile App (Paling Mudah)

### Langkah-langkah:

1. **Login via Mobile App**
   - Buka aplikasi mobile
   - Login dengan email dan password
   - **Catat token** yang muncul di log console (atau dari response login)

2. **Cek Log Console Mobile App**
   - Setelah login, cek log console
   - Cari baris yang berisi: `Token (first 20 chars): ...`
   - **Copy token lengkapnya** (bukan hanya 20 chars pertama)

3. **Test dengan Route Debug**
   - Buka Postman atau gunakan curl
   - Test endpoint: `GET https://ymsofterp.com/api/mobile/member/auth/test-token`
   - Header: `Authorization: Bearer {token_yang_dicopy}`
   - Lihat response untuk melihat apakah token valid

---

## 💻 Cara 2: Test via Postman/API Client

### Step 1: Login untuk Dapat Token

**Request:**
```
POST https://ymsofterp.com/api/mobile/member/auth/login
Content-Type: application/json

{
  "email": "email_member@example.com",
  "password": "password_member"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "member": { ... },
    "token": "1|abc123def456..."  // <-- INI TOKENNYA, COPY!
  }
}
```

**Copy token dari response** (contoh: `1|abc123def456...`)

### Step 2: Test Token di Database (Tanpa Auth)

**Request:**
```
GET https://ymsofterp.com/api/mobile/member/auth/test-token
Authorization: Bearer 1|abc123def456...
Accept: application/json
```

**Response yang Diharapkan:**
```json
{
  "has_token": true,
  "token_preview": "1|abc123def456...",
  "token_length": 80,
  "token_parsed": true,
  "token_id": "1",
  "db_token_found": true,
  "db_token_info": {
    "id": 1,
    "tokenable_id": 1,
    "tokenable_type": "App\\Models\\MemberAppsMember",
    "name": "mobile-app",
    "last_used_at": null,
    "expires_at": null,
    "created_at": "2025-11-17 18:28:57"
  },
  "member_found": true,
  "member_info": {
    "id": 1,
    "member_id": "UD-123456",
    "email": "email@example.com",
    "nama_lengkap": "Nama Member"
  }
}
```

**Interpretasi Response:**
- ✅ `db_token_found: true` → Token ada di database
- ✅ `member_found: true` → Member ditemukan
- ❌ `db_token_found: false` → Token tidak ada di database (login ulang)
- ❌ `token_parsed: false` → Token format salah

### Step 3: Test dengan Sanctum Auth (Dengan Middleware)

**Request:**
```
GET https://ymsofterp.com/api/mobile/member/auth/test-token-auth
Authorization: Bearer 1|abc123def456...
Accept: application/json
```

**Response yang Diharapkan (Jika Berhasil):**
```json
{
  "has_token": true,
  "token_preview": "1|abc123def456...",
  "user_authenticated": true,
  "user_id": 1,
  "user_email": "email@example.com"
}
```

**Response Jika Gagal:**
```json
{
  "message": "Unauthenticated."
}
```

**Interpretasi:**
- ✅ `user_authenticated: true` → Sanctum berhasil validasi token
- ❌ `user_authenticated: false` → Sanctum tidak bisa validasi (masalah di server)

### Step 4: Test Endpoint Asli (auth/me)

**Request:**
```
GET https://ymsofterp.com/api/mobile/member/auth/me
Authorization: Bearer 1|abc123def456...
Accept: application/json
```

**Response yang Diharapkan:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "member_id": "UD-123456",
    "email": "email@example.com",
    "nama_lengkap": "Nama Member",
    ...
  }
}
```

---

## 🖥️ Cara 3: Test via Terminal (curl)

### Step 1: Login
```bash
curl -X POST https://ymsofterp.com/api/mobile/member/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "email_member@example.com",
    "password": "password_member"
  }'
```

**Output:** Copy token dari response (field `token`)

### Step 2: Test Token (Tanpa Auth)
```bash
curl -X GET https://ymsofterp.com/api/mobile/member/auth/test-token \
  -H "Authorization: Bearer {TOKEN_DARI_STEP_1}" \
  -H "Accept: application/json"
```

### Step 3: Test Token (Dengan Auth)
```bash
curl -X GET https://ymsofterp.com/api/mobile/member/auth/test-token-auth \
  -H "Authorization: Bearer {TOKEN_DARI_STEP_1}" \
  -H "Accept: application/json"
```

### Step 4: Test Endpoint Asli
```bash
curl -X GET https://ymsofterp.com/api/mobile/member/auth/me \
  -H "Authorization: Bearer {TOKEN_DARI_STEP_1}" \
  -H "Accept: application/json"
```

---

## 🔍 Cara 4: Cek Log Laravel di Server

Setelah test, cek log untuk detail:

```bash
# SSH ke server
ssh user@server

# Cek log
tail -f storage/logs/laravel.log | grep -i "test token\|token debug\|auth me"
```

Atau cek log file langsung:
```bash
cat storage/logs/laravel.log | grep -i "test token" | tail -20
```

---

## 📊 Interpretasi Hasil Test

### ✅ Scenario 1: Semua Berhasil
- `test-token` → `db_token_found: true`, `member_found: true`
- `test-token-auth` → `user_authenticated: true`
- `auth/me` → `success: true` dengan data member

**Kesimpulan:** Authentication bekerja dengan baik ✅

### ❌ Scenario 2: Token Tidak Ada di Database
- `test-token` → `db_token_found: false`

**Solusi:**
- Token mungkin dari database lain (dev vs production)
- Login ulang untuk dapat token baru
- Pastikan login ke server yang benar

### ❌ Scenario 3: Token Ada Tapi Sanctum Tidak Bisa Validasi
- `test-token` → `db_token_found: true`
- `test-token-auth` → `user_authenticated: false` atau 401

**Kemungkinan Masalah:**
1. Sanctum tidak terinstall dengan benar
2. Token hash tidak match
3. Database connection issue saat validasi

**Solusi:**
1. Cek apakah Sanctum terinstall: `composer show laravel/sanctum`
2. Cek log Laravel untuk error detail
3. Pastikan database connection berfungsi

### ❌ Scenario 4: Token Format Salah
- `test-token` → `token_parsed: false`

**Solusi:**
- Pastikan token dari login response langsung digunakan
- Jangan modify atau trim token
- Token harus dalam format: `{id}|{hash}`

---

## 🚨 Troubleshooting

### Error: "Route not found"
- Pastikan sudah deploy `routes/api.php` yang baru
- Clear route cache: `php artisan route:clear`

### Error: "Class not found" atau "Method not found"
- Pastikan semua file sudah di-deploy
- Clear config cache: `php artisan config:clear`

### Token Selalu 401
1. Cek apakah token yang digunakan sama dengan yang di database
2. Cek log Laravel untuk error detail
3. Pastikan Sanctum terinstall: `composer show laravel/sanctum`
4. Test dengan route debug untuk lihat detail masalahnya

---

## 📝 Checklist Test

- [ ] Login berhasil dan dapat token
- [ ] Test `/auth/test-token` → `db_token_found: true`
- [ ] Test `/auth/test-token-auth` → `user_authenticated: true`
- [ ] Test `/auth/me` → `success: true` dengan data member
- [ ] Cek log Laravel tidak ada error

---

## ⚠️ PENTING

**Route debug (`/auth/test-token` dan `/auth/test-token-auth`) adalah TEMPORARY!**
- Hapus setelah masalah teratasi
- Jangan biarkan route debug di production terlalu lama
- Route debug tidak aman untuk production (expose info token)

Setelah fix, hapus route debug dari `routes/api.php`!

