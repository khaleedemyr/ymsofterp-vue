# Retail Warehouse Sale - Pagination & Filter Persistence Feature

Dokumentasi fitur pagination dan filter persistence untuk halaman index Retail Warehouse Sale.

## Overview

Fitur ini menambahkan pagination dengan per page selector dan memastikan filter state tetap tersimpan saat pindah page atau navigasi ke halaman detail.

## Fitur Utama

### 1. **Pagination**
- Pagination dengan navigasi Previous/Next
- Page numbers dengan smart ellipsis (...)
- Info pagination (menampilkan X sampai Y dari Z data)
- Responsive design untuk mobile dan desktop

### 2. **Per Page Selector**
- Dropdown untuk memilih jumlah data per halaman
- Options: 10, 15, 25, 50, 100
- Default: 15 items per page
- Auto-refresh saat ganti per page

### 3. **Filter Persistence**
- Filter search, tanggal, dan per page tetap tersimpan
- State tidak hilang saat pindah page
- State tidak hilang saat masuk ke detail dan kembali
- Menggunakan Inertia.js `preserveState: true`

### 4. **Smart Pagination**
- Menampilkan maksimal 7 page numbers
- Ellipsis (...) untuk page yang jauh
- Current page selalu ter-highlight
- Disabled state untuk Previous/Next yang tidak tersedia

## Perubahan yang Dibuat

### 1. Backend Changes

#### **Controller** (`app/Http/Controllers/RetailWarehouseSaleController.php`)
- ✅ **Pagination**: Menggunakan `paginate()` instead of `get()`
- ✅ **Per Page**: Support parameter `per_page` dengan default 15
- ✅ **Filter Persistence**: Include `per_page` dalam filters

```php
$sales = $query->orderByDesc('rws.created_at')->paginate($request->get('per_page', 15));

return Inertia::render('RetailWarehouseSale/Index', [
    'sales' => $sales,
    'filters' => $request->only(['search', 'from', 'to', 'per_page'])
]);
```

### 2. Frontend Changes

#### **Index.vue** (`resources/js/Pages/RetailWarehouseSale/Index.vue`)
- ✅ **Props Update**: `sales` dari Array ke Object (pagination object)
- ✅ **Per Page State**: Tambah `perPage` ref
- ✅ **Filter Grid**: Update dari 4 kolom ke 5 kolom
- ✅ **Per Page Selector**: Dropdown dengan options
- ✅ **Pagination Component**: Full pagination UI
- ✅ **Smart Page Numbers**: Function `getVisiblePages()`
- ✅ **State Management**: Preserve semua filter state

## UI/UX Features

### **Filter Section**
```
┌─────────────────────────────────────────────────────────┐
│ Cari    │ Dari Tanggal │ Sampai Tanggal │ Per Halaman │ Clear │
│ [input] │ [date]       │ [date]         │ [select]    │ [btn] │
└─────────────────────────────────────────────────────────┘
```

### **Pagination Section**
```
┌─────────────────────────────────────────────────────────┐
│ Menampilkan 1 sampai 15 dari 150 data                  │
│                                                         │
│ [← Sebelumnya] [1] [2] [3] ... [10] [Selanjutnya →]    │
└─────────────────────────────────────────────────────────┘
```

### **Per Page Options**
- 10 items per page
- 15 items per page (default)
- 25 items per page
- 50 items per page
- 100 items per page

## Technical Implementation

### **Pagination Logic**
```javascript
function getVisiblePages() {
  const current = props.sales.current_page;
  const last = props.sales.last_page;
  const pages = [];
  
  if (last <= 7) {
    // Show all pages if total pages <= 7
    for (let i = 1; i <= last; i++) {
      pages.push(i);
    }
  } else {
    // Show first page
    pages.push(1);
    
    if (current > 4) {
      pages.push('...');
    }
    
    // Show pages around current page
    const start = Math.max(2, current - 1);
    const end = Math.min(last - 1, current + 1);
    
    for (let i = start; i <= end; i++) {
      if (i !== 1 && i !== last) {
        pages.push(i);
      }
    }
    
    if (current < last - 3) {
      pages.push('...');
    }
    
    // Show last page
    if (last > 1) {
      pages.push(last);
    }
  }
  
  return pages;
}
```

