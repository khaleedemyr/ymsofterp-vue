# Cara Test Auth - Versi Paling Simple

## 🎯 Test Langsung dengan Endpoint yang Sudah Ada

Karena route debug mungkin belum ter-deploy atau ada masalah, **test langsung dengan endpoint `/auth/me`** yang sudah pasti ada:

### Via Postman/Browser:

**URL:**
```
GET https://ymsofterp.com/api/mobile/member/auth/me
```

**Headers:**
```
Authorization: Bearer {TOKEN_DARI_LOGIN}
Accept: application/json
Content-Type: application/json
```

### Via curl:

```bash
curl -X GET "https://ymsofterp.com/api/mobile/member/auth/me" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json"
```

---

## 📱 Cara Ambil Token dari Mobile App:

### Opsi 1: Login via Postman/curl (Paling Mudah)

```bash
curl -X POST "https://ymsofterp.com/api/mobile/member/auth/login" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "email_member@example.com",
    "password": "password_member"
  }'
```

**Response akan berisi token:**
```json
{
  "success": true,
  "data": {
    "token": "1|abc123def456..."  // <-- COPY INI
  }
}
```

### Opsi 2: Dari Mobile App Log

Setelah login di mobile app, cek log console untuk token preview, tapi **token lengkap ada di response login**.

---

## ✅ Interpretasi Hasil:

### Jika Berhasil (200 OK):
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

### Jika Gagal (401 Unauthorized):
```json
{
  "message": "Unauthenticated."
}
```
❌ **Token tidak valid** → Login ulang untuk dapat token baru

### Jika Error 400 Bad Request:
- Pastikan URL benar (tidak ada space)
- Pastikan header lengkap
- Pastikan Content-Type: application/json

---

## 🔧 Troubleshooting 400 Bad Request:

1. **Pastikan URL benar:**
   ```
   https://ymsofterp.com/api/mobile/member/auth/me
   ```
   (Tidak ada space di akhir)

2. **Pastikan Header lengkap:**
   ```
   Authorization: Bearer {TOKEN}
   Accept: application/json
   Content-Type: application/json
   ```

3. **Pastikan Token format benar:**
   - Format: `{id}|{hash}`
   - Contoh: `1|abc123def456ghi789...`
   - Tidak ada space di awal/akhir token

4. **Test dengan curl dulu** untuk pastikan format benar

---

## 📝 Langkah Test:

1. **Login** → Dapat token
2. **Test `/auth/me`** dengan token
3. **Lihat response:**
   - 200 + data member → ✅ Berhasil
   - 401 → ❌ Token invalid, login ulang
   - 400 → ❌ Format request salah, cek header/URL

