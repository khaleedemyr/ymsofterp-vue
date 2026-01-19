# Strategi Implementasi Perbaikan Performa HO Finance - AMAN & TERKONTROL

## Prinsip Keamanan

1. **Incremental Changes** - Perbaikan dilakukan bertahap, satu controller per waktu
2. **Backward Compatible** - Output data tetap sama, hanya cara query yang dioptimasi
3. **Validation First** - Setiap perubahan divalidasi sebelum deploy
4. **Easy Rollback** - Setiap perubahan bisa di-rollback dengan mudah
5. **Data Integrity** - Tidak ada perubahan struktur data atau logic bisnis

---

## Phase 1: Persiapan & Backup

### 1.1 Backup Database
```bash
# Backup database sebelum perubahan
mysqldump -u [user] -p [database] > backup_before_performance_fix_$(date +%Y%m%d_%H%M%S).sql
```

### 1.2 Backup Code
```bash
# Buat branch baru untuk perbaikan
git checkout -b performance-fix-ho-finance
git commit -m "Backup: Before performance optimization"
```

### 1.3 Enable Query Logging
```php
// Tambahkan di AppServiceProvider untuk monitoring
DB::listen(function ($query) {
    if ($query->time > 100) { // Log queries > 100ms
        \Log::warning('Slow Query', [
            'sql' => $query->sql,
            'bindings' => $query->bindings,
            'time' => $query->time
        ]);
    }
});
```

---

## Phase 2: Implementasi Bertahap (Satu Controller Per Waktu)

### Prioritas: Non Food Payment (Paling Mudah & Paling Berdampak)

**Alasan:**
- Masalah jelas: query di loop transform
- Solusi sederhana: batch query sebelum loop
- Risiko rendah: tidak mengubah output structure

---

## Phase 3: Strategi Perbaikan Per Controller

### 3.1 NON FOOD PAYMENT - Strategi Aman

#### Masalah Saat Ini:
```php
// Line 106-162: Query di dalam loop
$payments->getCollection()->transform(function($payment) {
    if ($payment->purchase_order_ops_id) {
        $outletBreakdown = DB::table('purchase_order_ops_items as poi')
            ->leftJoin(...)
            ->where('poi.purchase_order_ops_id', $payment->purchase_order_ops_id)
            ->get(); // Query untuk setiap payment!
    }
});
```

#### Solusi Aman:
```php
// 1. Kumpulkan semua PO IDs yang perlu di-query
$poIds = $payments->getCollection()
    ->pluck('purchase_order_ops_id')
    ->filter()
    ->unique()
    ->values()
    ->toArray();

// 2. Batch query sekali untuk semua PO
$outletBreakdowns = [];
if (!empty($poIds)) {
    $allBreakdowns = DB::table('purchase_order_ops_items as poi')
        ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
        ->leftJoin('tbl_data_outlet as o', 'pr.outlet_id', '=', 'o.id_outlet')
        ->leftJoin('purchase_requisition_categories as prc', 'pr.category_id', '=', 'prc.id')
        ->whereIn('poi.purchase_order_ops_id', $poIds) // WHERE IN instead of loop
        ->select(
            'poi.purchase_order_ops_id', // Tambahkan ini untuk grouping
            'pr.outlet_id',
            'o.nama_outlet as outlet_name',
            'prc.name as category_name',
            // ... other fields
            DB::raw('SUM(poi.total) as outlet_total')
        )
        ->groupBy('poi.purchase_order_ops_id', 'pr.outlet_id', ...) // Group by PO ID juga
        ->get()
        ->groupBy('purchase_order_ops_id'); // Group by PO ID untuk easy lookup
    
    // 3. Map hasil ke array untuk lookup cepat
    foreach ($allBreakdowns as $poId => $breakdown) {
        $outletBreakdowns[$poId] = $breakdown;
    }
}

// 4. Gunakan lookup di transform (tanpa query)
$payments->getCollection()->transform(function($payment) use ($outletBreakdowns) {
    if ($payment->purchase_order_ops_id) {
        $payment->outlet_breakdown = $outletBreakdowns[$payment->purchase_order_ops_id] ?? collect([]);
    } else {
        // Logic untuk PR payment tetap sama
    }
    return $payment;
});
```

**Keuntungan:**
- ✅ Output data 100% sama
- ✅ Dari 10 queries menjadi 1 query
- ✅ Tidak mengubah struktur response
- ✅ Mudah di-rollback (tinggal revert code)

