# 🚀 Optimasi Fitur Berat - CPU 100% (Category Cost Outlet, WIP Production, Stock Cut)

## 📊 **SITUASI SAAT INI**

- **CPU Usage:** 100% (overload)
- **Fitur yang Berat:**
  1. **Category Cost Outlet** - Banyak query ke `outlet_food_inventory_cards`
  2. **Outlet WIP Production** - Query kompleks dengan join multiple tables
  3. **Stock Cut** - Proses kalkulasi BOM dan update stock yang berat
- **Masalah:** Query tidak dioptimasi, tidak ada caching, scan full table

---

## 🎯 **STRATEGI OPTIMASI**

### **1. Database Indexing (PRIORITAS TINGGI)**

Tabel `outlet_food_inventory_cards` adalah tabel yang paling sering di-query dan kemungkinan besar tidak punya index yang tepat.

**Index yang Perlu Ditambahkan:**

```sql
-- Index untuk query berdasarkan outlet + warehouse + tanggal
ALTER TABLE `outlet_food_inventory_cards` 
ADD INDEX `idx_outlet_warehouse_date` (`id_outlet`, `warehouse_outlet_id`, `date`);

-- Index untuk query berdasarkan reference_type + reference_id
ALTER TABLE `outlet_food_inventory_cards` 
ADD INDEX `idx_reference` (`reference_type`, `reference_id`);

-- Index untuk query berdasarkan outlet + warehouse + reference_type + tanggal
ALTER TABLE `outlet_food_inventory_cards` 
ADD INDEX `idx_outlet_warehouse_ref_date` (`id_outlet`, `warehouse_outlet_id`, `reference_type`, `date`);

-- Index untuk inventory_item_id (sering di-join)
ALTER TABLE `outlet_food_inventory_cards` 
ADD INDEX `idx_inventory_item` (`inventory_item_id`);

-- Composite index untuk query stock report
ALTER TABLE `outlet_food_inventory_cards` 
ADD INDEX `idx_stock_report` (`id_outlet`, `warehouse_outlet_id`, `reference_type`, `date`, `inventory_item_id`);
```

**Index untuk Tabel Lain:**

```sql
-- Index untuk outlet_wip_production_headers
ALTER TABLE `outlet_wip_production_headers` 
ADD INDEX `idx_status_date` (`status`, `production_date`);

-- Index untuk outlet_internal_use_waste_headers
ALTER TABLE `outlet_internal_use_waste_headers` 
ADD INDEX `idx_status_type_date` (`status`, `type`, `date`);

-- Index untuk stock_cut_logs
ALTER TABLE `stock_cut_logs` 
ADD INDEX `idx_outlet_tanggal_status` (`outlet_id`, `tanggal`, `status`);

-- Index untuk stock_cut_details
ALTER TABLE `stock_cut_details` 
ADD INDEX `idx_log_item_warehouse` (`stock_cut_log_id`, `item_id`, `warehouse_outlet_id`);

-- Index untuk order_items (untuk stock cut)
ALTER TABLE `order_items` 
ADD INDEX `idx_outlet_date_stockcut` (`kode_outlet`, `created_at`, `stock_cut`);

-- Index untuk outlet_food_inventory_stocks
ALTER TABLE `outlet_food_inventory_stocks` 
ADD INDEX `idx_outlet_warehouse_item` (`id_outlet`, `warehouse_outlet_id`, `inventory_item_id`);
```

**Cara Cek Index yang Sudah Ada:**

```sql
SHOW INDEX FROM `outlet_food_inventory_cards`;
SHOW INDEX FROM `outlet_wip_production_headers`;
SHOW INDEX FROM `outlet_internal_use_waste_headers`;
```

---

### **2. Query Optimization - OutletStockReportController**

**Masalah:** Query melakukan scan full table dengan `whereBetween` tanpa index yang tepat.

**File:** `app/Http/Controllers/OutletStockReportController.php`

**Optimasi yang Perlu Dilakukan:**

#### **A. Optimasi Query WIP Production (Line 371-397)**

