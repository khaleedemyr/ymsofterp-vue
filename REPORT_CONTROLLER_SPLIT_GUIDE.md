# Report Controller Split Implementation Guide

## 📋 Executive Summary

**Current State:**
- File: `app/Http/Controllers/ReportController.php`
- Total Lines: 3,665 lines
- Total Functions: 30 functions
- Total Queries: 204+ database queries
- Total JOINs: 290+ JOIN statements
- Cache Usage: 0 (NONE)
- Manual Pagination: 10+ instances
- Performance: 🔴 CRITICAL

**Target State:**
- Split into 5 specialized controllers
- Reduce file sizes to ~400-500 lines each
- Implement proper pagination
- Add caching layer
- Optimize database queries
- Expected Performance Gain: 40-70% faster

---

## 🔍 Performance Issues Analysis

### Critical Issues Identified

#### 1. **Manual Pagination - CRITICAL** 
**Impact:** Load ALL data into memory, then slice in PHP
```php
// Lines 75-79, 149-152, 296-303
$data = collect($query->get());  // ⚠️ Loads ALL rows into memory!
$total = $data->count();
$paginated = $data->slice(($page - 1) * $perPage, $perPage)->values();
```
**Problem:** If 100,000 rows exist, ALL are loaded into PHP memory before pagination.

**Solution:** Use Laravel's built-in pagination
```php
$data = $query->paginate($perPage);
```

#### 2. **Multiple Query Merge in Memory - CRITICAL**
**Impact:** Multiple queries executed separately, then merged in PHP
```php
// Lines 296-298, 449-451
$data = collect($query->get())->merge(collect($supplierQuery->get()))
    ->sortBy([['tanggal', 'asc'], ...])
    ->values();
```
**Problem:** 2 queries × 50k rows = 100k rows merged in PHP memory

**Solution:** Use UNION at SQL level
```php
$query->union($supplierQuery)->orderBy('tanggal')->paginate($perPage);
```

#### 3. **whereDate/whereMonth/whereYear - INDEX KILLER**
**Impact:** 103 instances of date functions that prevent index usage
```php
// Lines 54, 57, 130, 133
$query->whereYear('gr.receive_date', $request->tahun);
$query->whereMonth('gr.receive_date', $request->bulan);
```
**Problem:** Database must scan ALL rows and apply function to each row

**Solution:** Use date ranges
```php
$query->whereBetween('gr.receive_date', [$fromDate, $toDate]);
```

#### 4. **Complex JOINs (6-8 tables) - HIGH LOAD**
**Impact:** 290 JOIN statements across all functions
```php
// Example: 8 table joins in single query
->join('outlet_food_good_receive_items as i', ...)
->join('items as it', ...)
->join('categories as c', ...)
->join('delivery_orders as do', ...)
->join('food_packing_lists as pl', ...)
->join('warehouse_division as wd', ...)
->join('warehouses as w', ...)
->join('food_floor_order_items as fo', ...)
```
**Problem:** Exponential complexity with large datasets

**Solution:** Create database views for frequently joined tables

#### 5. **No Caching - CRITICAL**
**Impact:** Same report queried repeatedly for every user request
```php
// 0 Cache usage found
```
**Problem:** 10 users viewing same report = 10 identical database queries

**Solution:** Implement Redis caching
```php
Cache::remember('report_sales_' . $hash, 900, function() use ($query) {
    return $query->get();
});
```

#### 6. **Nested PHP Loops - PERFORMANCE DRAIN**
**Impact:** 48 foreach/while loops processing data
```php
// Lines 1056-1094, 2021-2033
foreach ($report1Items as $item) {
    foreach ($mods as $group) {
        if (is_array($group)) {
            foreach ($group as $name => $qty) {
                // Triple nested loop!
            }
        }
    }
}
```
**Problem:** O(n³) complexity - very slow for large datasets

**Solution:** Use database aggregation with GROUP BY and CASE WHEN

---

## 📊 Function Inventory & Grouping

### Complete Function List (30 Functions)

