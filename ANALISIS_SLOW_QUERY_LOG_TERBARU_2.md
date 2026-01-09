# 🔥 Analisis Slow Query Log Terbaru - CPU 100%

## 🔥 **PENYEBAB UTAMA CPU 100%**

### **Query `att_log` dengan DATE() - CRITICAL!**

**Query time:** 1.759s dan 1.483s  
**Rows examined:** 683,918 rows  
**Status:** PENYEBAB UTAMA CPU 100%!

Query ini muncul berulang dan examine 683,918 rows setiap kali. Ini adalah penyebab utama CPU 100%.

---

## ⚠️ **QUERY YANG BERMASALAH**

### **1. Query `member_apps_members` dengan LOWER(TRIM(member_id)) - PALING BERMASALAH!**

**Query:**
```sql
SELECT * FROM `member_apps_members` 
WHERE LOWER(TRIM(member_id)) = 'u10729' 
LIMIT 1;
```

**Masalah:**
- **Query time:** 0.148s (lambat!)
- **Rows examined:** 64,742 rows
- **Rows sent:** 1 row
- Query examine 64,742 rows untuk return 1 row (sangat tidak efisien!)

**Kemungkinan masalah:**
1. Tidak ada index di `member_id` column
2. Fungsi `LOWER(TRIM(member_id))` membuat index tidak bisa digunakan (function-based)
3. Query scan semua rows (full table scan)

**Solusi:**
1. Tambah index di `member_id` column
2. Ubah query untuk tidak menggunakan `LOWER(TRIM())` atau gunakan generated column

---

### **2. Query `member_apps_members` dengan LOWER(TRIM(email)) - BERMASALAH!**

**Query:**
```sql
SELECT * FROM `member_apps_members` 
WHERE LOWER(TRIM(email)) = 'bellasulindra279@gmail.com' 
LIMIT 1;
```

**Masalah:**
- **Query time:** 0.174s (lambat!)
- **Rows examined:** 92,555 rows
- **Rows sent:** 0 rows
- Query examine 92,555 rows untuk return 0 rows (sangat tidak efisien!)

**Kemungkinan masalah:**
1. Tidak ada index di `email` column
2. Fungsi `LOWER(TRIM(email))` membuat index tidak bisa digunakan (function-based)
3. Query scan semua rows (full table scan)

**Solusi:**
1. Tambah index di `email` column
2. Ubah query untuk tidak menggunakan `LOWER(TRIM())` atau gunakan generated column

---

### **2. Query `order_payment` DELETE - SANGAT BERMASALAH!**

**Query:**
```sql
DELETE FROM `order_payment` 
WHERE `order_id` = 'BTRTEMP26010436';
```

**Masalah:**
- **Query time:** 0.251s (lambat!)
- **Rows examined:** 118,364 rows
- **Rows sent:** 0 rows
- Query examine 118,364 rows untuk delete (sangat tidak efisien!)

**Kemungkinan masalah:**
1. Tidak ada index di `order_id` column
2. Query scan semua rows untuk cari yang akan di-delete

**Solusi:**
1. Tambah index di `order_id` column

---

### **3. Query `order_promos` DELETE - BERMASALAH**

**Query:**
```sql
DELETE FROM `order_promos` 
WHERE `order_id` = 'BTRTEMP26010436';
```

**Masalah:**
- **Query time:** 0.038s (cepat, tapi examine banyak rows)
- **Rows examined:** 31,564 rows
- **Rows sent:** 0 rows
- Query examine 31,564 rows untuk delete (tidak efisien)

**Kemungkinan masalah:**
1. Tidak ada index di `order_id` column
2. Query scan banyak rows untuk cari yang akan di-delete

**Solusi:**
1. Tambah index di `order_id` column

---

### **5. Query `purchase_requisitions` dengan EXISTS - BISA DIOPTIMASI**

**Query:**
```sql
SELECT * FROM `purchase_requisitions` 
WHERE `status` = 'SUBMITTED' 
AND EXISTS (
    SELECT * FROM `purchase_requisition_approval_flows` 
    WHERE `purchase_requisitions`.`id` = `purchase_requisition_approval_flows`.`purchase_requisition_id` 
    AND `approver_id` = 909 
    AND `status` = 'PENDING'
) 
ORDER BY `created_at` DESC;
```

**Masalah:**
- **Query time:** 0.001s (cepat)
- **Rows examined:** 985 rows
- **Rows sent:** 0 rows
- Query examine 985 rows untuk 0 rows (tidak efisien, tapi masih cepat)

**Kemungkinan masalah:**
1. Tidak ada composite index di `purchase_requisition_approval_flows` untuk optimasi EXISTS

**Solusi:**
1. Tambah composite index di `purchase_requisition_approval_flows`

---

### **7. Query `purchase_order_ops_items` dengan SUM - SANGAT BERMASALAH!**

**Query:**
```sql
SELECT SUM(`poi`.`total`) as aggregate 
FROM `purchase_order_ops_items` as `poi` 
LEFT JOIN `purchase_order_ops` as `poo` ON `poi`.`purchase_order_ops_id` = `poo`.`id` 
LEFT JOIN `purchase_requisitions` as `pr` ON `poi`.`source_id` = `pr`.`id` 
LEFT JOIN `purchase_requisition_items` as `pri` ON `pr`.`id` = `pri`.`purchase_requisition_id` 
AND (`poi`.`pr_ops_item_id` = `pri`.`id` 
OR (`poi`.`pr_ops_item_id` IS NULL 
AND `poi`.`item_name` = `pri`.`item_name` 
AND `pri`.`category_id` = 21 
AND pri.id = (
    SELECT MIN(pri2.id) 
    FROM purchase_requisition_items as pri2 
    WHERE pri2.purchase_requisition_id = pri.purchase_requisition_id 
    ...
)));
```

**Masalah:**
- **Query time:** 0.067s (lambat!)
- **Rows examined:** 76,756 rows
- **Rows sent:** 0 rows
- Query examine 76,756 rows untuk return 0 rows (sangat tidak efisien!)
- **Muncul berulang** (2x dalam log)

