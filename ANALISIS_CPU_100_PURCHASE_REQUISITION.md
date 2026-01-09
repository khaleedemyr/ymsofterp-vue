# 🔥 Analisis CPU 100% - Purchase Requisition Queries

## ⚠️ **MASALAH: CPU 100%**

Server CPU kembali 100% karena beberapa query yang tidak efisien pada fitur Purchase Requisition.

---

## 📊 **QUERY YANG BERMASALAH**

### **1. Query `purchase_requisition_history` (PALING BERMASALAH)**

**Query:**
```sql
SELECT * FROM `purchase_requisition_history` 
WHERE `purchase_requisition_id` = ? 
AND `user_id` = ? 
AND `action` = 'viewed' 
ORDER BY `created_at` DESC 
LIMIT 1;
```

**Masalah:**
- **Query time:** 0.002s (cepat)
- **Rows examined:** 2,733 rows
- **Rows sent:** 0 rows
- **Muncul berulang** untuk multiple purchase_requisition_id (1150, 1149, 1148, 1147, dll)
- Query examine 2,733 rows untuk return 0 rows (tidak efisien!)

**Dampak:**
- Jika ada 10 PR di list, total examine: 10 × 2,733 = **27,330 rows**
- Query muncul berulang di setiap page load/list PR

**Kemungkinan masalah:**
1. Tidak ada index di `(purchase_requisition_id, user_id, action, created_at)`
2. Query scan semua rows dengan kondisi tersebut

**Lokasi penggunaan:**
- File: `app/Http/Controllers/PurchaseRequisitionController.php`
- Line ~348: Get last view time untuk setiap PR di list
- Line ~2571: Calculate unread comments count untuk setiap PR di list
- **Dipanggil untuk setiap PR di list** → muncul berulang!

---

### **2. Query `tbl_data_divisi` dengan EXISTS**

**Query:**
```sql
SELECT * FROM `tbl_data_divisi` 
WHERE EXISTS (
    SELECT * FROM `purchase_requisitions` 
    WHERE `tbl_data_divisi`.`id` = `purchase_requisitions`.`division_id`
) 
AND `status` = 'A' 
AND `tbl_data_divisi`.`deleted_at` IS NULL 
ORDER BY `nama_divisi` ASC;
```

**Masalah:**
- **Query time:** 0.008s (cepat)
- **Rows examined:** 9,054 rows
- **Rows sent:** 26 rows
- Query examine 9,054 rows untuk return 26 rows (tidak efisien!)

**Kemungkinan masalah:**
1. Tidak ada index di `purchase_requisitions.division_id`
2. EXISTS subquery scan semua purchase_requisitions untuk setiap divisi

---

### **3. Query `purchase_requisition_categories` dengan EXISTS**

**Query:**
```sql
SELECT * FROM `purchase_requisition_categories` 
WHERE EXISTS (
    SELECT * FROM `purchase_requisitions` 
    WHERE `purchase_requisition_categories`.`id` = `purchase_requisitions`.`category_id`
) 
ORDER BY `name` ASC;
```

**Masalah:**
- **Query time:** 0.012s (cepat)
- **Rows examined:** 15,163 rows
- **Rows sent:** 14 rows
- Query examine 15,163 rows untuk return 14 rows (tidak efisien!)

**Kemungkinan masalah:**
1. Tidak ada index di `purchase_requisitions.category_id`
2. EXISTS subquery scan semua purchase_requisitions untuk setiap kategori

---

## 🔧 **SOLUSI OPTIMASI**

### **A. Optimasi Query `purchase_requisition_history`**

#### **1. Check Indexes**

```sql
-- Login MySQL
mysql -u root -p
USE db_justus;

-- Check indexes untuk purchase_requisition_history
SHOW INDEXES FROM purchase_requisition_history;
```

#### **2. Tambah Composite Index**

```sql
-- Composite index untuk optimasi query
CREATE INDEX idx_pr_history_lookup ON purchase_requisition_history(
    purchase_requisition_id, 
    user_id, 
    action, 
    created_at DESC
);
```

**✅ HASIL: Index berhasil dibuat!**

Index `idx_pr_history_lookup` sudah dibuat dengan 4 kolom:
1. `purchase_requisition_id` (seq 1)
2. `user_id` (seq 2)
3. `action` (seq 3)
4. `created_at` (seq 4)

