# Packing List Pagination Fix

## 🎯 **Perbaikan Pagination di Index Packing List**

Saya telah menerapkan perbaikan pagination yang sama seperti di Delivery Order ke index Packing List untuk mengatasi bug pagination dan menambahkan fitur per page.

## 🔍 **Perbaikan yang Diterapkan**

### **1. Backend Controller (`PackingListController.php`)**

#### **A. Added per_page Parameter to Session**
```php
// BEFORE (Buggy)
if ($request->hasAny(['search', 'date_from', 'date_to', 'status', 'load_data'])) {
    session([
        'packing_list_filters' => [
            'search' => $request->search,
            'date_from' => $request->date_from,
            'date_to' => $request->date_to,
            'status' => $request->status,
            'load_data' => $request->load_data
        ]
    ]);
}

// AFTER (Fixed)
if ($request->hasAny(['search', 'date_from', 'date_to', 'status', 'load_data', 'per_page'])) {
    session([
        'packing_list_filters' => [
            'search' => $request->search,
            'date_from' => $request->date_from,
            'date_to' => $request->date_to,
            'status' => $request->status,
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
$packingLists = $query->orderByDesc('created_at')->paginate(15)->withQueryString();

// AFTER (Fixed)
$packingLists = $query->orderByDesc('created_at')->paginate($perPage)->withQueryString();
```

#### **D. Updated Return Data**
```php
// BEFORE (Buggy)
return inertia('PackingList/Index', [
    'user' => $user,
    'packingLists' => $packingLists ?: $this->getEmptyPagination(),
    'filters' => [
        'search' => $search,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'status' => $status,
        'load_data' => $loadData
    ],
]);

// AFTER (Fixed)
return inertia('PackingList/Index', [
    'user' => $user,
    'packingLists' => $packingLists ?: $this->getEmptyPagination(),
    'filters' => [
        'search' => $search,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'status' => $status,
        'load_data' => $loadData,
        'per_page' => $perPage  // ✅ Added per_page to filters
    ],
]);
```

### **2. Frontend Vue Component (`Index.vue`)**

#### **A. Added per_page Ref**
```javascript
// BEFORE (Buggy)
const search = ref(props.filters?.search || '');
const selectedStatus = ref(props.filters?.status || '');
const from = ref(props.filters?.date_from || '');
const to = ref(props.filters?.date_to || '');
const loadData = ref(props.filters?.load_data || '');

// AFTER (Fixed)
const search = ref(props.filters?.search || '');
const selectedStatus = ref(props.filters?.status || '');
const from = ref(props.filters?.date_from || '');
const to = ref(props.filters?.date_to || '');
const loadData = ref(props.filters?.load_data || '');
const perPage = ref(props.filters?.per_page || 15);  // ✅ Added per_page ref
```

#### **B. Updated Watch Function**
```javascript
// BEFORE (Buggy)
watch(
  () => props.filters,
  (filters) => {
    search.value = filters?.search || '';
    selectedStatus.value = filters?.status || '';
    from.value = filters?.date_from || '';
    to.value = filters?.date_to || '';
    loadData.value = filters?.load_data || '';
  },
  { immediate: true }
);

// AFTER (Fixed)
watch(
  () => props.filters,
  (filters) => {
    search.value = filters?.search || '';
    selectedStatus.value = filters?.status || '';
    from.value = filters?.date_from || '';
    to.value = filters?.date_to || '';
    loadData.value = filters?.load_data || '';
    perPage.value = filters?.per_page || 15;  // ✅ Added per_page
  },
  { immediate: true }
);
```

#### **C. Fixed goToPage Function**
```javascript
// BEFORE (Buggy)
function goToPage(url) {
  if (url) router.visit(url, { preserveState: true, replace: true });
}

// AFTER (Fixed)
function goToPage(url) {
  if (url) {
    // Extract page number from URL
    const urlParams = new URLSearchParams(url.split('?')[1]);
    const page = urlParams.get('page') || 1;
    
    router.get('/packing-list', {
      search: search.value,
      status: selectedStatus.value,
      date_from: from.value,
      date_to: to.value,
      load_data: loadData.value, // ✅ FIXED: Add load_data parameter
      per_page: perPage.value,   // ✅ FIXED: Add per_page parameter
      page
    }, { preserveState: true, replace: true });
  }
}
```

#### **D. Updated loadDataWithFilters Function**
```javascript
// BEFORE (Buggy)
function loadDataWithFilters() {
  router.get('/packing-list', { 
    search: search.value, 
    status: selectedStatus.value, 
    date_from: from.value, 
    date_to: to.value,
    load_data: '1'
  }, { preserveState: true, replace: true });
}

// AFTER (Fixed)
function loadDataWithFilters() {
  router.get('/packing-list', { 
    search: search.value, 
    status: selectedStatus.value, 
    date_from: from.value, 
    date_to: to.value,
    load_data: '1',
    per_page: perPage.value  // ✅ Added per_page parameter
  }, { preserveState: true, replace: true });
}
```

#### **E. Updated clearFilters Function**
```javascript
// BEFORE (Buggy)
function clearFilters() {
  // Call backend method to clear session filters
  router.get('/packing-list/clear-filters', {}, { 
    preserveState: false, 
    replace: true 
  });
}

// AFTER (Fixed)
function clearFilters() {
  search.value = '';
  selectedStatus.value = '';
  from.value = '';
  to.value = '';
  loadData.value = '';
  perPage.value = 15;  // ✅ Reset per_page to default
  
  // Call backend method to clear session filters
  router.get('/packing-list/clear-filters', {}, { 
    preserveState: false, 
    replace: true 
  });
}
```

#### **F. Added Per Page Selector**
```html
<!-- BEFORE (Buggy) -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
  <!-- Only 4 columns: Search, Status, Date From, Date To -->
</div>

<!-- AFTER (Fixed) -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-4">
  <!-- 5 columns: Search, Status, Date From, Date To, Per Page -->
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

#### **G. Added Per Page Watcher**
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
- ✅ **Updated grid layout** from 4 columns to 5 columns

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
6. ✅ **Consistent Experience**: Sama dengan Delivery Order

## ⚠️ **Important Notes**

1. **Backward Compatibility**: Semua fungsi existing tetap berfungsi
2. **Default Value**: Per page default tetap 15 (tidak berubah)
3. **Session Storage**: Per page setting tersimpan di session seperti filter lainnya
4. **Performance**: Per page yang lebih besar akan mempengaruhi loading time
5. **Layout**: Grid layout berubah dari 4 kolom ke 5 kolom untuk accommodate per page selector

**Perbaikan pagination di Packing List sudah diterapkan dan konsisten dengan Delivery Order!** 🎯