**Kemungkinan masalah:**
1. Tidak ada index di `purchase_order_ops_items.purchase_order_ops_id`
2. Tidak ada index di `purchase_order_ops_items.source_id`
3. Tidak ada index di `purchase_requisition_items.purchase_requisition_id`
4. Complex JOIN dengan subquery membuat query sangat lambat

**Solusi:**
1. Tambah index di kolom-kolom yang digunakan untuk JOIN
2. Optimasi query dengan mengurangi kompleksitas JOIN

---

### **8. Query `purchase_requisitions` dengan COUNT - BISA DIOPTIMASI**

**Query:**
```sql
SELECT COUNT(*) as aggregate 
FROM `purchase_requisitions` 
WHERE `created_by` = 2945 
AND `status` = 'SUBMITTED';
```

**Masalah:**
- **Query time:** 0.0007s (cepat)
- **Rows examined:** 985 rows
- **Rows sent:** 1 row
- Query examine 985 rows untuk return 1 row (tidak efisien, tapi masih cepat)

**Kemungkinan masalah:**
1. Tidak ada composite index di `(created_by, status)`

**Solusi:**
1. Tambah composite index di `purchase_requisitions(created_by, status)`

---

### **9. Query `att_log` dengan JOIN dan DATE() - SANGAT BERMASALAH!**

**Query:**
```sql
SELECT `a`.`scan_date`, `a`.`inoutmode`, `u`.`id` as `user_id`, `u`.`nama_lengkap`, 
       `o`.`id_outlet`, `o`.`nama_outlet` 
FROM `att_log` as `a` 
INNER JOIN `tbl_data_outlet` as `o` ON `a`.`sn` = `o`.`sn` 
INNER JOIN `user_pins` as `up` ON `a`.`pin` = `up`.`pin` AND `o`.`id_outlet` = `up`.`outlet_id` 
INNER JOIN `users` as `u` ON `up`.`user_id` = `u`.`id` 
WHERE `u`.`id` = 1933 
AND DATE(a.scan_date) BETWEEN '2025-10-26' AND '2025-11-25' 
ORDER BY `a`.`scan_date` ASC;
```

**Masalah:**
- **Query time:** 1.759s dan 1.483s (SANGAT LAMBAT!)
- **Rows examined:** 683,918 rows (SANGAT BANYAK!)
- **Rows sent:** 49 rows
- Query examine 683,918 rows untuk return 49 rows (sangat tidak efisien!)
- **Muncul berulang** (2x dalam log dengan query time berbeda)
- Fungsi `DATE(a.scan_date)` membuat index tidak bisa digunakan
- **INI ADALAH PENYEBAB UTAMA CPU 100%!**

**Kemungkinan masalah:**
1. Tidak ada index di `att_log.scan_date`
2. Fungsi `DATE()` membuat index tidak bisa digunakan → **FULL TABLE SCAN 683,918 ROWS!**
3. Tidak ada index di `att_log.sn` dan `att_log.pin`
4. Table `att_log` sangat besar (683k+ rows)

**Solusi URGENT:**
1. **PRIORITAS TINGGI:** Ubah query untuk tidak menggunakan `DATE()` function
2. Tambah index di `att_log(scan_date, sn, pin)`
3. Pertimbangkan partisi table `att_log` berdasarkan tanggal jika data sangat besar

---

### **10. Query `food_inventory_cost_histories` - BERMASALAH**

**Query:**
```sql
SELECT * FROM `food_inventory_cost_histories` 
WHERE `inventory_item_id` = 4655 
AND `warehouse_id` = 1 
ORDER BY `date` DESC, `created_at` DESC 
LIMIT 1;
```

**Masalah:**
- **Query time:** 0.038s (lambat)
- **Rows examined:** 24,331 rows
- **Rows sent:** 1 row
- Query examine 24,331 rows untuk return 1 row (tidak efisien)

**Kemungkinan masalah:**
1. Tidak ada composite index di `(inventory_item_id, warehouse_id, date, created_at)`

**Solusi:**
1. Tambah composite index di `food_inventory_cost_histories(inventory_item_id, warehouse_id, date DESC, created_at DESC)`

---

### **11. Query `item_availabilities` dengan LIKE - BISA DIOPTIMASI**

**Query:**
```sql
SELECT DISTINCT `items`.`id`, `items`.`name`, `items`.`sku`, ... 
FROM `item_availabilities` 
INNER JOIN `items` ON `item_availabilities`.`item_id` = `items`.`id` 
... 
WHERE `items`.`status` = 'active' 
AND (`item_availabilities`.`availability_type` = 'all' 
     OR (`item_availabilities`.`availability_type` = 'region' AND `item_availabilities`.`region_id` = '5') 
     OR (`item_availabilities`.`availability_type` = 'outlet' AND `item_availabilities`.`outlet_id` = '8')) 
AND (`items`.`name` LIKE '%La%' OR `items`.`sku` LIKE '%La%');
```

**Masalah:**
- **Query time:** 0.009s (cepat)
- **Rows examined:** 3,934 rows
- **Rows sent:** 213 rows
- Query examine 3,934 rows untuk return 213 rows (tidak efisien, tapi masih cepat)
- LIKE dengan wildcard di awal (`%La%`) membuat index tidak bisa digunakan

**Kemungkinan masalah:**
1. LIKE dengan wildcard di awal tidak bisa pakai index
2. Tidak ada full-text index untuk search

**Solusi:**
1. Pertimbangkan full-text index untuk `items.name` dan `items.sku`
2. Atau gunakan search engine seperti Elasticsearch untuk search yang lebih kompleks

---

### **10. Query `purchase_order_ops` dengan EXISTS - MASIH CEPAT**

