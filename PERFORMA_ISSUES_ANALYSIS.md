# Analisis Masalah Performa - Menu/Fitur yang Lambat

## 🚨 Masalah N+1 Query yang Ditemukan

### 1. **PurchaseRequisitionController** - ⚠️ KRITIS

**Lokasi**: `app/Http/Controllers/PurchaseRequisitionController.php` method `index()`

**Masalah**:
- **N+1 Query Problem** yang sangat parah di line 211-374
- Untuk setiap PR di hasil pagination, ada 4-5 query tambahan:
  1. Query PO details (line 211-252)
  2. Query payment details (line 260-296)
  3. Query direct payments (line 299-336)
  4. Query unread comments count (line 348-374)

**Dampak**:
- Jika ada 15 PR per halaman → **60-75 query tambahan**!
- Response time bisa > 10 detik
- Database load sangat tinggi

**Solusi**:
```php
// Batch load semua PO IDs untuk semua PR sekaligus
$allPrIds = $purchaseRequisitions->pluck('id')->toArray();

// Batch load PO details untuk semua PR
$allPoDetails = DB::table('purchase_order_ops_items as poi')
    ->join('purchase_order_ops as po', 'poi.purchase_order_ops_id', '=', 'po.id')
    ->leftJoin('users as creator', 'po.created_by', '=', 'creator.id')
    ->where('poi.source_type', 'purchase_requisition_ops')
    ->whereIn('poi.source_id', $allPrIds)
    ->select(
        'poi.source_id as pr_id',
        'po.number',
        'po.created_at',
        'creator.nama_lengkap as creator_name',
        'creator.email as creator_email'
    )
    ->get()
    ->groupBy('pr_id');

// Batch load payments untuk semua PR
$allPayments = DB::table('non_food_payments as nfp')
    ->leftJoin('users as creator', 'nfp.created_by', '=', 'creator.id')
    ->whereIn('nfp.purchase_requisition_id', $allPrIds)
    ->orWhereIn('nfp.purchase_order_ops_id', $allPoIds)
    ->whereIn('nfp.status', ['paid', 'approved'])
    ->where('nfp.status', '!=', 'cancelled')
    ->select('nfp.*', 'creator.nama_lengkap as creator_name')
    ->get()
    ->groupBy('purchase_requisition_id');

// Batch load unread comments untuk semua PR
$allUnreadComments = DB::table('purchase_requisition_comments')
    ->whereIn('purchase_requisition_id', $allPrIds)
    ->where('user_id', '!=', $userId)
    ->select('purchase_requisition_id', DB::raw('COUNT(*) as count'))
    ->groupBy('purchase_requisition_id')
    ->get()
    ->keyBy('purchase_requisition_id');

// Map di PHP instead of query per item
$purchaseRequisitions->transform(function($pr) use ($allPoDetails, $allPayments, $allUnreadComments) {
    $pr->po_details = $allPoDetails->get($pr->id, collect())->toArray();
    $pr->payment_details = $allPayments->get($pr->id, collect())->toArray();
    $pr->unread_comments_count = $allUnreadComments->get($pr->id)->count ?? 0;
    return $pr;
});
```

**Prioritas**: 🔴 **SANGAT TINGGI** - Ini adalah masalah terbesar!

---

### 2. **PurchaseOrderOpsController** - ⚠️ SEDANG

**Lokasi**: `app/Http/Controllers/PurchaseOrderOpsController.php` method `index()`

**Masalah**:
- Query dengan banyak `leftJoin` (line 28-44)
- Transform di collection (line 67-100) - tidak terlalu masalah tapi bisa dioptimasi

**Dampak**:
- Query bisa lambat jika data banyak
- Join dengan banyak tabel bisa lambat tanpa index

**Solusi**:
- Pastikan ada index di:
  - `purchase_order_ops.supplier_id`
  - `purchase_order_ops.created_by`
  - `purchase_order_ops.source_id`
  - `purchase_order_ops.date`
  - `purchase_order_ops.status`

**Prioritas**: 🟡 **SEDANG**

---

### 3. **OutletWIPController** - ✅ SUDAH DIOPTIMASI

**Status**: Sudah dioptimasi dengan:
- Pagination di database level
- Batch query untuk BOM
- Redis caching untuk items & warehouse

**Prioritas**: ✅ **SELESAI**

---

## 📊 Daftar Menu/Fitur yang Berpotensi Lambat

### Menu dengan Query Kompleks:
1. **Purchase Requisition** - ⚠️ **KRITIS** (N+1 query)
2. **Purchase Order Ops** - ⚠️ Sedang (banyak join)
3. **Stock Opname** - Perlu dicek
4. **Outlet Transfer** - Perlu dicek
5. **Monitoring Dashboard** - Query aggregasi
6. **Marketing Dashboard** - Query aggregasi
7. **Report Controllers** - Query aggregasi kompleks