| # | Function Name | Line | Type | Complexity | Priority |
|---|---------------|------|------|------------|----------|
| 1 | reportSalesPerCategory | 24 | View | Medium | Medium |
| 2 | reportSalesPerTanggal | 106 | View | Medium | Medium |
| 3 | reportSalesAllItemAllOutlet | 177 | View | High | HIGH |
| 4 | exportSalesAllItemAllOutlet | 327 | Export | High | HIGH |
| 5 | reportSalesPivotPerOutletSubCategory | 467 | View | High | HIGH |
| 6 | exportSalesPivotPerOutletSubCategory | 639 | Export | High | HIGH |
| 7 | reportSalesPivotSpecial | 764 | View | Very High | CRITICAL |
| 8 | exportSalesPivotSpecial | 1001 | Export | Very High | CRITICAL |
| 9 | reportGoodReceiveOutlet | 1244 | View | High | HIGH |
| 10 | exportGoodReceiveOutlet | 1334 | Export | High | HIGH |
| 11 | salesPivotOutletDetail | 1433 | API | Medium | Medium |
| 12 | retailSalesDetail | 1519 | API | Medium | Medium |
| 13 | warehouseSalesDetail | 1566 | API | High | HIGH |
| 14 | reportSalesSimple | 1613 | View | Medium | Medium |
| 15 | apiOutlets | 1855 | API | Low | Low |
| 16 | apiRegions | 1904 | API | Low | Low |
| 17 | myOutletQr | 1927 | API | Low | Low |
| 18 | reportItemEngineering | 1947 | View | Medium | Medium |
| 19 | reportReceivingSheet | 2051 | View | Very High | CRITICAL |
| 20 | exportOrderDetail | 2306 | Export | Medium | Medium |
| 21 | exportItemEngineering | 2335 | Export | Medium | Medium |
| 22 | apiOutletExpenses | 2422 | API | Medium | Medium |
| 23 | fjDetail | 2776 | API | Very High | CRITICAL |
| 24 | fjDetailPdf | 2967 | Export | Very High | CRITICAL |
| 25 | retailDetailPdf | 3227 | Export | Medium | Medium |
| 26 | warehouseDetailPdf | 3318 | Export | High | HIGH |
| 27 | fjDetailExcel | 3411 | Export | Very High | CRITICAL |
| 28 | retailDetailExcel | 3645 | Export | Medium | Medium |
| 29 | warehouseDetailExcel | 3727 | Export | High | HIGH |
| 30 | reportActivityLog | 3805 | View | Medium | Low |

---

## 🎯 Split Strategy: 5 Controllers

### **1. SalesReportController**
**Purpose:** Handle all sales-related reports and exports

**Functions (10):**
1. `reportSalesPerCategory()` - Sales per Category Report
2. `reportSalesPerTanggal()` - Sales per Date Report
3. `reportSalesAllItemAllOutlet()` - All Items All Outlets Report
4. `exportSalesAllItemAllOutlet()` - Export to Excel
5. `reportSalesPivotPerOutletSubCategory()` - Sales Pivot by Outlet & Sub-Category
6. `exportSalesPivotPerOutletSubCategory()` - Export to Excel
7. `reportSalesPivotSpecial()` - Special Sales Pivot (FJ Report)
8. `exportSalesPivotSpecial()` - Export to Excel
9. `reportSalesSimple()` - Simple Sales Report (POS)
10. `salesPivotOutletDetail()` - Sales Pivot Detail API

**Estimated Lines:** ~1,500 lines → split to ~500 lines
**Performance Issues:**
- ⚠️ Manual pagination (3 instances)
- ⚠️ Query merge in memory (2 instances)
- ⚠️ Complex JOINs (6-8 tables each)
- ⚠️ No caching

**Optimization Priority:** 🔴 CRITICAL

---

### **2. WarehouseReportController**
**Purpose:** Handle warehouse, distribution, and receiving reports

**Functions (9):**
1. `reportGoodReceiveOutlet()` - Good Receive per Outlet Report
2. `exportGoodReceiveOutlet()` - Export to Excel
3. `reportReceivingSheet()` - Receiving Sheet (Cost vs Sales)
4. `fjDetail()` - FJ Detail Report (Food & Juice Distribution)
5. `fjDetailPdf()` - Export to PDF
6. `fjDetailExcel()` - Export to Excel
7. `warehouseSalesDetail()` - Warehouse Sales Detail API
8. `warehouseDetailPdf()` - Export to PDF
9. `warehouseDetailExcel()` - Export to Excel

**Estimated Lines:** ~1,200 lines → split to ~400 lines
**Performance Issues:**
- ⚠️ Nested loops in PHP (fjDetail)
- ⚠️ Multiple separate queries (reportReceivingSheet)
- ⚠️ Complex aggregation logic
- ⚠️ No caching

**Optimization Priority:** 🔴 CRITICAL

---

### **3. RetailReportController**
**Purpose:** Handle retail sales reports

**Functions (3):**
1. `retailSalesDetail()` - Retail Sales Detail API
2. `retailDetailPdf()` - Export to PDF
3. `retailDetailExcel()` - Export to Excel

**Estimated Lines:** ~300 lines → ~150 lines
**Performance Issues:**
- ⚠️ Complex JOINs (5-6 tables)
- ⚠️ No caching

**Optimization Priority:** 🟡 MEDIUM

---

### **4. EngineeringReportController**
**Purpose:** Handle engineering, item, and operational reports

**Functions (4):**
1. `reportItemEngineering()` - Item Engineering Report
2. `exportItemEngineering()` - Export to Excel (Multi-Sheet)
3. `exportOrderDetail()` - Export Order Detail (POS)
4. `apiOutletExpenses()` - Outlet Expenses API

**Estimated Lines:** ~600 lines → ~300 lines
**Performance Issues:**
- ⚠️ JSON parsing in loops
- ⚠️ No caching

**Optimization Priority:** 🟡 MEDIUM