**Query:**
```sql
SELECT * FROM `purchase_order_ops` 
WHERE `status` NOT IN ('rejected', 'approved', 'received', 'cancelled') 
AND NOT EXISTS (
    SELECT * FROM `purchase_order_ops_approval_flows` 
    WHERE `purchase_order_ops`.`id` = `purchase_order_ops_approval_flows`.`purchase_order_ops_id` 
    AND `status` = 'REJECTED'
) 
AND EXISTS (
    SELECT * FROM `purchase_order_ops_approval_flows` 
    WHERE `purchase_order_ops`.`id` = `purchase_order_ops_approval_flows`.`purchase_order_ops_id` 
    AND `approver_id` = 1285 
    AND `status` = 'PENDING'
);
```

**Masalah:**
- **Query time:** 0.001s (cepat)
- **Rows examined:** 408 rows
- **Rows sent:** 0 rows
- Query examine 408 rows untuk 0 rows (tidak efisien, tapi masih cepat)

**Kemungkinan masalah:**
1. Tidak ada index di `purchase_order_ops_approval_flows` untuk optimasi EXISTS

**Solusi:**
1. Tambah composite index di `purchase_order_ops_approval_flows`

---

## 🔧 **SOLUSI OPTIMASI**

### **A. Optimasi Query `member_apps_members` dengan LOWER(TRIM(member_id))**

#### **1. Check Indexes**

```sql
-- Login MySQL
mysql -u root -p
USE db_justus;

-- Check indexes untuk member_apps_members
SHOW INDEXES FROM member_apps_members;
```

#### **2. Tambah Index dan Optimasi Query**

**Opsi 1: Tambah Index di `member_id` (jika belum ada)**

```sql
-- Tambah index di member_id
CREATE INDEX idx_member_id ON member_apps_members(member_id);
```

**Opsi 2: Ubah Query untuk Tidak Menggunakan Function**

Query saat ini:
```sql
WHERE LOWER(TRIM(member_id)) = 'u10729'
```

Query yang lebih efisien (jika member_id sudah normalized):
```sql
WHERE member_id = 'u10729'
```

Atau jika perlu case-insensitive:
```sql
WHERE member_id = 'u10729' COLLATE utf8mb4_general_ci
```

**Opsi 3: Gunakan Generated Column (MySQL 5.7+) - RECOMMENDED**

```sql
-- Tambah generated column untuk member_id normalized
ALTER TABLE member_apps_members 
ADD COLUMN member_id_normalized VARCHAR(255) 
GENERATED ALWAYS AS (LOWER(TRIM(member_id))) STORED;

-- Tambah index di generated column
CREATE INDEX idx_member_id_normalized ON member_apps_members(member_id_normalized);

-- Ubah query untuk menggunakan generated column
SELECT * FROM member_apps_members 
WHERE member_id_normalized = 'u10729' 
LIMIT 1;
```

#### **3. EXPLAIN Query**

```sql
EXPLAIN SELECT * FROM `member_apps_members` 
WHERE LOWER(TRIM(member_id)) = 'u10729' 
LIMIT 1;
```

**Expected setelah optimasi:**
- `type` = `ref` atau `eq_ref` (pakai index)
- `key` = nama index yang digunakan
- `rows` = < 10 (bukan 64,742)

---

### **B. Optimasi Query `member_apps_members` dengan LOWER(TRIM(email))**

#### **1. Check Indexes**

```sql
-- Check indexes untuk member_apps_members
SHOW INDEXES FROM member_apps_members;
```

#### **2. Tambah Index dan Optimasi Query**

**Opsi 1: Tambah Index di `email` (jika belum ada)**

```sql
-- Tambah index di email
CREATE INDEX idx_email ON member_apps_members(email);
```

**Opsi 2: Ubah Query untuk Tidak Menggunakan Function**

Query saat ini:
```sql
WHERE LOWER(TRIM(email)) = 'bellasulindra279@gmail.com'
```

Query yang lebih efisien (jika email sudah normalized):
```sql
WHERE email = 'bellasulindra279@gmail.com'
```

Atau jika perlu case-insensitive:
```sql
WHERE email = 'bellasulindra279@gmail.com' COLLATE utf8mb4_general_ci
```

**Opsi 3: Gunakan Generated Column (MySQL 5.7+) - RECOMMENDED**

```sql
-- Tambah generated column untuk email normalized
ALTER TABLE member_apps_members 
ADD COLUMN email_normalized VARCHAR(255) 
GENERATED ALWAYS AS (LOWER(TRIM(email))) STORED;

-- Tambah index di generated column
CREATE INDEX idx_email_normalized ON member_apps_members(email_normalized);

-- Ubah query untuk menggunakan generated column
SELECT * FROM member_apps_members 
WHERE email_normalized = 'bellasulindra279@gmail.com' 
LIMIT 1;
```

#### **3. EXPLAIN Query**

```sql
EXPLAIN SELECT * FROM `member_apps_members` 
WHERE LOWER(TRIM(email)) = 'bellasulindra279@gmail.com' 
LIMIT 1;
```

**Expected setelah optimasi:**
- `type` = `ref` atau `eq_ref` (pakai index)
- `key` = nama index yang digunakan
- `rows` = < 10 (bukan 92,555)

---

### **B. Optimasi Query `order_payment` DELETE**

#### **1. Check Indexes**

```sql
-- Check indexes untuk order_payment
SHOW INDEXES FROM order_payment;
```

#### **2. Tambah Index**

```sql
-- Tambah index untuk order_id
CREATE INDEX idx_order_id ON order_payment(order_id);
```

#### **3. EXPLAIN Query**

```sql
EXPLAIN DELETE FROM `order_payment` 
WHERE `order_id` = 'BTRTEMP26010436';
```

**Expected setelah tambah index:**
- `type` = `ref` (pakai index)
- `key` = `idx_order_id`
- `rows` = < 100 (bukan 118,364)

---

### **C. Optimasi Query `order_promos` DELETE**

#### **1. Check Indexes**

```sql
-- Check indexes untuk order_promos
SHOW INDEXES FROM order_promos;
```

#### **2. Tambah Index**

