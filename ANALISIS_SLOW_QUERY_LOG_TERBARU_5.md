# 📊 Analisis Slow Query Log Terbaru - 9 Januari 2026 (09:05)

## 🎯 **HASIL ANALISIS**

**Dari slow query log terbaru:**
- ✅ **Kebanyakan query sangat cepat** (< 2ms)
- ⚠️ **2 query yang perlu dioptimize:**
  1. Query dengan `DATE(a.scan_date)` - Rows_examined: 12,804
  2. Query `item_bom` dengan JOIN - Rows_examined: 8,993 (dipanggil 2x)

---

## 📋 **DETAIL ANALISIS**

### **1. Query dengan `DATE(a.scan_date)`** ⚠️ MASALAH!

**Query:**
```sql
select 
    COUNT(DISTINCT DATE(a.scan_date)) as total_days,
    COUNT(DISTINCT CASE WHEN a.inoutmode = 0 THEN DATE(a.scan_date) END) as present_days,
    0 as late_days,
    ...
from att_log a
where ...
```

**Stats:**
- Query_time: 0.009842 (masih cepat, tapi bisa lebih baik)
- Rows_examined: 12,804 (TERLALU BANYAK!)
- **Masih menggunakan `DATE(a.scan_date)`** - ini sudah kita fix sebelumnya!

**Masalah:**
- ⚠️ **Masih menggunakan `DATE()` function** yang mencegah index usage
- ⚠️ **Rows_examined: 12,804** - terlalu banyak rows yang di-scan

**Rekomendasi:**
- ✅ **Ganti `DATE(a.scan_date)` dengan range-based WHERE clause**
- ✅ **Pastikan index `idx_scan_date` sudah dibuat**

**Implementasi:**
```php
// Before (masih menggunakan DATE())
->whereBetween(DB::raw('DATE(a.scan_date)'), [$startDate, $endDate])

// After (range-based)
->where('a.scan_date', '>=', $startDate . ' 00:00:00')
->where('a.scan_date', '<', date('Y-m-d', strtotime($endDate . ' +1 day')) . ' 00:00:00')
```

**Check apakah sudah di-fix:**
- File: `AttendanceController.php`, `PayrollReportController.php`, `AttendanceReportController.php`, dll
- Cari: `DATE(a.scan_date)` atau `DATE(scan_date)`
- Ganti dengan range-based WHERE clause

---

### **2. Query `item_bom` dengan JOIN** ⚠️ MASALAH!

**Query:**
```sql
select `item_bom`.*, `material`.`name` as `material_name`, `units`.`name` as `unit_name` 
from `item_bom` 
inner join `items` as `material` on `item_bom`.`material_item_id` = `material`.`id` 
inner join `units` on `item_bom`.`unit_id` = `units`.`id` 
where `item_bom`.`item_id` = 54667;
```

**Stats:**
- Query_time: 0.009833 dan 0.007282 (masih cepat, tapi bisa lebih baik)
- Rows_examined: 8,993 (TERLALU BANYAK!)
- **Dipanggil 2x** dalam waktu yang sama (mungkin duplicate request)

**Masalah:**
- ⚠️ **Rows_examined: 8,993** - terlalu banyak rows yang di-scan
- ⚠️ **Tidak ada index pada `item_id`** atau index tidak digunakan
- ⚠️ **Query dipanggil 2x** - mungkin duplicate request atau tidak ada caching

**Rekomendasi:**
1. ✅ **Check index pada `item_bom.item_id`:**
   ```sql
   SHOW INDEXES FROM item_bom WHERE Column_name = 'item_id';
   ```

2. ✅ **Jika tidak ada index, buat index:**
   ```sql
   CREATE INDEX idx_item_id ON item_bom(item_id);
   ```

3. ✅ **Check apakah JOIN columns sudah terindex:**
   ```sql
   SHOW INDEXES FROM item_bom WHERE Column_name IN ('material_item_id', 'unit_id');
   ```

4. ✅ **Jika tidak ada, buat composite index:**
   ```sql
   CREATE INDEX idx_item_bom_lookup ON item_bom(item_id, material_item_id, unit_id);
   ```

5. ✅ **Cache query result** (karena dipanggil 2x):
   ```php
   // Before
   $bom = ItemBom::where('item_id', $itemId)
       ->with(['material', 'unit'])
       ->get();

   // After (dengan cache)
   $bom = Cache::remember("item_bom_{$itemId}", 3600, function () use ($itemId) {
       return ItemBom::where('item_id', $itemId)
           ->with(['material', 'unit'])
           ->get();
   });
   ```

---

### **3. Query Lainnya** ✅ SUDAH BAIK