### Menu dengan Banyak Data:
- Semua menu dengan pagination
- Menu report dengan date range filter
- Menu dashboard dengan aggregasi

---

## 🔧 Solusi Umum untuk Semua Menu

### 1. Database Indexing
**File**: Buat SQL index untuk setiap tabel yang sering di-query

**Contoh untuk Purchase Requisition**:
```sql
-- Index untuk purchase_requisitions
ALTER TABLE `purchase_requisitions` 
ADD INDEX `idx_pr_created_by` (`created_by`),
ADD INDEX `idx_pr_status` (`status`),
ADD INDEX `idx_pr_date` (`created_at`),
ADD INDEX `idx_pr_mode` (`mode`);

-- Index untuk purchase_order_ops_items
ALTER TABLE `purchase_order_ops_items`
ADD INDEX `idx_poi_source` (`source_type`, `source_id`);

-- Index untuk non_food_payments
ALTER TABLE `non_food_payments`
ADD INDEX `idx_nfp_pr_id` (`purchase_requisition_id`),
ADD INDEX `idx_nfp_po_id` (`purchase_order_ops_id`),
ADD INDEX `idx_nfp_status` (`status`);

-- Index untuk purchase_requisition_comments
ALTER TABLE `purchase_requisition_comments`
ADD INDEX `idx_prc_pr_id` (`purchase_requisition_id`),
ADD INDEX `idx_prc_user_id` (`user_id`),
ADD INDEX `idx_prc_created_at` (`created_at`);
```

### 2. Redis Caching
**Untuk data master yang jarang berubah**:
- Divisi
- Categories
- Suppliers
- Outlets
- Users (untuk dropdown)

**Contoh**:
```php
$divisions = Cache::remember('divisions_active', 3600, function() {
    return Divisi::active()->orderBy('nama_divisi')->get();
});
```

### 3. Eager Loading
**Gunakan `with()` untuk relasi yang pasti digunakan**:
```php
$query = PurchaseRequisition::with([
    'division',
    'outlet',
    'category',
    'creator'
]);
```

### 4. Batch Loading
**Jangan query di dalam loop, batch load dulu**:
```php
// ❌ BAD - N+1 query
foreach ($prs as $pr) {
    $pr->po_details = DB::table('purchase_order_ops_items')
        ->where('source_id', $pr->id)
        ->get();
}

// ✅ GOOD - Batch load
$allPrIds = $prs->pluck('id')->toArray();
$allPoDetails = DB::table('purchase_order_ops_items')
    ->whereIn('source_id', $allPrIds)
    ->get()
    ->groupBy('source_id');

foreach ($prs as $pr) {
    $pr->po_details = $allPoDetails->get($pr->id, collect());
}
```

---

## 📋 Checklist Optimasi untuk Semua Menu

### Untuk Setiap Controller yang Lambat:

1. **Cek N+1 Query**:
   - [ ] Apakah ada query di dalam `foreach` atau `map`?
   - [ ] Apakah ada query di dalam `transform`?
   - [ ] Apakah ada `whereHas` yang tidak perlu?

2. **Cek Database Index**:
   - [ ] Apakah kolom yang di-`where` sudah ada index?
   - [ ] Apakah kolom yang di-`join` sudah ada index?
   - [ ] Apakah kolom yang di-`orderBy` sudah ada index?

3. **Cek Caching**:
   - [ ] Apakah data master bisa di-cache?
   - [ ] Apakah query yang sama dipanggil berulang?

4. **Cek Pagination**:
   - [ ] Apakah pagination di database level atau PHP level?
   - [ ] Apakah query fetch semua data dulu baru di-paginate?

---

## 🎯 Prioritas Optimasi

### Prioritas 1 (Kritis - Lakukan Segera):
1. ✅ **PurchaseRequisitionController** - Fix N+1 query
2. ✅ **Database Index** - Tambahkan index untuk semua tabel utama

### Prioritas 2 (Penting - Lakukan Setelah Prioritas 1):
1. ⏳ **PurchaseOrderOpsController** - Optimasi query
2. ⏳ **Redis Caching** - Cache data master di semua controller
3. ⏳ **Stock Opname** - Cek dan optimasi jika perlu

### Prioritas 3 (Opsional - Jika Masih Ada Waktu):
1. ⏳ **Dashboard Controllers** - Optimasi query aggregasi
2. ⏳ **Report Controllers** - Optimasi query kompleks

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

### 3. Gunakan Laravel Debugbar
```bash
composer require barryvdh/laravel-debugbar --dev
```

---

## 📝 Next Steps

1. **Fix PurchaseRequisitionController** - Ini yang paling kritis
2. **Buat SQL Index** - Untuk semua tabel yang sering di-query
3. **Implementasi Redis Caching** - Untuk data master
4. **Monitor Performa** - Setelah optimasi, cek apakah sudah lebih cepat