```sql
-- Tambah index untuk order_id
CREATE INDEX idx_order_id ON order_promos(order_id);
```

#### **3. EXPLAIN Query**

```sql
EXPLAIN DELETE FROM `order_promos` 
WHERE `order_id` = 'BTRTEMP26010436';
```

**Expected setelah tambah index:**
- `type` = `ref` (pakai index)
- `key` = `idx_order_id`
- `rows` = < 50 (bukan 31,564)

---

### **D. Optimasi Query `purchase_order_ops` dengan EXISTS**

#### **1. Check Indexes**

```sql
-- Check indexes untuk purchase_order_ops_approval_flows
SHOW INDEXES FROM purchase_order_ops_approval_flows;
```

#### **2. Tambah Composite Index**

```sql
-- Composite index untuk optimasi EXISTS queries
CREATE INDEX idx_approval_flows_lookup ON purchase_order_ops_approval_flows(
    purchase_order_ops_id, 
    status, 
    approver_id
);
```

#### **3. EXPLAIN Query**

```sql
EXPLAIN SELECT * FROM `purchase_order_ops` 
WHERE `status` NOT IN ('rejected', 'approved', 'received', 'cancelled') 
AND NOT EXISTS (
    SELECT * FROM `purchase_order_ops_approval_flows` 
    WHERE `purchase_order_ops`.`id` = `purchase_order_ops_approval_flows`.`purchase_order_ops_id` 
    AND `status` = 'REJECTED'
) 
AND EXISTS (
    SELECT * FROM `purchase_order_ops_approval_flows` 
    WHERE `purchase_order_ops`.`id` = `purchase_order_ops_approval_flows`.`purchase_order_ops_id` 
    AND `approver_id` = 1285 
    AND `status` = 'PENDING'
);
```

**Expected setelah tambah index:**
- `type` = `ref` (pakai index)
- `key` = `idx_approval_flows_lookup`
- `rows` = < 50 (bukan 408)

---

### **E. Optimasi Query `purchase_order_ops_items` dengan SUM**

#### **1. Check Indexes**

```sql
-- Check indexes untuk purchase_order_ops_items
SHOW INDEXES FROM purchase_order_ops_items;

-- Check indexes untuk purchase_order_ops
SHOW INDEXES FROM purchase_order_ops;

-- Check indexes untuk purchase_requisitions
SHOW INDEXES FROM purchase_requisitions;

-- Check indexes untuk purchase_requisition_items
SHOW INDEXES FROM purchase_requisition_items;
```

#### **2. Tambah Indexes**

```sql
-- Index untuk purchase_order_ops_items
CREATE INDEX idx_purchase_order_ops_id ON purchase_order_ops_items(purchase_order_ops_id);
CREATE INDEX idx_source_id ON purchase_order_ops_items(source_id);
CREATE INDEX idx_pr_ops_item_id ON purchase_order_ops_items(pr_ops_item_id);

-- Index untuk purchase_requisition_items
CREATE INDEX idx_purchase_requisition_id ON purchase_requisition_items(purchase_requisition_id);
CREATE INDEX idx_category_id ON purchase_requisition_items(category_id);
CREATE INDEX idx_item_name ON purchase_requisition_items(item_name);
```

#### **3. EXPLAIN Query**

```sql
EXPLAIN SELECT SUM(`poi`.`total`) as aggregate 
FROM `purchase_order_ops_items` as `poi` 
LEFT JOIN `purchase_order_ops` as `poo` ON `poi`.`purchase_order_ops_id` = `poo`.`id` 
LEFT JOIN `purchase_requisitions` as `pr` ON `poi`.`source_id` = `pr`.`id` 
LEFT JOIN `purchase_requisition_items` as `pri` ON `pr`.`id` = `pri`.`purchase_requisition_id`;
```

**Expected setelah tambah index:**
- `type` = `ref` atau `eq_ref` (pakai index)
- `key` = nama index yang digunakan
- `rows` = < 1,000 (bukan 76,756)

---

### **F. Optimasi Query `purchase_requisitions` dengan COUNT**

#### **1. Check Indexes**

```sql
-- Check indexes untuk purchase_requisitions
SHOW INDEXES FROM purchase_requisitions;
```

#### **2. Tambah Composite Index**

```sql
-- Composite index untuk created_by dan status
CREATE INDEX idx_created_by_status ON purchase_requisitions(created_by, status);
```

#### **3. EXPLAIN Query**

```sql
EXPLAIN SELECT COUNT(*) as aggregate 
FROM `purchase_requisitions` 
WHERE `created_by` = 2945 
AND `status` = 'SUBMITTED';
```

**Expected setelah tambah index:**
- `type` = `ref` (pakai index)
- `key` = `idx_created_by_status`
- `rows` = < 100 (bukan 985)

---

### **G. Optimasi Query `att_log` dengan JOIN dan DATE() - URGENT!**

#### **1. Check Indexes**

```sql
-- Check indexes untuk att_log
SHOW INDEXES FROM att_log;

-- Check indexes untuk tbl_data_outlet
SHOW INDEXES FROM tbl_data_outlet;

-- Check indexes untuk user_pins
SHOW INDEXES FROM user_pins;

-- Check ukuran table att_log
SELECT COUNT(*) as total_rows FROM att_log;
```

#### **2. Tambah Indexes (URGENT!)**

```sql
-- Index untuk att_log (PRIORITAS TINGGI!)
CREATE INDEX idx_scan_date ON att_log(scan_date);
CREATE INDEX idx_sn ON att_log(sn);
CREATE INDEX idx_pin ON att_log(pin);
CREATE INDEX idx_sn_pin ON att_log(sn, pin);
CREATE INDEX idx_user_pin_date ON att_log(pin, scan_date); -- Composite untuk optimasi JOIN

-- Index untuk user_pins
CREATE INDEX idx_pin_outlet ON user_pins(pin, outlet_id);
CREATE INDEX idx_user_id ON user_pins(user_id);
```