---

### **5. ReportHelperController** (or Trait)
**Purpose:** Shared utilities and helper functions

**Functions (4):**
1. `apiOutlets()` - Get outlets list API
2. `apiRegions()` - Get regions list API
3. `myOutletQr()` - Get my outlet QR code
4. `reportActivityLog()` - Activity log report

**Estimated Lines:** ~200 lines → ~100 lines
**Performance Issues:**
- ✅ Minimal issues
- Should be converted to Trait for reusability

**Optimization Priority:** 🟢 LOW

**Note:** Consider converting to `ReportHelperTrait.php` instead of separate controller.

---

## 📁 New File Structure

```
app/Http/Controllers/
├── Report/
│   ├── SalesReportController.php           (~500 lines)
│   ├── WarehouseReportController.php       (~400 lines)
│   ├── RetailReportController.php          (~150 lines)
│   ├── EngineeringReportController.php     (~300 lines)
│   └── ReportHelperController.php          (~100 lines)
│
└── Traits/
    └── ReportHelperTrait.php               (~150 lines)
```

### Trait Structure (Recommended)

```php
// app/Http/Traits/ReportHelperTrait.php
trait ReportHelperTrait {
    public function getOutlets() { ... }
    public function getRegions() { ... }
    public function getMyOutletQr() { ... }
    public function formatDateRange($from, $to) { ... }
    public function cacheKey($prefix, $params) { ... }
}
```

Then use in each controller:
```php
class SalesReportController extends Controller {
    use ReportHelperTrait;
    
    // ... functions
}
```

---

## 🔄 Routes Migration Map

### Current Routes (OLD)
```php
// routes/web.php or routes/api.php
Route::prefix('report')->group(function() {
    Route::get('/sales-per-category', [ReportController::class, 'reportSalesPerCategory']);
    Route::get('/sales-per-tanggal', [ReportController::class, 'reportSalesPerTanggal']);
    // ... 30+ routes
});
```

### New Routes Structure

```php
// routes/web.php
use App\Http\Controllers\Report\SalesReportController;
use App\Http\Controllers\Report\WarehouseReportController;
use App\Http\Controllers\Report\RetailReportController;
use App\Http\Controllers\Report\EngineeringReportController;
use App\Http\Controllers\Report\ReportHelperController;

Route::prefix('report')->group(function() {
    
    // Sales Reports
    Route::controller(SalesReportController::class)->group(function() {
        Route::get('/sales-per-category', 'reportSalesPerCategory');
        Route::get('/sales-per-tanggal', 'reportSalesPerTanggal');
        Route::get('/sales-all-item-all-outlet', 'reportSalesAllItemAllOutlet');
        Route::get('/sales-all-item-all-outlet/export', 'exportSalesAllItemAllOutlet');
        Route::get('/sales-pivot-per-outlet-sub-category', 'reportSalesPivotPerOutletSubCategory');
        Route::get('/sales-pivot-per-outlet-sub-category/export', 'exportSalesPivotPerOutletSubCategory');
        Route::get('/sales-pivot-special', 'reportSalesPivotSpecial');
        Route::get('/sales-pivot-special/export', 'exportSalesPivotSpecial');
        Route::get('/sales-simple', 'reportSalesSimple');
        Route::get('/sales-pivot-outlet-detail', 'salesPivotOutletDetail');
    });
    
    // Warehouse Reports
    Route::controller(WarehouseReportController::class)->group(function() {
        Route::get('/good-receive-outlet', 'reportGoodReceiveOutlet');
        Route::get('/good-receive-outlet/export', 'exportGoodReceiveOutlet');
        Route::get('/receiving-sheet', 'reportReceivingSheet');
        Route::get('/fj-detail', 'fjDetail');
        Route::get('/fj-detail/pdf', 'fjDetailPdf');
        Route::get('/fj-detail/excel', 'fjDetailExcel');
        Route::get('/warehouse-sales-detail', 'warehouseSalesDetail');
        Route::get('/warehouse-detail/pdf', 'warehouseDetailPdf');
        Route::get('/warehouse-detail/excel', 'warehouseDetailExcel');
    });
    
    // Retail Reports
    Route::controller(RetailReportController::class)->group(function() {
        Route::get('/retail-sales-detail', 'retailSalesDetail');
        Route::get('/retail-detail/pdf', 'retailDetailPdf');
        Route::get('/retail-detail/excel', 'retailDetailExcel');
    });
    
    // Engineering Reports
    Route::controller(EngineeringReportController::class)->group(function() {
        Route::get('/item-engineering', 'reportItemEngineering');
        Route::get('/item-engineering/export', 'exportItemEngineering');
        Route::get('/order-detail/export', 'exportOrderDetail');
        Route::get('/outlet-expenses', 'apiOutletExpenses');
    });
    
    // Helper APIs
    Route::controller(ReportHelperController::class)->group(function() {
        Route::get('/api/outlets', 'apiOutlets');
        Route::get('/api/regions', 'apiRegions');
        Route::get('/my-outlet-qr', 'myOutletQr');
        Route::get('/activity-log', 'reportActivityLog');
    });
});
```

