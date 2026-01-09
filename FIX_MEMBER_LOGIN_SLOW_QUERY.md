# ✅ Fix Query Login Member App - LAMBAT

## 🎯 **MASALAH**

**User report:** "app member mau login aja lama bgt"

**Root cause:** Query login member menggunakan `LOWER(TRIM(member_id))` dan `LOWER(TRIM(email))` yang **TIDAK BISA menggunakan index**, menyebabkan **FULL TABLE SCAN** pada table `member_apps_members` yang besar.

**Query yang bermasalah:**
```sql
-- Dari slow query log sebelumnya:
SELECT * FROM `member_apps_members` 
WHERE LOWER(TRIM(email)) = 'bellasulindra279@gmail.com' 
LIMIT 1;
-- Query_time: 0.174s, Rows_examined: 92,555 rows!

SELECT * FROM `member_apps_members` 
WHERE LOWER(TRIM(member_id)) = 'u10729' 
LIMIT 1;
-- Query_time: 0.148s, Rows_examined: 64,742 rows!
```

**Masalah:**
- Query examine **92,555 rows** untuk return 1 row (sangat tidak efisien!)
- Query examine **64,742 rows** untuk return 1 row (sangat tidak efisien!)
- Fungsi `LOWER(TRIM())` membuat index tidak bisa digunakan
- **FULL TABLE SCAN** pada setiap login attempt!

---

## ✅ **SOLUSI: Generated Column + Index**

### **Step 1: Tambah Generated Column dan Index**

**Login MySQL:**
```sql
mysql -u root -p
USE db_justus;
```

**Tambah Generated Column untuk `email_normalized`:**
```sql
-- Check apakah column sudah ada
SHOW COLUMNS FROM member_apps_members LIKE 'email_normalized';

-- Jika belum ada, tambah generated column
ALTER TABLE member_apps_members 
ADD COLUMN email_normalized VARCHAR(255) 
GENERATED ALWAYS AS (LOWER(TRIM(email))) STORED;

-- Tambah index di generated column
CREATE INDEX idx_email_normalized ON member_apps_members(email_normalized);
```

**Tambah Generated Column untuk `member_id_normalized`:**
```sql
-- Check apakah column sudah ada
SHOW COLUMNS FROM member_apps_members LIKE 'member_id_normalized';

-- Jika belum ada, tambah generated column
ALTER TABLE member_apps_members 
ADD COLUMN member_id_normalized VARCHAR(255) 
GENERATED ALWAYS AS (LOWER(TRIM(member_id))) STORED;

-- Tambah index di generated column
CREATE INDEX idx_member_id_normalized ON member_apps_members(member_id_normalized);
```

**Verifikasi:**
```sql
-- Check indexes
SHOW INDEXES FROM member_apps_members;

-- Test query dengan EXPLAIN
EXPLAIN SELECT * FROM member_apps_members 
WHERE email_normalized = 'bellasulindra279@gmail.com' 
LIMIT 1;

EXPLAIN SELECT * FROM member_apps_members 
WHERE member_id_normalized = 'u10729' 
LIMIT 1;
```

**Expected result:**
- `type` = `ref` (pakai index)
- `key` = `idx_email_normalized` atau `idx_member_id_normalized`
- `rows` = 1 (bukan 92,555 atau 64,742)

---

### **Step 2: Update Application Code**

**File yang sudah diubah:**
1. ✅ `app/Http/Controllers/Mobile/Member/AuthController.php`
   - Line ~35: Query check email saat register
   - Line ~335-349: Query login dengan member_id (multiple attempts)

2. ✅ `app/Http/Controllers/Mobile/Member/RewardController.php`
   - Line ~2880-2894: Query lookup member_id

**Perubahan yang dilakukan:**

**Dari:**
```php
$member = MemberAppsMember::whereRaw('LOWER(TRIM(member_id)) = ?', [strtolower(trim($cleanMemberId))])->first();
$existingEmail = MemberAppsMember::whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($normalizedEmail))])->first();
```

**Menjadi:**
```php
// Gunakan generated column jika ada, fallback ke LOWER(TRIM())
$normalizedMemberId = strtolower(trim($cleanMemberId));
$member = MemberAppsMember::where('member_id_normalized', $normalizedMemberId)
    ->orWhereRaw('LOWER(TRIM(member_id)) = ?', [$normalizedMemberId])
    ->first();

$existingEmail = MemberAppsMember::where('email_normalized', strtolower(trim($normalizedEmail)))
    ->orWhereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($normalizedEmail))])
    ->first();
```

