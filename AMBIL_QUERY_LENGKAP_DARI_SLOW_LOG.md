# 🔍 Ambil Query Lengkap dari Slow Log - Cara yang Benar

## ⚠️ **JANGAN PAKAI QUERY CONTOH!**

Query yang saya berikan sebelumnya hanya **contoh**, bukan query yang sebenarnya. Anda perlu **ambil query lengkap dari slow log** dulu.

---

## ✅ **LANGKAH 1: Ambil Query Lengkap dari Slow Log**

### **A. Lihat Query Lengkap untuk Query 1**

```bash
# Lihat query lengkap untuk Query 1 (Query_time: 1.175462)
cat /var/lib/mysql/YMServer-slow.log | grep -B 5 -A 30 "Query_time: 1.175462"
```

**Copy semua output** yang muncul, terutama bagian query SQL-nya.

### **B. Lihat Query Lengkap untuk Query 2**

```bash
# Lihat query lengkap untuk Query 2 (Query_time: 1.225787)
cat /var/lib/mysql/YMServer-slow.log | grep -B 5 -A 30 "Query_time: 1.225787"
```

**Copy semua output** yang muncul.

### **C. Atau Lihat Semua Query dengan do.id**

```bash
# Lihat semua query yang mengandung do.id
cat /var/lib/mysql/YMServer-slow.log | grep -B 5 -A 30 "do.id"
```

---

## 🔍 **LANGKAH 2: Identifikasi Query yang Benar**

### **A. Format Query di Slow Log**

Query di slow log biasanya formatnya seperti ini:

```
# Query_time: 1.175462  Lock_time: 0.000172  Rows_sent: 1  Rows_examined: 1469699
use db_justus;
SET timestamp=1767926741;
SELECT COUNT(*) as total FROM (
    SELECT do.id, do.number, do.created_at
    FROM delivery_orders do
    WHERE do.some_column = 'value'
    AND do.another_column >= '2024-01-01'
) subquery;
```

**Yang perlu di-copy:**
- Bagian dari `SELECT` sampai `;` (atau sampai query selesai)
- **JANGAN** copy bagian `use db_justus;` atau `SET timestamp=...;`

### **B. Contoh Query yang Benar**

Setelah dapat output dari command di atas, cari bagian query SQL-nya. Contoh:

```sql
SELECT COUNT(*) as total FROM (
    SELECT do.id, do.number, do.created_at
    FROM delivery_orders do
    WHERE do.some_column = 'value'
) subquery;
```

**Ini yang perlu di-copy untuk EXPLAIN.**

---

## 🔧 **LANGKAH 3: Check Struktur Table**

Sebelum analisis, check struktur table yang digunakan:

```bash
# Login MySQL
mysql -u root -p
```

```sql
-- Use database
USE db_justus;

-- List semua tables (cari table yang mungkin digunakan)
SHOW TABLES LIKE '%order%';

-- Check struktur table (ganti dengan nama table yang benar)
DESCRIBE delivery_orders;
-- atau
SHOW COLUMNS FROM delivery_orders;

-- Atau jika table name berbeda, cari table dengan alias 'do'
-- Check semua tables yang mungkin
SHOW TABLES;
```

---

## 📋 **LANGKAH 4: Analisis Query dengan EXPLAIN**

### **A. Setelah Dapat Query Lengkap dari Slow Log**

```sql
-- Login MySQL
mysql -u root -p
USE db_justus;

-- EXPLAIN dengan query lengkap yang sudah di-copy dari slow log
-- (Ganti dengan query lengkap yang sebenarnya)
EXPLAIN [QUERY_LENGKAP_DARI_SLOW_LOG];
```

### **B. Contoh (Dengan Query yang Benar)**

Jika query lengkap dari slow log adalah:
```sql
SELECT COUNT(*) as total FROM (
    SELECT do.id, do.number, do.created_at
    FROM delivery_orders do
    WHERE do.created_at >= '2024-01-01'
) subquery;
```