#### **3. Optimasi Query (UBAH DI APLIKASI - URGENT!)**

**⚠️ PENTING: Query ini HARUS diubah di aplikasi!**

**Query saat ini (SANGAT LAMBAT - 1.7s, 683k rows):**
```sql
WHERE DATE(a.scan_date) BETWEEN '2025-10-26' AND '2025-11-25'
```

**Query yang lebih efisien (HARUS diubah di aplikasi):**
```sql
WHERE a.scan_date >= '2025-10-26 00:00:00' 
AND a.scan_date < '2025-11-26 00:00:00'
```

**Atau jika perlu hanya tanggal tanpa waktu:**
```sql
WHERE DATE(a.scan_date) >= '2025-10-26' 
AND DATE(a.scan_date) <= '2025-11-25'
-- Tapi ini masih lambat, lebih baik gunakan range datetime
```

#### **4. EXPLAIN Query**

```sql
-- EXPLAIN query yang sudah dioptimasi
EXPLAIN SELECT `a`.`scan_date`, `a`.`inoutmode`, `u`.`id` as `user_id`, 
       `u`.`nama_lengkap`, `o`.`id_outlet`, `o`.`nama_outlet` 
FROM `att_log` as `a` 
INNER JOIN `tbl_data_outlet` as `o` ON `a`.`sn` = `o`.`sn` 
INNER JOIN `user_pins` as `up` ON `a`.`pin` = `up`.`pin` AND `o`.`id_outlet` = `up`.`outlet_id` 
INNER JOIN `users` as `u` ON `up`.`user_id` = `u`.`id` 
WHERE `u`.`id` = 1933 
AND a.scan_date >= '2025-10-26 00:00:00' 
AND a.scan_date < '2025-11-26 00:00:00' 
ORDER BY `a`.`scan_date` ASC;
```

**Expected setelah optimasi:**
- `type` = `range` atau `ref` (pakai index)
- `key` = `idx_scan_date` atau `idx_user_pin_date`
- `rows` = < 1,000 (bukan 683,918!)
- **Query time:** < 0.01s (bukan 1.7s!)

#### **5. Lokasi File yang Perlu Diubah**

Cari file yang menggunakan query `att_log` dengan `DATE(a.scan_date)`:
```bash
# Cari di codebase
grep -r "DATE(a.scan_date)" app/
grep -r "DATE.*scan_date" app/
grep -r "att_log.*DATE" app/
```

**File yang kemungkinan perlu diubah:**
- Controller yang menangani attendance/absensi
- Report controller untuk attendance
- API endpoint untuk attendance data

---

### **H. Optimasi Query `food_inventory_cost_histories`**

#### **1. Check Indexes**

```sql
-- Check indexes untuk food_inventory_cost_histories
SHOW INDEXES FROM food_inventory_cost_histories;
```

#### **2. Tambah Composite Index**

```sql
-- Composite index untuk optimasi query
CREATE INDEX idx_inventory_warehouse_date ON food_inventory_cost_histories(
    inventory_item_id, 
    warehouse_id, 
    date DESC, 
    created_at DESC
);
```

#### **3. EXPLAIN Query**

```sql
EXPLAIN SELECT * FROM `food_inventory_cost_histories` 
WHERE `inventory_item_id` = 4655 
AND `warehouse_id` = 1 
ORDER BY `date` DESC, `created_at` DESC 
LIMIT 1;
```

**Expected setelah tambah index:**
- `type` = `ref` (pakai index)
- `key` = `idx_inventory_warehouse_date`
- `rows` = < 10 (bukan 24,331)

---

## 🚨 **PRIORITAS OPTIMASI**

| Query | Prioritas | Alasan |
|-------|-----------|--------|
| `member_apps_members` dengan LOWER(TRIM(member_id)) | **CRITICAL** | Examine 64,742 rows, query time 0.148s - sangat lambat! |
| `member_apps_members` dengan LOWER(TRIM(email)) | **CRITICAL** | Examine 92,555 rows, query time 0.174s - sangat lambat! |
| `order_payment` DELETE | **HIGH** | Examine 118,364 rows, query time 0.251s - sangat lambat! |
| `order_promos` DELETE | **MEDIUM** | Examine 31,564 rows, query time 0.038s - bisa dioptimasi |
| `att_log` dengan JOIN dan DATE() | **CRITICAL** | Examine 683,918 rows, query time 1.7s - SANGAT LAMBAT! PENYEBAB UTAMA CPU 100%! |
| `purchase_order_ops_items` dengan SUM | **HIGH** | Examine 76,756 rows, query time 0.067s - sangat lambat! |
| `food_inventory_cost_histories` | **MEDIUM** | Examine 24,331 rows, query time 0.038s - bisa dioptimasi |
| `purchase_requisitions` dengan COUNT | **MEDIUM** | Examine 985 rows, query time 0.0007s - bisa dioptimasi |
| `purchase_requisitions` dengan EXISTS | **MEDIUM** | Examine 985 rows, query time 0.001s - bisa dioptimasi |
| `item_availabilities` dengan LIKE | **LOW** | Examine 3,934 rows, query time 0.009s - bisa dioptimasi |
| `purchase_order_ops` dengan EXISTS | **LOW** | Query time 0.001s cepat, tapi bisa dioptimasi |

---

## 📋 **COMMAND SQL LENGKAP**

