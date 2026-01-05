# 💾 Implementasi Caching untuk Fitur Berat

## 📋 **OVERVIEW**

Dokumen ini berisi contoh implementasi caching untuk fitur-fitur berat yang menyebabkan CPU 100%.

---

## 🎯 **1. CACHING UNTUK OUTLET STOCK REPORT**

**File:** `app/Http/Controllers/OutletStockReportController.php`

### **A. Tambahkan Cache di Method `index`**

```php
use Illuminate\Support\Facades\Cache;

public function index(Request $request)
{
    // ... existing validation code ...
    
    $outletId = $request->input('outlet_id');
    $warehouseOutletId = $request->input('warehouse_outlet_id');
    $bulan = $request->input('bulan'); // Format: YYYY-MM
    
    // Generate cache key berdasarkan parameter
    $cacheKey = sprintf(
        'outlet_stock_report_%d_%d_%s',
        $outletId,
        $warehouseOutletId,
        $bulan
    );
    
    // Cache untuk 5 menit (300 detik)
    // Data report biasanya tidak berubah terlalu sering
    $data = Cache::remember($cacheKey, 300, function() use ($outletId, $warehouseOutletId, $bulan) {
        // ... semua query yang berat (good receive, stock cut, wip, dll) ...
        
        // Return hasil query
        return [
            'items' => $items,
            'goodReceiveData' => $goodReceiveData,
            'goodSoldData' => $goodSoldData,
            // ... data lainnya ...
        ];
    });
    
    return inertia('OutletStockReport/Index', [
        'data' => $data,
        // ... lainnya ...
    ]);
}
```

### **B. Invalidate Cache Ketika Data Berubah**

Tambahkan di controller yang mengubah stock:

**File:** `app/Http/Controllers/OutletFoodGoodReceiveController.php`

```php
use Illuminate\Support\Facades\Cache;

public function store(Request $request)
{
    // ... existing code untuk insert good receive ...
    
    // Invalidate cache untuk outlet stock report
    // Invalidate untuk semua bulan (karena stock berubah)
    $outletId = $request->input('outlet_id');
    $warehouseOutletId = $request->input('warehouse_outlet_id');
    
    // Hapus cache untuk 12 bulan terakhir (atau semua jika perlu)
    for ($i = 0; $i < 12; $i++) {
        $bulan = date('Y-m', strtotime("-$i months"));
        $cacheKey = sprintf('outlet_stock_report_%d_%d_%s', $outletId, $warehouseOutletId, $bulan);
        Cache::forget($cacheKey);
    }
    
    // ... rest of code ...
}
```

**File:** `app/Http/Controllers/StockCutController.php`

```php
use Illuminate\Support\Facades\Cache;

public function potongStockOrderItems(Request $request)
{
    // ... existing code untuk stock cut ...
    
    // Invalidate cache setelah stock cut
    $id_outlet = $request->input('id_outlet');
    $tanggal = $request->input('tanggal');
    $bulan = date('Y-m', strtotime($tanggal));
    
    // Hapus cache untuk bulan tersebut
    $warehouseIds = DB::table('warehouse_outlets')
        ->where('outlet_id', $id_outlet)
        ->pluck('id');
    
    foreach ($warehouseIds as $warehouseId) {
        $cacheKey = sprintf('outlet_stock_report_%d_%d_%s', $id_outlet, $warehouseId, $bulan);
        Cache::forget($cacheKey);
    }
    
    // ... rest of code ...
}
```

---

## 🎯 **2. CACHING UNTUK WIP PRODUCTION LIST**

**File:** `app/Http/Controllers/OutletWIPController.php`

### **A. Tambahkan Cache di Method `index`**

```php
use Illuminate\Support\Facades\Cache;

public function index(Request $request)
{
    $user = auth()->user();
    
    // Generate cache key berdasarkan parameter
    $cacheParams = [
        'outlet_id' => $user->id_outlet,
        'date_from' => $request->input('date_from'),
        'date_to' => $request->input('date_to'),
        'search' => $request->input('search'),
        'page' => $request->input('page', 1),
        'per_page' => $request->input('per_page', 10),
    ];
    
    $cacheKey = 'wip_production_list_' . md5(json_encode($cacheParams));
    
    // Cache untuk 2 menit (120 detik)
    // Data list tidak berubah terlalu sering
    $data = Cache::remember($cacheKey, 120, function() use ($request, $user) {
        // ... existing query code ...
        
        return [
            'headers' => $headers,
            'items' => $items,
            'warehouse_outlets' => $warehouse_outlets,
        ];
    });
    
    return inertia('OutletWIP/Index', $data);
}
```