**Query yang sudah cepat (< 2ms):**
- ✅ `announcement_files` - 0.000120 detik, Rows_examined: 3
- ✅ `purchase_requisitions` - 0.001573 detik, Rows_examined: 988
- ✅ `tbl_kalender_perusahaan` - 0.000169 detik, Rows_examined: 50
- ✅ `leave_types` - 0.000166 detik, Rows_examined: 20
- ✅ `holiday_attendance_compensations` - 0.000821 detik, Rows_examined: 1
- ✅ `purchase_order_ops` - 0.001611 detik, Rows_examined: 418
- ✅ `quotes` - 0.000214 detik, Rows_examined: 9

**Kesimpulan:** Query-query ini sudah optimal, tidak perlu di-fix.

---

## 🎯 **ACTION ITEMS**

### **URGENT:**
1. 🔴 **Fix query dengan `DATE(a.scan_date)`** - Ganti dengan range-based WHERE clause
2. 🔴 **Check dan fix index pada `item_bom.item_id`** - Buat index jika belum ada

### **IMPORTANT:**
3. ⚠️ **Cache query `item_bom`** - Karena dipanggil 2x, cache result
4. ⚠️ **Check duplicate requests** - Kenapa query `item_bom` dipanggil 2x?

---

## 🔍 **CARA CHECK DAN FIX**

### **1. Check Query dengan DATE() Function**

```bash
# Cari file yang masih menggunakan DATE(scan_date)
cd /path/to/laravel
grep -r "DATE(a.scan_date)" app/
grep -r "DATE(scan_date)" app/
grep -r "whereDate.*scan_date" app/
```

**Jika masih ada, fix dengan:**
```php
// Ganti
->whereBetween(DB::raw('DATE(a.scan_date)'), [$start, $end])
// Dengan
->where('a.scan_date', '>=', $start . ' 00:00:00')
->where('a.scan_date', '<', date('Y-m-d', strtotime($end . ' +1 day')) . ' 00:00:00')
```

---

### **2. Check Index pada item_bom**

```sql
-- Check index
SHOW INDEXES FROM item_bom;

-- Check apakah item_id sudah terindex
SHOW INDEXES FROM item_bom WHERE Column_name = 'item_id';

-- Jika tidak ada, buat index
CREATE INDEX idx_item_id ON item_bom(item_id);

-- Atau composite index untuk optimize JOIN
CREATE INDEX idx_item_bom_lookup ON item_bom(item_id, material_item_id, unit_id);
```

---

### **3. Check EXPLAIN untuk item_bom Query**

```sql
EXPLAIN SELECT `item_bom`.*, `material`.`name` as `material_name`, `units`.`name` as `unit_name` 
FROM `item_bom` 
INNER JOIN `items` AS `material` ON `item_bom`.`material_item_id` = `material`.`id` 
INNER JOIN `units` ON `item_bom`.`unit_id` = `units`.`id` 
WHERE `item_bom`.`item_id` = 54667;
```

**Check:**
- `type` harus `ref` atau `eq_ref` (bukan `ALL`)
- `key` harus menggunakan index (bukan NULL)
- `rows` harus kecil (bukan 8,993)

---

## 📊 **EXPECTED RESULTS SETELAH FIX**

| Query | Before | After (Expected) |
|-------|--------|------------------|
| **DATE(scan_date)** | Rows_examined: 12,804 | Rows_examined: < 1,000 |
| **item_bom** | Rows_examined: 8,993 | Rows_examined: < 100 |
| **Query Time** | 0.009-0.010 detik | < 0.001 detik |

---

## ✅ **CHECKLIST**

- [ ] **Check query dengan DATE(scan_date)** - Ganti dengan range-based WHERE
- [ ] **Check index item_bom.item_id** - Buat index jika belum ada
- [ ] **Check EXPLAIN item_bom query** - Verify index digunakan
- [ ] **Cache item_bom query** - Karena dipanggil 2x
- [ ] **Check duplicate requests** - Kenapa dipanggil 2x?

---

## 🎯 **KESIMPULAN**

**Status Slow Query:** ⚠️ **Ada 2 query yang perlu dioptimize**

**Query yang perlu di-fix:**
1. ✅ Query dengan `DATE(a.scan_date)` - Ganti dengan range-based WHERE
2. ✅ Query `item_bom` - Buat index pada `item_id` dan cache result

**Query lainnya:** ✅ **Sudah cepat, tidak perlu di-fix**

**Expected setelah fix:**
- ✅ Rows_examined akan turun drastis
- ✅ Query time akan lebih cepat
- ✅ CPU usage akan turun

**Status:** ⚠️ **Fix 2 query ini untuk optimasi lebih lanjut!**