**Validasi:**
1. Bandingkan output sebelum & sesudah (JSON response)
2. Pastikan semua outlet breakdown muncul
3. Pastikan total amount sama
4. Test dengan berbagai filter (supplier, status, date)

---

### 3.2 CONTRA BON - Strategi Aman

#### Masalah Saat Ini:
```php
// Line 56-200: Multiple queries di dalam loop
$contraBons->getCollection()->transform(function ($contraBon) {
    // Query GR numbers
    $grNumbersFromItems = DB::table('food_contra_bon_items as cbi')
        ->where('cbi.contra_bon_id', $contraBon->id)
        ->get();
    
    // Query PR numbers
    $prNumbers = DB::table('pr_foods as pr')
        ->where('poi.purchase_order_food_id', $po->id)
        ->get();
    
    // ... lebih banyak query
});
```

#### Solusi Aman (Bertahap):

**Step 1: Batch Query GR Numbers**
```php
// Sebelum transform, kumpulkan semua Contra Bon IDs
$contraBonIds = $contraBons->getCollection()->pluck('id')->toArray();

// Batch query GR numbers untuk semua Contra Bon sekaligus
$grNumbersMap = [];
if (!empty($contraBonIds)) {
    $allGrNumbers = DB::table('food_contra_bon_items as cbi')
        ->join('food_good_receive_items as gri', 'cbi.gr_item_id', '=', 'gri.id')
        ->join('food_good_receives as gr', 'gri.good_receive_id', '=', 'gr.id')
        ->whereIn('cbi.contra_bon_id', $contraBonIds) // WHERE IN
        ->whereNotNull('cbi.gr_item_id')
        ->select('cbi.contra_bon_id', 'gr.gr_number')
        ->distinct()
        ->get()
        ->groupBy('contra_bon_id')
        ->map(function($items) {
            return $items->pluck('gr_number')->unique()->filter()->values()->toArray();
        });
    
    $grNumbersMap = $allGrNumbers->toArray();
}

// Di transform, gunakan lookup
$contraBons->getCollection()->transform(function ($contraBon) use ($grNumbersMap) {
    $contraBon->gr_numbers = $grNumbersMap[$contraBon->id] ?? [];
    // ... rest of logic
});
```

**Step 2: Batch Query PR Numbers**
```php
// Kumpulkan semua PO IDs
$poIds = $contraBons->getCollection()
    ->filter(function($cb) {
        return $cb->purchaseOrder && $cb->purchaseOrder->source_type === 'pr_foods';
    })
    ->pluck('purchaseOrder.id')
    ->unique()
    ->values()
    ->toArray();

// Batch query PR numbers
$prNumbersMap = [];
if (!empty($poIds)) {
    $allPrNumbers = DB::table('pr_foods as pr')
        ->join('pr_food_items as pri', 'pr.id', '=', 'pri.pr_food_id')
        ->join('purchase_order_food_items as poi', 'pri.id', '=', 'poi.pr_food_item_id')
        ->whereIn('poi.purchase_order_food_id', $poIds)
        ->select('poi.purchase_order_food_id', 'pr.pr_number')
        ->distinct()
        ->get()
        ->groupBy('purchase_order_food_id')
        ->map(function($items) {
            return $items->pluck('pr_number')->unique()->filter()->values()->toArray();
        });
    
    $prNumbersMap = $allPrNumbers->toArray();
}
```

**Step 3: Batch Query RO Supplier Data**
```php
// Kumpulkan PO IDs dengan source_type ro_supplier
$roPoIds = $contraBons->getCollection()
    ->filter(function($cb) {
        return $cb->purchaseOrder && $cb->purchaseOrder->source_type === 'ro_supplier';
    })
    ->pluck('purchaseOrder.id')
    ->unique()
    ->values()
    ->toArray();

// Batch query RO data
$roDataMap = [];
if (!empty($roPoIds)) {
    $allRoData = DB::table('food_floor_orders as fo')
        ->join('purchase_order_food_items as poi', 'fo.id', '=', 'poi.ro_id')
        ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
        ->whereIn('poi.purchase_order_food_id', $roPoIds)
        ->select('poi.purchase_order_food_id', 'fo.order_number', 'o.nama_outlet')
        ->distinct()
        ->get()
        ->groupBy('purchase_order_food_id')
        ->map(function($items) {
            return [
                'order_numbers' => $items->pluck('order_number')->unique()->filter()->values()->toArray(),
                'outlet_names' => $items->pluck('nama_outlet')->unique()->filter()->values()->toArray()
            ];
        });
    
    $roDataMap = $allRoData->toArray();
}
```