```sql
-- Login MySQL
mysql -u root -p
USE db_justus;

-- ============================================
-- 1. OPTIMASI member_apps_members (CRITICAL)
-- ============================================

-- Check indexes
SHOW INDEXES FROM member_apps_members;

-- Opsi 1: Tambah index di member_id dan email (jika belum ada)
CREATE INDEX idx_member_id ON member_apps_members(member_id);
CREATE INDEX idx_email ON member_apps_members(email);

-- Opsi 2: Tambah generated column untuk member_id dan email normalized (RECOMMENDED)
ALTER TABLE member_apps_members 
ADD COLUMN member_id_normalized VARCHAR(255) 
GENERATED ALWAYS AS (LOWER(TRIM(member_id))) STORED;

ALTER TABLE member_apps_members 
ADD COLUMN email_normalized VARCHAR(255) 
GENERATED ALWAYS AS (LOWER(TRIM(email))) STORED;

CREATE INDEX idx_member_id_normalized ON member_apps_members(member_id_normalized);
CREATE INDEX idx_email_normalized ON member_apps_members(email_normalized);

-- EXPLAIN queries
EXPLAIN SELECT * FROM `member_apps_members` 
WHERE LOWER(TRIM(member_id)) = 'u10729' 
LIMIT 1;

EXPLAIN SELECT * FROM `member_apps_members` 
WHERE LOWER(TRIM(email)) = 'bellasulindra279@gmail.com' 
LIMIT 1;

-- ============================================
-- 2. OPTIMASI order_payment DELETE (HIGH)
-- ============================================

-- Check indexes
SHOW INDEXES FROM order_payment;

-- Tambah index untuk order_id
CREATE INDEX idx_order_id ON order_payment(order_id);

-- EXPLAIN query
EXPLAIN DELETE FROM `order_payment` 
WHERE `order_id` = 'BTRTEMP26010436';

-- ============================================
-- 3. OPTIMASI order_promos DELETE (MEDIUM)
-- ============================================

-- Check indexes
SHOW INDEXES FROM order_promos;

-- Tambah index untuk order_id
CREATE INDEX idx_order_id ON order_promos(order_id);

-- EXPLAIN query
EXPLAIN DELETE FROM `order_promos` 
WHERE `order_id` = 'BTRTEMP26010436';

-- ============================================
-- 5. OPTIMASI purchase_requisitions EXISTS (MEDIUM)
-- ============================================

-- Check indexes
SHOW INDEXES FROM purchase_requisition_approval_flows;

-- Tambah composite index untuk optimasi EXISTS
CREATE INDEX idx_pr_approval_flows_lookup ON purchase_requisition_approval_flows(
    purchase_requisition_id, 
    approver_id, 
    status
);

-- EXPLAIN query
EXPLAIN SELECT * FROM `purchase_requisitions` 
WHERE `status` = 'SUBMITTED' 
AND EXISTS (
    SELECT * FROM `purchase_requisition_approval_flows` 
    WHERE `purchase_requisitions`.`id` = `purchase_requisition_approval_flows`.`purchase_requisition_id` 
    AND `approver_id` = 909 
    AND `status` = 'PENDING'
) 
ORDER BY `created_at` DESC;

-- ============================================
-- 7. OPTIMASI purchase_order_ops_items SUM (HIGH)
-- ============================================

-- Check indexes
SHOW INDEXES FROM purchase_order_ops_items;
SHOW INDEXES FROM purchase_order_ops;
SHOW INDEXES FROM purchase_requisitions;
SHOW INDEXES FROM purchase_requisition_items;

-- Tambah indexes
CREATE INDEX idx_purchase_order_ops_id ON purchase_order_ops_items(purchase_order_ops_id);
CREATE INDEX idx_source_id ON purchase_order_ops_items(source_id);
CREATE INDEX idx_pr_ops_item_id ON purchase_order_ops_items(pr_ops_item_id);
CREATE INDEX idx_purchase_requisition_id ON purchase_requisition_items(purchase_requisition_id);
CREATE INDEX idx_category_id ON purchase_requisition_items(category_id);
CREATE INDEX idx_item_name ON purchase_requisition_items(item_name);

-- ============================================
-- 8. OPTIMASI purchase_requisitions COUNT (MEDIUM)
-- ============================================

-- Check indexes
SHOW INDEXES FROM purchase_requisitions;

-- Tambah composite index
CREATE INDEX idx_created_by_status ON purchase_requisitions(created_by, status);

-- EXPLAIN query
EXPLAIN SELECT COUNT(*) as aggregate 
FROM `purchase_requisitions` 
WHERE `created_by` = 2945 
AND `status` = 'SUBMITTED';

-- ============================================
-- 9. OPTIMASI att_log JOIN (CRITICAL - URGENT!)
-- ============================================

-- Check indexes
SHOW INDEXES FROM att_log;
SHOW INDEXES FROM tbl_data_outlet;
SHOW INDEXES FROM user_pins;

-- Check ukuran table
SELECT COUNT(*) as total_rows FROM att_log;

-- Tambah indexes (URGENT!)
CREATE INDEX idx_scan_date ON att_log(scan_date);
CREATE INDEX idx_sn ON att_log(sn);
CREATE INDEX idx_pin ON att_log(pin);
CREATE INDEX idx_sn_pin ON att_log(sn, pin);
CREATE INDEX idx_user_pin_date ON att_log(pin, scan_date);
CREATE INDEX idx_pin_outlet ON user_pins(pin, outlet_id);
CREATE INDEX idx_user_id ON user_pins(user_id);

-- ⚠️ PENTING: Query HARUS diubah di aplikasi!
-- Ubah dari: DATE(a.scan_date) BETWEEN '2025-10-26' AND '2025-11-25'
-- Menjadi: a.scan_date >= '2025-10-26 00:00:00' AND a.scan_date < '2025-11-26 00:00:00'

-- Check indexes
SHOW INDEXES FROM att_log;
SHOW INDEXES FROM tbl_data_outlet;
SHOW INDEXES FROM user_pins;

-- Tambah indexes
CREATE INDEX idx_scan_date ON att_log(scan_date);
CREATE INDEX idx_sn ON att_log(sn);
CREATE INDEX idx_pin ON att_log(pin);
CREATE INDEX idx_sn_pin ON att_log(sn, pin);
CREATE INDEX idx_pin_outlet ON user_pins(pin, outlet_id);

-- ============================================
-- 10. OPTIMASI purchase_order_ops EXISTS (LOW)
-- ============================================

-- Check indexes
SHOW INDEXES FROM purchase_order_ops_approval_flows;

-- Tambah composite index
CREATE INDEX idx_approval_flows_lookup ON purchase_order_ops_approval_flows(
    purchase_order_ops_id, 
    status, 
    approver_id
);

-- EXPLAIN query
EXPLAIN SELECT * FROM `purchase_order_ops` 
WHERE `status` NOT IN ('rejected', 'approved', 'received', 'cancelled') 
AND NOT EXISTS (
    SELECT * FROM `purchase_order_ops_approval_flows` 
    WHERE `purchase_order_ops`.`id` = `purchase_order_ops_approval_flows`.`purchase_order_ops_id` 
    AND `status` = 'REJECTED'
) 
AND EXISTS (
    SELECT * FROM `purchase_order_ops_approval_flows` 
    WHERE `purchase_order_ops`.`id` = `purchase_order_ops_approval_flows`.`purchase_order_ops_id` 
    AND `approver_id` = 1285 
    AND `status` = 'PENDING'
);
```

