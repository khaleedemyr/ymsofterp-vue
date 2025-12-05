# Delivery Order Pagination Fix

## 🐛 **Bug Description**

**Problem**: Di index delivery order, ada bug saat pindah halaman:
- ✅ **Data hilang** saat pindah ke page 2, 3, dst
- ✅ **Tidak ada per page selector** untuk mengatur jumlah data per halaman

## 🔍 **Root Cause Analysis**

### **1. Pagination Bug**
Di function `goToPage()` di frontend, parameter `load_data` tidak dikirim ke backend:

```javascript
// BUGGY CODE (Before Fix)
function goToPage(page) {
  router.get(route('delivery-order.index'), {
    search: search.value,
    dateFrom: dateFrom.value,
    dateTo: dateTo.value,
    page  // ❌ Missing load_data parameter
  }, { preserveState: true });
}
```

**Akibat**: Saat pindah halaman, backend tidak tahu bahwa data harus dimuat (`load_data` tidak ada), sehingga data tidak ditampilkan.

### **2. Missing Per Page Feature**
- ❌ Tidak ada parameter `per_page` di controller
- ❌ Tidak ada per page selector di frontend
- ❌ Pagination selalu menggunakan 15 items per page

## ✅ **Solution Implemented**

### **1. Fixed Backend Controller**

**File**: `app/Http/Controllers/DeliveryOrderController.php`

#### **A. Added per_page Parameter to Session**
```php
// BEFORE (Buggy)
if ($request->hasAny(['search', 'dateFrom', 'dateTo', 'load_data'])) {
    session([
        'delivery_order_filters' => [
            'search' => $request->search,
            'dateFrom' => $request->dateFrom,
            'dateTo' => $request->dateTo,
            'load_data' => $request->load_data
        ]
    ]);
}

// AFTER (Fixed)
if ($request->hasAny(['search', 'dateFrom', 'dateTo', 'load_data', 'per_page'])) {
    session([
        'delivery_order_filters' => [
            'search' => $request->search,
            'dateFrom' => $request->dateFrom,
            'dateTo' => $request->dateTo,
            'load_data' => $request->load_data,
            'per_page' => $request->per_page  // ✅ Added per_page
        ]
    ]);
}
```

#### **B. Added per_page Variable**
```php
// BEFORE (Buggy)
$loadData = $request->load_data ?? $filters['load_data'] ?? '';

// AFTER (Fixed)
$loadData = $request->load_data ?? $filters['load_data'] ?? '';
$perPage = $request->per_page ?? $filters['per_page'] ?? 15;  // ✅ Added per_page
```

#### **C. Updated Pagination**
```php
// BEFORE (Buggy)
$orders = $packingListQuery->union($roSupplierQuery)
    ->orderByDesc('created_at')
    ->paginate(15)  // ❌ Hardcoded 15
    ->withQueryString();

// AFTER (Fixed)
$orders = $packingListQuery->union($roSupplierQuery)
    ->orderByDesc('created_at')
    ->paginate($perPage)  // ✅ Dynamic per_page
    ->withQueryString();
```

#### **D. Updated Return Data**
```php
// BEFORE (Buggy)
return Inertia::render('DeliveryOrder/Index', [
    'orders' => $orders ?: $this->getEmptyPagination(),
    'filters' => [
        'search' => $search,
        'dateFrom' => $dateFrom,
        'dateTo' => $dateTo,
        'load_data' => $loadData
    ],
]);

// AFTER (Fixed)
return Inertia::render('DeliveryOrder/Index', [
    'orders' => $orders ?: $this->getEmptyPagination(),
    'filters' => [
        'search' => $search,
        'dateFrom' => $dateFrom,
        'dateTo' => $dateTo,
        'load_data' => $loadData,
        'per_page' => $perPage  // ✅ Added per_page to filters
    ],
]);
```

### **2. Fixed Frontend Vue Component**

**File**: `resources/js/Pages/DeliveryOrder/Index.vue`

#### **A. Added per_page Ref**
```javascript
// BEFORE (Buggy)
const search = ref(props.filters?.search || '');
const dateFrom = ref(props.filters?.dateFrom || '');
const dateTo = ref(props.filters?.dateTo || '');
const loadData = ref(props.filters?.load_data || '');

// AFTER (Fixed)
const search = ref(props.filters?.search || '');
const dateFrom = ref(props.filters?.dateFrom || '');
const dateTo = ref(props.filters?.dateTo || '');
const loadData = ref(props.filters?.load_data || '');
const perPage = ref(props.filters?.per_page || 15);  // ✅ Added per_page ref
```

#### **B. Fixed goToPage Function**
```javascript
// BEFORE (Buggy)
function goToPage(page) {
  router.get(route('delivery-order.index'), {
    search: search.value,
    dateFrom: dateFrom.value,
    dateTo: dateTo.value,
    page  // ❌ Missing load_data and per_page
  }, { preserveState: true });
}

// AFTER (Fixed)
function goToPage(page) {
  router.get(route('delivery-order.index'), {
    search: search.value,
    dateFrom: dateFrom.value,
    dateTo: dateTo.value,
    load_data: loadData.value,  // ✅ FIXED: Add load_data parameter
    per_page: perPage.value,    // ✅ FIXED: Add per_page parameter
    page
  }, { preserveState: true });
}
```

