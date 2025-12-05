# Fix: tokenable_type Terpotong

## 🔍 Masalah:
Di database, `tokenable_type` = `App\Models\MemberApps` (terpotong)
Seharusnya: `App\Models\MemberAppsMember`

Ini menyebabkan Sanctum tidak bisa menemukan token karena mencari dengan `tokenable_type` = `App\Models\MemberAppsMember`.

## ✅ Solusi:

### 1. Cek Struktur Kolom

Jalankan di database:
```sql
DESCRIBE personal_access_tokens;
```

Atau:
```sql
SHOW COLUMNS FROM personal_access_tokens LIKE 'tokenable_type';
```

Pastikan kolom `tokenable_type` adalah `VARCHAR(255)` atau lebih panjang.

### 2. Jika Kolom Terlalu Pendek, Alter Table

```sql
ALTER TABLE personal_access_tokens 
MODIFY COLUMN tokenable_type VARCHAR(255);
```

### 3. Fix Data yang Sudah Ada

Jalankan query ini untuk update data yang terpotong:
```sql
UPDATE personal_access_tokens 
SET tokenable_type = 'App\\Models\\MemberAppsMember'
WHERE tokenable_type = 'App\\Models\\MemberApps'
  AND tokenable_id = 1;
```

**PENTING:** Gunakan `\\` (double backslash) karena SQL escape.

### 4. Verifikasi

```sql
SELECT 
    id,
    tokenable_type,
    tokenable_id,
    name,
    created_at
FROM personal_access_tokens
WHERE tokenable_id = 1
ORDER BY created_at DESC
LIMIT 10;
```

Pastikan semua `tokenable_type` = `App\Models\MemberAppsMember`.

### 5. Test Lagi

Setelah fix, test lagi dengan:
```powershell
.\test-auth.ps1
```

---

## 📝 File SQL:

Saya sudah buat file `database/sql/fix_tokenable_type.sql` dengan query lengkap.

---

## 🚨 Jika Masih Error:

1. **Hapus token lama** dan login ulang:
```sql
DELETE FROM personal_access_tokens 
WHERE tokenable_id = 1 
  AND tokenable_type = 'App\\Models\\MemberApps';
```

2. **Login ulang** untuk generate token baru dengan `tokenable_type` yang benar.

3. **Test lagi** dengan script.

---

## ✅ Checklist:

- [ ] Cek struktur kolom `tokenable_type` (minimal VARCHAR(255))
- [ ] Alter table jika perlu
- [ ] Update data yang terpotong
- [ ] Verifikasi data sudah benar
- [ ] Test authentication lagi