---

## 📊 **EXPECTED RESULTS SETELAH OPTIMASI**

### **1. Query `member_apps_members` dengan LOWER(TRIM(member_id))**

| Metric | Sebelum | Sesudah | Improvement |
|--------|---------|---------|-------------|
| **Rows examined** | 64,742 | < 10 | **99.98% lebih efisien!** |
| **Query time** | 0.148s | < 0.001s | **148x lebih cepat!** |
| **Type** | ALL (full scan) | ref (pakai index) | ✅ Optimized |

### **2. Query `member_apps_members` dengan LOWER(TRIM(email))**

| Metric | Sebelum | Sesudah | Improvement |
|--------|---------|---------|-------------|
| **Rows examined** | 92,555 | < 10 | **99.99% lebih efisien!** |
| **Query time** | 0.174s | < 0.001s | **174x lebih cepat!** |
| **Type** | ALL (full scan) | ref (pakai index) | ✅ Optimized |

### **3. Query `order_payment` DELETE**

| Metric | Sebelum | Sesudah | Improvement |
|--------|---------|---------|-------------|
| **Rows examined** | 118,364 | < 100 | **99.9% lebih efisien!** |
| **Query time** | 0.251s | < 0.001s | **250x lebih cepat!** |
| **Type** | ALL (full scan) | ref (pakai index) | ✅ Optimized |

### **4. Query `order_promos` DELETE**

| Metric | Sebelum | Sesudah | Improvement |
|--------|---------|---------|-------------|
| **Rows examined** | 31,564 | < 50 | **99.8% lebih efisien!** |
| **Query time** | 0.038s | < 0.001s | **38x lebih cepat!** |
| **Type** | ALL (full scan) | ref (pakai index) | ✅ Optimized |

### **5. Query `purchase_order_ops_items` dengan SUM**

| Metric | Sebelum | Sesudah | Improvement |
|--------|---------|---------|-------------|
| **Rows examined** | 76,756 | < 1,000 | **98.7% lebih efisien!** |
| **Query time** | 0.067s | < 0.001s | **67x lebih cepat!** |
| **Type** | ALL (full scan) | ref (pakai index) | ✅ Optimized |

### **6. Query `purchase_requisitions` dengan COUNT**

| Metric | Sebelum | Sesudah | Improvement |
|--------|---------|---------|-------------|
| **Rows examined** | 985 | < 100 | **90% lebih efisien!** |
| **Query time** | 0.0007s | < 0.0001s | **7x lebih cepat!** |
| **Type** | ALL atau ref (tidak efisien) | ref (pakai index) | ✅ Optimized |

### **7. Query `att_log` dengan JOIN dan DATE()**

| Metric | Sebelum | Sesudah | Improvement |
|--------|---------|---------|-------------|
| **Rows examined** | 7,886 | < 500 | **93.7% lebih efisien!** |
| **Query time** | 0.008s | < 0.001s | **8x lebih cepat!** |
| **Type** | ALL (full scan) | range atau ref (pakai index) | ✅ Optimized |

---

## ⚠️ **CATATAN PENTING**

1. **Query `member_apps_members` dengan LOWER(TRIM(member_id)) dan LOWER(TRIM(email)) adalah yang PALING BERMASALAH**
   - `member_id`: Examine 64,742 rows, query time 0.148s (lambat!)
   - `email`: Examine 92,555 rows, query time 0.174s (lambat!)
   - **PRIORITAS CRITICAL** untuk dioptimasi
   - Solusi: Gunakan generated column untuk `member_id_normalized` dan `email_normalized` dengan index

2. **Query DELETE `order_payment` dan `order_promos` sangat bermasalah**
   - Examine 118,364 dan 31,564 rows
   - Query time 0.251s dan 0.038s
   - **PRIORITAS HIGH/MEDIUM** untuk dioptimasi
   - Solusi: Tambah index di `order_id`

3. **Query `purchase_order_ops_items` dengan SUM sangat bermasalah**
   - Examine 76,756 rows, query time 0.067s
   - **PRIORITAS HIGH** untuk dioptimasi
   - Solusi: Tambah index di kolom-kolom yang digunakan untuk JOIN

4. **Query `att_log` dengan DATE() function bermasalah**
   - Examine 7,886 rows, query time 0.008s
   - Fungsi `DATE()` membuat index tidak bisa digunakan
   - Solusi: Ubah query untuk tidak menggunakan `DATE()` function

5. **CPU 100% kemungkinan karena:**
   - Query `member_apps_members` full table scan (64k-92k rows)
   - Query DELETE `order_payment` full table scan (118,364 rows)
   - Query `purchase_order_ops_items` full table scan (76,756 rows)
   - Kombinasi query-query ini membuat CPU overload

---

## 🚨 **SOLUSI URGENT - QUERY `att_log` dengan DATE()**

### **File yang Perlu Diubah:**

1. **`app/Http/Controllers/AttendanceController.php`**
   - Line ~265: `whereBetween(DB::raw('DATE(a.scan_date)'), ...)`
   - Line ~487: `whereBetween(DB::raw('DATE(a.scan_date)'), ...)`
   - Line ~890: `whereBetween(DB::raw('DATE(a.scan_date)'), ...)`

