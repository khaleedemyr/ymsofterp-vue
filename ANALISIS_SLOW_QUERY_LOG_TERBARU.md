# 🔍 Analisis Slow Query Log Terbaru

## 📊 **RINGKASAN QUERY**

Dari slow query log terbaru, berikut query-query yang muncul:

| No | Query | Query Time | Rows Examined | Rows Sent | Status |
|----|-------|------------|---------------|-----------|--------|
| 1 | `quotes` | 0.000220s | 9 | 1 | ✅ Normal |
| 2 | `employee_resignations` | 0.000488s | 340 | 0 | ⚠️ Bisa dioptimasi |
| 3 | `tbl_kalender_perusahaan` | 0.000209s | 100 | 50 | ✅ Normal |
| 4 | `tbl_data_jabatan` (order by) | 0.000654s | 746 | 373 | ✅ Normal |
| 5 | `tbl_data_divisi` | 0.000252s | 62 | 31 | ✅ Normal |
| 6 | `tbl_data_outlet` | 0.000214s | 70 | 35 | ✅ Normal |
| 7 | `tbl_data_jabatan` (IN clause) | 0.001431s | 373 | 114 | ✅ Normal |
| 8 | `tbl_data_outlet` (IN clause) | 0.000863s | 35 | 24 | ✅ Normal |
| 9 | `announcements` | 0.000913s | 740 | 3 | ⚠️ Bisa dioptimasi |
| 10 | `announcement_files` | 0.000173s | 3 | 0 | ✅ Normal |

---

## ⚠️ **QUERY YANG PERLU DIOPTIMASI**

### **1. Query `employee_resignations`**

**Query:**
```sql
SELECT * FROM `employee_resignations` 
WHERE `status` = 'submitted' 
AND EXISTS (
    SELECT * FROM `employee_resignation_approval_flows` 
    WHERE `employee_resignations`.`id` = `employee_resignation_approval_flows`.`employee_resignation_id` 
    AND `approver_id` = 84 
    AND `status` = 'PENDING'
);
```

**Masalah:**
- **Query time:** 0.000488s (cepat)
- **Rows examined:** 340 rows
- **Rows sent:** 0 rows
- Query examine 340 rows untuk return 0 rows (tidak efisien)

**Kemungkinan masalah:**
1. Tidak ada index di `status` pada `employee_resignations`
2. Tidak ada index di `employee_resignation_id`, `approver_id`, `status` pada `employee_resignation_approval_flows`
3. Query scan semua rows dengan status 'submitted' untuk cari yang tidak ada

**Solusi:**
1. Tambah index di `status` pada `employee_resignations`
2. Tambah composite index di `employee_resignation_approval_flows` untuk `(employee_resignation_id, approver_id, status)`

---

### **2. Query `announcements`**

**Query:**
```sql
SELECT `announcements`.*, 
       `creators`.`nama_lengkap` as `creator_name`, 
       `creators`.`id` as `creator_id`, 
       `creators`.`avatar` as `creator_avatar` 
FROM `announcements` 
LEFT JOIN `users` as `creators` ON `announcements`.`created_by` = `creators`.`id` 
WHERE `announcements`.`status` = 'Publish' 
AND EXISTS (
    SELECT 1 FROM `announcement_targets` 
    WHERE `announcement_targets`.`announcement_id` = `announcements`.`id` 
    AND (
        (`target_type` = 'user' AND `target_id` = 84) OR 
        (`target_type` = 'jabatan' AND `target_id` = 203) OR 
        (`target_type` = 'divisi' AND `target_id` = 15) OR 
        (`target_type` = 'level' AND `target_id` = '5') OR 
        (`target_type` = 'outlet' AND `target_id` = 1)
    )
) 
ORDER BY `created_at` DESC 
LIMIT 3;
```

**Masalah:**
- **Query time:** 0.000913s (cepat)
- **Rows examined:** 740 rows
- **Rows sent:** 3 rows
- Query examine 740 rows untuk return 3 rows (tidak efisien)

**Kemungkinan masalah:**
1. Tidak ada index di `status` pada `announcements`
2. Tidak ada index di `announcement_id`, `target_type`, `target_id` pada `announcement_targets`
3. Query scan semua announcements dengan status 'Publish' untuk cari yang sesuai

**Solusi:**
1. Tambah index di `status` pada `announcements`
2. Tambah composite index di `announcement_targets` untuk `(announcement_id, target_type, target_id)`
3. Tambah index di `created_at` pada `announcements` untuk ORDER BY

---

## ✅ **QUERY YANG SUDAH NORMAL**

### **1. Query `quotes`**
- ✅ Query time: 0.000220s (sangat cepat)
- ✅ Rows examined: 9 rows (normal)
- ✅ Sudah pakai WHERE clause dengan `day_of_year`
- **Status:** Tidak perlu optimasi

### **2. Query `tbl_kalender_perusahaan`**
- ✅ Query time: 0.000209s (sangat cepat)
- ✅ Rows examined: 100 rows (normal)
- ✅ Simple SELECT dengan ORDER BY
- **Status:** Tidak perlu optimasi