### **B. Invalidate Cache Ketika Data Berubah**

```php
use Illuminate\Support\Facades\Cache;

public function store(Request $request)
{
    // ... existing code untuk insert WIP production ...
    
    // Invalidate cache untuk WIP production list
    // Hapus semua cache yang terkait dengan outlet ini
    $user = auth()->user();
    $outletId = $user->id_outlet;
    
    // Gunakan pattern matching (jika menggunakan Redis)
    // Atau hapus cache untuk beberapa kombinasi parameter yang umum
    $commonParams = [
        ['date_from' => null, 'date_to' => null],
        ['date_from' => date('Y-m-01'), 'date_to' => date('Y-m-t')],
        // ... kombinasi lainnya jika perlu ...
    ];
    
    foreach ($commonParams as $params) {
        $cacheKey = 'wip_production_list_' . md5(json_encode(array_merge(['outlet_id' => $outletId], $params)));
        Cache::forget($cacheKey);
    }
    
    // Atau lebih simple: flush semua cache dengan tag (jika menggunakan Redis tags)
    // Cache::tags(['wip_production', $outletId])->flush();
    
    // ... rest of code ...
}

public function update(Request $request, $id)
{
    // ... existing code ...
    
    // Invalidate cache (sama seperti store)
    // ... invalidate code ...
    
    // ... rest of code ...
}

public function destroy($id)
{
    // ... existing code ...
    
    // Invalidate cache (sama seperti store)
    // ... invalidate code ...
    
    // ... rest of code ...
}
```

---

## 🎯 **3. CACHING UNTUK CATEGORY COST OUTLET**

**File:** `app/Http/Controllers/OutletInternalUseWasteController.php`

### **A. Tambahkan Cache di Method `index`**

```php
use Illuminate\Support\Facades\Cache;

public function index(Request $request)
{
    $user = auth()->user();
    
    // Generate cache key berdasarkan parameter
    $cacheParams = [
        'outlet_id' => $user->id_outlet,
        'search' => $request->input('search'),
        'outlet_id_filter' => $request->input('outlet_id'),
        'type' => $request->input('type'),
        'date_from' => $request->input('date_from'),
        'date_to' => $request->input('date_to'),
        'page' => $request->input('page', 1),
        'per_page' => $request->input('per_page', 10),
    ];
    
    $cacheKey = 'category_cost_outlet_' . md5(json_encode($cacheParams));
    
    // Cache untuk 3 menit (180 detik)
    $data = Cache::remember($cacheKey, 180, function() use ($request, $user) {
        // ... existing query code ...
        
        return [
            'data' => $data,
            'outlets' => $outlets,
            'filters' => $request->only(['search', 'outlet_id', 'type', 'date_from', 'date_to', 'per_page']),
        ];
    });
    
    return inertia('OutletInternalUseWaste/Index', $data);
}
```

### **B. Invalidate Cache Ketika Data Berubah**

```php
use Illuminate\Support\Facades\Cache;

public function store(Request $request)
{
    // ... existing code untuk insert category cost outlet ...
    
    // Invalidate cache
    $outletId = $request->input('outlet_id');
    
    // Hapus cache untuk outlet ini (semua kombinasi parameter)
    // Gunakan pattern atau flush semua cache dengan prefix
    // Jika menggunakan Redis, bisa gunakan:
    // $keys = Cache::getRedis()->keys('category_cost_outlet_*');
    // foreach ($keys as $key) {
    //     Cache::forget($key);
    // }
    
    // Atau lebih simple: invalidate berdasarkan outlet_id
    // (perlu modifikasi cache key untuk include outlet_id di awal)
    
    // ... rest of code ...
}

public function update(Request $request, $id)
{
    // ... existing code ...
    
    // Invalidate cache
    // ... invalidate code ...
    
    // ... rest of code ...
}

public function destroy($id)
{
    // ... existing code ...
    
    // Invalidate cache
    // ... invalidate code ...
    
    // ... rest of code ...
}
```

---

## 🎯 **4. CACHING DENGAN REDIS TAGS (ADVANCED)**

Jika menggunakan Redis, bisa gunakan cache tags untuk invalidate lebih mudah:

### **A. Setup Cache Tags**