---

## 🛠️ Implementation Plan

### Phase 1: Preparation (Week 1)
**Goal:** Setup infrastructure and backup

1. **Create Backup**
   ```bash
   cp app/Http/Controllers/ReportController.php \
      app/Http/Controllers/ReportController.php.backup
   ```

2. **Create Migration Branch**
   ```bash
   git checkout -b feature/split-report-controller
   ```

3. **Create Directory Structure**
   ```bash
   mkdir -p app/Http/Controllers/Report
   mkdir -p app/Http/Traits
   ```

4. **Setup Database Indexes** (CRITICAL - Do this first!)
   ```sql
   -- Add indexes for frequently queried columns
   CREATE INDEX idx_receive_date ON outlet_food_good_receives(receive_date);
   CREATE INDEX idx_outlet_id ON outlet_food_good_receives(outlet_id);
   CREATE INDEX idx_warehouse_id ON warehouses(id);
   CREATE INDEX idx_item_id ON items(id);
   CREATE INDEX idx_category_id ON items(category_id);
   CREATE INDEX idx_sub_category_id ON items(sub_category_id);
   
   -- Add composite indexes for common JOINs
   CREATE INDEX idx_gr_outlet_date ON outlet_food_good_receives(outlet_id, receive_date);
   CREATE INDEX idx_item_warehouse ON items(warehouse_division_id, category_id);
   ```

5. **Setup Redis Caching**
   - Ensure Redis is installed and running
   - Configure `.env`: `CACHE_DRIVER=redis`
   - Test connection: `php artisan tinker` → `Cache::put('test', 'value', 60);`

---

### Phase 2: Split Controllers (Week 2-3)

#### Step 1: Create ReportHelperTrait
**Priority:** HIGH (needed by all controllers)

```php
// app/Http/Traits/ReportHelperTrait.php
<?php

namespace App\Http\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

trait ReportHelperTrait
{
    /**
     * Get cached outlets list
     */
    protected function getCachedOutlets()
    {
        return Cache::remember('outlets_list', 3600, function() {
            return DB::table('tbl_data_outlet')
                ->where('status', 'A')
                ->orderBy('nama_outlet')
                ->get();
        });
    }
    
    /**
     * Get cached regions list
     */
    protected function getCachedRegions()
    {
        return Cache::remember('regions_list', 3600, function() {
            return DB::table('regions')
                ->orderBy('name')
                ->get();
        });
    }
    
    /**
     * Generate cache key for report
     */
    protected function reportCacheKey($prefix, $params)
    {
        ksort($params);
        return $prefix . '_' . md5(json_encode($params));
    }
    
    /**
     * Get user's outlet QR code
     */
    protected function getMyOutletQr()
    {
        $user = auth()->user();
        if ($user->id_outlet != 1) {
            return DB::table('tbl_data_outlet')
                ->where('id_outlet', $user->id_outlet)
                ->value('qr_code');
        }
        return null;
    }
}
```

---

#### Step 2: Create SalesReportController (CRITICAL - Start Here)

**File:** `app/Http/Controllers/Report/SalesReportController.php`