**Expected setelah tambah index:**
- `type` = `ref` (pakai index)
- `key` = `idx_pr_history_lookup`
- `rows` = < 10 (bukan 2,733)

#### **3. EXPLAIN Query (VERIFIKASI)**

```sql
EXPLAIN SELECT * FROM `purchase_requisition_history` 
WHERE `purchase_requisition_id` = 1150 
AND `user_id` = 26 
AND `action` = 'viewed' 
ORDER BY `created_at` DESC 
LIMIT 1;
```

**✅ Expected hasil EXPLAIN:**
- `type` = `ref` (pakai index)
- `key` = `idx_pr_history_lookup`
- `rows` = < 10 (bukan 2,733)
- `Extra` = `Using index condition` atau `Using where`

**Silakan jalankan EXPLAIN query di atas untuk verifikasi!**

---

### **B. Optimasi Query `tbl_data_divisi` dengan EXISTS**

#### **1. Check Indexes**

```sql
-- Check indexes untuk purchase_requisitions
SHOW INDEXES FROM purchase_requisitions;

-- Check indexes untuk tbl_data_divisi
SHOW INDEXES FROM tbl_data_divisi;
```

#### **2. Tambah Index**

```sql
-- Index untuk division_id di purchase_requisitions
CREATE INDEX idx_division_id ON purchase_requisitions(division_id);

-- Index untuk status dan deleted_at di tbl_data_divisi (jika belum ada)
CREATE INDEX idx_status_deleted ON tbl_data_divisi(status, deleted_at);
```

#### **3. Optimasi Query (Alternatif: Gunakan JOIN)**

```sql
-- Query yang lebih efisien dengan JOIN
SELECT DISTINCT d.* 
FROM tbl_data_divisi d
INNER JOIN purchase_requisitions pr ON d.id = pr.division_id
WHERE d.status = 'A' 
AND d.deleted_at IS NULL 
ORDER BY d.nama_divisi ASC;
```

**Expected setelah optimasi:**
- `type` = `ref` atau `eq_ref` (pakai index)
- `rows` = < 100 (bukan 9,054)

---

### **C. Optimasi Query `purchase_requisition_categories` dengan EXISTS**

#### **1. Check Indexes**

```sql
-- Check indexes untuk purchase_requisitions
SHOW INDEXES FROM purchase_requisitions;
```

#### **2. Tambah Index**

```sql
-- Index untuk category_id di purchase_requisitions
CREATE INDEX idx_category_id ON purchase_requisitions(category_id);
```

#### **3. Optimasi Query (Alternatif: Gunakan JOIN)**

```sql
-- Query yang lebih efisien dengan JOIN
SELECT DISTINCT c.* 
FROM purchase_requisition_categories c
INNER JOIN purchase_requisitions pr ON c.id = pr.category_id
ORDER BY c.name ASC;
```

**Expected setelah optimasi:**
- `type` = `ref` atau `eq_ref` (pakai index)
- `rows` = < 50 (bukan 15,163)

---

## 🚨 **PRIORITAS OPTIMASI**

| Query | Prioritas | Alasan |
|-------|-----------|--------|
| `purchase_requisition_history` | **HIGH** | Muncul berulang, examine 2,733 rows untuk 0 rows, sangat tidak efisien |
| `tbl_data_divisi` dengan EXISTS | **MEDIUM** | Examine 9,054 rows untuk 26 rows |
| `purchase_requisition_categories` dengan EXISTS | **MEDIUM** | Examine 15,163 rows untuk 14 rows |

---

## 📋 **COMMAND SQL LENGKAP**

