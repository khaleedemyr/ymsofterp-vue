# Analisa Masalah Performa Menu HO Finance

## Ringkasan Masalah
Ketika beberapa user mengakses menu-menu di dalam group "HO Finance", CPU server mencapai 100%. Masalah ini disebabkan oleh **N+1 Query Problem** dan **Extreme Join Queries** yang terjadi di dalam loop transform data.

## Menu yang Terkena Dampak
1. **Contra Bon** (`/contra-bons`)
2. **Food Payment** (`/food-payments`)
3. **Outlet Payment** (`/outlet-payments`)
4. **Non Food Payment** (`/non-food-payments`)

---

## 1. CONTRA BON - Masalah Utama

### File: `app/Http/Controllers/ContraBonController.php`

### Masalah di Method `index()` (Line 19-53)

#### A. Eager Loading yang Berlebihan (Line 21)
```php
$query = ContraBon::with([
    'supplier', 
    'purchaseOrder', 
    'retailFood', 
    'warehouseRetailFood', 
    'retailNonFood', 
    'creator', 
    'sources.purchaseOrder', 
    'sources.retailFood', 
    'sources.warehouseRetailFood', 
    'sources.retailNonFood'
])->orderByDesc('created_at');
```

**Masalah:**
- Eager loading terlalu banyak relasi sekaligus
- Nested eager loading (`sources.purchaseOrder`, dll) bisa menyebabkan Cartesian Product
- Jika ada 10 Contra Bon dengan masing-masing 3 sources, bisa menghasilkan 30+ rows yang perlu di-process

#### B. N+1 Query Problem di Transform (Line 56-200)

**Masalah Kritis:** Di dalam loop `transform()`, untuk setiap Contra Bon, dilakukan multiple queries:

1. **Query GR Numbers dari Items** (Line 79-86)
   ```php
   $grNumbersFromItems = DB::table('food_contra_bon_items as cbi')
       ->join('food_good_receive_items as gri', 'cbi.gr_item_id', '=', 'gri.id')
       ->join('food_good_receives as gr', 'gri.good_receive_id', '=', 'gr.id')
       ->where('cbi.contra_bon_id', $contraBon->id)
       ->whereNotNull('cbi.gr_item_id')
       ->distinct()
       ->pluck('gr.gr_number')
       ->toArray();
   ```
   **Dampak:** Jika ada 10 Contra Bon di pagination, ini akan dieksekusi 10 kali!

2. **Query PR Numbers** (Line 91-97)
   ```php
   $prNumbers = DB::table('pr_foods as pr')
       ->join('pr_food_items as pri', 'pr.id', '=', 'pri.pr_food_id')
       ->join('purchase_order_food_items as poi', 'pri.id', '=', 'poi.pr_food_item_id')
       ->where('poi.purchase_order_food_id', $po->id)
       ->distinct()
       ->pluck('pr.pr_number')
       ->toArray();
   ```
   **Dampak:** Query dengan 3 JOINs di dalam loop!

3. **Query RO Supplier Data** (Line 101-107)
   ```php
   $roData = DB::table('food_floor_orders as fo')
       ->join('purchase_order_food_items as poi', 'fo.id', '=', 'poi.ro_id')
       ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
       ->where('poi.purchase_order_food_id', $po->id)
       ->select('fo.order_number', 'o.nama_outlet')
       ->distinct()
       ->get();
   ```
   **Dampak:** Query dengan 2 JOINs di dalam loop!

4. **Query Individual Retail Food** (Line 192)
   ```php
   $retailFood = \App\Models\RetailFood::find($contraBon->source_id);
   ```
   **Dampak:** Query individual untuk setiap Contra Bon dengan source_type retail_food!

5. **Query Individual Warehouse Retail Food** (Line 198)
   ```php
   $warehouseRetailFood = \App\Models\RetailWarehouseFood::find($contraBon->source_id);
   ```
   **Dampak:** Query individual untuk setiap Contra Bon dengan source_type warehouse_retail_food!

### Perhitungan Query untuk 10 Contra Bon:
- **Base Query:** 1 query (dengan eager loading)
- **GR Numbers Query:** 10 queries (1 per Contra Bon)
- **PR Numbers Query:** ~5 queries (jika 5 Contra Bon punya PO dengan source_type pr_foods)
- **RO Supplier Query:** ~3 queries (jika 3 Contra Bon punya PO dengan source_type ro_supplier)
- **Retail Food Query:** ~2 queries (jika 2 Contra Bon punya source_type retail_food)
- **Warehouse Retail Food Query:** ~1 query

**Total: ~22 queries untuk 10 records!**

**Jika ada 10 user mengakses bersamaan: 220 queries!**

---

## 2. FOOD PAYMENT - Masalah Utama