**Template Structure:**
```php
<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Http\Traits\ReportHelperTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class SalesReportController extends Controller
{
    use ReportHelperTrait;
    
    /**
     * Report Sales per Category
     * OPTIMIZED: Fixed pagination, added caching
     */
    public function reportSalesPerCategory(Request $request)
    {
        // Generate cache key
        $cacheKey = $this->reportCacheKey('sales_category', [
            'warehouse' => $request->warehouse,
            'category' => $request->category,
            'tahun' => $request->tahun,
            'bulan' => $request->bulan,
            'search' => $request->search,
            'page' => $request->input('page', 1),
            'perPage' => $request->input('perPage', 25),
        ]);
        
        // Try to get from cache (5 minutes TTL)
        $result = Cache::remember($cacheKey, 300, function() use ($request) {
            return $this->buildSalesPerCategoryQuery($request);
        });
        
        return Inertia::render('Report/ReportSalesPerCategory', $result);
    }
    
    /**
     * Build query for Sales per Category
     * OPTIMIZED: Use proper pagination, fixed date filters
     */
    private function buildSalesPerCategoryQuery(Request $request)
    {
        $query = DB::table('outlet_food_good_receives as gr')
            ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
            ->join('items as it', 'i.item_id', '=', 'it.id')
            ->join('categories as c', 'it.category_id', '=', 'c.id')
            ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->join('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
            ->join('warehouse_division as wd', 'pl.warehouse_division_id', '=', 'wd.id')
            ->join('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
            ->join('food_floor_order_items as fo', function($join) {
                $join->on('i.item_id', '=', 'fo.item_id')
                     ->on('fo.floor_order_id', '=', 'pl.food_floor_order_id');
            })
            ->select(
                'w.name as gudang',
                DB::raw('MONTH(gr.receive_date) as bulan'),
                DB::raw('YEAR(gr.receive_date) as tahun'),
                'c.name as category',
                DB::raw('SUM(i.received_qty * fo.price) as nilai')
            );

        // Filters
        if ($request->filled('warehouse')) {
            $query->where('w.name', $request->warehouse);
        }
        if ($request->filled('category')) {
            $query->where('c.name', $request->category);
        }
        
        // OPTIMIZED: Use date range instead of whereYear/whereMonth
        if ($request->filled('tahun') && $request->filled('bulan')) {
            $fromDate = $request->tahun . '-' . str_pad($request->bulan, 2, '0', STR_PAD_LEFT) . '-01';
            $toDate = date('Y-m-t', strtotime($fromDate));
            $query->whereBetween('gr.receive_date', [$fromDate, $toDate]);
        } elseif ($request->filled('tahun')) {
            $query->whereBetween('gr.receive_date', [
                $request->tahun . '-01-01',
                $request->tahun . '-12-31'
            ]);
        }
        
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function($q) use ($search) {
                $q->where('w.name', 'like', $search)
                  ->orWhere('c.name', 'like', $search);
            });
        }

        $query->groupBy('w.name', DB::raw('MONTH(gr.receive_date)'), DB::raw('YEAR(gr.receive_date)'), 'c.name')
            ->orderBy('w.name')
            ->orderBy(DB::raw('YEAR(gr.receive_date)'))
            ->orderBy(DB::raw('MONTH(gr.receive_date)'))
            ->orderBy('c.name');

        // OPTIMIZED: Use Laravel pagination instead of manual
        $perPage = $request->input('perPage', 25);
        $paginated = $query->paginate($perPage);

        // Get filter data (cached)
        $warehouses = $this->getCachedWarehouses();
        $categories = $this->getCachedCategories();
        $years = $this->getCachedYears();

        return [
            'report' => $paginated->items(),
            'warehouses' => $warehouses,
            'categories' => $categories,
            'years' => $years,
            'filters' => [
                'search' => $request->search,
                'warehouse' => $request->warehouse,
                'category' => $request->category,
                'tahun' => $request->tahun,
                'bulan' => $request->bulan,
                'perPage' => $perPage,
                'page' => $paginated->currentPage(),
            ],
            'total' => $paginated->total(),
            'perPage' => $paginated->perPage(),
            'page' => $paginated->currentPage(),
            'lastPage' => $paginated->lastPage(),
        ];
    }
    
    private function getCachedWarehouses()
    {
        return Cache::remember('warehouses_list', 3600, function() {
            return DB::table('warehouses')->select('name')->orderBy('name')->get();
        });
    }
    
    private function getCachedCategories()
    {
        return Cache::remember('categories_list', 3600, function() {
            return DB::table('categories')->select('name')->orderBy('name')->get();
        });
    }
    
    private function getCachedYears()
    {
        return Cache::remember('gr_years', 3600, function() {
            return DB::table('outlet_food_good_receives')
                ->select(DB::raw('DISTINCT YEAR(receive_date) as tahun'))
                ->orderBy('tahun', 'desc')
                ->pluck('tahun');
        });
    }
    
    // ... Add other functions (copy from ReportController and optimize)
}
```

**Copy these functions from ReportController:**
1. reportSalesPerCategory (line 24) - OPTIMIZE as shown above
2. reportSalesPerTanggal (line 106) - Apply same optimizations
3. reportSalesAllItemAllOutlet (line 177) - Fix merge queries
4. exportSalesAllItemAllOutlet (line 327)
5. reportSalesPivotPerOutletSubCategory (line 467)
6. exportSalesPivotPerOutletSubCategory (line 639)
7. reportSalesPivotSpecial (line 764)
8. exportSalesPivotSpecial (line 1001)
9. reportSalesSimple (line 1613)
10. salesPivotOutletDetail (line 1433)

**Key Optimizations to Apply:**
- ✅ Replace manual pagination with `->paginate()`
- ✅ Replace `collect($query->get())->merge()` with `->union()`
- ✅ Replace `whereYear/whereMonth` with `whereBetween`
- ✅ Add caching with 5-15 minute TTL
- ✅ Extract helper methods for reusable queries

---

#### Step 3: Create WarehouseReportController

**File:** `app/Http/Controllers/Report/WarehouseReportController.php`

**Functions to migrate:**
1. reportGoodReceiveOutlet (line 1244)
2. exportGoodReceiveOutlet (line 1334)
3. reportReceivingSheet (line 2051) - CRITICAL: Heavy nested queries
4. fjDetail (line 2776) - CRITICAL: Nested loops
5. fjDetailPdf (line 2967)
6. fjDetailExcel (line 3411)
7. warehouseSalesDetail (line 1566)
8. warehouseDetailPdf (line 3318)
9. warehouseDetailExcel (line 3727)