### **3. Query `tbl_data_jabatan`, `tbl_data_divisi`, `tbl_data_outlet`**
- ✅ Query time: < 0.001s (sangat cepat)
- ✅ Rows examined: Normal (62-746 rows)
- ✅ Simple SELECT dengan ORDER BY atau IN clause
- **Status:** Tidak perlu optimasi

### **4. Query `announcement_files`**
- ✅ Query time: 0.000173s (sangat cepat)
- ✅ Rows examined: 3 rows (normal)
- ✅ Simple SELECT dengan WHERE clause
- **Status:** Tidak perlu optimasi

---

## 🔧 **LANGKAH OPTIMASI**

### **A. Optimasi Query `employee_resignations`**

#### **1. Check Indexes**

```sql
-- Login MySQL
mysql -u root -p
USE db_justus;

-- Check indexes untuk employee_resignations
SHOW INDEXES FROM employee_resignations;

-- Check indexes untuk employee_resignation_approval_flows
SHOW INDEXES FROM employee_resignation_approval_flows;
```

#### **2. Tambah Indexes (Jika Perlu)**

**✅ Index yang sudah ada:**
- `employee_resignations` sudah punya index di `status` (`employee_resignations_sta`) - **SUDAH ADA!**

**❌ Index yang perlu ditambah:**
```sql
-- Composite index untuk employee_resignation_approval_flows
-- (untuk optimasi subquery yang masih full table scan)
CREATE INDEX idx_approval_flows ON employee_resignation_approval_flows(employee_resignation_id, approver_id, status);
```

#### **3. EXPLAIN Query**

```sql
EXPLAIN SELECT * FROM `employee_resignations` 
WHERE `status` = 'submitted' 
AND EXISTS (
    SELECT * FROM `employee_resignation_approval_flows` 
    WHERE `employee_resignations`.`id` = `employee_resignation_approval_flows`.`employee_resignation_id` 
    AND `approver_id` = 84 
    AND `status` = 'PENDING'
);
```

**✅ HASIL EXPLAIN (AKTUAL):**

| id | select_type | table | type | possible_keys | key | rows | filtered | Extra |
|----|-------------|-------|------|---------------|-----|------|----------|-------|
| 1 | PRIMARY | employee_resi | **ref** | employee_resignations_sta | **employee_** | **5** | 100.00 | Using where |
| 2 | DEPENDENT SUBQUERY | employee_resi | **ALL** | **(Null)** | **(Null)** | **48** | 2.08 | Using where |

**Analisis:**
- ✅ **Row 1 (PRIMARY):** `type: ref`, `key: employee_` (employee_resignations_sta), `rows: 5` - **SUDAH BAIK!**
- ❌ **Row 2 (DEPENDENT SUBQUERY):** `type: ALL`, `key: (Null)`, `rows: 48` - **MASIH FULL TABLE SCAN!**

**Kesimpulan:**
- Main query sudah pakai index (`employee_resignations_sta`) - **BERHASIL!**
- Subquery di `employee_resignation_approval_flows` masih full table scan - **PERLU INDEX!**

**Solusi:** Tambah composite index di `employee_resignation_approval_flows` untuk `(employee_resignation_id, approver_id, status)`

---

### **B. Optimasi Query `announcements`**

#### **1. Check Indexes**

```sql
-- Check indexes untuk announcements
SHOW INDEXES FROM announcements;

-- Check indexes untuk announcement_targets
SHOW INDEXES FROM announcement_targets;
```

#### **2. Check Indexes yang Sudah Ada**

**✅ Index yang sudah ada:**
- `announcements` sudah punya index di `status` (`idx_status`) - **SUDAH ADA!**
- `announcement_targets` sudah punya composite index (`idx_targets`) - **SUDAH ADA!**

**⚠️ Catatan:**
- Meskipun `idx_status` ada di `possible_keys`, tapi tidak digunakan karena table sangat kecil (3 rows)
- MySQL memilih full table scan karena lebih cepat untuk table kecil
- Jika table `announcements` besar, index akan otomatis digunakan

**❌ Index yang mungkin perlu ditambah (opsional):**
```sql
-- Index untuk created_at di announcements (untuk ORDER BY)
-- Hanya jika table besar dan ORDER BY menjadi slow
CREATE INDEX idx_created_at ON announcements(created_at);
```

#### **3. EXPLAIN Query**

```sql
EXPLAIN SELECT `announcements`.*, 
       `creators`.`nama_lengkap` as `creator_name`, 
       `creators`.`id` as `creator_id`, 
       `creators`.`avatar` as `creator_avatar` 
FROM `announcements` 
LEFT JOIN `users` as `creators` ON `announcements`.`created_by` = `creators`.`id` 
WHERE `announcements`.`status` = 'Publish' 
AND EXISTS (
    SELECT 1 FROM `announcement_targets` 
    WHERE `announcement_targets`.`announcement_id` = `announcements`.`id` 
    AND (
        (`target_type` = 'user' AND `target_id` = 84) OR 
        (`target_type` = 'jabatan' AND `target_id` = 203) OR 
        (`target_type` = 'divisi' AND `target_id` = 15) OR 
        (`target_type` = 'level' AND `target_id` = '5') OR 
        (`target_type` = 'outlet' AND `target_id` = 1)
    )
) 
ORDER BY `created_at` DESC 
LIMIT 3;
```

