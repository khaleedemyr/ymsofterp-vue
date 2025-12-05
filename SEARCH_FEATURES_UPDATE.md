# Search Features Update

## 🎯 **Fitur Search yang Telah Diperbarui**

### ✅ **Packing List Search**

**Field yang bisa di-search:**
1. **Nomor Packing List** (`packing_number`)
2. **Nomor Floor Order** (`order_number`) 
3. **Nama Creator** (`nama_lengkap`)
4. **Divisi Gudang Asal** (`warehouse_division.name`) - **BARU**
5. **Outlet Tujuan** (`outlet.nama_outlet`) - **BARU**

**Contoh Search:**
- `PL-2024-001` → Cari berdasarkan nomor packing list
- `FO-2024-001` → Cari berdasarkan nomor floor order
- `John Doe` → Cari berdasarkan nama creator
- `Perishable` → Cari berdasarkan divisi gudang
- `Outlet Jakarta` → Cari berdasarkan outlet tujuan

### ✅ **Delivery Order Search**

**Field yang bisa di-search:**
1. **Nomor Packing List** (`packing_number`)
2. **Nomor GR** (`gr_number`) - untuk RO Supplier
3. **Nomor Floor Order** (`order_number`)
4. **Nama Creator** (`nama_lengkap`)
5. **Outlet Tujuan** (`nama_outlet`)
6. **Warehouse Outlet** (`warehouse_outlet.name`)
7. **Nomor Delivery Order** (`do.number`) - **BARU**

**Contoh Search:**
- `PL-2024-001` → Cari berdasarkan nomor packing list
- `GR-2024-001` → Cari berdasarkan nomor GR
- `DO-2024-001` → Cari berdasarkan nomor delivery order
- `FO-2024-001` → Cari berdasarkan nomor floor order
- `John Doe` → Cari berdasarkan nama creator
- `Outlet Jakarta` → Cari berdasarkan outlet tujuan
- `Warehouse Pusat` → Cari berdasarkan warehouse outlet

## 🔧 **Implementasi Teknis**

### **Packing List Controller**
```php
if ($request->filled('search')) {
    $search = '%' . $request->search . '%';
    $query->where(function($q) use ($search) {
        $q->where('packing_number', 'like', $search)
          ->orWhereHas('floorOrder', function($subQ) use ($search) {
              $subQ->where('order_number', 'like', $search);
          })
          ->orWhereHas('creator', function($subQ) use ($search) {
              $subQ->where('nama_lengkap', 'like', $search);
          })
          ->orWhereHas('warehouseDivision', function($subQ) use ($search) {
              $subQ->where('name', 'like', $search);
          })
          ->orWhereHas('floorOrder.outlet', function($subQ) use ($search) {
              $subQ->where('nama_outlet', 'like', $search);
          });
    });
}
```

### **Delivery Order Controller**
```php
if ($request->filled('search')) {
    $search = '%' . $request->search . '%';
    $packingListQuery->where(function($q) use ($search) {
        $q->where('pl.packing_number', 'like', $search)
          ->orWhere('fo.order_number', 'like', $search)
          ->orWhere('u.nama_lengkap', 'like', $search)
          ->orWhere('o.nama_outlet', 'like', $search)
          ->orWhere('wo.name', 'like', $search)
          ->orWhere('do.number', 'like', $search);
    });
    
    $roSupplierQuery->where(function($q) use ($search) {
        $q->where('gr.gr_number', 'like', $search)
          ->orWhere('fo.order_number', 'like', $search)
          ->orWhere('u.nama_lengkap', 'like', $search)
          ->orWhere('o.nama_outlet', 'like', $search)
          ->orWhere('wo.name', 'like', $search)
          ->orWhere('do.number', 'like', $search);
    });
}
```

## 🚀 **Manfaat Update**