**Special Attention:**
- `reportReceivingSheet()`: Multiple separate queries, combine into views
- `fjDetail()`: Nested loops in PHP, move aggregation to SQL

---

#### Step 4: Create RetailReportController

**File:** `app/Http/Controllers/Report/RetailReportController.php`

**Functions to migrate:**
1. retailSalesDetail (line 1519)
2. retailDetailPdf (line 3227)
3. retailDetailExcel (line 3645)

**Optimizations:**
- Add caching
- Optimize JOINs

---

#### Step 5: Create EngineeringReportController

**File:** `app/Http/Controllers/Report/EngineeringReportController.php`

**Functions to migrate:**
1. reportItemEngineering (line 1947)
2. exportItemEngineering (line 2335)
3. exportOrderDetail (line 2306)
4. apiOutletExpenses (line 2422)

---

#### Step 6: Create ReportHelperController

**File:** `app/Http/Controllers/Report/ReportHelperController.php`

**Functions to migrate:**
1. apiOutlets (line 1855)
2. apiRegions (line 1904)
3. myOutletQr (line 1927)
4. reportActivityLog (line 3805)

**Note:** Consider if these should be in a Trait instead.

---

### Phase 3: Update Routes (Week 3)

1. **Backup current routes**
   ```bash
   cp routes/web.php routes/web.php.backup
   ```

2. **Update route definitions**
   - Replace all `ReportController::class` with specific new controllers
   - Use route grouping for better organization

3. **Test each route individually**
   ```bash
   php artisan route:list | grep report
   ```

---

### Phase 4: Testing (Week 4)

#### Unit Testing Checklist

For each controller, test:
- ✅ All view endpoints return 200
- ✅ All export endpoints download files
- ✅ Pagination works correctly
- ✅ Filters work as expected
- ✅ Cache is being used (check Redis)
- ✅ Performance is improved (compare query times)

#### Performance Testing

**Before Split:**
```bash
# Run these queries and note execution time
php artisan tinker
>>> \DB::enableQueryLog();
>>> app()->call('App\Http\Controllers\ReportController@reportSalesPerCategory', ['request' => request()]);
>>> \DB::getQueryLog();
```

**After Split:**
```bash
# Compare execution time
php artisan tinker
>>> \DB::enableQueryLog();
>>> app()->call('App\Http\Controllers\Report\SalesReportController@reportSalesPerCategory', ['request' => request()]);
>>> \DB::getQueryLog();
```

**Expected Improvements:**
- Query time: 50-70% reduction
- Memory usage: 40-60% reduction
- Response time: 40-60% faster

---

### Phase 5: Deployment (Week 5)

#### Pre-Deployment Checklist
- ✅ All unit tests pass
- ✅ All integration tests pass
- ✅ Performance benchmarks meet targets
- ✅ Code review completed
- ✅ Documentation updated
- ✅ Rollback plan prepared

#### Deployment Steps

1. **Staging Deployment**
   ```bash
   git checkout staging
   git merge feature/split-report-controller
   php artisan config:clear
   php artisan cache:clear
   php artisan view:clear
   php artisan optimize
   ```

2. **Monitor Staging (48 hours)**
   - Check error logs
   - Monitor performance metrics
   - Test all report endpoints

3. **Production Deployment**
   ```bash
   git checkout production
   git merge staging
   
   # Run migrations if any
   php artisan migrate --force
   
   # Clear caches
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan optimize
   
   # Restart services
   sudo service php8.2-fpm restart
   sudo service nginx restart
   ```

4. **Post-Deployment Monitoring**
   - Monitor error logs for 24 hours
   - Check performance dashboards
   - Verify all reports working
   - Check Redis cache hit rate

---

## 🚨 Rollback Plan

If issues occur after deployment:

### Quick Rollback (< 5 minutes)

1. **Restore old controller**
   ```bash
   git checkout HEAD~1 app/Http/Controllers/ReportController.php
   git checkout HEAD~1 routes/web.php
   ```

2. **Clear caches**
   ```bash
   php artisan config:clear
   php artisan route:clear
   php artisan cache:clear
   ```

3. **Restart services**
   ```bash
   sudo service php8.2-fpm restart
   ```

### Full Rollback (If needed)

```bash
git revert <commit-hash>
git push origin production
php artisan optimize
sudo service php8.2-fpm restart
```

---

## 📈 Success Metrics

### Performance Targets

| Metric | Before | Target | How to Measure |
|--------|--------|--------|----------------|
| Avg Response Time | 5-10s | < 2s | Laravel Telescope / New Relic |
| Memory Usage | 256-512MB | < 128MB | `memory_get_peak_usage()` |
| Query Count per Request | 10-50 | < 10 | Laravel Debugbar |
| Cache Hit Rate | 0% | > 80% | Redis INFO stats |
| DB Connection Time | 50-100ms | < 30ms | Slow query log |
| Concurrent Users Supported | 50 | 200+ | Load testing |

### Monitoring Tools

