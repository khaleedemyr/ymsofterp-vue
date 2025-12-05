# Filter Persistence Feature

## 🎯 **Fitur Filter yang Tidak Hilang**

Filter sekarang akan **tetap tersimpan** saat:
- ✅ **Pindah halaman** (pagination)
- ✅ **Refresh browser** (F5)
- ✅ **Navigasi kembali** (back button)
- ✅ **Tutup dan buka browser** (selama session masih aktif)

## 🔧 **Implementasi Teknis**

### **1. Backend - Session Storage**

**PackingListController.php:**
```php
// Simpan filter di session
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

// Ambil filter dari session
$filters = session('packing_list_filters', []);
$search = $request->search ?? $filters['search'] ?? '';
$dateFrom = $request->date_from ?? $filters['date_from'] ?? '';
// ... dst
```

**DeliveryOrderController.php:**
```php
// Simpan filter di session
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
```

### **2. Clear Filters Method**

**PackingListController.php:**
```php
public function clearFilters()
{
    session()->forget('packing_list_filters');
    return redirect()->route('packing-list.index');
}
```

**DeliveryOrderController.php:**
```php
public function clearFilters()
{
    session()->forget('delivery_order_filters');
    return redirect()->route('delivery-order.index');
}
```

### **3. Frontend - Clear Filters**

**PackingList/Index.vue:**
```javascript
function clearFilters() {
  // Call backend method to clear session filters
  router.get('/packing-list/clear-filters', {}, { 
    preserveState: false, 
    replace: true 
  });
}
```

**DeliveryOrder/Index.vue:**
```javascript
function clearFilters() {
  // Call backend method to clear session filters
  router.get(route('delivery-order.clear-filters'), {}, { 
    preserveState: false, 
    replace: true 
  });
}
```

## 🚀 **Cara Kerja**

### **1. Saat User Mengisi Filter:**
1. User pilih filter (search, date, status)
2. User klik "Load Data"
3. Backend menyimpan filter di session
4. Data ditampilkan dengan filter

### **2. Saat User Pindah Halaman:**
1. User klik pagination (Next, Previous, Page 2, dll)
2. Backend mengambil filter dari session
3. Data ditampilkan dengan filter yang sama
4. Filter form tetap terisi

### **3. Saat User Refresh Browser:**
1. User tekan F5 atau refresh
2. Backend mengambil filter dari session
3. Data ditampilkan dengan filter yang sama
4. Filter form tetap terisi

### **4. Saat User Klik "Clear Filter":**
1. User klik tombol "Clear Filter"
2. Backend menghapus filter dari session
3. Halaman reload tanpa filter
4. Filter form kosong

## 📊 **Session Storage Structure**

### **Packing List Filters:**
```php
session('packing_list_filters') = [
    'search' => 'PL-2024',
    'date_from' => '2024-01-01',
    'date_to' => '2024-01-31',
    'status' => 'packing',
    'load_data' => '1'
]
```

### **Delivery Order Filters:**
```php
session('delivery_order_filters') = [
    'search' => 'DO-2024',
    'dateFrom' => '2024-01-01',
    'dateTo' => '2024-01-31',
    'load_data' => '1'
]
```

## 🎯 **Manfaat**

### **1. User Experience:**
- ✅ **Tidak Hilang Filter** - Filter tetap tersimpan
- ✅ **Mudah Navigasi** - Bisa pindah halaman tanpa kehilangan filter
- ✅ **Refresh Aman** - Bisa refresh tanpa kehilangan filter
- ✅ **Back Button Friendly** - Bisa kembali tanpa kehilangan filter

### **2. Productivity:**
- ✅ **Efisien** - Tidak perlu isi filter berulang kali
- ✅ **Konsisten** - Filter tetap sama di semua navigasi
- ✅ **User Friendly** - Sesuai dengan ekspektasi user

### **3. Technical:**
- ✅ **Session Based** - Menggunakan Laravel session
- ✅ **Per User** - Setiap user punya filter sendiri
- ✅ **Automatic Cleanup** - Session otomatis expired
- ✅ **Memory Efficient** - Hanya menyimpan data yang diperlukan

## ⚠️ **Important Notes**

1. **Session Duration**: Filter akan hilang saat session expired (default 2 jam)
2. **Per User**: Setiap user punya filter session sendiri
3. **Per Menu**: Packing List dan Delivery Order punya filter session terpisah
4. **Clear Manual**: User bisa clear filter dengan tombol "Clear Filter"
5. **Browser Dependent**: Filter hilang jika clear browser data

## 🔄 **Routes yang Perlu Ditambahkan**

**web.php:**
```php
// Packing List
Route::get('/packing-list/clear-filters', [PackingListController::class, 'clearFilters'])->name('packing-list.clear-filters');

// Delivery Order  
Route::get('/delivery-order/clear-filters', [DeliveryOrderController::class, 'clearFilters'])->name('delivery-order.clear-filters');
```

## 🧪 **Testing Checklist**

### **Packing List:**
- [ ] Filter tersimpan saat pindah halaman
- [ ] Filter tersimpan saat refresh browser
- [ ] Filter tersimpan saat navigasi kembali
- [ ] Clear filter berfungsi dengan benar
- [ ] Filter per user berbeda

### **Delivery Order:**
- [ ] Filter tersimpan saat pindah halaman
- [ ] Filter tersimpan saat refresh browser
- [ ] Filter tersimpan saat navigasi kembali
- [ ] Clear filter berfungsi dengan benar
- [ ] Filter per user berbeda

## 🚀 **Expected Results**

| Action | Before | After |
|--------|--------|-------|
| **Pindah Halaman** | ❌ Filter hilang | ✅ Filter tetap |
| **Refresh Browser** | ❌ Filter hilang | ✅ Filter tetap |
| **Navigasi Kembali** | ❌ Filter hilang | ✅ Filter tetap |
| **Clear Filter** | ✅ Berfungsi | ✅ Berfungsi |
| **Per User** | ✅ Terpisah | ✅ Terpisah |

Dengan fitur ini, user tidak akan kehilangan filter saat navigasi dan bisa bekerja lebih efisien! 🎯