2. **`app/Http/Controllers/PayrollReportController.php`**
   - Line ~724: `whereBetween(DB::raw('DATE(a.scan_date)'), ...)`
   - Line ~2075: `whereDate('a.scan_date', ...)`
   - Line ~2612: `whereBetween(DB::raw('DATE(a.scan_date)'), ...)`
   - Line ~3494: `whereBetween(DB::raw('DATE(a.scan_date)'), ...)`
   - Line ~5532: `whereBetween(DB::raw('DATE(a.scan_date)'), ...)`

3. **`app/Http/Controllers/AttendanceReportController.php`**
   - Line ~54: `whereBetween(DB::raw('DATE(a.scan_date)'), ...)`
   - Line ~684: `whereBetween(DB::raw('DATE(a.scan_date)'), ...)`
   - Line ~1079: `whereBetween(DB::raw('DATE(a.scan_date)'), ...)`
   - Line ~1513: `whereBetween(DB::raw('DATE(a.scan_date)'), ...)`
   - Line ~1708: `whereBetween(DB::raw('DATE(a.scan_date)'), ...)`
   - Line ~2278: `whereBetween(DB::raw('DATE(a.scan_date)'), ...)`

4. **`app/Http/Controllers/ScheduleAttendanceCorrectionController.php`**
   - Line ~146: `whereBetween(DB::raw('DATE(att_log.scan_date)'), ...)`

5. **`app/Services/ExtraOffService.php`**
   - Line ~67: `DATE(a.scan_date)`
   - Line ~284: `where(DB::raw('DATE(a.scan_date)'), ...)`

6. **`app/Services/HolidayAttendanceService.php`**
   - Line ~96: `where(DB::raw('DATE(a.scan_date)'), ...)`

### **Cara Mengubah Query:**

**Dari:**
```php
->whereBetween(DB::raw('DATE(a.scan_date)'), [$startDate, $endDate])
```

**Menjadi:**
```php
->where('a.scan_date', '>=', $startDate . ' 00:00:00')
->where('a.scan_date', '<', date('Y-m-d', strtotime($endDate . ' +1 day')) . ' 00:00:00')
```

**Atau:**
```php
->whereBetween('a.scan_date', [
    $startDate . ' 00:00:00', 
    $endDate . ' 23:59:59'
])
```

**Dari:**
```php
->whereDate('a.scan_date', $date)
```

**Menjadi:**
```php
->where('a.scan_date', '>=', $date . ' 00:00:00')
->where('a.scan_date', '<', date('Y-m-d', strtotime($date . ' +1 day')) . ' 00:00:00')
```

**Dari:**
```php
->where(DB::raw('DATE(a.scan_date)'), $date)
```

**Menjadi:**
```php
->where('a.scan_date', '>=', $date . ' 00:00:00')
->where('a.scan_date', '<', date('Y-m-d', strtotime($date . ' +1 day')) . ' 00:00:00')
```

### **Expected Improvement:**

| Metric | Sebelum | Sesudah | Improvement |
|--------|---------|---------|-------------|
| **Rows examined** | 683,918 | < 1,000 | **99.85% lebih efisien!** |
| **Query time** | 1.7s | < 0.01s | **170x lebih cepat!** |
| **Type** | ALL (full scan) | range (pakai index) | ✅ Optimized |

---

## 🎯 **KESIMPULAN**

**Masalah utama:**
1. **Query `att_log` dengan DATE() - CRITICAL** (683,918 rows, 1.7s) - **PENYEBAB UTAMA CPU 100%!**
2. Query `member_apps_members` dengan LOWER(TRIM(member_id)) - **CRITICAL** (64,742 rows, 0.148s)
3. Query `member_apps_members` dengan LOWER(TRIM(email)) - **CRITICAL** (92,555 rows, 0.174s)
4. Query DELETE `order_payment` - **HIGH** (118,364 rows, 0.251s)
5. Query `purchase_order_ops_items` dengan SUM - **HIGH** (76,756 rows, 0.067s)
6. Query `food_inventory_cost_histories` - **MEDIUM** (24,331 rows, 0.038s)
7. Query DELETE `order_promos` - **MEDIUM** (31,564 rows, 0.038s)
8. Query `purchase_requisitions` dengan COUNT - **MEDIUM** (985 rows, 0.0007s)
9. Query `purchase_requisitions` dengan EXISTS - **MEDIUM** (985 rows, 0.001s)
10. Query `purchase_order_ops` dengan EXISTS - **LOW** (408 rows, 0.001s)

**Solusi URGENT:**
1. **PRIORITAS CRITICAL:** 
   - **UBAH QUERY `att_log` di aplikasi** - ganti `DATE(a.scan_date)` dengan range datetime
   - Tambah index di `att_log(scan_date, sn, pin)`
   - File yang perlu diubah: `AttendanceController.php`, `PayrollReportController.php`, `AttendanceReportController.php`, dll
2. **PRIORITAS CRITICAL:** Optimasi query `member_apps_members` dengan generated column untuk `member_id_normalized` dan `email_normalized`
3. **PRIORITAS HIGH:** Tambah index di `order_payment.order_id` dan index untuk `purchase_order_ops_items`
4. **PRIORITAS MEDIUM:** Tambah index di `order_promos.order_id`, composite index di `purchase_requisitions(created_by, status)`, dan `food_inventory_cost_histories`
5. **PRIORITAS LOW:** Tambah composite index di `purchase_requisition_approval_flows` dan `purchase_order_ops_approval_flows`

**Status:** CPU 100% terutama karena query `att_log` dengan `DATE()` function yang examine 683,918 rows dengan query time 1.7 detik. **HARUS diubah di aplikasi segera!**

**Status:** CPU 100% karena query-query ini yang tidak efisien. Optimasi dengan index akan mengurangi CPU usage secara signifikan.