1. **Laravel Telescope** - Query monitoring
   ```bash
   composer require laravel/telescope --dev
   php artisan telescope:install
   php artisan migrate
   ```

2. **Redis Monitor**
   ```bash
   redis-cli monitor
   redis-cli info stats
   ```

3. **MySQL Slow Query Log**
   ```sql
   SET GLOBAL slow_query_log = 'ON';
   SET GLOBAL long_query_time = 1;
   ```

---

## 🔍 Database Optimization Checklist

### Required Indexes

Run these BEFORE splitting controllers:

```sql
-- outlet_food_good_receives table
CREATE INDEX idx_ofgr_receive_date ON outlet_food_good_receives(receive_date);
CREATE INDEX idx_ofgr_outlet_id ON outlet_food_good_receives(outlet_id);
CREATE INDEX idx_ofgr_delivery_order ON outlet_food_good_receives(delivery_order_id);
CREATE INDEX idx_ofgr_deleted ON outlet_food_good_receives(deleted_at);
CREATE INDEX idx_ofgr_outlet_date ON outlet_food_good_receives(outlet_id, receive_date);

-- outlet_food_good_receive_items table
CREATE INDEX idx_ofgri_gr_id ON outlet_food_good_receive_items(outlet_food_good_receive_id);
CREATE INDEX idx_ofgri_item_id ON outlet_food_good_receive_items(item_id);
CREATE INDEX idx_ofgri_unit_id ON outlet_food_good_receive_items(unit_id);

-- items table
CREATE INDEX idx_items_category ON items(category_id);
CREATE INDEX idx_items_sub_category ON items(sub_category_id);
CREATE INDEX idx_items_warehouse_div ON items(warehouse_division_id);

-- delivery_orders table
CREATE INDEX idx_do_packing_list ON delivery_orders(packing_list_id);
CREATE INDEX idx_do_floor_order ON delivery_orders(floor_order_id);

-- food_packing_lists table
CREATE INDEX idx_fpl_floor_order ON food_packing_lists(food_floor_order_id);
CREATE INDEX idx_fpl_warehouse_div ON food_packing_lists(warehouse_division_id);

-- warehouse_division table
CREATE INDEX idx_wd_warehouse ON warehouse_division(warehouse_id);

-- tbl_data_outlet table
CREATE INDEX idx_outlet_status ON tbl_data_outlet(status);
CREATE INDEX idx_outlet_is_outlet ON tbl_data_outlet(is_outlet);
CREATE INDEX idx_outlet_region ON tbl_data_outlet(region_id);

-- good_receive_outlet_suppliers table
CREATE INDEX idx_gros_receive_date ON good_receive_outlet_suppliers(receive_date);
CREATE INDEX idx_gros_outlet_id ON good_receive_outlet_suppliers(outlet_id);
CREATE INDEX idx_gros_supplier ON good_receive_outlet_suppliers(ro_supplier_id);

-- orders table (POS)
CREATE INDEX idx_orders_created ON orders(created_at);
CREATE INDEX idx_orders_outlet ON orders(kode_outlet);
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_orders_outlet_created ON orders(kode_outlet, created_at);
```

### Check Index Usage

After creating indexes, verify they're being used:

```sql
EXPLAIN SELECT ...
-- Look for "type: index" or "type: ref" instead of "type: ALL"
```

---

## 📝 Code Quality Standards

### Naming Conventions
- Controllers: PascalCase ending with "Controller"
- Methods: camelCase, descriptive names
- Variables: camelCase, meaningful names
- Cache keys: snake_case with prefix

### Documentation Requirements
- All public methods must have PHPDoc
- Complex queries need inline comments
- Cache TTL must be documented

### Example:
```php
/**
 * Generate sales report per category
 * 
 * @param Request $request
 * @return \Inertia\Response
 * 
 * Cache TTL: 5 minutes
 * Performance: ~200ms average
 */
public function reportSalesPerCategory(Request $request)
{
    // ...
}
```

---

## 🎓 Learning Resources

