# Hasil Scan Performa - Menu/Fitur yang Berpotensi Lambat

## ✅ Controller yang Sudah Dioptimasi

### 1. **OutletWIPController** ✅
- **Status**: Sudah dioptimasi
- **Optimasi**: Pagination di database, batch query BOM, Redis caching
- **Prioritas**: Tidak perlu diubah

### 2. **PurchaseRequisitionController** ✅
- **Status**: Baru saja dioptimasi
- **Optimasi**: Fix N+1 query dengan batch loading
- **Prioritas**: Tidak perlu diubah (sudah selesai)

### 3. **NonFoodPaymentController** ✅
- **Status**: Sudah ada optimasi batch loading
- **Optimasi**: Batch query untuk outlet breakdown (line 136-199)
- **Prioritas**: Sudah cukup baik

### 4. **PackingListController** ✅
- **Status**: Sudah ada optimasi batch loading
- **Optimasi**: Batch query untuk items (line 112-124)
- **Prioritas**: Sudah cukup baik

### 5. **DeliveryOrderController** ✅
- **Status**: Sudah ada optimasi
- **Optimasi**: Lazy loading dengan flag `load_data`
- **Prioritas**: Sudah cukup baik

---

## ⚠️ Controller yang Perlu Dicek Lebih Detail

### 1. **StockOpnameController** - 🟡 SEDANG

**Lokasi**: `app/Http/Controllers/StockOpnameController.php`

**Masalah Potensial**:
- Query dengan `with()` untuk eager loading (line 31-36) - ini sudah baik
- Query outlets di setiap request (line 74-83) - bisa di-cache
- Tidak ada masalah N+1 query yang jelas

**Rekomendasi**:
- ✅ Cache outlets query (data jarang berubah)
- ⏳ Cek method lain yang mungkin ada N+1 query

**Prioritas**: 🟡 **SEDANG** - Bisa dioptimasi dengan caching

---

### 2. **OutletTransferController** - 🟡 SEDANG

**Lokasi**: `app/Http/Controllers/OutletTransferController.php`

**Masalah Potensial**:
- Query di dalam loop untuk inventory items (line 79) - perlu dicek method `store()`
- Query untuk item master di dalam loop (line 89) - bisa di-batch load

**Rekomendasi**:
- ⏳ Batch load inventory items
- ⏳ Batch load item masters dengan units

**Prioritas**: 🟡 **SEDANG** - Perlu dicek method `store()` dan `index()`

---

### 3. **PurchaseOrderOpsController** - 🟡 SEDANG

**Lokasi**: `app/Http/Controllers/PurchaseOrderOpsController.php`

**Masalah Potensial**:
- Banyak `leftJoin` (line 28-44) - perlu index yang tepat
- Transform di collection (line 67-100) - tidak masalah, tapi bisa dioptimasi

**Rekomendasi**:
- ✅ Pastikan index sudah ada (sudah ada di SQL file)
- ⏳ Cek apakah ada N+1 query di method lain

**Prioritas**: 🟡 **SEDANG** - Perlu index database

---

### 4. **FoodPaymentController** - ⚠️ PERLU DICEK

**Lokasi**: `app/Http/Controllers/FoodPaymentController.php`

**Masalah Potensial**:
- Belum di-scan detail
- Kemungkinan ada pattern serupa dengan NonFoodPaymentController

**Rekomendasi**:
- ⏳ Scan detail untuk N+1 query
- ⏳ Cek apakah perlu batch loading

**Prioritas**: 🟡 **SEDANG** - Perlu dicek

---

### 5. **OutletPaymentController** - ⚠️ PERLU DICEK

**Lokasi**: `app/Http/Controllers/OutletPaymentController.php`

**Masalah Potensial**:
- Belum di-scan detail
- Kemungkinan ada pattern serupa dengan NonFoodPaymentController

**Rekomendasi**:
- ⏳ Scan detail untuk N+1 query
- ⏳ Cek apakah perlu batch loading

**Prioritas**: 🟡 **SEDANG** - Perlu dicek

---

### 6. **Report Controllers** - 🔴 TINGGI (Jika Banyak Data)

**Lokasi**: 
- `PurchaseRequisitionOpsReportController.php`
- `PurchaseOrderReportController.php`
- `PayrollReportController.php`
- `AttendanceReportController.php`
- dll

**Masalah Potensial**:
- Query aggregasi kompleks
- Bisa lambat jika data banyak
- Biasanya tidak ada pagination

