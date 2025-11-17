# Cara Test Auth - Versi Simple

## 🚀 Quick Test (Paling Cepat)

### 1. Login via Mobile App
- Login dengan email & password
- **Catat token** dari response (atau dari log console)

### 2. Test di Browser/Postman

**URL:** `https://ymsofterp.com/api/mobile/member/auth/test-token`

**Header:**
```
Authorization: Bearer {TOKEN_DARI_LOGIN}
Accept: application/json
```

**Contoh Token:** `1|abc123def456ghi789...` (format: `{id}|{hash}`)

### 3. Lihat Response

**Jika Berhasil:**
```json
{
  "has_token": true,
  "db_token_found": true,
  "member_found": true,
  "member_info": {
    "id": 1,
    "email": "...",
    "nama_lengkap": "..."
  }
}
```

**Jika Gagal:**
```json
{
  "has_token": true,
  "db_token_found": false  // <-- Token tidak ada di database
}
```

---

## 📱 Cara Ambil Token dari Mobile App

### Via Log Console:
1. Buka aplikasi mobile
2. Login
3. Cek log console, cari: `Token (first 20 chars): ...`
4. **Tapi ini hanya preview!** Token lengkap ada di response login

### Via Response Login (Jika ada logging):
- Cek log: `Login successful` atau `token_preview`
- Token lengkap biasanya 80+ karakter

### Via Database (Jika punya akses):
```sql
SELECT 
    id,
    tokenable_id,
    LEFT(token, 20) as token_preview,
    created_at
FROM personal_access_tokens 
WHERE tokenable_type = 'App\\Models\\MemberAppsMember'
ORDER BY created_at DESC 
LIMIT 1;
```

**Tapi ini hanya ID dan preview, bukan token lengkap!**

---

## 🔧 Test dengan curl (Terminal)

```bash
# Ganti {TOKEN} dengan token dari login
curl -X GET "https://ymsofterp.com/api/mobile/member/auth/test-token" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Accept: application/json" | jq
```

---

## ✅ Hasil yang Diharapkan

Jika semua berjalan baik:
- ✅ `db_token_found: true` → Token ada di database
- ✅ `member_found: true` → Member ditemukan
- ✅ `member_info` ada → Data member lengkap

Jika ada masalah:
- ❌ `db_token_found: false` → **Login ulang** untuk dapat token baru
- ❌ `token_parsed: false` → Token format salah
- ❌ `member_found: false` → Member tidak ditemukan di database

---

## 🎯 Next Step Setelah Test

1. **Jika `db_token_found: true`** → Test endpoint `/auth/test-token-auth` (dengan middleware)
2. **Jika `db_token_found: false`** → Login ulang, token mungkin dari database lain
3. **Cek log Laravel** untuk detail error jika masih gagal