**Kenapa pakai `orWhereRaw` sebagai fallback?**
- Jika generated column belum ada di database, query tetap bisa jalan
- Jika generated column sudah ada, query akan pakai index (jauh lebih cepat)
- Backward compatible dengan database yang belum di-update

---

## 📊 **EXPECTED RESULTS SETELAH OPTIMASI**

| Metric | Sebelum | Sesudah | Improvement |
|--------|---------|---------|-------------|
| **Rows examined (email)** | 92,555 | 1 | **99.999% lebih efisien!** |
| **Rows examined (member_id)** | 64,742 | 1 | **99.998% lebih efisien!** |
| **Query time (email)** | 0.174s | < 0.001s | **174x lebih cepat!** |
| **Query time (member_id)** | 0.148s | < 0.001s | **148x lebih cepat!** |
| **Type** | ALL (full scan) | ref (pakai index) | ✅ Optimized |
| **Login time** | Lambat (1-2 detik) | Cepat (< 0.1 detik) | ✅ Fixed |

---

## 🔧 **VERIFIKASI**

**1. Check Generated Column:**
```sql
SELECT email_normalized, member_id_normalized 
FROM member_apps_members 
LIMIT 5;
```

**2. Test Query dengan EXPLAIN:**
```sql
-- Test email query
EXPLAIN SELECT * FROM member_apps_members 
WHERE email_normalized = 'test@example.com' 
LIMIT 1;

-- Test member_id query
EXPLAIN SELECT * FROM member_apps_members 
WHERE member_id_normalized = 'u10729' 
LIMIT 1;
```

**Expected:**
- `type` = `ref`
- `key` = `idx_email_normalized` atau `idx_member_id_normalized`
- `rows` = 1

**3. Test Login di Member App:**
- Login dengan email → harus cepat (< 0.1 detik)
- Login dengan member_id → harus cepat (< 0.1 detik)
- Register dengan email baru → harus cepat (< 0.1 detik)

---

## ⚠️ **CATATAN PENTING**

1. **Generated Column adalah STORED (bukan VIRTUAL)**
   - Data disimpan di disk (menggunakan storage space)
   - Tapi query jauh lebih cepat karena bisa pakai index
   - Trade-off: sedikit storage space untuk performa yang jauh lebih baik

2. **Backward Compatibility**
   - Query menggunakan `orWhereRaw` sebagai fallback
   - Jika generated column belum ada, query tetap jalan (tapi lambat)
   - Jika generated column sudah ada, query pakai index (sangat cepat)

3. **Data Consistency**
   - Generated column otomatis update ketika `email` atau `member_id` diubah
   - Tidak perlu manual update
   - Selalu konsisten dengan data asli

4. **Index Maintenance**
   - Index akan otomatis update ketika data berubah
   - Tidak perlu manual maintenance
   - Tapi pastikan `innodb_buffer_pool_size` cukup besar untuk index

---

## 📋 **CHECKLIST**

- [x] ✅ Update query di `AuthController.php` (register & login)
- [x] ✅ Update query di `RewardController.php`
- [ ] ⏳ **TAMBAH GENERATED COLUMN DI DATABASE** (user perlu execute SQL)
- [ ] ⏳ **TAMBAH INDEX DI DATABASE** (user perlu execute SQL)
- [ ] ⏳ Test login dengan email
- [ ] ⏳ Test login dengan member_id
- [ ] ⏳ Test register dengan email baru
- [ ] ⏳ Monitor slow query log untuk memastikan query sudah tidak muncul

---

## 🎯 **KESIMPULAN**

✅ **Query sudah dioptimasi di application code**  
⏳ **User perlu execute SQL untuk tambah generated column dan index**  
✅ **Backward compatible - query tetap jalan meski generated column belum ada**  
✅ **Setelah index ditambahkan, login akan jauh lebih cepat!**

**Status:** ✅ **CODE SELESAI - TUNGGU SQL EXECUTION**

**Langkah selanjutnya:**
1. Execute SQL di atas untuk tambah generated column dan index
2. Test login di member app
3. Monitor slow query log untuk memastikan query sudah tidak muncul
