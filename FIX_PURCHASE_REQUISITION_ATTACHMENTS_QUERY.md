# 🔍 Analisis & Fix Query purchase_requisition_attachments

## ⚠️ **MASALAH YANG DITEMUKAN**

### **Query yang Muncul Berulang:**

```sql
SELECT pra.*, o.nama_outlet, u.nama_lengkap
FROM purchase_requisition_attachments pra
LEFT JOIN users u ON pra.uploaded_by = u.id
LEFT JOIN tbl_data_outlet o ON pra.outlet_id = o.id_outlet
WHERE pra.purchase_requisition_id = ?
```

**Masalah:**
- **Rows_examined: 214** (full table scan) untuk **Rows_sent: 0**
- Query examine semua rows di table (214 rows) tapi return 0 rows
- **Type: ALL** = Full table scan (tidak pakai index)
- **Key: (Null)** = Tidak ada index yang digunakan
- Muncul berulang untuk multiple purchase_requisition_id (1108, 1109, 1115, 1116, 1117, 1118, 1119, 1120, 1121, 1122)
- **Query time: 0.001s** (cepat, tapi examine semua rows - tidak efisien)

**Kemungkinan masalah:**
1. Tidak ada index di `purchase_requisition_id`
2. Query examine semua rows di table untuk cari yang tidak ada

---

## 🔍 **LANGKAH 1: Check Indexes**

### **A. Check Indexes yang Sudah Ada**

```sql
-- Login MySQL
mysql -u root -p
USE db_justus;

-- Check indexes untuk purchase_requisition_attachments
SHOW INDEXES FROM purchase_requisition_attachments;
```

**Hasil Check Indexes:**
- ✅ PRIMARY key pada `id`
- ✅ Index `idx_pr_attachments_outlet` pada `outlet_id`
- ❌ **TIDAK ADA index pada `purchase_requisition_id`** ← **MASALAHNYA!**

**Expected:** Harus ada index di `purchase_requisition_id`

---

## 🚀 **LANGKAH 2: Analisis dengan EXPLAIN**

### **A. EXPLAIN Query**

```sql
-- EXPLAIN query
EXPLAIN SELECT pra.*, o.nama_outlet, u.nama_lengkap
FROM purchase_requisition_attachments pra
LEFT JOIN users u ON pra.uploaded_by = u.id
LEFT JOIN tbl_data_outlet o ON pra.outlet_id = o.id_outlet
WHERE pra.purchase_requisition_id = 1108;
```

**Hasil EXPLAIN (SEBELUM TAMBAH INDEX):**

| Table | Type | Possible Keys | Key | Rows | Filtered | Extra |
|-------|------|---------------|-----|------|----------|-------|
| `pra` | **ALL** | **(Null)** | **(Null)** | **214** | 10.00 | Using where |
| `u` | eq_ref | PRIMARY | PRIMARY | 1 | 100.00 | Using where |
| `o` | eq_ref | PRIMARY | PRIMARY | 1 | 100.00 | Using where |

**Analisis:**
- ❌ **Table `pra`: `type: ALL`** = Full table scan (BURUK!)
- ❌ **`possible_keys: (Null)`** = Tidak ada index yang bisa digunakan
- ❌ **`key: (Null)`** = Tidak pakai index
- ❌ **`rows: 214`** = Examine semua rows di table (214 rows)
- ✅ **Table `u` dan `o`**: Pakai PRIMARY key (BAIK)

**Kesimpulan:** Perlu tambah index pada `purchase_requisition_id`!

---

## 🔧 **LANGKAH 3: Tambah Index (Jika Perlu)**

### **A. Tambah Index untuk purchase_requisition_id**

```sql
-- Check apakah sudah ada index
SHOW INDEXES FROM purchase_requisition_attachments;

-- Jika belum ada, tambah index
CREATE INDEX idx_purchase_requisition_id ON purchase_requisition_attachments(purchase_requisition_id);
```

### **B. Tambah Index untuk JOIN Columns (Jika Perlu)**

```sql
-- Index untuk uploaded_by (jika belum ada)
CREATE INDEX idx_uploaded_by ON purchase_requisition_attachments(uploaded_by);

-- Index untuk outlet_id (jika belum ada)
CREATE INDEX idx_outlet_id ON purchase_requisition_attachments(outlet_id);
```

---

## 📊 **ANALISIS QUERY**

### **A. Query Examine 214 Rows untuk Return 0 Rows**

**Kemungkinan:**
1. **Tidak ada index di `purchase_requisition_id`** ✅ **KONFIRMASI!**
   - Query scan semua rows (214 rows) untuk cari yang tidak ada
   - **Type: ALL** = Full table scan
   - **Key: (Null)** = Tidak pakai index
   - Solusi: Tambah index

2. **Query dipanggil untuk PR yang tidak punya attachment**
   - Normal jika PR tidak punya attachment
   - Tapi query tetap examine semua rows (214 rows) - tidak efisien
   - Solusi: Tambah index agar query hanya examine beberapa rows

### **B. Query Muncul Berulang**

Query muncul untuk multiple purchase_requisition_id:
- 1108, 1109, 1115, 1116, 1117, 1118, 1119, 1120, 1121, 1122

**Kemungkinan:**
- Query dipanggil untuk setiap PR di list/dashboard
- Jika tidak ada index, setiap query examine 214 rows (full table scan)
- Total: 10 queries × 214 rows = 2,140 rows examined (tidak efisien!)

**Solusi:** Tambah index agar setiap query hanya examine beberapa rows (< 10 rows).

---

## 🔧 **SOLUSI CEPAT**