**Sebelum:**
```php
$wipStockCards = DB::table('outlet_food_inventory_cards as card')
    ->join('outlet_food_inventory_items as fi', 'card.inventory_item_id', '=', 'fi.id')
    ->leftJoin('outlet_wip_production_headers as h', function($join) {
        $join->on('card.reference_id', '=', 'h.id')
             ->where('card.reference_type', '=', 'outlet_wip_production');
    })
    ->where('card.id_outlet', $outletId)
    ->where('card.warehouse_outlet_id', $warehouseOutletId)
    ->where('card.reference_type', 'outlet_wip_production')
    ->where(function($query) {
        $query->where('h.status', 'PROCESSED')
              ->orWhereNull('h.status');
    })
    ->whereBetween('card.date', [$tanggalAwalBulan, $tanggalAkhirBulan])
    ->select(...)
    ->groupBy('fi.item_id')
    ->get();
```

**Sesudah (Optimized):**
```php
// Gunakan index yang sudah dibuat
// Pastikan index idx_outlet_warehouse_ref_date sudah ada
$wipStockCards = DB::table('outlet_food_inventory_cards as card')
    ->join('outlet_food_inventory_items as fi', 'card.inventory_item_id', '=', 'fi.id')
    ->leftJoin('outlet_wip_production_headers as h', function($join) {
        $join->on('card.reference_id', '=', 'h.id')
             ->where('card.reference_type', '=', 'outlet_wip_production');
    })
    ->where('card.id_outlet', $outletId)
    ->where('card.warehouse_outlet_id', $warehouseOutletId)
    ->where('card.reference_type', 'outlet_wip_production')
    ->where('card.date', '>=', $tanggalAwalBulan) // Lebih efisien dari whereBetween
    ->where('card.date', '<=', $tanggalAkhirBulan)
    ->where(function($query) {
        $query->where('h.status', 'PROCESSED')
              ->orWhereNull('h.status');
    })
    ->select(...)
    ->groupBy('fi.item_id')
    ->get();
```

#### **B. Optimasi Query Internal Use Waste (Line 282-319)**

**Sesudah (Optimized):**
```php
// Gabungkan 2 query menjadi 1 dengan UNION untuk mengurangi overhead
$internalUseWasteQuery = DB::table('outlet_food_inventory_cards as card')
    ->join('outlet_food_inventory_items as fi', 'card.inventory_item_id', '=', 'fi.id')
    ->join('outlet_internal_use_waste_headers as h', 'card.reference_id', '=', 'h.id')
    ->where('card.id_outlet', $outletId)
    ->where('card.warehouse_outlet_id', $warehouseOutletId)
    ->where('card.reference_type', 'outlet_internal_use_waste')
    ->where('card.date', '>=', $tanggalAwalBulan)
    ->where('card.date', '<=', $tanggalAkhirBulan)
    ->where(function($query) use ($typesRequiringApproval, $typesNotRequiringApproval) {
        $query->where(function($q) use ($typesRequiringApproval) {
            $q->whereIn('h.type', $typesRequiringApproval)
              ->where('h.status', 'APPROVED');
        })
        ->orWhere(function($q) use ($typesNotRequiringApproval) {
            $q->whereIn('h.type', $typesNotRequiringApproval)
              ->whereIn('h.status', ['PROCESSED', 'APPROVED']);
        });
    })
    ->select(
        'fi.item_id',
        'h.type',
        DB::raw('SUM(COALESCE(card.out_qty_small, 0)) as total_out_qty_small'),
        DB::raw('SUM(COALESCE(card.out_qty_medium, 0)) as total_out_qty_medium'),
        DB::raw('SUM(COALESCE(card.out_qty_large, 0)) as total_out_qty_large')
    )
    ->groupBy('fi.item_id', 'h.type')
    ->get();
```

#### **C. Limit Query Result (Jika Tidak Perlu Semua Data)**

Jika user tidak perlu melihat semua item sekaligus, tambahkan pagination:

```php
// Untuk query yang mengembalikan banyak data, gunakan chunk atau limit
$wipStockCards = DB::table('outlet_food_inventory_cards as card')
    // ... query ...
    ->limit(1000) // Batasi jika tidak perlu semua
    ->get();
```

---

### **3. Caching Strategy**

**Masalah:** Data yang sama di-query berulang-ulang tanpa caching.

**Solusi:** Implementasi caching untuk data yang jarang berubah.

#### **A. Cache untuk Outlet Stock Report**

**File:** `app/Http/Controllers/OutletStockReportController.php`

**Tambahkan di method `index` atau method yang generate report:**