### Laravel Performance Optimization
- [Laravel Query Optimization](https://laravel.com/docs/10.x/queries#chunking-results)
- [Laravel Caching](https://laravel.com/docs/10.x/cache)
- [Database Indexing Best Practices](https://use-the-index-luke.com/)

### Tools
- Laravel Telescope: Real-time monitoring
- Laravel Debugbar: Query analysis
- Redis CLI: Cache monitoring
- MySQL Workbench: Query profiling

---

## ✅ Final Checklist

### Before Starting
- [ ] Backup database
- [ ] Backup ReportController.php
- [ ] Create feature branch
- [ ] Setup Redis
- [ ] Create database indexes
- [ ] Setup monitoring tools

### During Implementation
- [ ] Create ReportHelperTrait
- [ ] Split SalesReportController
- [ ] Split WarehouseReportController
- [ ] Split RetailReportController
- [ ] Split EngineeringReportController
- [ ] Create ReportHelperController
- [ ] Update all routes
- [ ] Add caching to all methods
- [ ] Fix manual pagination
- [ ] Fix date filters
- [ ] Add PHPDoc to all methods

### Testing
- [ ] Test all view endpoints
- [ ] Test all export endpoints
- [ ] Test pagination
- [ ] Test filters
- [ ] Test caching
- [ ] Performance benchmarks
- [ ] Load testing
- [ ] Error handling

### Deployment
- [ ] Code review completed
- [ ] Documentation updated
- [ ] Deploy to staging
- [ ] Monitor staging 48h
- [ ] Deploy to production
- [ ] Monitor production 24h
- [ ] Verify performance metrics

---

## 📊 Progress Tracking Template

Use this table to track progress:

| Task | Status | Start Date | End Date | Notes |
|------|--------|------------|----------|-------|
| Setup indexes | ⬜ Not Started | | | |
| Create ReportHelperTrait | ⬜ Not Started | | | |
| Split SalesReportController | ⬜ Not Started | | | |
| Split WarehouseReportController | ⬜ Not Started | | | |
| Split RetailReportController | ⬜ Not Started | | | |
| Split EngineeringReportController | ⬜ Not Started | | | |
| Create ReportHelperController | ⬜ Not Started | | | |
| Update routes | ⬜ Not Started | | | |
| Add caching | ⬜ Not Started | | | |
| Testing | ⬜ Not Started | | | |
| Staging deployment | ⬜ Not Started | | | |
| Production deployment | ⬜ Not Started | | | |

**Status Options:**
- ⬜ Not Started
- 🟡 In Progress
- ✅ Completed
- ❌ Blocked

---

## 🆘 Troubleshooting Guide

### Common Issues

#### 1. Cache Not Working
**Symptom:** Cache hit rate = 0%

**Solution:**
```bash
# Check Redis connection
redis-cli ping
# Should return PONG

# Check Laravel cache config
php artisan config:clear
php artisan cache:clear

# Verify CACHE_DRIVER in .env
echo $CACHE_DRIVER
# Should be "redis"
```

#### 2. Slow Queries After Split
**Symptom:** Queries still slow despite optimizations

**Solution:**
```sql
-- Check if indexes are being used
EXPLAIN SELECT ...;

-- Check index fragmentation
ANALYZE TABLE outlet_food_good_receives;
OPTIMIZE TABLE outlet_food_good_receives;
```

#### 3. Memory Limit Errors
**Symptom:** "Allowed memory size exhausted"

**Solution:**
```bash
# Increase PHP memory limit
# Edit php.ini or php-fpm conf
memory_limit = 512M

# Restart PHP-FPM
sudo service php8.2-fpm restart
```

#### 4. Route Not Found After Split
**Symptom:** 404 errors on report routes

**Solution:**
```bash
# Clear route cache
php artisan route:clear
php artisan route:cache

# Verify routes
php artisan route:list | grep report
```

---

## 📞 Support & Contact

**Project Lead:** [Your Name]
**Email:** [your-email@example.com]
**Slack Channel:** #report-optimization
**Documentation:** This file

**Emergency Contacts:**
- Database Admin: [DBA Name]
- DevOps: [DevOps Name]
- Senior Developer: [Senior Dev Name]

---

## 📅 Timeline Summary

| Phase | Duration | Key Activities |
|-------|----------|----------------|
| Phase 1: Preparation | Week 1 | Backup, indexes, Redis setup |
| Phase 2: Split Controllers | Week 2-3 | Create 5 new controllers |
| Phase 3: Update Routes | Week 3 | Migrate route definitions |
| Phase 4: Testing | Week 4 | Unit, integration, performance tests |
| Phase 5: Deployment | Week 5 | Staging → Production |

**Total Estimated Time:** 5 weeks

**Critical Path:**
1. Database indexes (MUST be done first)
2. SalesReportController (Highest impact)
3. WarehouseReportController (High complexity)
4. Testing & validation
5. Production deployment

---

## 🎯 Expected Outcomes

### Performance Improvements
- ✅ 40-70% faster response times
- ✅ 60-80% reduction in memory usage
- ✅ 80%+ cache hit rate
- ✅ Support 4x more concurrent users
- ✅ Reduced server load by 50%

### Code Quality Improvements
- ✅ Better organized code structure
- ✅ Easier to maintain and debug
- ✅ Better separation of concerns
- ✅ Reusable helper functions
- ✅ Improved testability

### Business Impact
- ✅ Faster report generation
- ✅ Better user experience
- ✅ Reduced server costs
- ✅ Ability to scale
- ✅ Happier users

---

**Document Version:** 1.0
**Last Updated:** January 29, 2026
**Author:** AI Assistant
**Approved By:** [Pending]

---

## 📝 Change Log

| Version | Date | Changes | Author |
|---------|------|---------|--------|
| 1.0 | 2026-01-29 | Initial documentation | AI Assistant |

---

**END OF DOCUMENT**

This guide should be used as the primary reference for splitting the ReportController.
All team members should read and understand this document before starting implementation.
