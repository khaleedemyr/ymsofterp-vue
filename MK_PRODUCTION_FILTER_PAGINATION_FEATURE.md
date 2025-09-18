# MK Production - Filter & Pagination Feature

Dokumentasi fitur filter dan pagination untuk halaman MK Production.

## Overview

Fitur ini menambahkan kemampuan filtering dan pagination untuk halaman MK Production, memungkinkan user untuk mencari, memfilter, dan mengatur jumlah data per halaman dengan mudah.

## Fitur Utama

### 1. **Search Filter**
- Pencarian berdasarkan item name, batch number, user name, dan notes
- Real-time search dengan debounce
- Case-insensitive search

### 2. **Item Filter**
- Dropdown filter berdasarkan item hasil produksi
- Semua item yang memiliki composition_type = 'composed' dan status = 'active'
- Option "Semua Item" untuk menampilkan semua data

### 3. **Date Range Filter**
- Filter berdasarkan tanggal produksi
- Date picker untuk "Dari Tanggal" dan "Sampai Tanggal"
- Support range tanggal untuk analisis periode

### 4. **Per Page Selector**
- Dropdown untuk memilih jumlah data per halaman
- Options: 10, 15, 25, 50, 100
- Default: 15 items per page

### 5. **Pagination**
- Navigasi Previous/Next dengan disabled state
- Page numbers dengan Laravel pagination links
- Info pagination (menampilkan X sampai Y dari Z data)
- Responsive design untuk mobile dan desktop

### 6. **Filter Persistence**
- Filter state tetap tersimpan saat pindah page
- State tidak hilang saat navigasi ke detail dan kembali
- Menggunakan Inertia.js `preserveState: true`

## Perubahan yang Dibuat

### 1. Backend Changes

#### **Controller** (`app/Http/Controllers/MKProductionController.php`)
- ✅ **Query Builder**: Menggunakan query builder untuk filter
- ✅ **Search Filter**: Search berdasarkan multiple fields
- ✅ **Item Filter**: Filter berdasarkan item_id
- ✅ **Date Filter**: Filter berdasarkan production_date range
- ✅ **Pagination**: Support per_page parameter
- ✅ **Filter Persistence**: Include filters dalam response

```php
// Query untuk histori produksi dengan filter
$query = DB::table('mk_productions')
    ->leftJoin('items', 'mk_productions.item_id', '=', 'items.id')
    ->leftJoin('units', 'mk_productions.unit_id', '=', 'units.id')
    ->leftJoin('users', 'mk_productions.created_by', '=', 'users.id')
    ->select(
        'mk_productions.*',
        'items.name as item_name',
        'units.name as unit_name',
        'users.nama_lengkap as created_by_name'
    );

// Apply filters
if ($request->filled('search')) {
    $search = '%' . $request->search . '%';
    $query->where(function($q) use ($search) {
        $q->where('items.name', 'like', $search)
          ->orWhere('mk_productions.batch_number', 'like', $search)
          ->orWhere('users.nama_lengkap', 'like', $search)
          ->orWhere('mk_productions.notes', 'like', $search);
    });
}

if ($request->filled('item_id')) {
    $query->where('mk_productions.item_id', $request->item_id);
}

if ($request->filled('from_date')) {
    $query->whereDate('mk_productions.production_date', '>=', $request->from_date);
}

if ($request->filled('to_date')) {
    $query->whereDate('mk_productions.production_date', '<=', $request->to_date);
}

// Pagination dengan per page
$perPage = $request->get('per_page', 15);
$productions = $query->orderByDesc('mk_productions.production_date')
    ->orderByDesc('mk_productions.id')
    ->paginate($perPage);
```

### 2. Frontend Changes

#### **Index.vue** (`resources/js/Pages/MKProduction/Index.vue`)
- ✅ **Filter Section**: Grid layout dengan 6 kolom filter
- ✅ **Search Input**: Real-time search dengan placeholder yang jelas
- ✅ **Item Dropdown**: Filter berdasarkan item hasil produksi
- ✅ **Date Range**: Date picker untuk range tanggal
- ✅ **Per Page Selector**: Dropdown untuk jumlah data per halaman
- ✅ **Clear Filters**: Tombol untuk reset semua filter
- ✅ **Pagination UI**: Improved pagination dengan info dan controls
- ✅ **State Management**: Preserve semua filter state

## UI/UX Features

### **Filter Section Layout**
```
┌─────────────────────────────────────────────────────────────────────────┐
│ Cari        │ Item        │ Dari Tanggal │ Sampai Tanggal │ Per Page │ Clear │
│ [input]     │ [select]    │ [date]       │ [date]         │ [select] │ [btn] │
└─────────────────────────────────────────────────────────────────────────┘
```

