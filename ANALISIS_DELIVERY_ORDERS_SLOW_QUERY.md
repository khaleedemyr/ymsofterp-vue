# 🔍 Analisis Slow Query - delivery_orders Table

## 📊 **STRUKTUR TABLE delivery_orders**

### **Indexes yang Sudah Ada:**
- ✅ `id` (PRIMARY KEY)
- ✅ `number` (MUL - indexed)
- ✅ `packing_list_id` (MUL - indexed)
- ✅ `ro_supplier_gr_id` (MUL - indexed)
- ✅ `created_by` (MUL - indexed)
- ✅ `created_at` (MUL - indexed)
- ✅ `floor_order_id` (MUL - indexed)

### **Columns yang TIDAK Ada Index:**
- ❌ `source_type` (tidak ada index)
- ❌ `updated_at` (tidak ada index)

---

## 🔍 **LANGKAH 1: Ambil Query Lengkap dari Slow Log**

### **A. Lihat Query Lengkap**

```bash
# Lihat query lengkap untuk Query 1 (Query_time: 1.175462)
cat /var/lib/mysql/YMServer-slow.log | grep -B 5 -A 30 "Query_time: 1.175462"

# Lihat query lengkap untuk Query 2 (Query_time: 1.225787)
cat /var/lib/mysql/YMServer-slow.log | grep -B 5 -A 30 "Query_time: 1.225787"
```

**Copy query lengkap** (dari `SELECT` sampai `;`)

### **B. Identifikasi WHERE Conditions**

Dari query lengkap, identifikasi:
- Column apa yang digunakan di WHERE clause?
- Apakah column tersebut sudah ada index?
- Apakah ada kombinasi multiple columns di WHERE?

---

## 🔧 **LANGKAH 2: Analisis dengan EXPLAIN**

### **A. Setelah Dapat Query Lengkap**

```sql
-- Login MySQL
mysql -u root -p
USE db_justus;

-- EXPLAIN dengan query lengkap dari slow log
EXPLAIN [QUERY_LENGKAP_DARI_SLOW_LOG];
```

### **B. Check Hasil EXPLAIN**

**Yang perlu dicek:**
- **type**: 
  - `ALL` = Full table scan (SANGAT BURUK!)
  - `ref` atau `eq_ref` = Pakai index (BAIK)
- **key**: Index yang digunakan (jika NULL = tidak pakai index)
- **rows**: Jumlah rows yang diperiksa (harusnya kecil, bukan 1.5 juta)
- **Extra**: 
  - `Using where` = Filter dengan WHERE
  - `Using index` = Pakai index (BAIK)
  - `Using filesort` = Perlu sorting (BISA LAMBAT)

---

## 🚀 **LANGKAH 3: Kemungkinan Masalah**

### **A. Query Memeriksa 1.5 Juta Rows**

Dari slow log:
- **Rows_examined: 1,469,699** untuk **Rows_sent: 1**
- **Rows_examined: 1,469,391** untuk **Rows_sent: 5**

**Kemungkinan penyebab:**
1. **WHERE condition tidak pakai index**
   - Column di WHERE tidak ada index
   - Atau kombinasi columns yang tidak ada composite index

2. **Full table scan**
   - Query memeriksa semua rows di table
   - Tidak ada index yang bisa digunakan

3. **Subquery yang tidak efisien**
   - Query `SELECT COUNT(*) FROM (SELECT ...)` mungkin tidak optimal

### **B. Columns yang Mungkin Menjadi Masalah**

Berdasarkan struktur table:
- `source_type` - **TIDAK ada index** (jika digunakan di WHERE)
- `updated_at` - **TIDAK ada index** (jika digunakan di WHERE atau ORDER BY)
- Kombinasi multiple columns - mungkin perlu composite index

---

## 📋 **LANGKAH 4: Solusi Optimasi**

### **A. Tambah Index untuk Column yang Sering di-WHERE**

Setelah identifikasi column yang digunakan di WHERE clause:

```sql
-- Jika WHERE source_type
CREATE INDEX idx_source_type ON delivery_orders(source_type);

-- Jika WHERE updated_at
CREATE INDEX idx_updated_at ON delivery_orders(updated_at);

-- Jika WHERE multiple columns (composite index)
CREATE INDEX idx_source_type_created_at ON delivery_orders(source_type, created_at);
```