### File: `app/Http/Controllers/FoodPaymentController.php`

### Masalah di Method `index()` (Line 18-87)

#### A. Eager Loading dengan Closure (Line 20-22)
```php
$query = FoodPayment::with([
    'supplier', 
    'creator', 
    'financeManager', 
    'contraBons' => function($q) {
        $q->select('food_contra_bons.id', 'food_contra_bons.supplier_invoice_number', 'food_contra_bons.number');
    }
])->orderByDesc('created_at');
```

**Masalah:**
- Eager loading `contraBons` dengan closure bisa menyebabkan subquery yang tidak optimal
- Jika 1 Food Payment punya 5 Contra Bon, dan ada 10 Food Payment, ini bisa menghasilkan 50+ rows

#### B. Multiple `whereHas()` di Search (Line 39-57)
```php
->orWhereHas('supplier', function($q2) use ($search) {
    $q2->where('name', 'like', "%$search%");
})
->orWhereHas('creator', function($q2) use ($search) {
    $q2->where('nama_lengkap', 'like', "%$search%");
})
->orWhereHas('financeManager', function($q2) use ($search) {
    $q2->where('nama_lengkap', 'like', "%$search%");
})
->orWhereHas('gmFinance', function($q2) use ($search) {
    $q2->where('nama_lengkap', 'like', "%$search%");
})
->orWhereHas('contraBons', function($q2) use ($search) {
    $q2->where('supplier_invoice_number', 'like', "%$search%");
});
```

**Masalah:**
- Setiap `whereHas()` menghasilkan subquery
- Jika user melakukan search, ini akan menghasilkan 5+ subqueries
- Subquery ini bisa sangat lambat jika tabel besar

#### C. Transform dengan Collection Operation (Line 73-81)
```php
$payments->getCollection()->transform(function($payment) {
    $payment->invoice_numbers = $payment->contraBons
        ->pluck('supplier_invoice_number')
        ->filter()
        ->unique()
        ->values()
        ->toArray();
    return $payment;
});
```

**Masalah:**
- Meskipun tidak ada query tambahan, operasi collection di loop bisa lambat jika data besar

### Masalah di Method `getContraBonUnpaid()` (Line 678-847)

#### A. Query di dalam Loop (Line 730-844)
```php
$contraBons = $contraBons->map(function($contraBon) {
    // ... banyak query di dalam loop
    if ($contraBon->source_type === 'purchase_order' && $contraBon->purchaseOrder) {
        if ($contraBon->purchaseOrder->source_type === 'ro_supplier') {
            $outletData = \DB::table('food_floor_orders as fo')
                ->join('purchase_order_food_items as poi', 'fo.id', '=', 'poi.ro_id')
                ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
                ->where('poi.purchase_order_food_id', $contraBon->purchaseOrder->id)
                ->select('o.nama_outlet')
                ->distinct()
                ->get();
            // ...
        }
    }
    // ... lebih banyak query
});
```

**Masalah:**
- Query dengan JOINs di dalam loop untuk setiap Contra Bon
- Jika ada 50 Contra Bon unpaid, ini bisa menghasilkan 50+ queries dengan JOINs

---

## 3. OUTLET PAYMENT - Masalah Utama

### File: `app/Http/Controllers/OutletPaymentController.php`

### Masalah di Method `index()` (Line 15-168)

#### A. Multiple Left Joins (Line 25-39)
```php
$query = OutletPayment::query()
    ->leftJoin('tbl_data_outlet as o', 'outlet_payments.outlet_id', '=', 'o.id_outlet')
    ->leftJoin('users as u', 'outlet_payments.created_by', '=', 'u.id')
    ->leftJoin('outlet_food_good_receives as gr', 'outlet_payments.gr_id', '=', 'gr.id')
    ->leftJoin('retail_warehouse_sales as rws', 'outlet_payments.retail_sales_id', '=', 'rws.id')
    ->select(...)
```

**Masalah:**
- 4 LEFT JOINs sekaligus bisa lambat jika tabel besar
- LEFT JOIN dengan `retail_warehouse_sales` bisa sangat lambat jika tabel ini besar

#### B. Query GR List dengan Complex Join (Line 90-121)
```php
$grQuery = DB::table('outlet_food_good_receives as gr')
    ->leftJoin('outlet_payments as op', function($join) {
        $join->on('gr.id', '=', 'op.gr_id')
             ->where('op.status', '!=', 'cancelled');
    })
    ->leftJoin('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
    ->whereNull('op.id')
    ->select(...)
```

**Masalah:**
- Query ini dieksekusi setiap kali halaman index di-load
- LEFT JOIN dengan subquery condition bisa lambat

