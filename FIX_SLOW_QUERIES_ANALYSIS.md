# 🚨 Fix Slow Queries - Analisis & Solusi

## ⚠️ **MASALAH YANG DITEMUKAN**

### **Query 1: COUNT(*) dengan Subquery**
```
Query_time: 1.175462
Rows_sent: 1
Rows_examined: 1,469,699 (HAMPIR 1.5 JUTA!)
Query: SELECT COUNT(*) as total FROM (SELECT do.id, ...)
```

### **Query 2: SELECT dengan Multiple Columns**
```
Query_time: 1.225787
Rows_sent: 5
Rows_examined: 1,469,391 (HAMPIR 1.5 JUTA!)
Query: SELECT do.id, do.number, do.created_at, ...
```

**Masalah Utama:**
- Query memeriksa **1.5 juta rows** hanya untuk mengembalikan **1-5 rows**
- Ratio sangat buruk: **1,469,699:1** dan **1,469,391:5**
- Ini adalah **FULL TABLE SCAN** - tidak pakai index!

---

## 🔍 **LANGKAH 1: Analisis Query Lengkap**

### **A. Lihat Query Lengkap dari Slow Log**

```bash
# Lihat query lengkap (ganti angka sesuai query yang ingin dilihat)
cat /var/lib/mysql/YMServer-slow.log | grep -A 20 "Query_time: 1.175462"

# Atau lihat semua query dengan do.id
cat /var/lib/mysql/YMServer-slow.log | grep -B 5 -A 20 "do.id"
```

### **B. Identifikasi Table yang Digunakan**

Dari query, terlihat menggunakan alias `do` yang kemungkinan adalah:
- `delivery_orders`
- `data_orders`
- Atau table lain dengan prefix `*_orders`

**Cari table yang digunakan:**
```bash
# Cari query lengkap untuk lihat FROM clause
cat /var/lib/mysql/YMServer-slow.log | grep -A 30 "do.id" | grep -i "FROM\|JOIN"
```

---

## 🔧 **LANGKAH 2: Analisis dengan EXPLAIN**

### **A. Dapatkan Query Lengkap**

Setelah dapat query lengkap dari slow log, gunakan EXPLAIN:

```sql
-- Login MySQL
mysql -u root -p

-- Use database
USE db_justus;

-- EXPLAIN query (ganti dengan query lengkap dari slow log)
EXPLAIN SELECT COUNT(*) as total FROM (
    SELECT do.id, do.number, do.created_at
    FROM delivery_orders do
    WHERE ...
    ...
) subquery;
```

### **B. Check Hasil EXPLAIN**

**Yang perlu dicek:**
- **type**: 
  - `ALL` = Full table scan (SANGAT BURUK!)
  - `ref` atau `eq_ref` = Pakai index (BAIK)
- **rows**: Jumlah rows yang diperiksa (harusnya kecil, bukan 1.5 juta)
- **key**: Index yang digunakan (jika NULL = tidak pakai index)
- **Extra**: 
  - `Using where` = Filter dengan WHERE
  - `Using index` = Pakai index (BAIK)
  - `Using filesort` = Perlu sorting (BISA LAMBAT)

---

## 🚀 **LANGKAH 3: Solusi Optimasi**

### **A. Tambah Index untuk Column yang Sering di-WHERE**

Berdasarkan query yang terlihat (`do.id`, `do.number`, `do.created_at`), kemungkinan perlu index untuk:

```sql
-- Login MySQL
mysql -u root -p
USE db_justus;

-- Check indexes yang sudah ada
SHOW INDEXES FROM delivery_orders;
-- atau ganti dengan nama table yang benar

-- Tambah index untuk column yang sering di-WHERE
-- (Ganti table_name dan column_name sesuai query yang ditemukan)

-- Contoh: Jika query WHERE created_at
CREATE INDEX idx_created_at ON delivery_orders(created_at);

-- Contoh: Jika query WHERE number
CREATE INDEX idx_number ON delivery_orders(number);

-- Contoh: Composite index untuk multiple columns
CREATE INDEX idx_created_at_status ON delivery_orders(created_at, status);
```

### **B. Optimasi Query COUNT(*) dengan Subquery**

Query `SELECT COUNT(*) FROM (SELECT ...)` bisa dioptimasi:

**Sebelum (LAMBAT):**
```sql
SELECT COUNT(*) as total FROM (
    SELECT do.id, do.number, do.created_at
    FROM delivery_orders do
    WHERE ...
) subquery;
```

**Sesudah (LEBIH CEPAT):**
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

### **C. Optimasi Query dengan LIMIT**

Jika query hanya butuh beberapa rows, tambahkan LIMIT:

```sql
-- Sebelum
SELECT do.id, do.number, do.created_at
FROM delivery_orders do
WHERE ...
ORDER BY do.created_at DESC;

-- Sesudah (jika hanya butuh 5 rows)
SELECT do.id, do.number, do.created_at
FROM delivery_orders do
WHERE ...
ORDER BY do.created_at DESC
LIMIT 5;
```

---

## 📋 **LANGKAH 4: Identifikasi Table yang Digunakan**

### **A. Cari Query Lengkap**

```bash
# Cari query lengkap dengan do.id
cat /var/lib/mysql/YMServer-slow.log | grep -B 5 -A 30 "do.id" | head -50

# Atau cari semua query yang examine banyak rows
cat /var/lib/mysql/YMServer-slow.log | grep "Rows_examined: 1[0-9][0-9][0-9][0-9][0-9][0-9]" | head -20
```

### **B. Identifikasi Table dari Query**

Setelah dapat query lengkap, identifikasi:
1. **Table name** (dari FROM clause)
2. **WHERE conditions** (column yang di-filter)
3. **JOIN tables** (jika ada)
4. **ORDER BY columns** (jika ada)

---

## 🔍 **LANGKAH 5: Check Indexes yang Sudah Ada**

### **A. Check Indexes untuk Table yang Terkena**

```sql
-- Login MySQL
mysql -u root -p
USE db_justus;

-- List semua tables
SHOW TABLES;

-- Check indexes untuk table tertentu (ganti dengan nama table yang benar)
SHOW INDEXES FROM delivery_orders;
-- atau
SHOW INDEXES FROM data_orders;
-- atau table lain yang teridentifikasi

-- Check semua indexes di database
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    COLUMN_NAME,
    SEQ_IN_INDEX,
    CARDINALITY
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = 'db_justus'
AND TABLE_NAME LIKE '%order%'
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;
```

---

## 📊 **LANGKAH 6: Buat Indexes yang Diperlukan**

### **A. Analisis Query untuk Tentukan Index**

Berdasarkan query yang ditemukan, tentukan index yang diperlukan:

**Jika query WHERE created_at:**
```sql
CREATE INDEX idx_created_at ON table_name(created_at);
```

**Jika query WHERE number:**
```sql
CREATE INDEX idx_number ON table_name(number);
```

**Jika query WHERE multiple columns:**
```sql
CREATE INDEX idx_composite ON table_name(column1, column2);
```

**Jika query ORDER BY created_at:**
```sql
CREATE INDEX idx_created_at ON table_name(created_at);
```

### **B. Check File Indexes yang Sudah Ada**

```bash
# Check file SQL untuk indexes
cat CHECK_EXISTING_INDEXES.sql
cat CREATE_MISSING_INDEXES.sql
```

---

## 🔧 **COMMAND CEPAT**

### **Analisis Query Lengkap**

```bash
# 1. Lihat query lengkap dari slow log
cat /var/lib/mysql/YMServer-slow.log | grep -B 5 -A 30 "do.id" | head -50

# 2. Cari table name dari query
cat /var/lib/mysql/YMServer-slow.log | grep -A 30 "do.id" | grep -i "FROM\|JOIN"

# 3. Check indexes untuk table tertentu
mysql -u root -p -e "USE db_justus; SHOW INDEXES FROM delivery_orders;" 2>/dev/null || echo "Table not found, check table name first"
```

### **Buat Index Cepat**

```sql
-- Setelah identifikasi table dan column yang perlu index
-- Login MySQL
mysql -u root -p
USE db_justus;

-- Tambah index (contoh)
CREATE INDEX idx_created_at ON delivery_orders(created_at);
CREATE INDEX idx_number ON delivery_orders(number);
```

---

## ⚠️ **CATATAN PENTING**

1. **Jangan tambah index sembarangan** - terlalu banyak index juga bisa lambat
2. **Test di staging** sebelum apply ke production
3. **Monitor setelah tambah index** - pastikan query jadi lebih cepat
4. **Backup database** sebelum tambah index (untuk table besar)

---

## 📊 **EXPECTED RESULTS**

Setelah optimasi:

| Metric | Sebelum | Sesudah |
|--------|---------|---------|
| **Rows_examined** | 1,469,699 | < 1000 |
| **Query_time** | 1.18 detik | < 0.1 detik |
| **Rows_examined:Rows_sent** | 1,469,699:1 | < 100:1 |

---

## 🎯 **ACTION PLAN**

1. ✅ **Identifikasi query lengkap** dari slow log
2. ⏳ **Analisis dengan EXPLAIN** untuk lihat apakah pakai index
3. ⏳ **Identifikasi table** yang digunakan
4. ⏳ **Check indexes** yang sudah ada
5. ⏳ **Tambah index** yang diperlukan
6. ⏳ **Test query** setelah tambah index
7. ⏳ **Monitor** untuk verifikasi

---

**Langkah pertama: Lihat query lengkap untuk identifikasi table dan WHERE conditions!** ✅
