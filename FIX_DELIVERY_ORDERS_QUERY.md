# 🚨 Fix Slow Query - delivery_orders dengan Multiple JOINs

## ⚠️ **MASALAH YANG DITEMUKAN**

### **Query yang Slow:**
```sql
SELECT COUNT(*) as total FROM (
    SELECT 
        do.id, do.number, do.created_at,
        DATE_FORMAT(do.created_at, '%d/%m/%Y') as created_date,
        DATE_FORMAT(do.created_at, '%H:%i:%s') as created_time,
        do.packing_list_id, do.ro_supplier_gr_id,
        u.nama_lengkap as created_by_name,
        COALESCE(pl.packing_number, gr.gr_number) as packing_number,
        fo.order_number as floor_order_number,
        o.nama_outlet,
        wo.name as warehouse_outlet_name,
        CONCAT(...) as warehouse_info
    FROM delivery_orders do
    LEFT JOIN users u ON do.created_by = u.id
    LEFT JOIN food_packing_lists pl ON do.packing_list_id = pl.id
    LEFT JOIN food_good_receives gr ON do.ro_supplier_gr_id = gr.id
    LEFT JOIN purchase_order_foods po ON gr.po_id = po.id
    LEFT JOIN food_floor_orders fo ON (...)
    LEFT JOIN tbl_data_outlet o ON fo.id_outlet = o.id_outlet
    LEFT JOIN warehouse_outlets wo ON fo.warehouse_outlet_id = wo.id
    LEFT JOIN warehouse_division wd ON pl.warehouse_division_id = wd.id
    LEFT JOIN warehouses w ON wd.warehouse_id = w.id
    -- TIDAK ADA WHERE CLAUSE!
) subquery;
```

### **Masalah Utama:**
1. ❌ **TIDAK ADA WHERE CLAUSE** - Query memeriksa SEMUA rows di table!
2. ❌ **COUNT(*) dengan subquery kompleks** - Tidak efisien
3. ❌ **9 LEFT JOINs** - Sangat kompleks dan lambat
4. ❌ **Rows_examined: 1,469,699** untuk **Rows_sent: 1** - Ratio sangat buruk!

---

## 🔍 **LANGKAH 1: Analisis dengan EXPLAIN**

### **A. EXPLAIN Query**

```sql
-- Login MySQL
mysql -u root -p
USE db_justus;

-- EXPLAIN query lengkap (tambahkan EXPLAIN di depan)
EXPLAIN SELECT COUNT(*) as total FROM (
    SELECT 
        do.id, do.number, do.created_at,
        DATE_FORMAT(do.created_at, '%d/%m/%Y') as created_date,
        DATE_FORMAT(do.created_at, '%H:%i:%s') as created_time,
        do.packing_list_id, do.ro_supplier_gr_id,
        u.nama_lengkap as created_by_name,
        COALESCE(pl.packing_number, gr.gr_number) as packing_number,
        fo.order_number as floor_order_number,
        o.nama_outlet,
        wo.name as warehouse_outlet_name,
        CONCAT(COALESCE(w.name, ''), CASE WHEN w.name IS NOT NULL AND wd.name IS NOT NULL THEN ' - ' ELSE '' END, COALESCE(wd.name, '')) as warehouse_info
    FROM delivery_orders do
    LEFT JOIN users u ON do.created_by = u.id
    LEFT JOIN food_packing_lists pl ON do.packing_list_id = pl.id
    LEFT JOIN food_good_receives gr ON do.ro_supplier_gr_id = gr.id
    LEFT JOIN purchase_order_foods po ON gr.po_id = po.id
    LEFT JOIN food_floor_orders fo ON (
        (do.packing_list_id IS NOT NULL AND pl.food_floor_order_id = fo.id) OR
        (do.ro_supplier_gr_id IS NOT NULL AND po.source_id = fo.id)
    )
    LEFT JOIN tbl_data_outlet o ON fo.id_outlet = o.id_outlet
    LEFT JOIN warehouse_outlets wo ON fo.warehouse_outlet_id = wo.id
    LEFT JOIN warehouse_division wd ON pl.warehouse_division_id = wd.id
    LEFT JOIN warehouses w ON wd.warehouse_id = w.id
) subquery;
```

### **B. Check Hasil EXPLAIN**

**Yang perlu dicek:**
- **type**: Harusnya banyak yang `ALL` (full table scan) karena tidak ada WHERE
- **rows**: Akan sangat besar (1.5 juta+)
- **key**: Banyak yang `NULL` (tidak pakai index)