#### C. Query di Method `getGrItems()` (Line 901-953)
```php
$items = DB::table('outlet_food_good_receive_items as gri')
    ->join('items as i', 'gri.item_id', '=', 'i.id')
    ->join('units as u', 'gri.unit_id', '=', 'u.id')
    ->join('outlet_food_good_receives as gr', 'gri.outlet_food_good_receive_id', '=', 'gr.id')
    ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
    ->leftJoin('food_floor_order_items as foi', function($join) {
        $join->on('gri.item_id', '=', 'foi.item_id')
             ->on('do.floor_order_id', '=', 'foi.floor_order_id');
    })
    ->where('gri.outlet_food_good_receive_id', $grId)
    ->whereNull('gr.deleted_at')
    ->select(...)
```

**Masalah:**
- 5 JOINs (4 INNER + 1 LEFT) untuk setiap GR
- Jika user membuka detail beberapa GR, ini akan dieksekusi berkali-kali

#### D. Query di Method `reportInvoiceOutlet()` (Line 1071-1400)

**Masalah Kritis:**
- Method ini melakukan UNION antara 2 query besar (GR dan RWS)
- Setiap query punya 7-8 JOINs
- Query untuk detail items dilakukan di dalam loop (Line 1284-1329)
- Jika ada 100 records di pagination, ini bisa menghasilkan 200+ queries!

---

## 4. NON FOOD PAYMENT - Masalah Utama

### File: `app/Http/Controllers/NonFoodPaymentController.php`

### Masalah di Method `index()` (Line 19-176)

#### A. Multiple Left Joins (Line 34-47)
```php
$query = NonFoodPayment::withoutGlobalScopes()
    ->leftJoin('suppliers as s', 'non_food_payments.supplier_id', '=', 's.id')
    ->leftJoin('users as u', 'non_food_payments.created_by', '=', 'u.id')
    ->leftJoin('purchase_order_ops as poo', 'non_food_payments.purchase_order_ops_id', '=', 'poo.id')
    ->leftJoin('purchase_requisitions as pr', 'non_food_payments.purchase_requisition_id', '=', 'pr.id')
    ->select(...)
    ->distinct();
```

**Masalah:**
- 4 LEFT JOINs sekaligus
- `distinct()` setelah JOIN bisa sangat lambat karena MySQL perlu sort data

#### B. N+1 Query Problem di Transform (Line 106-162)
```php
$payments->getCollection()->transform(function($payment) {
    if ($payment->purchase_order_ops_id) {
        // Query outlet breakdown untuk setiap payment
        $outletBreakdown = DB::table('purchase_order_ops_items as poi')
            ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
            ->leftJoin('tbl_data_outlet as o', 'pr.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('purchase_requisition_categories as prc', 'pr.category_id', '=', 'prc.id')
            ->where('poi.purchase_order_ops_id', $payment->purchase_order_ops_id)
            ->select(...)
            ->groupBy(...)
            ->get();
    }
});
```

**Masalah Kritis:**
- Query dengan 3 LEFT JOINs + GROUP BY di dalam loop!
- Jika ada 10 Non Food Payment di pagination, ini akan dieksekusi 10 kali
- GROUP BY di dalam loop sangat lambat!

**Perhitungan:**
- 10 payments × 1 query dengan 3 JOINs + GROUP BY = 10 queries berat
- Jika ada 10 user: 100 queries berat bersamaan!

---

## Ringkasan Masalah

### 1. N+1 Query Problem
- **Contra Bon:** ~22 queries untuk 10 records
- **Food Payment:** ~5-10 queries untuk 10 records (tergantung search)
- **Non Food Payment:** ~10 queries berat untuk 10 records
- **Outlet Payment:** Bervariasi, bisa 50+ queries untuk detail items

### 2. Extreme Join Queries
- Multiple JOINs (4-8 JOINs) di dalam loop
- LEFT JOINs yang tidak perlu
- JOINs tanpa index yang tepat

### 3. Query di Transform Loop
- Semua controller melakukan query di dalam `transform()` atau `map()`
- Query berat (dengan JOINs dan GROUP BY) di dalam loop

### 4. Missing Indexes
- Kemungkinan besar foreign keys tidak ter-index dengan baik
- JOIN conditions tidak ter-index

---

## Dampak ke Server

### Skenario: 10 User Mengakses Bersamaan

1. **Contra Bon:**
   - 10 user × 22 queries = **220 queries**
   - Setiap query dengan 2-3 JOINs
   - **Total: ~660 JOIN operations**

2. **Non Food Payment:**
   - 10 user × 10 queries = **100 queries**
   - Setiap query dengan 3 JOINs + GROUP BY
   - **Total: ~300 JOIN operations + 100 GROUP BY**

3. **Food Payment:**
   - 10 user × 10 queries = **100 queries**
   - Dengan multiple `whereHas()` subqueries
   - **Total: ~500 subqueries**