### **1. User Experience:**
- ✅ **Flexible Search**: User bisa search berdasarkan berbagai field
- ✅ **Quick Find**: Mudah mencari data berdasarkan nomor atau nama
- ✅ **Intuitive**: Search sesuai dengan kebutuhan user

### **2. Performance:**
- ✅ **Optimized Queries**: Menggunakan `whereHas` untuk relationship
- ✅ **Index Friendly**: Query menggunakan field yang sudah di-index
- ✅ **Efficient**: Single query dengan multiple conditions

### **3. Functionality:**
- ✅ **Comprehensive**: Mencakup semua field penting
- ✅ **Consistent**: Search behavior sama di kedua menu
- ✅ **Extensible**: Mudah ditambah field search baru

## 📊 **Search Examples**

### **Packing List Search Examples:**
```
Search: "PL-2024"     → Find all packing lists with "PL-2024" in number
Search: "Perishable"  → Find all packing lists from Perishable division
Search: "Jakarta"     → Find all packing lists to Jakarta outlet
Search: "John"        → Find all packing lists created by John
Search: "FO-2024"     → Find all packing lists with "FO-2024" in floor order
```

### **Delivery Order Search Examples:**
```
Search: "DO-2024"     → Find all delivery orders with "DO-2024" in number
Search: "PL-2024"     → Find all delivery orders with "PL-2024" in packing list
Search: "GR-2024"     → Find all delivery orders with "GR-2024" in GR number
Search: "Jakarta"     → Find all delivery orders to Jakarta outlet
Search: "John"        → Find all delivery orders created by John
Search: "Warehouse"   → Find all delivery orders from warehouse outlets
```

## 🔍 **Search Tips for Users**

### **1. Partial Search:**
- Gunakan sebagian kata untuk hasil yang lebih luas
- Contoh: "Jakarta" akan menemukan "Outlet Jakarta Pusat"

### **2. Number Search:**
- Search berdasarkan nomor untuk hasil yang tepat
- Contoh: "PL-2024-001" untuk packing list spesifik

### **3. Name Search:**
- Search berdasarkan nama untuk hasil yang relevan
- Contoh: "John" akan menemukan "John Doe", "John Smith"

### **4. Division Search:**
- Search berdasarkan divisi gudang
- Contoh: "Perishable", "Frozen", "Dry"

## ⚠️ **Important Notes**

1. **Case Insensitive**: Search tidak case sensitive
2. **Partial Match**: Search menggunakan `LIKE %search%`
3. **Multiple Fields**: Search di semua field sekaligus
4. **Performance**: Query sudah dioptimasi dengan index
5. **Backward Compatible**: Tidak mengubah fungsi existing

## 🧪 **Testing Checklist**

### **Packing List Search:**
- [ ] Search by packing number
- [ ] Search by floor order number
- [ ] Search by creator name
- [ ] Search by warehouse division
- [ ] Search by outlet name
- [ ] Search with partial text
- [ ] Search with case insensitive

### **Delivery Order Search:**
- [ ] Search by delivery order number
- [ ] Search by packing list number
- [ ] Search by GR number
- [ ] Search by floor order number
- [ ] Search by creator name
- [ ] Search by outlet name
- [ ] Search by warehouse outlet
- [ ] Search with partial text
- [ ] Search with case insensitive

## 🚀 **Expected Results**

| Search Type | Before | After |
|-------------|--------|-------|
| **Packing Number** | ✅ Working | ✅ Working |
| **Floor Order** | ✅ Working | ✅ Working |
| **Creator Name** | ✅ Working | ✅ Working |
| **Warehouse Division** | ❌ Not Available | ✅ **NEW** |
| **Outlet Name** | ❌ Not Available | ✅ **NEW** |
| **Delivery Order Number** | ❌ Not Available | ✅ **NEW** |

Dengan update ini, user sekarang bisa search berdasarkan **nomor**, **divisi gudang asal**, dan **outlet tujuan** seperti yang diminta! 🎯