```php
use Illuminate\Support\Facades\Cache;

public function index(Request $request)
{
    // ... existing code ...
    
    // Generate cache key berdasarkan parameter
    $cacheKey = 'outlet_stock_report_' . $outletId . '_' . $warehouseOutletId . '_' . $bulanCarbon->format('Y-m');
    
    // Cache untuk 5 menit (data report biasanya tidak berubah terlalu sering)
    $data = Cache::remember($cacheKey, 300, function() use ($outletId, $warehouseOutletId, $bulanCarbon) {
        // ... semua query yang berat ...
        return $result;
    });
    
    // Invalidate cache ketika ada perubahan data
    // Tambahkan di method store/update/delete yang mempengaruhi stock
}
```

#### **B. Cache untuk WIP Production List**

**File:** `app/Http/Controllers/OutletWIPController.php`

```php
public function index(Request $request)
{
    $user = auth()->user();
    
    // Cache key
    $cacheKey = 'wip_production_list_' . $user->id_outlet . '_' . md5(json_encode($request->all()));
    
    // Cache 2 menit
    $data = Cache::remember($cacheKey, 120, function() use ($request, $user) {
        // ... existing query ...
        return $result;
    });
    
    return inertia('OutletWIP/Index', ['data' => $data]);
}
```

#### **C. Cache untuk Category Cost Outlet**

**File:** `app/Http/Controllers/OutletInternalUseWasteController.php`

```php
public function index(Request $request)
{
    // ... existing code ...
    
    $cacheKey = 'category_cost_outlet_' . md5(json_encode($request->all()));
    
    $data = Cache::remember($cacheKey, 180, function() use ($request) {
        // ... existing query ...
        return $result;
    });
    
    // ... rest of code ...
}
```

#### **D. Invalidate Cache Strategy**

Tambahkan di method yang mengubah data:

```php
// Di OutletInternalUseWasteController::store()
Cache::forget('category_cost_outlet_*'); // Atau gunakan tag

// Di OutletWIPController::store()
Cache::forget('wip_production_list_*');

// Di StockCutController::potongStockOrderItems()
Cache::forget('outlet_stock_report_*');
```

**Atau gunakan Cache Tags (jika menggunakan Redis/Memcached):**

```php
// Set cache dengan tag
Cache::tags(['outlet_stock', $outletId])->put($cacheKey, $data, 300);

// Invalidate semua cache dengan tag
Cache::tags(['outlet_stock', $outletId])->flush();
```

---

### **4. Optimasi Stock Cut Controller**

**File:** `app/Http/Controllers/StockCutController.php`

**Masalah:** Query BOM dan stock check dilakukan berulang-ulang.

#### **A. Batch Query untuk BOM**

**Sebelum:** Query BOM per item (N+1 problem)

**Sesudah:** Query semua BOM sekaligus

```php
// Ambil semua item_id yang perlu di-check
$itemIds = $orderItems->pluck('item_id')->unique();

// Query semua BOM sekaligus
$boms = DB::table('item_boms')
    ->whereIn('item_id', $itemIds)
    ->get()
    ->groupBy('item_id'); // Group by item_id untuk akses cepat

// Gunakan $boms[$itemId] untuk akses BOM per item
```

#### **B. Batch Query untuk Stock Check**

```php
// Query semua stock sekaligus
$stocks = DB::table('outlet_food_inventory_stocks as s')
    ->join('outlet_food_inventory_items as fi', 's.inventory_item_id', '=', 'fi.id')
    ->where('s.id_outlet', $id_outlet)
    ->where('s.warehouse_outlet_id', $warehouse_id)
    ->whereIn('fi.item_id', $itemIds)
    ->select('fi.item_id', 's.*')
    ->get()
    ->keyBy('item_id'); // Key by item_id untuk akses cepat

// Gunakan $stocks[$itemId] untuk akses stock per item
```

---

### **5. Database Query Monitoring**

**Enable MySQL Slow Query Log:**

```sql
-- Enable slow query log
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 1; -- Log query yang > 1 detik
SET GLOBAL slow_query_log_file = '/var/log/mysql/slow-query.log';
```

**Check Slow Queries:**

```bash
# Lihat slow queries
mysqldumpslow /var/log/mysql/slow-query.log | head -20

# Atau gunakan pt-query-digest (jika ada)
pt-query-digest /var/log/mysql/slow-query.log
```