### **Filter Persistence**
```javascript
function debouncedSearch() {
  router.get('/retail-warehouse-sale', { 
    search: search.value, 
    from: from.value, 
    to: to.value,
    per_page: perPage.value
  }, { preserveState: true, replace: true });
}

function goToPage(page) {
  router.get('/retail-warehouse-sale', { 
    search: search.value, 
    from: from.value, 
    to: to.value,
    per_page: perPage.value,
    page: page
  }, { preserveState: true, replace: true });
}
```

### **State Management**
```javascript
// Watch untuk auto search
watch(
  () => props.filters,
  (filters) => {
    search.value = filters?.search || '';
    from.value = filters?.from || '';
    to.value = filters?.to || '';
    perPage.value = filters?.per_page || 15;
  },
  { immediate: true }
);
```

## Database Query

### **Pagination Query**
```sql
SELECT 
  rws.*,
  c.name as customer_name,
  c.code as customer_code,
  w.name as warehouse_name,
  wd.name as division_name,
  u.nama_lengkap as created_by_name
FROM retail_warehouse_sales as rws
LEFT JOIN customers as c ON rws.customer_id = c.id
LEFT JOIN warehouses as w ON rws.warehouse_id = w.id
LEFT JOIN warehouse_division as wd ON rws.warehouse_division_id = wd.id
LEFT JOIN users as u ON rws.created_by = u.id
WHERE (rws.number LIKE '%search%' OR c.name LIKE '%search%' OR ...)
  AND DATE(rws.created_at) >= 'from_date'
  AND DATE(rws.created_at) <= 'to_date'
ORDER BY rws.created_at DESC
LIMIT 15 OFFSET 0
```

## Performance Considerations

### **Optimizations**
- **Pagination**: Hanya load data yang diperlukan per page
- **Indexes**: Database indexes untuk efficient querying
- **Preserve State**: Mengurangi unnecessary re-renders
- **Debounced Search**: Mengurangi API calls

### **User Experience**
- **Fast Navigation**: Instant page switching
- **Filter Persistence**: Tidak kehilangan filter saat navigasi
- **Responsive Design**: Works di semua device sizes
- **Loading States**: Smooth transitions

## Browser Compatibility

- **Chrome/Chromium**: Full support
- **Firefox**: Full support
- **Safari**: Full support
- **Edge**: Full support

## Testing

### **Manual Testing**
1. **Pagination**: Test Previous/Next buttons
2. **Page Numbers**: Test click page numbers
3. **Per Page**: Test ganti per page options
4. **Filter Persistence**: Test filter tetap saat pindah page
5. **Navigation**: Test ke detail dan kembali
6. **Responsive**: Test di mobile dan desktop
7. **Edge Cases**: Test dengan data kosong, 1 page, banyak page

### **Edge Cases**
- Data kosong (no pagination shown)
- 1 page only (no pagination shown)
- Very large datasets (ellipsis working)
- Network errors during pagination
- Filter dengan hasil kosong

## Future Enhancements

1. **Jump to Page**: Input field untuk langsung ke page tertentu
2. **Page Size Presets**: Remember user's preferred page size
3. **Export Paginated**: Export data sesuai page yang sedang dilihat
4. **Bulk Actions**: Select multiple items across pages
5. **Advanced Filters**: More filter options dengan pagination
6. **Infinite Scroll**: Alternative to pagination
7. **Search Highlight**: Highlight search terms in results

## Security

- **Input Validation**: Validate page numbers dan per_page
- **SQL Injection**: Parameterized queries
- **XSS Protection**: Escape output data
- **CSRF Protection**: Laravel CSRF token

## Maintenance

### **Regular Tasks**
1. **Monitor Performance**: Cek query execution time
2. **Update Indexes**: Optimize database indexes jika perlu
3. **Test Compatibility**: Test dengan browser baru
4. **User Feedback**: Collect feedback untuk improvement

### **Logs**
- Pagination activity logs
- Performance metrics
- Error logs untuk troubleshooting

## Troubleshooting

### **Common Issues**
1. **Filter Hilang**: Pastikan `preserveState: true` digunakan
2. **Pagination Error**: Cek page number validation
3. **Slow Loading**: Cek database indexes
4. **Mobile Issues**: Test responsive design

### **Debug Tips**
- Check browser network tab untuk API calls
- Verify filter parameters di URL
- Test dengan data sample yang berbeda
- Check console untuk JavaScript errors