**Keuntungan:**
- ✅ Output tetap sama
- ✅ Dari ~22 queries menjadi ~4 queries
- ✅ Implementasi bertahap (bisa test per step)

---

### 3.3 FOOD PAYMENT - Strategi Aman

#### Masalah: Multiple `whereHas()` subqueries

#### Solusi: Gunakan JOINs dengan subquery atau raw query
```php
// Ganti whereHas dengan LEFT JOIN + WHERE
if ($request->search) {
    $search = $request->search;
    $query->leftJoin('suppliers as s', 'food_payments.supplier_id', '=', 's.id')
          ->leftJoin('users as u_creator', 'food_payments.created_by', '=', 'u_creator.id')
          ->leftJoin('users as u_fm', 'food_payments.finance_manager_approved_by', '=', 'u_fm.id')
          ->leftJoin('users as u_gm', 'food_payments.gm_finance_approved_by', '=', 'u_gm.id')
          ->leftJoin('food_payment_contra_bons as fpcb', 'food_payments.id', '=', 'fpcb.food_payment_id')
          ->leftJoin('food_contra_bons as fc', 'fpcb.contra_bon_id', '=', 'fc.id')
          ->where(function($q) use ($search) {
              $q->where('food_payments.number', 'like', "%$search%")
                ->orWhere('food_payments.payment_type', 'like', "%$search%")
                ->orWhere('s.name', 'like', "%$search%") // Direct column, bukan subquery
                ->orWhere('u_creator.nama_lengkap', 'like', "%$search%")
                ->orWhere('u_fm.nama_lengkap', 'like', "%$search%")
                ->orWhere('u_gm.nama_lengkap', 'like', "%$search%")
                ->orWhere('fc.supplier_invoice_number', 'like', "%$search%");
          })
          ->select('food_payments.*') // Pastikan select hanya food_payments columns
          ->distinct(); // Hindari duplicate dari JOINs
}
```

**Keuntungan:**
- ✅ Lebih cepat dari whereHas subqueries
- ✅ Output tetap sama
- ✅ Mudah di-rollback

---

### 3.4 OUTLET PAYMENT - Strategi Aman

#### Masalah: Query detail items di loop

#### Solusi: Lazy Loading via AJAX
- Jangan load detail items di index
- Load detail via API endpoint saat user expand/collapse
- Method `getGrItems()` dan `getRetailSalesItems()` sudah ada, tinggal digunakan

**Perubahan Minimal:**
- Hapus query detail items dari `reportInvoiceOutlet()`
- Load detail via AJAX saat dibutuhkan
- Atau batasi detail items hanya untuk current page

---

## Phase 4: Testing & Validation

### 4.1 Checklist Validasi Per Controller

#### Non Food Payment:
- [ ] Output JSON response sama persis (bandingkan sebelum/sesudah)
- [ ] Semua outlet breakdown muncul
- [ ] Total amount per outlet benar
- [ ] Filter supplier bekerja
- [ ] Filter status bekerja
- [ ] Filter date bekerja
- [ ] Search bekerja
- [ ] Pagination bekerja
- [ ] Performance: Query count berkurang (cek query log)

#### Contra Bon:
- [ ] Output JSON response sama persis
- [ ] Source numbers muncul semua
- [ ] Source outlets muncul semua
- [ ] Source type display benar
- [ ] Filter search bekerja
- [ ] Filter status bekerja
- [ ] Filter date bekerja
- [ ] Performance: Query count berkurang drastis

#### Food Payment:
- [ ] Output JSON response sama persis
- [ ] Search bekerja dengan baik
- [ ] Filter bekerja
- [ ] Contra bon relations muncul
- [ ] Performance: Query lebih cepat

#### Outlet Payment:
- [ ] Index page load cepat
- [ ] Detail items bisa di-load via AJAX
- [ ] Report invoice outlet tetap berfungsi
- [ ] Performance: Index query lebih cepat

### 4.2 Performance Testing