---

## 🚀 **LANGKAH 2: Solusi Optimasi**

### **A. Tambahkan WHERE Clause (PENTING!)**

Query ini **TIDAK ADA WHERE CLAUSE**, jadi memeriksa semua rows. Tambahkan WHERE clause sesuai kebutuhan:

```sql
-- Contoh: Filter berdasarkan tanggal
SELECT COUNT(*) as total FROM (
    SELECT 
        do.id, do.number, do.created_at,
        ...
    FROM delivery_orders do
    LEFT JOIN users u ON do.created_by = u.id
    LEFT JOIN food_packing_lists pl ON do.packing_list_id = pl.id
    LEFT JOIN food_good_receives gr ON do.ro_supplier_gr_id = gr.id
    LEFT JOIN purchase_order_foods po ON gr.po_id = po.id
    LEFT JOIN food_floor_orders fo ON (...)
    LEFT JOIN tbl_data_outlet o ON fo.id_outlet = o.id_outlet
    LEFT JOIN warehouse_outlets wo ON fo.warehouse_outlet_id = wo.id
    LEFT JOIN warehouse_division wd ON pl.warehouse_division_id = wd.id
    LEFT JOIN warehouses w ON wd.warehouse_id = w.id
    WHERE do.created_at >= '2024-01-01'  -- TAMBAHKAN WHERE CLAUSE!
    AND do.created_at <= '2024-12-31'
) subquery;
```

### **B. Optimasi COUNT(*) dengan Subquery**

**Sebelum (LAMBAT):**
```sql
SELECT COUNT(*) as total FROM (
    SELECT ... FROM delivery_orders do ... LEFT JOIN ...
) subquery;
```

**Sesudah (LEBIH CEPAT):**
```sql
-- Langsung COUNT tanpa subquery
SELECT COUNT(*) as total
FROM delivery_orders do
LEFT JOIN users u ON do.created_by = u.id
LEFT JOIN food_packing_lists pl ON do.packing_list_id = pl.id
LEFT JOIN food_good_receives gr ON do.ro_supplier_gr_id = gr.id
LEFT JOIN purchase_order_foods po ON gr.po_id = po.id
LEFT JOIN food_floor_orders fo ON (...)
LEFT JOIN tbl_data_outlet o ON fo.id_outlet = o.id_outlet
LEFT JOIN warehouse_outlets wo ON fo.warehouse_outlet_id = wo.id
LEFT JOIN warehouse_division wd ON pl.warehouse_division_id = wd.id
LEFT JOIN warehouses w ON wd.warehouse_id = w.id
WHERE do.created_at >= '2024-01-01'  -- TAMBAHKAN WHERE!
AND do.created_at <= '2024-12-31';
```

**Atau jika perlu distinct:**
```sql
SELECT COUNT(DISTINCT do.id) as total
FROM delivery_orders do
LEFT JOIN ...
WHERE do.created_at >= '2024-01-01';
```

### **C. Tambah Index untuk WHERE Clause**

Setelah tambah WHERE clause, pastikan column di WHERE ada index:

```sql
-- Jika WHERE created_at (sudah ada index berdasarkan struktur table)
-- Index sudah ada: created_at (MUL)

-- Jika WHERE column lain, tambah index:
CREATE INDEX idx_column_name ON delivery_orders(column_name);
```

### **D. Optimasi JOIN Conditions**

Pastikan semua JOIN conditions pakai index:

```sql
-- Check indexes untuk JOIN columns
SHOW INDEXES FROM delivery_orders;
SHOW INDEXES FROM users;
SHOW INDEXES FROM food_packing_lists;
SHOW INDEXES FROM food_good_receives;
-- ... dst

-- Pastikan semua foreign keys ada index:
-- do.created_by → users.id (harus ada index)
-- do.packing_list_id → food_packing_lists.id (harus ada index)
-- do.ro_supplier_gr_id → food_good_receives.id (harus ada index)
```

---

## 📋 **LANGKAH 3: Check Indexes untuk JOIN Columns**

### **A. Check Indexes yang Sudah Ada**

```sql
-- Check indexes untuk delivery_orders
SHOW INDEXES FROM delivery_orders;

-- Check indexes untuk tables yang di-JOIN
SHOW INDEXES FROM users;
SHOW INDEXES FROM food_packing_lists;
SHOW INDEXES FROM food_good_receives;
SHOW INDEXES FROM purchase_order_foods;
SHOW INDEXES FROM food_floor_orders;
```