```sql
-- Login MySQL
mysql -u root -p
USE db_justus;

-- ============================================
-- 1. OPTIMASI purchase_requisition_history
-- ============================================

-- Check indexes
SHOW INDEXES FROM purchase_requisition_history;

-- Tambah composite index
CREATE INDEX idx_pr_history_lookup ON purchase_requisition_history(
    purchase_requisition_id, 
    user_id, 
    action, 
    created_at DESC
);

-- EXPLAIN query
EXPLAIN SELECT * FROM `purchase_requisition_history` 
WHERE `purchase_requisition_id` = 1150 
AND `user_id` = 26 
AND `action` = 'viewed' 
ORDER BY `created_at` DESC 
LIMIT 1;

-- ============================================
-- 2. OPTIMASI purchase_requisitions (untuk EXISTS queries)
-- ============================================

-- Check indexes
SHOW INDEXES FROM purchase_requisitions;

-- Tambah index untuk division_id
CREATE INDEX idx_division_id ON purchase_requisitions(division_id);

-- Tambah index untuk category_id
CREATE INDEX idx_category_id ON purchase_requisitions(category_id);

-- ============================================
-- 3. OPTIMASI tbl_data_divisi
-- ============================================

-- Check indexes
SHOW INDEXES FROM tbl_data_divisi;

-- Tambah index untuk status dan deleted_at (jika belum ada)
CREATE INDEX idx_status_deleted ON tbl_data_divisi(status, deleted_at);

-- EXPLAIN query dengan EXISTS
EXPLAIN SELECT * FROM `tbl_data_divisi` 
WHERE EXISTS (
    SELECT * FROM `purchase_requisitions` 
    WHERE `tbl_data_divisi`.`id` = `purchase_requisitions`.`division_id`
) 
AND `status` = 'A' 
AND `tbl_data_divisi`.`deleted_at` IS NULL 
ORDER BY `nama_divisi` ASC;

-- ============================================
-- 4. OPTIMASI purchase_requisition_categories
-- ============================================

-- EXPLAIN query dengan EXISTS
EXPLAIN SELECT * FROM `purchase_requisition_categories` 
WHERE EXISTS (
    SELECT * FROM `purchase_requisitions` 
    WHERE `purchase_requisition_categories`.`id` = `purchase_requisitions`.`category_id`
) 
ORDER BY `name` ASC;
```

---

## 📊 **EXPECTED RESULTS SETELAH OPTIMASI**

### **1. Query `purchase_requisition_history`**

| Metric | Sebelum | Sesudah | Improvement |
|--------|---------|---------|-------------|
| **Rows examined** | 2,733 | < 10 | **99.6% lebih efisien!** |
| **Query time** | 0.002s | < 0.001s | Lebih cepat |
| **Type** | ALL atau ref (tidak efisien) | ref (pakai index) | ✅ Optimized |

### **2. Query `tbl_data_divisi`**

| Metric | Sebelum | Sesudah | Improvement |
|--------|---------|---------|-------------|
| **Rows examined** | 9,054 | < 100 | **98.9% lebih efisien!** |
| **Query time** | 0.008s | < 0.001s | Lebih cepat |
| **Type** | ALL (full scan) | ref (pakai index) | ✅ Optimized |

### **3. Query `purchase_requisition_categories`**

| Metric | Sebelum | Sesudah | Improvement |
|--------|---------|---------|-------------|
| **Rows examined** | 15,163 | < 50 | **99.7% lebih efisien!** |
| **Query time** | 0.012s | < 0.001s | Lebih cepat |
| **Type** | ALL (full scan) | ref (pakai index) | ✅ Optimized |

---

## ⚠️ **CATATAN PENTING**

1. **Query `purchase_requisition_history` adalah yang PALING BERMASALAH**
   - Muncul berulang untuk setiap PR di list
   - Examine 2,733 rows untuk 0 rows (sangat tidak efisien!)
   - **PRIORITAS TINGGI** untuk dioptimasi

2. **Query dengan EXISTS subquery juga bermasalah**
   - `tbl_data_divisi` dan `purchase_requisition_categories` menggunakan EXISTS
   - Bisa dioptimasi dengan menambah index atau menggunakan JOIN

3. **CPU 100% kemungkinan karena:**
   - Query `purchase_requisition_history` muncul berulang
   - Query dengan EXISTS scan banyak rows
   - Kombinasi query-query ini membuat CPU overload

---

## 🎯 **KESIMPULAN**

**Masalah utama:**
1. Query `purchase_requisition_history` examine 2,733 rows untuk 0 rows - **PALING BERMASALAH**
2. Query `tbl_data_divisi` dengan EXISTS examine 9,054 rows
3. Query `purchase_requisition_categories` dengan EXISTS examine 15,163 rows

**Solusi:**
1. **PRIORITAS TINGGI:** Tambah composite index di `purchase_requisition_history`
2. Tambah index di `purchase_requisitions.division_id` dan `purchase_requisitions.category_id`
3. Pertimbangkan optimasi query dengan JOIN untuk menggantikan EXISTS

**Status:** CPU 100% karena query-query ini yang tidak efisien. Optimasi dengan index akan mengurangi CPU usage secara signifikan.