```php
// Buat script test sederhana
Route::get('/test-performance', function() {
    $start = microtime(true);
    $queryCount = 0;
    
    DB::listen(function() use (&$queryCount) {
        $queryCount++;
    });
    
    // Test controller
    $controller = new \App\Http\Controllers\NonFoodPaymentController();
    $request = new \Illuminate\Http\Request();
    $response = $controller->index($request);
    
    $end = microtime(true);
    $time = ($end - $start) * 1000; // milliseconds
    
    return [
        'time_ms' => $time,
        'query_count' => $queryCount,
        'memory_mb' => memory_get_peak_usage(true) / 1024 / 1024
    ];
});
```

**Target:**
- Query count: Turun 70-90%
- Response time: Turun 50-80%
- Memory: Stabil atau turun

### 4.3 Manual Testing Checklist

1. **Test dengan Data Real:**
   - Login sebagai user yang punya akses
   - Buka setiap menu (Contra Bon, Food Payment, dll)
   - Pastikan semua data muncul
   - Test filter, search, pagination

2. **Test dengan Multiple Users:**
   - Buka di beberapa browser/tab
   - Pastikan tidak ada error
   - Monitor CPU usage (harus turun)

3. **Test Edge Cases:**
   - Data kosong
   - Data dengan banyak relations
   - Search dengan special characters
   - Filter dengan nilai tidak valid

---

## Phase 5: Rollback Plan

### 5.1 Jika Ada Masalah

**Quick Rollback:**
```bash
# Rollback code
git checkout [previous-commit-hash]
# atau
git revert [commit-hash]

# Restore database jika perlu (jarang diperlukan karena tidak ada perubahan schema)
mysql -u [user] -p [database] < backup_before_performance_fix_[timestamp].sql
```

### 5.2 Feature Flag (Opsional - Extra Safety)

```php
// Di .env
ENABLE_PERFORMANCE_OPTIMIZATION=false

// Di Controller
if (config('app.enable_performance_optimization', false)) {
    // New optimized code
} else {
    // Old code (fallback)
}
```

---

## Phase 6: Monitoring Post-Deploy

### 6.1 Monitor Query Log
```php
// Pastikan query logging aktif
// Cek log untuk slow queries
tail -f storage/logs/laravel.log | grep "Slow Query"
```

### 6.2 Monitor Server Resources
```bash
# Monitor CPU
top -p $(pgrep php-fpm | head -1)

# Monitor MySQL
mysqladmin -u root -p processlist
```

### 6.3 Monitor Error Logs
```bash
# Cek error logs
tail -f storage/logs/laravel.log | grep ERROR
```

---

## Timeline Implementasi

### Week 1: Non Food Payment
- Day 1-2: Implementasi
- Day 3: Testing & Validation
- Day 4: Deploy & Monitor
- Day 5: Fix issues jika ada

### Week 2: Contra Bon
- Day 1-3: Implementasi bertahap (3 steps)
- Day 4: Testing & Validation
- Day 5: Deploy & Monitor

### Week 3: Food Payment & Outlet Payment
- Day 1-2: Food Payment
- Day 3-4: Outlet Payment
- Day 5: Final testing & deploy

---

## Garansi Keamanan

### Yang TIDAK Akan Berubah:
1. ✅ Output data structure (JSON response)
2. ✅ Business logic
3. ✅ Database schema
4. ✅ API contracts
5. ✅ User experience

### Yang AKAN Berubah:
1. ✅ Query execution (lebih efisien)
2. ✅ Number of queries (lebih sedikit)
3. ✅ Response time (lebih cepat)
4. ✅ Server load (lebih rendah)

### Jika Ada Masalah:
1. ✅ Rollback dalam 5 menit (git revert)
2. ✅ Database tidak perlu restore (tidak ada perubahan schema)
3. ✅ Tidak ada data loss risk
4. ✅ Bisa test di staging dulu

---

## Rekomendasi: Mulai dengan Non Food Payment

**Alasan:**
1. ✅ Paling mudah diimplementasi
2. ✅ Paling berdampak (query di loop dengan GROUP BY)
3. ✅ Risiko paling rendah
4. ✅ Mudah di-rollback
5. ✅ Bisa jadi proof of concept

**Setelah Non Food Payment sukses, lanjut ke controller lain dengan confidence tinggi.**

---

## Next Step: Implementasi Non Food Payment?

Saya bisa mulai implementasi Non Food Payment dengan:
1. Backup code (git branch)
2. Implementasi batch query
3. Validation script
4. Testing checklist

**Apakah Anda setuju untuk mulai dengan Non Food Payment?**