**Rekomendasi**:
- ⏳ Pastikan ada index untuk kolom yang di-aggregate
- ⏳ Pertimbangkan pagination atau limit date range
- ⏳ Pertimbangkan background job untuk report besar

**Prioritas**: 🔴 **TINGGI** - Jika user sering complain lambat

---

### 7. **Dashboard Controllers** - 🔴 TINGGI (Jika Banyak Data)

**Lokasi**:
- `MarketingDashboardController.php`
- `CrmDashboardController.php`
- `OutletDashboardController.php`
- dll

**Masalah Potensial**:
- Query aggregasi kompleks
- Bisa lambat jika data banyak
- Biasanya load saat pertama kali buka

**Rekomendasi**:
- ⏳ Cache hasil aggregasi (5-15 menit)
- ⏳ Pastikan ada index untuk kolom yang di-aggregate
- ⏳ Pertimbangkan background job untuk update cache

**Prioritas**: 🔴 **TINGGI** - Jika user sering complain lambat

---

## 📊 Summary Prioritas Optimasi

### Prioritas 1 (Kritis - Lakukan Segera):
1. ✅ **PurchaseRequisitionController** - DONE (baru saja dioptimasi)
2. ✅ **OutletWIPController** - DONE (sudah dioptimasi sebelumnya)
3. ⏳ **Jalankan SQL Index** - File `optimize_purchase_requisition_indexes.sql` dan `optimize_outlet_wip_indexes.sql`

### Prioritas 2 (Penting - Lakukan Setelah Prioritas 1):
1. ⏳ **StockOpnameController** - Cache outlets query
2. ⏳ **OutletTransferController** - Cek dan optimasi method `store()` jika ada N+1 query
3. ⏳ **PurchaseOrderOpsController** - Pastikan index sudah ada

### Prioritas 3 (Opsional - Jika Masih Ada Waktu):
1. ⏳ **FoodPaymentController** - Scan detail
2. ⏳ **OutletPaymentController** - Scan detail
3. ⏳ **Report Controllers** - Optimasi query aggregasi
4. ⏳ **Dashboard Controllers** - Implementasi caching

---

## 🔍 Cara Identifikasi Masalah

### 1. Enable Query Log
```php
// Di AppServiceProvider atau Controller
DB::enableQueryLog();

// ... your code ...

dd(DB::getQueryLog()); // Lihat semua query yang dijalankan
```

### 2. Cek Slow Query Log MySQL
```sql
-- Enable slow query log
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 2; -- Query > 2 detik

-- Cek slow queries
SELECT * FROM mysql.slow_log ORDER BY start_time DESC LIMIT 10;
```

### 3. Pattern yang Harus Diwaspadai

**❌ BAD - N+1 Query**:
```php
foreach ($items as $item) {
    $item->details = DB::table('details')->where('item_id', $item->id)->get();
}
```

**✅ GOOD - Batch Loading**:
```php
$itemIds = $items->pluck('id')->toArray();
$allDetails = DB::table('details')->whereIn('item_id', $itemIds)->get()->groupBy('item_id');

foreach ($items as $item) {
    $item->details = $allDetails->get($item->id, collect());
}
```

---

## 📝 Checklist untuk Setiap Controller

Untuk setiap controller yang lambat, cek:

1. **N+1 Query**:
   - [ ] Apakah ada query di dalam `foreach` atau `map`?
   - [ ] Apakah ada query di dalam `transform`?
   - [ ] Apakah ada `whereHas` yang tidak perlu?

2. **Database Index**:
   - [ ] Apakah kolom yang di-`where` sudah ada index?
   - [ ] Apakah kolom yang di-`join` sudah ada index?
   - [ ] Apakah kolom yang di-`orderBy` sudah ada index?

3. **Caching**:
   - [ ] Apakah data master bisa di-cache?
   - [ ] Apakah query yang sama dipanggil berulang?
   - [ ] Apakah hasil aggregasi bisa di-cache?

4. **Pagination**:
   - [ ] Apakah pagination di database level atau PHP level?
   - [ ] Apakah query fetch semua data dulu baru di-paginate?

---

## 🎯 Next Steps

1. **Test aplikasi** - Pastikan optimasi PurchaseRequisitionController tidak error
2. **Jalankan SQL index** - File `optimize_purchase_requisition_indexes.sql`
3. **Monitor performa** - Cek apakah sudah lebih cepat
4. **Scan controller lain** - Jika masih ada yang lambat, scan lebih detail