4. **Outlet Payment:**
   - 10 user × 50 queries = **500 queries**
   - Dengan 5-7 JOINs per query
   - **Total: ~3000 JOIN operations**

**Total Estimasi: ~4500+ database operations bersamaan!**

Ini akan menyebabkan:
- CPU 100% karena MySQL processing
- Memory spike karena banyak query result sets
- Connection pool exhaustion
- Slow query log penuh
- Timeout errors

---

## Rekomendasi Perbaikan (Strategi)

### Prioritas 1: Fix N+1 Query Problem

1. **Batch Query untuk Data yang Di-loop**
   - Kumpulkan semua IDs yang perlu di-query
   - Lakukan 1 query untuk semua data sekaligus
   - Map hasil query ke collection

2. **Eager Loading yang Tepat**
   - Gunakan eager loading untuk relasi yang pasti digunakan
   - Hindari nested eager loading yang berlebihan
   - Gunakan `withCount()` untuk aggregasi

3. **Pre-compute Data**
   - Buat computed columns di database
   - Atau cache hasil transform

### Prioritas 2: Optimize Join Queries

1. **Kurangi JOINs**
   - Gunakan subquery jika lebih efisien
   - Pisahkan query besar menjadi beberapa query kecil
   - Gunakan raw query dengan SELECT yang spesifik

2. **Add Indexes**
   - Index semua foreign keys
   - Index columns yang digunakan di JOIN conditions
   - Composite indexes untuk WHERE + JOIN conditions

3. **Query Optimization**
   - Gunakan `EXPLAIN` untuk setiap query
   - Hindari `SELECT *`
   - Gunakan `LIMIT` yang tepat

### Prioritas 3: Refactor Transform Logic

1. **Move Query ke Before Transform**
   - Lakukan semua query sebelum loop
   - Map hasil query ke array/hash
   - Gunakan array lookup di dalam loop

2. **Lazy Loading untuk Detail**
   - Jangan load detail di index
   - Load detail via AJAX saat dibutuhkan
   - Gunakan pagination yang lebih kecil

3. **Caching**
   - Cache hasil query yang tidak sering berubah
   - Cache computed values
   - Use Redis untuk frequently accessed data

---

## Next Steps

1. **Buat Indexes** untuk foreign keys dan JOIN conditions
2. **Refactor ContraBonController::index()** - batch query untuk GR numbers, PR numbers, RO data
3. **Refactor NonFoodPaymentController::index()** - batch query untuk outlet breakdown
4. **Refactor FoodPaymentController::index()** - optimize whereHas queries
5. **Refactor OutletPaymentController** - lazy load detail items
6. **Add Query Monitoring** - log slow queries
7. **Performance Testing** - test dengan multiple concurrent users

---

## File yang Perlu Diperbaiki

1. `app/Http/Controllers/ContraBonController.php` - Method `index()`
2. `app/Http/Controllers/FoodPaymentController.php` - Method `index()` dan `getContraBonUnpaid()`
3. `app/Http/Controllers/NonFoodPaymentController.php` - Method `index()`
4. `app/Http/Controllers/OutletPaymentController.php` - Method `index()`, `getGrItems()`, `reportInvoiceOutlet()`

---

## Database Indexes yang Diperlukan

```sql
-- Contra Bon
CREATE INDEX idx_contra_bon_source_type_id ON food_contra_bons(source_type, source_id);
CREATE INDEX idx_contra_bon_items_contra_bon_id ON food_contra_bon_items(contra_bon_id);
CREATE INDEX idx_contra_bon_items_gr_item_id ON food_contra_bon_items(gr_item_id);

-- Food Payment
CREATE INDEX idx_food_payment_contra_bon_food_payment ON food_payment_contra_bons(food_payment_id);
CREATE INDEX idx_food_payment_contra_bon_contra_bon ON food_payment_contra_bons(contra_bon_id);

-- Non Food Payment
CREATE INDEX idx_poo_items_poo_id ON purchase_order_ops_items(purchase_order_ops_id);
CREATE INDEX idx_poo_items_source_id ON purchase_order_ops_items(source_id);

-- Outlet Payment
CREATE INDEX idx_outlet_payment_gr_id ON outlet_payments(gr_id);
CREATE INDEX idx_outlet_payment_retail_sales_id ON outlet_payments(retail_sales_id);
CREATE INDEX idx_gr_items_gr_id ON outlet_food_good_receive_items(outlet_food_good_receive_id);
CREATE INDEX idx_gr_items_item_id ON outlet_food_good_receive_items(item_id);
```

---

**Dokumen ini dibuat untuk analisa awal. Perlu dibuat strategi perbaikan detail untuk setiap controller.**