Maka EXPLAIN-nya:
```sql
EXPLAIN SELECT COUNT(*) as total FROM (
    SELECT do.id, do.number, do.created_at
    FROM delivery_orders do
    WHERE do.created_at >= '2024-01-01'
) subquery;
```

---

## 🚀 **LANGKAH 5: Identifikasi Table dan Column yang Benar**

### **A. Dari Query Lengkap, Identifikasi:**

1. **Table name** (dari `FROM` clause)
   - Contoh: `FROM delivery_orders do` → table: `delivery_orders`
   - Atau: `FROM data_orders do` → table: `data_orders`

2. **Column names** (dari WHERE clause)
   - Contoh: `WHERE do.created_at >= '2024-01-01'` → column: `created_at`
   - Contoh: `WHERE do.number = '123'` → column: `number`

3. **Check apakah column ada di table:**
   ```sql
   DESCRIBE delivery_orders;
   -- atau
   SHOW COLUMNS FROM delivery_orders;
   ```

### **B. Check Indexes yang Sudah Ada**

```sql
-- Check indexes untuk table yang digunakan
SHOW INDEXES FROM delivery_orders;
-- (ganti dengan nama table yang benar)
```

---

## 🔧 **COMMAND CEPAT**

### **Step 1: Ambil Query Lengkap**

```bash
# Lihat query lengkap
cat /var/lib/mysql/YMServer-slow.log | grep -B 5 -A 30 "Query_time: 1.175462"
```

**Copy query lengkap** (dari `SELECT` sampai `;`)

### **Step 2: Check Struktur Table**

```bash
mysql -u root -p
```

```sql
USE db_justus;

-- List tables
SHOW TABLES LIKE '%order%';

-- Check struktur table (ganti dengan nama table yang benar)
DESCRIBE delivery_orders;
```

### **Step 3: EXPLAIN Query**

```sql
-- EXPLAIN dengan query lengkap dari slow log
EXPLAIN [QUERY_LENGKAP_YANG_SUDAH_DI_COPY];
```

---

## ⚠️ **CATATAN PENTING**

1. **JANGAN pakai query contoh** - itu hanya template!
2. **Ambil query lengkap dari slow log** dengan command di atas
3. **Check struktur table** dulu sebelum analisis
4. **Pastikan column yang digunakan ada** di table
5. **Copy query lengkap** (dari SELECT sampai ;)

---

## 📊 **WORKFLOW LENGKAP**

```bash
# 1. Ambil query lengkap dari slow log
cat /var/lib/mysql/YMServer-slow.log | grep -B 5 -A 30 "Query_time: 1.175462"

# Output akan muncul, contoh:
# # Query_time: 1.175462 ...
# use db_justus;
# SET timestamp=...;
# SELECT COUNT(*) as total FROM (
#     SELECT do.id, do.number, do.created_at
#     FROM delivery_orders do
#     WHERE do.created_at >= '2024-01-01'
# ) subquery;

# 2. Copy query lengkap (dari SELECT sampai ;)
# SELECT COUNT(*) as total FROM (
#     SELECT do.id, do.number, do.created_at
#     FROM delivery_orders do
#     WHERE do.created_at >= '2024-01-01'
# ) subquery;

# 3. Login MySQL dan check struktur table
mysql -u root -p
USE db_justus;
DESCRIBE delivery_orders;

# 4. EXPLAIN dengan query lengkap
EXPLAIN SELECT COUNT(*) as total FROM (
    SELECT do.id, do.number, do.created_at
    FROM delivery_orders do
    WHERE do.created_at >= '2024-01-01'
) subquery;

# 5. Analisis hasil EXPLAIN
# - type = ALL? → Perlu index
# - key = NULL? → Perlu index
# - rows = besar? → Perlu optimasi
```

---

**Langkah pertama: Jalankan command di Langkah 1 untuk ambil query lengkap dari slow log!** ✅