### **Search Functionality**
- **Placeholder**: "Cari item, batch, user, catatan..."
- **Search Fields**: 
  - Item name
  - Batch number
  - User name (created_by)
  - Notes
- **Real-time**: Search saat user mengetik

### **Item Filter**
- **Options**: Semua item hasil produksi (composed & active)
- **Default**: "Semua Item"
- **Dynamic**: Berdasarkan data dari backend

### **Date Range Filter**
- **From Date**: Tanggal mulai produksi
- **To Date**: Tanggal akhir produksi
- **Validation**: To date harus >= from date
- **Format**: Date picker dengan format YYYY-MM-DD

### **Per Page Options**
- 10 items per page
- 15 items per page (default)
- 25 items per page
- 50 items per page
- 100 items per page

### **Pagination Section**
```
┌─────────────────────────────────────────────────────────────────────────┐
│ Menampilkan 1 sampai 15 dari 150 data                                  │
│                                                                         │
│ [← Sebelumnya] [1] [2] [3] ... [10] [Selanjutnya →]                    │
└─────────────────────────────────────────────────────────────────────────┘
```

## Technical Implementation

### **Filter Logic**
```javascript
function debouncedSearch() {
  router.get('/mk-production', { 
    search: search.value, 
    item_id: selectedItem.value,
    from_date: fromDate.value,
    to_date: toDate.value,
    per_page: perPage.value
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
    selectedItem.value = filters?.item_id || '';
    fromDate.value = filters?.from_date || '';
    toDate.value = filters?.to_date || '';
    perPage.value = filters?.per_page || 15;
  },
  { immediate: true }
);
```

### **Clear Filters**
```javascript
function clearFilters() {
  search.value = '';
  selectedItem.value = '';
  fromDate.value = '';
  toDate.value = '';
  perPage.value = 15;
  debouncedSearch();
}
```

## Database Query

### **Search Query**
```sql
SELECT 
  mk_productions.*,
  items.name as item_name,
  units.name as unit_name,
  users.nama_lengkap as created_by_name
FROM mk_productions
LEFT JOIN items ON mk_productions.item_id = items.id
LEFT JOIN units ON mk_productions.unit_id = units.id
LEFT JOIN users ON mk_productions.created_by = users.id
WHERE (
  items.name LIKE '%search%' OR
  mk_productions.batch_number LIKE '%search%' OR
  users.nama_lengkap LIKE '%search%' OR
  mk_productions.notes LIKE '%search%'
)
AND mk_productions.item_id = ?
AND DATE(mk_productions.production_date) >= ?
AND DATE(mk_productions.production_date) <= ?
ORDER BY mk_productions.production_date DESC, mk_productions.id DESC
LIMIT 15 OFFSET 0
```

## Performance Considerations

### **Optimizations**
- **Indexed Queries**: Database indexes untuk efficient querying
- **Pagination**: Hanya load data yang diperlukan per page
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
1. **Search Filter**: Test search dengan berbagai keyword
2. **Item Filter**: Test filter berdasarkan item
3. **Date Range**: Test filter berdasarkan tanggal
4. **Per Page**: Test ganti per page options
5. **Pagination**: Test Previous/Next dan page numbers
6. **Filter Persistence**: Test filter tetap saat navigasi
7. **Clear Filters**: Test reset semua filter
8. **Responsive**: Test di mobile dan desktop

### **Edge Cases**
- Search dengan keyword yang tidak ada
- Date range yang tidak valid
- Per page dengan nilai ekstrem
- Pagination dengan data kosong
- Network errors selama filtering

## Future Enhancements

1. **Advanced Filters**: Filter berdasarkan warehouse, status, dll
2. **Export Function**: Export data sesuai filter
3. **Saved Filters**: Simpan filter favorit user
4. **Bulk Actions**: Select multiple items untuk operasi batch
5. **Real-time Updates**: Auto-refresh data
6. **Search Highlight**: Highlight search terms dalam hasil
7. **Filter Presets**: Template filter untuk periode tertentu

## Security

- **Input Validation**: Validate semua filter parameters
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
- Filter activity logs
- Performance metrics
- Error logs untuk troubleshooting
- User interaction logs

## Troubleshooting

### **Common Issues**
1. **Filter Tidak Berfungsi**: Cek parameter yang dikirim ke backend
2. **Pagination Error**: Cek page number validation
3. **Slow Loading**: Cek database indexes
4. **Mobile Issues**: Test responsive design

### **Debug Tips**
- Check browser network tab untuk API calls
- Verify filter parameters di URL
- Test dengan data sample yang berbeda
- Check console untuk JavaScript errors