**Check Query Execution Plan:**

```sql
-- Untuk query yang lambat, cek execution plan
EXPLAIN SELECT ... FROM outlet_food_inventory_cards ...;

-- Pastikan menggunakan index (lihat kolom "key")
-- Jika "key" NULL, berarti tidak menggunakan index (perlu tambah index)
```

---

### **6. Optimasi PHP-FPM (Tambahan)**

Selain optimasi database, pastikan PHP-FPM sudah dioptimasi:

**File:** `/opt/cpanel/ea-php82/root/etc/php-fpm.d/www.conf` (atau sesuai PHP version)

```ini
; Untuk high traffic dengan query berat
pm = dynamic
pm.max_children = 24
pm.start_servers = 12
pm.min_spare_servers = 8
pm.max_spare_servers = 12
pm.max_requests = 100

; Timeout untuk query berat
request_terminate_timeout = 300s
```

**Restart PHP-FPM setelah perubahan.**

---

## 📋 **CHECKLIST IMPLEMENTASI**

### **Phase 1: Database Indexing (PRIORITAS TINGGI)**
- [ ] Tambahkan index untuk `outlet_food_inventory_cards`
- [ ] Tambahkan index untuk `outlet_wip_production_headers`
- [ ] Tambahkan index untuk `outlet_internal_use_waste_headers`
- [ ] Tambahkan index untuk `stock_cut_logs` dan `stock_cut_details`
- [ ] Tambahkan index untuk `order_items`
- [ ] Test query performance setelah index ditambahkan

### **Phase 2: Query Optimization**
- [ ] Optimasi query WIP Production di `OutletStockReportController`
- [ ] Optimasi query Internal Use Waste di `OutletStockReportController`
- [ ] Optimasi query Stock Cut (batch query untuk BOM dan stock)
- [ ] Test query execution plan (EXPLAIN)

### **Phase 3: Caching**
- [ ] Implementasi cache untuk Outlet Stock Report
- [ ] Implementasi cache untuk WIP Production list
- [ ] Implementasi cache untuk Category Cost Outlet
- [ ] Setup cache invalidation strategy
- [ ] Test cache hit rate

### **Phase 4: Monitoring**
- [ ] Enable MySQL slow query log
- [ ] Monitor slow queries selama 1-2 hari
- [ ] Optimasi query yang masih lambat
- [ ] Monitor CPU usage setelah optimasi

---

## 🎯 **EXPECTED RESULTS**

Setelah optimasi:

| Metric | Sebelum | Sesudah |
|--------|---------|---------|
| **CPU Usage** | 100% | 40-60% |
| **Query Time (Stock Report)** | 5-10 detik | 0.5-1 detik |
| **Query Time (WIP Production)** | 3-5 detik | 0.3-0.5 detik |
| **Query Time (Stock Cut)** | 10-20 detik | 1-2 detik |
| **Cache Hit Rate** | 0% | 60-80% |
| **Database Load** | High | Medium |

---

## ⚠️ **CATATAN PENTING**

1. **Index akan memperlambat INSERT/UPDATE sedikit, tapi sangat mempercepat SELECT**
   - Trade-off yang worth it untuk aplikasi yang banyak read

2. **Cache harus di-invalidate ketika data berubah**
   - Pastikan semua method yang mengubah data juga invalidate cache

3. **Monitor slow query log secara berkala**
   - Query yang lambat harus dioptimasi atau di-cache

4. **Test di staging dulu sebelum production**
   - Pastikan tidak ada breaking changes

---

## 🚀 **URUTAN IMPLEMENTASI (RECOMMENDED)**

1. **HARI 1: Database Indexing**
   - Tambahkan semua index yang direkomendasikan
   - Test query performance
   - Monitor CPU usage

2. **HARI 2: Query Optimization**
   - Optimasi query yang paling berat dulu (Stock Report)
   - Test dan verify
   - Monitor CPU usage

3. **HARI 3: Caching**
   - Implementasi caching untuk fitur yang paling sering diakses
   - Setup cache invalidation
   - Monitor cache hit rate

4. **HARI 4-7: Monitoring & Fine-tuning**
   - Monitor slow query log
   - Fine-tune query yang masih lambat
   - Adjust cache TTL jika perlu

---

**Mulai dengan Database Indexing - ini akan memberikan impact terbesar dengan effort terkecil!** ✅