### **B. Optimasi Query COUNT(*) dengan Subquery**

**Jika query seperti ini:**
```sql
SELECT COUNT(*) as total FROM (
    SELECT do.id, do.number, do.created_at
    FROM delivery_orders do
    WHERE ...
) subquery;
```

**Bisa dioptimasi menjadi:**
```sql
-- Langsung COUNT tanpa subquery
SELECT COUNT(*) as total
FROM delivery_orders do
WHERE ...;
```

**Atau jika perlu distinct:**
```sql
SELECT COUNT(DISTINCT do.id) as total
FROM delivery_orders do
WHERE ...;
```

### **C. Tambah LIMIT jika Hanya Butuh Beberapa Rows**

**Jika query hanya butuh beberapa rows:**
```sql
-- Tambahkan LIMIT
SELECT do.id, do.number, do.created_at
FROM delivery_orders do
WHERE ...
ORDER BY do.created_at DESC
LIMIT 5;  -- atau jumlah yang diperlukan
```

---

## 🔧 **COMMAND CEPAT**

### **Step 1: Ambil Query Lengkap**

```bash
cat /var/lib/mysql/YMServer-slow.log | grep -B 5 -A 30 "Query_time: 1.175462"
```

### **Step 2: Identifikasi WHERE Conditions**

Dari query lengkap, cari:
- `WHERE do.column_name = ...`
- `WHERE do.column_name >= ...`
- `WHERE do.column_name IN (...)`
- `ORDER BY do.column_name`

### **Step 3: Check Apakah Column Ada Index**

```sql
SHOW INDEXES FROM delivery_orders;
```

### **Step 4: EXPLAIN Query**

```sql
EXPLAIN [QUERY_LENGKAP_DARI_SLOW_LOG];
```

### **Step 5: Tambah Index Jika Perlu**

```sql
-- Contoh: Jika WHERE source_type
CREATE INDEX idx_source_type ON delivery_orders(source_type);

-- Contoh: Jika WHERE updated_at
CREATE INDEX idx_updated_at ON delivery_orders(updated_at);
```

---

## 📊 **REKOMENDASI INDEX**

Berdasarkan struktur table dan slow query:

### **A. Index yang Mungkin Perlu (Tergantung Query)**

```sql
-- Jika query WHERE source_type
CREATE INDEX idx_source_type ON delivery_orders(source_type);

-- Jika query WHERE updated_at
CREATE INDEX idx_updated_at ON delivery_orders(updated_at);

-- Jika query WHERE source_type AND created_at
CREATE INDEX idx_source_type_created_at ON delivery_orders(source_type, created_at);

-- Jika query ORDER BY updated_at
CREATE INDEX idx_updated_at ON delivery_orders(updated_at);
```

### **B. Check Indexes yang Sudah Ada**

```sql
-- Check semua indexes
SHOW INDEXES FROM delivery_orders;

-- Atau
SELECT 
    INDEX_NAME,
    COLUMN_NAME,
    SEQ_IN_INDEX
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = 'db_justus'
AND TABLE_NAME = 'delivery_orders'
ORDER BY INDEX_NAME, SEQ_IN_INDEX;
```

---

## ⚠️ **CATATAN PENTING**

1. **Jangan tambah index sembarangan** - terlalu banyak index juga bisa lambat
2. **Tambah index berdasarkan query yang benar-benar slow**
3. **Test setelah tambah index** - pastikan query jadi lebih cepat
4. **Monitor slow query log** setelah optimasi

---

## 🎯 **ACTION PLAN**

1. ✅ **Ambil query lengkap** dari slow log
2. ⏳ **Identifikasi WHERE conditions** yang digunakan
3. ⏳ **EXPLAIN query** untuk lihat apakah pakai index
4. ⏳ **Tambah index** untuk column yang tidak ada index
5. ⏳ **Test query** setelah tambah index
6. ⏳ **Monitor** untuk verifikasi

---

**Langkah pertama: Ambil query lengkap dari slow log dengan command di atas!** ✅
