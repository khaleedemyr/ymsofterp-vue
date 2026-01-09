# 🔍 Analisis Query dari Slow Log - Cara yang Benar

## ⚠️ **JANGAN COPY-PASTE QUERY CONTOH!**

Query yang saya berikan sebelumnya hanya **contoh**, bukan query yang sebenarnya. Anda perlu **ambil query lengkap dari slow log** dulu.

---

## ✅ **LANGKAH 1: Ambil Query Lengkap dari Slow Log**

### **A. Lihat Query Lengkap**

```bash
# Lihat query lengkap untuk Query 1 (Query_time: 1.175462)
cat /var/lib/mysql/YMServer-slow.log | grep -B 5 -A 30 "Query_time: 1.175462"

# Lihat query lengkap untuk Query 2 (Query_time: 1.225787)
cat /var/lib/mysql/YMServer-slow.log | grep -B 5 -A 30 "Query_time: 1.225787"

# Atau lihat semua query dengan do.id
cat /var/lib/mysql/YMServer-slow.log | grep -B 5 -A 30 "do.id"
```

### **B. Copy Query Lengkap**

Setelah dapat output, **copy query lengkap** (dari `SELECT` sampai `;` atau sampai query selesai).

**Contoh format query di slow log:**
```
# Query_time: 1.175462  Lock_time: 0.000172  Rows_sent: 1  Rows_examined: 1469699
use db_justus;
SET timestamp=1767926741;
SELECT COUNT(*) as total FROM (
    SELECT do.id, do.number, do.created_at
    FROM delivery_orders do
    WHERE do.status = 'pending'
    AND do.created_at >= '2024-01-01'
) subquery;
```

**Yang perlu di-copy untuk EXPLAIN:**
```sql
SELECT COUNT(*) as total FROM (
    SELECT do.id, do.number, do.created_at
    FROM delivery_orders do
    WHERE do.status = 'pending'
    AND do.created_at >= '2024-01-01'
) subquery;
```

---

## 🔍 **LANGKAH 2: Analisis dengan EXPLAIN**

### **A. Login MySQL**

```bash
mysql -u root -p
```

### **B. Use Database**

```sql
USE db_justus;
```

### **C. EXPLAIN Query (Gunakan Query Lengkap dari Slow Log)**

```sql
-- JANGAN copy query contoh! Gunakan query lengkap dari slow log!
-- Contoh format (ganti dengan query lengkap Anda):

EXPLAIN SELECT COUNT(*) as total FROM (
    SELECT do.id, do.number, do.created_at
    FROM delivery_orders do
    WHERE do.status = 'pending'
    AND do.created_at >= '2024-01-01'
) subquery;
```

**Atau untuk query kedua:**

```sql
EXPLAIN SELECT do.id, do.number, do.created_at
FROM delivery_orders do
WHERE do.status = 'pending'
AND do.created_at >= '2024-01-01'
ORDER BY do.created_at DESC
LIMIT 5;
```

---

## 🔧 **LANGKAH 3: Analisis Hasil EXPLAIN**

### **A. Check Hasil EXPLAIN**

Setelah jalankan EXPLAIN, check kolom berikut:

| Kolom | Arti | Nilai Baik | Nilai Buruk |
|-------|------|------------|-------------|
| **type** | Tipe scan | `ref`, `eq_ref`, `range` | `ALL` (full table scan) |
| **key** | Index yang digunakan | Nama index | `NULL` (tidak pakai index) |
| **rows** | Rows yang diperiksa | Kecil (< 1000) | Besar (> 10000) |
| **Extra** | Info tambahan | `Using index` | `Using filesort`, `Using temporary` |

### **B. Interpretasi Hasil**

**Jika `type = ALL`:**
- ❌ **FULL TABLE SCAN** - sangat lambat!
- **Solusi:** Tambah index untuk column di WHERE clause

**Jika `key = NULL`:**
- ❌ Tidak pakai index
- **Solusi:** Tambah index untuk column di WHERE clause

**Jika `rows` sangat besar:**
- ❌ Memeriksa terlalu banyak rows
- **Solusi:** Optimasi query atau tambah index

---

## 🚀 **LANGKAH 4: Identifikasi Table dan Column**

### **A. Dari Query Lengkap, Identifikasi:**

1. **Table name** (dari `FROM` clause)
   - Contoh: `FROM delivery_orders do` → table: `delivery_orders`

2. **WHERE conditions** (column yang di-filter)
   - Contoh: `WHERE do.status = 'pending'` → column: `status`
   - Contoh: `WHERE do.created_at >= '2024-01-01'` → column: `created_at`