### **A. Check Indexes**

```sql
-- Login MySQL
mysql -u root -p
USE db_justus;

-- Check indexes
SHOW INDEXES FROM purchase_requisition_attachments;
```

### **B. Tambah Index (Jika Belum Ada)**

```sql
-- Tambah index untuk purchase_requisition_id
CREATE INDEX idx_purchase_requisition_id ON purchase_requisition_attachments(purchase_requisition_id);

-- Verifikasi
SHOW INDEXES FROM purchase_requisition_attachments;
```

### **C. Test Query Setelah Tambah Index**

```sql
-- EXPLAIN query setelah tambah index
EXPLAIN SELECT pra.*, o.nama_outlet, u.nama_lengkap
FROM purchase_requisition_attachments pra
LEFT JOIN users u ON pra.uploaded_by = u.id
LEFT JOIN tbl_data_outlet o ON pra.outlet_id = o.id_outlet
WHERE pra.purchase_requisition_id = 1108;
```

**✅ HASIL SETELAH TAMBAH INDEX (AKTUAL):**

| Table | Type | Possible Keys | Key | Rows | Filtered | Extra |
|-------|------|---------------|-----|------|----------|-------|
| `pra` | **ref** | **idx_purchase_requisition_ic** | **idx_purcha** | **1** | 100.00 | (Null) |
| `u` | eq_ref | PRIMARY, indx_id, indx_c1 | PRIMARY | 1 | 100.00 | Using where |
| `o` | eq_ref | PRIMARY, indx_id_outlet | PRIMARY | 1 | 100.00 | Using where |

**✅ Hasil Aktual:**
- ✅ `type` = `ref` (pakai index) - **BERHASIL!**
- ✅ `key` = `idx_purcha` (idx_purchase_requisition_id) - **BERHASIL!**
- ✅ `rows` = **1** (bukan 214) - **SANGAT EFEKTIF!**

**🎉 OPTIMASI BERHASIL!**
- **Sebelum:** `type: ALL`, `key: (Null)`, `rows: 214` (full table scan)
- **Sesudah:** `type: ref`, `key: idx_purcha`, `rows: 1` (pakai index, sangat efisien!)
- **Improvement:** 214 rows → 1 row = **99.5% lebih efisien!**

---

## 📊 **HASIL AKTUAL SETELAH OPTIMASI**

Setelah tambah index:

| Metric | Sebelum | Sesudah | Improvement |
|--------|---------|---------|-------------|
| **Rows_examined** | 214 (full scan) | **1** (pakai index) | **99.5% lebih efisien!** |
| **Query time** | 0.001s | < 0.001s | Lebih cepat |
| **Type** | ALL (full scan) | **ref** (pakai index) | ✅ Optimized |
| **Key** | NULL | **idx_purcha** | ✅ Pakai index |
| **Possible Keys** | (Null) | idx_purchase_requisition_ic | ✅ Index tersedia |

**🎉 OPTIMASI SANGAT BERHASIL!**
- Query sekarang hanya examine **1 row** (bukan 214 rows)
- **99.5% lebih efisien** dari sebelumnya
- Index berfungsi dengan sempurna

---

## ⚠️ **CATATAN**

1. **Query ini cepat (0.001s)** - tidak urgent, tapi bisa dioptimasi
2. **Examine 214 rows (full table scan) untuk 0 rows** - tidak efisien, perlu index
3. **Type: ALL, Key: (Null)** - konfirmasi tidak ada index yang digunakan
4. **Muncul berulang** - jika ada 100 PR, total examine 21,400 rows (tidak efisien!)
5. **Tambah index** - akan membuat query lebih efisien (rows < 10, type: ref)

---

## 🎯 **PRIORITAS**

**Prioritas: MEDIUM** (tidak urgent, tapi bisa dioptimasi)

**Alasan:**
- Query sudah cepat (0.001s)
- Tapi examine semua rows (214 rows, full table scan) untuk return 0 rows
- **Type: ALL, Key: (Null)** = Tidak pakai index
- Muncul berulang, jadi total rows examined bisa besar (10 queries × 214 = 2,140 rows)
- Optimasi dengan index akan membuat lebih efisien (rows < 10, type: ref)

---

## 📋 **COMMAND CEPAT**

```sql
-- 1. Check indexes
SHOW INDEXES FROM purchase_requisition_attachments;

-- 2. Tambah index (jika belum ada)
CREATE INDEX idx_purchase_requisition_id ON purchase_requisition_attachments(purchase_requisition_id);

-- 3. EXPLAIN query
EXPLAIN SELECT pra.*, o.nama_outlet, u.nama_lengkap
FROM purchase_requisition_attachments pra
LEFT JOIN users u ON pra.uploaded_by = u.id
LEFT JOIN tbl_data_outlet o ON pra.outlet_id = o.id_outlet
WHERE pra.purchase_requisition_id = 1108;
```

---

## ✅ **KESIMPULAN**

**🎉 OPTIMASI BERHASIL DILAKUKAN!**

**Hasil:**
- ✅ Index `idx_purchase_requisition_id` berhasil ditambahkan
- ✅ Query sekarang pakai index (`type: ref`, `key: idx_purcha`)
- ✅ Rows examined: **214 → 1** (99.5% lebih efisien!)
- ✅ Query lebih cepat dan efisien

**Dampak:**
- Query yang muncul berulang (10+ queries) sekarang sangat efisien
- Total rows examined: 2,140 → 10 rows (99.5% improvement!)
- Server load berkurang, performa lebih baik

**Status: ✅ SELESAI - Query sudah dioptimasi dengan sempurna!**