**✅ HASIL EXPLAIN (AKTUAL):**

| id | select_type | table | type | possible_keys | key | rows | filtered | Extra |
|----|-------------|-------|------|---------------|-----|------|----------|-------|
| 1 | PRIMARY | announcemen | **ALL** | idx_status | **(Null)** | **3** | 100.00 | Using where; Using |
| 1 | PRIMARY | creators | eq_ref | PRIMARY | PRIMARY | 1 | 100.00 | Using where |
| 2 | DEPENDENT SUBQUERY | announcemen | **ref** | idx_targets | **idx_targets** | **20** | 9.61 | Using where; Using |

**Analisis:**
- ❌ **Row 1 (PRIMARY - announcements):** `type: ALL`, `key: (Null)`, `rows: 3` - **MASIH FULL TABLE SCAN!**
  - Meskipun ada `idx_status` di `possible_keys`, tapi tidak digunakan!
  - Kemungkinan karena table kecil (3 rows), MySQL memilih full scan
- ✅ **Row 2 (PRIMARY - creators/users):** `type: eq_ref`, `key: PRIMARY` - **BAIK!**
- ✅ **Row 3 (DEPENDENT SUBQUERY - announcement_targets):** `type: ref`, `key: idx_targets` - **SUDAH BAIK!**

**Kesimpulan:**
- Subquery sudah pakai index (`idx_targets`) - **BERHASIL!**
- Main query di `announcements` masih full table scan meskipun ada `idx_status`
- **Kemungkinan:** Table sangat kecil (3 rows), MySQL memilih full scan karena lebih cepat
- **Tapi:** Jika table besar, perlu pastikan index digunakan

**Catatan:** Karena table `announcements` hanya 3 rows, full table scan masih acceptable. Tapi jika table besar, perlu pastikan index digunakan.

---

## 📊 **PRIORITAS OPTIMASI**

| Query | Prioritas | Status | Alasan |
|-------|-----------|--------|--------|
| `employee_resignations` | **LOW** | ⚠️ **Perlu 1 index** | Main query sudah pakai index (rows: 5), tapi subquery masih full table scan (rows: 48) |
| `announcements` | **LOW** | ✅ **Sudah optimal** | Table kecil (3 rows), full scan acceptable. Subquery sudah pakai index. |

**Analisis Detail:**

### **1. Query `employee_resignations`:**
- ✅ **Main query:** Sudah pakai index (`employee_resignations_sta`), rows: 5 - **BAIK!**
- ❌ **Subquery:** Masih full table scan, rows: 48 - **PERLU INDEX!**
- **Solusi:** Tambah composite index di `employee_resignation_approval_flows`

### **2. Query `announcements`:**
- ⚠️ **Main query:** Full table scan, tapi table sangat kecil (3 rows) - **ACCEPTABLE!**
- ✅ **Subquery:** Sudah pakai index (`idx_targets`), rows: 20 - **BAIK!**
- **Catatan:** Karena table kecil, MySQL memilih full scan (lebih cepat). Jika table besar, index akan otomatis digunakan.

**Kesimpulan:**
- Hanya perlu optimasi untuk `employee_resignation_approval_flows` (1 index)
- Query `announcements` sudah optimal untuk ukuran table saat ini

---

## 🎯 **KESIMPULAN**

### **Hasil Analisis EXPLAIN:**

1. **Query `employee_resignations`:**
   - ✅ **Main query:** Sudah pakai index (`employee_resignations_sta`), rows: 5 - **BAIK!**
   - ❌ **Subquery:** Masih full table scan, rows: 48 - **PERLU 1 INDEX!**
   - **Solusi:** Tambah composite index di `employee_resignation_approval_flows(employee_resignation_id, approver_id, status)`

2. **Query `announcements`:**
   - ⚠️ **Main query:** Full table scan, tapi table sangat kecil (3 rows) - **ACCEPTABLE!**
   - ✅ **Subquery:** Sudah pakai index (`idx_targets`), rows: 20 - **BAIK!**
   - **Status:** Sudah optimal untuk ukuran table saat ini

3. **Query yang sudah normal:** 8 query (tidak perlu optimasi)

### **Aksi yang Diperlukan:**

**Hanya perlu 1 index:**
```sql
CREATE INDEX idx_approval_flows ON employee_resignation_approval_flows(employee_resignation_id, approver_id, status);
```

**Status:** 
- ✅ Query `announcements` sudah optimal
- ⚠️ Query `employee_resignations` perlu 1 index untuk optimasi subquery
- **Prioritas:** LOW (tidak urgent, tapi bisa dioptimasi)