3. **ORDER BY columns** (jika ada)
   - Contoh: `ORDER BY do.created_at DESC` → column: `created_at`

4. **JOIN tables** (jika ada)
   - Contoh: `JOIN users u ON do.user_id = u.id` → column: `user_id`

### **B. Check Indexes yang Sudah Ada**

```sql
-- Ganti dengan nama table yang benar dari query
SHOW INDEXES FROM delivery_orders;

-- Atau check semua indexes
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    COLUMN_NAME,
    SEQ_IN_INDEX
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = 'db_justus'
AND TABLE_NAME = 'delivery_orders'  -- ganti dengan table yang benar
ORDER BY INDEX_NAME, SEQ_IN_INDEX;
```

---

## 📋 **LANGKAH 5: Tambah Index yang Diperlukan**

### **A. Berdasarkan WHERE Conditions**

```sql
-- Jika WHERE do.status
CREATE INDEX idx_status ON delivery_orders(status);

-- Jika WHERE do.created_at
CREATE INDEX idx_created_at ON delivery_orders(created_at);

-- Jika WHERE multiple columns
CREATE INDEX idx_status_created_at ON delivery_orders(status, created_at);
```

### **B. Berdasarkan ORDER BY**

```sql
-- Jika ORDER BY do.created_at
CREATE INDEX idx_created_at ON delivery_orders(created_at);

-- Jika ORDER BY dengan WHERE
CREATE INDEX idx_status_created_at ON delivery_orders(status, created_at);
```

---

## 🔧 **COMMAND LENGKAP (Step-by-Step)**

### **Step 1: Ambil Query Lengkap**

```bash
# Lihat query lengkap
cat /var/lib/mysql/YMServer-slow.log | grep -B 5 -A 30 "Query_time: 1.175462"
```

**Copy query lengkap** (dari `SELECT` sampai `;`)

### **Step 2: Login MySQL dan EXPLAIN**

```bash
mysql -u root -p
```

```sql
USE db_justus;

-- Paste query lengkap dari slow log, tambahkan EXPLAIN di depan
EXPLAIN [QUERY_LENGKAP_DARI_SLOW_LOG];
```

### **Step 3: Analisis Hasil**

- Check `type` (harusnya bukan `ALL`)
- Check `key` (harusnya ada nama index, bukan `NULL`)
- Check `rows` (harusnya kecil)

### **Step 4: Identifikasi Table dan Column**

- Dari query, identifikasi table name
- Identifikasi column di WHERE clause
- Identifikasi column di ORDER BY

### **Step 5: Check Indexes**

```sql
SHOW INDEXES FROM [TABLE_NAME];
```

### **Step 6: Tambah Index**

```sql
CREATE INDEX idx_[column_name] ON [table_name]([column_name]);
```

---

## ⚠️ **CATATAN PENTING**

1. **JANGAN copy-paste query contoh** - itu hanya template!
2. **Ambil query lengkap dari slow log** dulu
3. **Pastikan query lengkap** (dari SELECT sampai ;)
4. **Test EXPLAIN** sebelum tambah index
5. **Backup database** sebelum tambah index (untuk table besar)

---

## 📊 **CONTOH WORKFLOW**

```bash
# 1. Ambil query lengkap
cat /var/lib/mysql/YMServer-slow.log | grep -B 5 -A 30 "Query_time: 1.175462"

# Output akan seperti:
# # Query_time: 1.175462 ...
# use db_justus;
# SET timestamp=...;
# SELECT COUNT(*) as total FROM (
#     SELECT do.id, do.number, do.created_at
#     FROM delivery_orders do
#     WHERE do.status = 'pending'
# ) subquery;

# 2. Copy query lengkap (dari SELECT sampai ;)
# SELECT COUNT(*) as total FROM (
#     SELECT do.id, do.number, do.created_at
#     FROM delivery_orders do
#     WHERE do.status = 'pending'
# ) subquery;

# 3. Login MySQL
mysql -u root -p

# 4. EXPLAIN
USE db_justus;
EXPLAIN SELECT COUNT(*) as total FROM (
    SELECT do.id, do.number, do.created_at
    FROM delivery_orders do
    WHERE do.status = 'pending'
) subquery;

# 5. Check hasil EXPLAIN
# - type = ALL? → Perlu index
# - key = NULL? → Perlu index

# 6. Check indexes yang ada
SHOW INDEXES FROM delivery_orders;

# 7. Tambah index jika perlu
CREATE INDEX idx_status ON delivery_orders(status);
```

---

**Langkah pertama: Ambil query lengkap dari slow log dengan command di atas!** ✅