**File:** `config/cache.php`

Pastikan Redis driver sudah dikonfigurasi dengan benar.

### **B. Contoh Penggunaan Cache Tags**

```php
use Illuminate\Support\Facades\Cache;

// Set cache dengan tag
Cache::tags(['outlet_stock', $outletId, $warehouseOutletId])
    ->put($cacheKey, $data, 300);

// Atau menggunakan remember dengan tag
$data = Cache::tags(['outlet_stock', $outletId, $warehouseOutletId])
    ->remember($cacheKey, 300, function() {
        // ... query ...
    });

// Invalidate semua cache dengan tag tertentu
Cache::tags(['outlet_stock', $outletId])->flush();
```

### **C. Implementasi dengan Tags**

```php
// OutletStockReportController
$data = Cache::tags(['outlet_stock', $outletId, $warehouseOutletId])
    ->remember($cacheKey, 300, function() use ($outletId, $warehouseOutletId, $bulan) {
        // ... query ...
    });

// Invalidate ketika stock berubah
Cache::tags(['outlet_stock', $outletId])->flush();
```

---

## 🎯 **5. CACHE WARMING (OPTIONAL)**

Untuk data yang sering diakses, bisa pre-load cache:

**File:** `app/Console/Commands/WarmCache.php`

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WarmCache extends Command
{
    protected $signature = 'cache:warm';
    protected $description = 'Warm up cache for frequently accessed data';

    public function handle()
    {
        $this->info('Warming cache...');
        
        // Warm cache untuk outlet stock report (bulan ini dan bulan lalu)
        $outlets = DB::table('tbl_data_outlet')->where('status', 'A')->get();
        
        foreach ($outlets as $outlet) {
            $warehouses = DB::table('warehouse_outlets')
                ->where('outlet_id', $outlet->id_outlet)
                ->get();
            
            foreach ($warehouses as $warehouse) {
                // Warm cache untuk bulan ini
                $bulan = date('Y-m');
                $cacheKey = sprintf('outlet_stock_report_%d_%d_%s', $outlet->id_outlet, $warehouse->id, $bulan);
                
                // Trigger cache dengan memanggil controller method (atau service)
                // Atau langsung query dan set cache
            }
        }
        
        $this->info('Cache warmed successfully!');
    }
}
```

**Schedule di `app/Console/Kernel.php`:**

```php
protected function schedule(Schedule $schedule)
{
    // Warm cache setiap jam
    $schedule->command('cache:warm')->hourly();
}
```

---

## 🎯 **6. MONITORING CACHE**

### **A. Check Cache Hit Rate**

```php
// Tambahkan di controller untuk monitoring
$cacheStats = [
    'hit' => Cache::get('cache_hit_count', 0),
    'miss' => Cache::get('cache_miss_count', 0),
];

// Atau gunakan Redis command
// redis-cli INFO stats | grep keyspace
```

### **B. Cache Debugging**

```php
// Check apakah cache key ada
if (Cache::has($cacheKey)) {
    // Cache hit
    $data = Cache::get($cacheKey);
} else {
    // Cache miss
    $data = // ... query ...
    Cache::put($cacheKey, $data, 300);
}
```

---

## 📋 **CHECKLIST IMPLEMENTASI**

- [ ] Setup Redis/Memcached (jika belum ada)
- [ ] Implementasi cache untuk Outlet Stock Report
- [ ] Implementasi cache untuk WIP Production List
- [ ] Implementasi cache untuk Category Cost Outlet
- [ ] Setup cache invalidation di semua method yang mengubah data
- [ ] Test cache hit rate
- [ ] Monitor cache memory usage
- [ ] Fine-tune cache TTL berdasarkan kebutuhan

---

## ⚠️ **CATATAN PENTING**

1. **Cache harus di-invalidate ketika data berubah**
   - Pastikan semua method yang mengubah data juga invalidate cache

2. **Cache TTL harus disesuaikan dengan kebutuhan**
   - Data yang sering berubah: TTL pendek (1-2 menit)
   - Data yang jarang berubah: TTL panjang (5-10 menit)

3. **Monitor cache memory usage**
   - Pastikan tidak melebihi memory limit
   - Gunakan cache eviction policy yang tepat

4. **Test di staging dulu**
   - Pastikan tidak ada breaking changes
   - Verify cache invalidation bekerja dengan benar

---

**Implementasi caching akan sangat mengurangi load database dan CPU usage!** ✅