### **B. Tambah Index jika Perlu**

```sql
-- Jika JOIN column tidak ada index, tambah:
CREATE INDEX idx_created_by ON delivery_orders(created_by);
CREATE INDEX idx_packing_list_id ON delivery_orders(packing_list_id);
CREATE INDEX idx_ro_supplier_gr_id ON delivery_orders(ro_supplier_gr_id);

-- Check apakah foreign keys sudah ada index
-- (Biasanya sudah ada, tapi perlu dicek)
```

---

## 🔧 **LANGKAH 4: Optimasi Query Structure**

### **A. Hapus Subquery yang Tidak Perlu**

Jika hanya butuh COUNT, tidak perlu subquery:

```sql
-- Sebelum (LAMBAT)
SELECT COUNT(*) as total FROM (
    SELECT do.id, do.number, ...
    FROM delivery_orders do
    LEFT JOIN ...
) subquery;

-- Sesudah (LEBIH CEPAT)
SELECT COUNT(*) as total
FROM delivery_orders do
LEFT JOIN ...
WHERE ...;
```

### **B. Gunakan EXISTS Jika Hanya Butuh Check Existence**

Jika hanya butuh check apakah ada data:

```sql
-- Lebih cepat daripada COUNT(*)
SELECT EXISTS(
    SELECT 1
    FROM delivery_orders do
    LEFT JOIN ...
    WHERE ...
) as has_data;
```

---

## 📊 **REKOMENDASI INDEX**

Berdasarkan query dan struktur table:

### **A. Indexes yang Mungkin Perlu**

```sql
-- Pastikan semua JOIN columns ada index (kemungkinan sudah ada)
-- Check dulu dengan SHOW INDEXES

-- Jika WHERE created_at (sudah ada berdasarkan struktur)
-- Index: created_at (MUL) ✅

-- Jika WHERE column lain, tambah:
CREATE INDEX idx_column_name ON delivery_orders(column_name);
```

### **B. Composite Index untuk WHERE Multiple Columns**

```sql
-- Jika WHERE created_at AND source_type
CREATE INDEX idx_created_at_source_type ON delivery_orders(created_at, source_type);
```

---

## ⚠️ **CATATAN PENTING**

1. **TAMBAHKAN WHERE CLAUSE** - Ini yang paling penting!
2. **Query tanpa WHERE akan scan semua rows** - sangat lambat!
3. **COUNT(*) dengan subquery tidak efisien** - langsung COUNT saja
4. **Pastikan semua JOIN columns ada index**
5. **Test query setelah optimasi**

---

## 🎯 **ACTION PLAN**

1. ✅ **Identifikasi WHERE conditions** yang seharusnya ada
2. ⏳ **Tambah WHERE clause** ke query
3. ⏳ **Optimasi COUNT(*) dengan subquery** - hapus subquery
4. ⏳ **Check indexes** untuk JOIN columns
5. ⏳ **EXPLAIN query** setelah optimasi
6. ⏳ **Test query** - pastikan lebih cepat
7. ⏳ **Monitor** untuk verifikasi

---

## 🔧 **COMMAND CEPAT**

### **Step 1: EXPLAIN Query Saat Ini**

```sql
EXPLAIN SELECT COUNT(*) as total FROM (
    SELECT ... FROM delivery_orders do LEFT JOIN ...
) subquery;
```

### **Step 2: Check Indexes**

```sql
SHOW INDEXES FROM delivery_orders;
SHOW INDEXES FROM users;
SHOW INDEXES FROM food_packing_lists;
```

### **Step 3: Optimasi Query**

```sql
-- Tambah WHERE clause dan hapus subquery
SELECT COUNT(*) as total
FROM delivery_orders do
LEFT JOIN ...
WHERE do.created_at >= '2024-01-01';
```

### **Step 4: EXPLAIN Query Setelah Optimasi**

```sql
EXPLAIN SELECT COUNT(*) as total
FROM delivery_orders do
LEFT JOIN ...
WHERE do.created_at >= '2024-01-01';
```

---

**Masalah utama: Query TIDAK ADA WHERE CLAUSE, jadi scan semua rows!** ⚠️

**Solusi: Tambahkan WHERE clause sesuai kebutuhan aplikasi!** ✅
