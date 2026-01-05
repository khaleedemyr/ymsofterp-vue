# 🔧 Fix Error Purchase Requisition Food - JSON Response Too Large

## Masalah
Error "Unexpected JSON response from an Inertia request" di menu Purchase Requisition Food versi web. Response JSON terlalu besar karena memuat semua data personal user (requester) yang tidak diperlukan.

---

## ✅ SOLUSI YANG SUDAH DITERAPKAN

### **Perbaikan 1: Optimasi Eager Loading di Method `index()`**

**Sebelum:**
```php
$query = PrFood::with([
    'warehouse', 
    'warehouseDivision', 
    'requester',  // ❌ Load semua field user (termasuk data personal)
    ...
]);
```

**Sesudah:**
```php
$query = PrFood::with([
    'warehouse:id,code,name,location,status', 
    'warehouseDivision:id,name,warehouse_id,status', 
    'requester:id,nama_lengkap,email,no_hp',  // ✅ Hanya field yang diperlukan
    ...
]);
```

**Dampak:**
- Response size berkurang drastis (dari ~MB menjadi beberapa KB)
- Hanya field yang diperlukan yang di-load
- Performa lebih cepat

---

### **Perbaikan 2: Optimasi Eager Loading di Method `getPendingApprovals()`**

**Sebelum:**
```php
$query = PrFood::with(['warehouse', 'requester', 'items'])
```

**Sesudah:**
```php
$query = PrFood::with([
    'warehouse:id,code,name,location,status',
    'requester:id,nama_lengkap,email',
    'items:id,pr_food_id,item_id,qty,unit'
])
```

---

## 📋 Field yang Di-Load

### **Requester (User)**
Hanya field yang diperlukan untuk display:
- `id` - ID user
- `nama_lengkap` - Nama lengkap
- `email` - Email (optional)
- `no_hp` - No HP (optional)

**Tidak di-load:**
- Data personal (nama_ayah, nama_ibu, dll)
- Data keluarga
- Data pendidikan
- Data kesehatan
- Data lainnya yang tidak diperlukan untuk list PR Food

### **Warehouse**
Hanya field yang diperlukan:
- `id`, `code`, `name`, `location`, `status`

### **Warehouse Division**
Hanya field yang diperlukan:
- `id`, `name`, `warehouse_id`, `status`

---

## ✅ Verifikasi

Setelah perbaikan:

1. **Test di browser:**
   - Buka menu Purchase Requisition Food
   - Pastikan tidak ada error "Unexpected JSON response"
   - Data list PR Food muncul dengan benar

2. **Check response size:**
   - Buka Developer Tools (F12)
   - Tab Network
   - Reload halaman
   - Check size response untuk endpoint PR Food
   - Harusnya jauh lebih kecil dari sebelumnya

3. **Test pagination:**
   - Pastikan pagination bekerja dengan benar
   - Test filter/search
   - Test sorting

---

## 🔍 Troubleshooting

### Jika masih ada error:

1. **Clear cache:**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   php artisan view:clear
   ```

2. **Check apakah perubahan sudah di-deploy:**
   - Pastikan file `PrFoodController.php` sudah di-update
   - Check timestamp file

3. **Check browser cache:**
   - Hard refresh: `Ctrl+Shift+R` (Windows) atau `Cmd+Shift+R` (Mac)
   - Clear browser cache

4. **Check log:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

---

## 📝 Catatan

1. **Untuk detail PR Food** (method `show()`), masih load semua data karena diperlukan untuk detail view
2. **Untuk API response**, juga sudah di-optimize
3. **Pagination** tetap bekerja normal dengan perbaikan ini

---

## 🎯 Expected Results

Setelah perbaikan:
- ✅ Tidak ada error "Unexpected JSON response"
- ✅ Halaman load lebih cepat
- ✅ Response size jauh lebih kecil
- ✅ Data yang ditampilkan tetap lengkap untuk kebutuhan list
- ✅ Pagination dan filter bekerja normal

---

## 🔗 File yang Diubah

- `app/Http/Controllers/PrFoodController.php`
  - Method `index()` - Line 21-28
  - Method `getPendingApprovals()` - Line 607