#### **C. Updated loadDataWithFilters Function**
```javascript
// BEFORE (Buggy)
function loadDataWithFilters() {
  router.get(route('delivery-order.index'), {
    search: search.value,
    dateFrom: dateFrom.value,
    dateTo: dateTo.value,
    load_data: '1'
  }, { preserveState: true });
}

// AFTER (Fixed)
function loadDataWithFilters() {
  router.get(route('delivery-order.index'), {
    search: search.value,
    dateFrom: dateFrom.value,
    dateTo: dateTo.value,
    load_data: '1',
    per_page: perPage.value  // ✅ Added per_page parameter
  }, { preserveState: true });
}
```

#### **D. Updated clearFilters Function**
```javascript
// BEFORE (Buggy)
function clearFilters() {
  // Call backend method to clear session filters
  router.get(route('delivery-order.clear-filters'), {}, { 
    preserveState: false, 
    replace: true 
  });
}

// AFTER (Fixed)
function clearFilters() {
  search.value = '';
  dateFrom.value = '';
  dateTo.value = '';
  loadData.value = '';
  perPage.value = 15;  // ✅ Reset per_page to default
  
  // Call backend method to clear session filters
  router.get(route('delivery-order.clear-filters'), {}, { 
    preserveState: false, 
    replace: true 
  });
}
```

#### **E. Added Per Page Selector**
```html
<!-- BEFORE (Buggy) -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
  <!-- Only 3 columns: Search, Date From, Date To -->
</div>

<!-- AFTER (Fixed) -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
  <!-- 4 columns: Search, Date From, Date To, Per Page -->
  <div>
    <label class="block text-sm font-medium text-gray-700 mb-2">Per Page</label>
    <select 
      v-model="perPage" 
      class="w-full px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
    >
      <option value="10">10</option>
      <option value="15">15</option>
      <option value="25">25</option>
      <option value="50">50</option>
      <option value="100">100</option>
    </select>
  </div>
</div>
```

#### **F. Added Per Page Watcher**
```javascript
// Watch per_page changes to reload data
watch(perPage, (newPerPage) => {
  if (loadData.value === '1') {
    loadDataWithFilters();  // ✅ Auto reload when per_page changes
  }
});
```

## 🎯 **Key Changes Made**

### **1. Backend Changes**
- ✅ **Added per_page parameter** to session storage
- ✅ **Dynamic pagination** instead of hardcoded 15
- ✅ **per_page in filters** returned to frontend
- ✅ **Session persistence** for per_page setting

### **2. Frontend Changes**
- ✅ **Fixed goToPage function** - added missing load_data and per_page parameters
- ✅ **Added per_page ref** for reactive data binding
- ✅ **Added per page selector** with options: 10, 15, 25, 50, 100
- ✅ **Added per_page watcher** for auto-reload when changed
- ✅ **Updated all functions** to include per_page parameter

## 📊 **Expected Results**

| Scenario | Before Fix | After Fix |
|----------|------------|-----------|
| **Pindah ke Page 2** | ❌ Data hilang | ✅ Data tetap ada |
| **Pindah ke Page 3** | ❌ Data hilang | ✅ Data tetap ada |
| **Per Page Options** | ❌ Tidak ada | ✅ 10, 15, 25, 50, 100 |
| **Per Page Persistence** | ❌ Tidak tersimpan | ✅ Tersimpan di session |
| **Filter Persistence** | ✅ Sudah ada | ✅ Tetap berfungsi |

## 🧪 **Testing Scenarios**

### **Test Case 1: Pagination Navigation**
1. Load data dengan filter
2. Pindah ke page 2 → ✅ Data harus tetap ada
3. Pindah ke page 3 → ✅ Data harus tetap ada
4. Kembali ke page 1 → ✅ Data harus tetap ada

### **Test Case 2: Per Page Functionality**
1. Pilih per page = 10 → ✅ Data reload dengan 10 items
2. Pilih per page = 25 → ✅ Data reload dengan 25 items
3. Pilih per page = 50 → ✅ Data reload dengan 50 items
4. Refresh browser → ✅ Per page setting tetap tersimpan

### **Test Case 3: Combined Functionality**
1. Set filter + per page = 25
2. Pindah ke page 2 → ✅ Data tetap ada dengan 25 items per page
3. Ubah per page ke 10 → ✅ Data reload dengan 10 items per page
4. Clear filter → ✅ Per page reset ke 15

## 🚀 **Benefits**

1. ✅ **Fixed Pagination Bug**: Data tidak hilang saat pindah halaman
2. ✅ **Per Page Control**: User bisa memilih jumlah data per halaman
3. ✅ **Better UX**: Pagination yang lebih fleksibel dan user-friendly
4. ✅ **Session Persistence**: Setting per page tersimpan di session
5. ✅ **Auto Reload**: Data otomatis reload saat per page berubah

## ⚠️ **Important Notes**

1. **Backward Compatibility**: Semua fungsi existing tetap berfungsi
2. **Default Value**: Per page default tetap 15 (tidak berubah)
3. **Session Storage**: Per page setting tersimpan di session seperti filter lainnya
4. **Performance**: Per page yang lebih besar akan mempengaruhi loading time

**Bug pagination di Delivery Order sudah diperbaiki dan fitur per page sudah ditambahkan!** 🎯
