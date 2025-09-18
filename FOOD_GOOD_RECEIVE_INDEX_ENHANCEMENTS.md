# Food Good Receive Index - Notes Display & Filter Persistence

Dokumentasi fitur untuk menampilkan notes di halaman index Food Good Receive dan implementasi filter persistence.

## Overview

Fitur ini menambahkan kolom keterangan (notes) pada tabel index Food Good Receive dan memastikan filter tidak hilang saat navigasi atau ganti page.

## Fitur Utama

### 1. **Notes Display**
- Kolom keterangan ditampilkan di tabel index
- Notes ditampilkan dengan truncate untuk panjang teks
- Tooltip untuk melihat notes lengkap
- Indikator "-" untuk notes kosong

### 2. **Enhanced Search**
- Search dapat mencari berdasarkan notes
- Placeholder yang informatif
- Real-time search dengan debouncing

### 3. **Filter Persistence**
- Filter tersimpan di sessionStorage
- Filter tidak hilang saat ganti page
- Filter tidak hilang saat navigasi ke detail/edit
- Auto-restore filter saat kembali ke halaman

## Perubahan yang Dibuat

### 1. Backend Changes

#### **Index Query Update**
```php
$query = DB::table('food_good_receives as gr')
    ->leftJoin('purchase_order_foods as po', 'gr.po_id', '=', 'po.id')
    ->leftJoin('suppliers as s', 'gr.supplier_id', '=', 's.id')
    ->leftJoin('users as u', 'gr.received_by', '=', 'u.id')
    ->select(
        'gr.id',
        'gr.gr_number',
        'gr.receive_date',
        'gr.notes', // Added notes field
        'po.number as po_number',
        's.name as supplier_name',
        'u.nama_lengkap as received_by_name'
    );
```

#### **Search Enhancement**
```php
if ($request->search) {
    $search = $request->search;
    $query->where(function($q) use ($search) {
        $q->where('gr.gr_number', 'like', "%$search%")
          ->orWhere('po.number', 'like', "%$search%")
          ->orWhere('s.name', 'like', "%$search%")
          ->orWhere('u.nama_lengkap', 'like', "%$search%")
          ->orWhere('gr.notes', 'like', "%$search%") // Added notes search
        ;
    });
}
```

### 2. Frontend Changes

#### **Table Header Update**
```html
<thead class="bg-gradient-to-r from-blue-50 to-blue-100">
  <tr>
    <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tl-2xl">Nomor GR</th>
    <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Tanggal</th>
    <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">No. PO</th>
    <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Supplier</th>
    <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Petugas</th>
    <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Keterangan</th> <!-- New column -->
    <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tr-2xl">Aksi</th>
  </tr>
</thead>
```

#### **Table Body Update**
```html
<tr v-for="gr in goodReceives.data" :key="gr.id" class="hover:bg-blue-50 transition shadow-sm">
  <td class="px-6 py-3 font-semibold">{{ gr.gr_number }}</td>
  <td class="px-6 py-3">{{ gr.receive_date }}</td>
  <td class="px-6 py-3">{{ gr.po_number }}</td>
  <td class="px-6 py-3">{{ gr.supplier_name }}</td>
  <td class="px-6 py-3">{{ gr.received_by_name }}</td>
  <td class="px-6 py-3">
    <div v-if="gr.notes" class="max-w-xs">
      <div class="text-sm text-gray-700 truncate" :title="gr.notes">
        {{ gr.notes }}
      </div>
    </div>
    <div v-else class="text-gray-400 text-sm italic">
      -
    </div>
  </td>
  <td class="px-6 py-3">
    <!-- Action buttons -->
  </td>
</tr>
```

#### **Search Placeholder Update**
```html
<input
  v-model="search"
  @input="onSearchInput"
  type="text"
  placeholder="Cari Nomor GR, PO, Supplier, Petugas, atau Keterangan..."
  class="w-64 px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
/>
```

#### **Filter Persistence Implementation**
```javascript
// Filter persistence functions
const saveFilterState = () => {
  const filterState = {
    search: search.value,
    from: from.value,
    to: to.value
  };
  sessionStorage.setItem('foodGoodReceiveFilters', JSON.stringify(filterState));
};

const restoreFilterState = () => {
  try {
    const savedFilters = sessionStorage.getItem('foodGoodReceiveFilters');
    if (savedFilters) {
      const filterState = JSON.parse(savedFilters);
      search.value = filterState.search || '';
      from.value = filterState.from || '';
      to.value = filterState.to || '';
    }
  } catch (error) {
    console.error('Error restoring filter state:', error);
  }
};

// Watch for filter changes to auto-save
watch([search, from, to], () => {
  saveFilterState();
}, { deep: true });

// Initialize filter state on mount
onMounted(() => {
  restoreFilterState();
  saveFilterState(); // Save initial state
});
```

#### **Navigation Functions Update**
```javascript
// All navigation functions now save filter state
async function openDetail(id) {
  saveFilterState();
  // ... rest of function
}

async function openEdit(id) {
  saveFilterState();
  // ... rest of function
}

function goToPage(url) {
  if (url) {
    saveFilterState();
    router.get(url, { search: search.value, from: from.value, to: to.value }, { preserveState: true, replace: true });
  }
}
```

## UI/UX Features

### **Table Layout**

#### **Before (Without Notes Column)**
```
┌─────────────────────────────────────────────────────────┐
│ Nomor GR │ Tanggal │ No. PO │ Supplier │ Petugas │ Aksi │
├─────────────────────────────────────────────────────────┤
│ GR-001   │ 2024-01-01 │ PO-001 │ Supplier A │ User 1 │ [Detail] [Edit] [Delete] │
└─────────────────────────────────────────────────────────┘
```

#### **After (With Notes Column)**
```
┌─────────────────────────────────────────────────────────────────────────┐
│ Nomor GR │ Tanggal │ No. PO │ Supplier │ Petugas │ Keterangan │ Aksi │
├─────────────────────────────────────────────────────────────────────────┤
│ GR-001   │ 2024-01-01 │ PO-001 │ Supplier A │ User 1 │ Barang dalam kondisi baik │ [Detail] [Edit] [Delete] │
│ GR-002   │ 2024-01-02 │ PO-002 │ Supplier B │ User 2 │ - │ [Detail] [Edit] [Delete] │
└─────────────────────────────────────────────────────────────────────────┘
```

### **Notes Display Design**

#### **With Notes**
- **Container**: `max-w-xs` untuk membatasi lebar
- **Text**: `text-sm text-gray-700` untuk ukuran dan warna
- **Truncate**: `truncate` untuk memotong teks panjang
- **Tooltip**: `:title="gr.notes"` untuk melihat teks lengkap

#### **Without Notes**
- **Text**: `text-gray-400 text-sm italic`
- **Content**: "-" sebagai indikator kosong

### **Search Enhancement**

#### **Placeholder Text**
```
Before: "Cari Nomor GR, PO, Supplier, atau Petugas..."
After:  "Cari Nomor GR, PO, Supplier, Petugas, atau Keterangan..."
```

#### **Search Functionality**
- **Real-time**: Search dengan debouncing 400ms
- **Multi-field**: Mencari di semua field termasuk notes
- **Case-insensitive**: Search tidak case sensitive
- **Partial match**: Mencari dengan LIKE operator

## Technical Implementation

### **1. Backend Data Flow**

#### **Query Structure**
```sql
SELECT 
    gr.id,
    gr.gr_number,
    gr.receive_date,
    gr.notes, -- New field
    po.number as po_number,
    s.name as supplier_name,
    u.nama_lengkap as received_by_name
FROM food_good_receives as gr
LEFT JOIN purchase_order_foods as po ON gr.po_id = po.id
LEFT JOIN suppliers as s ON gr.supplier_id = s.id
LEFT JOIN users as u ON gr.received_by = u.id
WHERE (
    gr.gr_number LIKE '%search%' OR
    po.number LIKE '%search%' OR
    s.name LIKE '%search%' OR
    u.nama_lengkap LIKE '%search%' OR
    gr.notes LIKE '%search%' -- New search field
)
```

### **2. Frontend Data Flow**

#### **Filter Persistence Flow**
```
1. User changes filter → 2. Watch triggers → 3. saveFilterState() → 4. sessionStorage
1. Page loads → 2. onMounted → 3. restoreFilterState() → 4. Update refs
1. Navigation → 2. saveFilterState() → 3. Navigate → 4. Filter preserved
```

#### **SessionStorage Structure**
```javascript
{
  "foodGoodReceiveFilters": {
    "search": "search term",
    "from": "2024-01-01",
    "to": "2024-01-31"
  }
}
```

### **3. Vue.js Reactivity**

#### **Reactive References**
```javascript
const search = ref(props.filters?.search || '');
const from = ref(props.filters?.from || '');
const to = ref(props.filters?.to || '');
```

#### **Watchers**
```javascript
watch([search, from, to], () => {
  saveFilterState();
}, { deep: true });
```

#### **Lifecycle Hooks**
```javascript
onMounted(() => {
  restoreFilterState();
  saveFilterState();
});
```

## Filter Persistence Features

### **1. Auto-Save**
- **Trigger**: Setiap perubahan filter
- **Method**: Vue.js watchers
- **Storage**: sessionStorage
- **Scope**: Per session browser

### **2. Auto-Restore**
- **Trigger**: Page load/mount
- **Method**: onMounted lifecycle
- **Source**: sessionStorage
- **Fallback**: Props dari server

### **3. Navigation Persistence**
- **Detail Modal**: Filter tersimpan sebelum buka modal
- **Edit Modal**: Filter tersimpan sebelum buka modal
- **Pagination**: Filter tersimpan sebelum ganti page
- **Delete Action**: Filter tersimpan sebelum reload

### **4. Error Handling**
```javascript
const restoreFilterState = () => {
  try {
    const savedFilters = sessionStorage.getItem('foodGoodReceiveFilters');
    if (savedFilters) {
      const filterState = JSON.parse(savedFilters);
      // Restore filters
    }
  } catch (error) {
    console.error('Error restoring filter state:', error);
    // Graceful fallback to props
  }
};
```

## Search Functionality

### **1. Search Fields**
- **GR Number**: `gr.gr_number`
- **PO Number**: `po.number`
- **Supplier Name**: `s.name`
- **User Name**: `u.nama_lengkap`
- **Notes**: `gr.notes` (New)

### **2. Search Behavior**
- **Real-time**: Search saat user mengetik
- **Debounced**: 400ms delay untuk performa
- **Case-insensitive**: Tidak case sensitive
- **Partial match**: Mencari substring

### **3. Search Performance**
- **Database Index**: Pastikan index pada field yang dicari
- **Query Optimization**: Menggunakan LIKE dengan wildcard
- **Pagination**: Hasil search tetap ter-paginate

## Notes Display Features

### **1. Visual Design**
- **Max Width**: `max-w-xs` untuk konsistensi layout
- **Text Size**: `text-sm` untuk readability
- **Color**: `text-gray-700` untuk kontras
- **Truncate**: `truncate` untuk teks panjang

### **2. User Experience**
- **Tooltip**: Hover untuk melihat teks lengkap
- **Empty State**: "-" untuk notes kosong
- **Responsive**: Layout tetap responsive

### **3. Content Handling**
- **Long Text**: Otomatis truncate dengan ellipsis
- **Empty Notes**: Tampilkan "-" dengan style italic
- **Special Characters**: HTML escape otomatis

## Performance Considerations

### **1. Database Performance**
- **Index**: Pastikan index pada field yang dicari
- **Query Optimization**: Efficient JOIN dan WHERE clauses
- **Pagination**: Limit hasil query

### **2. Frontend Performance**
- **Debouncing**: 400ms delay untuk search
- **Vue Reactivity**: Efficient watchers
- **SessionStorage**: Lightweight storage

### **3. Memory Management**
- **SessionStorage**: Otomatis clear saat session berakhir
- **Vue Cleanup**: Proper component cleanup
- **Event Listeners**: No memory leaks

## Browser Compatibility

- **Chrome/Chromium**: Full support
- **Firefox**: Full support
- **Safari**: Full support
- **Edge**: Full support
- **SessionStorage**: Supported in all modern browsers

## Security Considerations

### **1. Input Sanitization**
- **Search Input**: Backend validation
- **Notes Display**: HTML escape otomatis
- **SessionStorage**: JSON parsing dengan error handling

### **2. Data Protection**
- **Notes Privacy**: Notes hanya visible untuk user yang authorized
- **Search Security**: SQL injection protection
- **Session Management**: SessionStorage scope terbatas

## Testing

### **Manual Testing**
1. **Notes Display**: Test tampilan notes di tabel
2. **Search Notes**: Test search berdasarkan notes
3. **Filter Persistence**: Test filter tidak hilang saat navigasi
4. **Pagination**: Test filter tetap saat ganti page
5. **Modal Navigation**: Test filter tetap saat buka modal

### **Test Cases**

#### **Notes Display Test Cases**
- ✅ Notes dengan teks pendek - should display normally
- ✅ Notes dengan teks panjang - should truncate with tooltip
- ✅ Notes kosong - should display "-"
- ✅ Notes dengan karakter khusus - should display safely

#### **Search Test Cases**
- ✅ Search by GR number - should work
- ✅ Search by PO number - should work
- ✅ Search by supplier name - should work
- ✅ Search by user name - should work
- ✅ Search by notes - should work (New)
- ✅ Search dengan teks tidak ada - should return empty

#### **Filter Persistence Test Cases**
- ✅ Filter tersimpan saat ganti page - should persist
- ✅ Filter tersimpan saat buka detail - should persist
- ✅ Filter tersimpan saat buka edit - should persist
- ✅ Filter restore saat kembali ke halaman - should restore
- ✅ Filter clear saat session berakhir - should clear

## Troubleshooting

### **Common Issues**
1. **Notes tidak muncul**: Check backend query include notes
2. **Search tidak work**: Check search query include notes field
3. **Filter hilang**: Check sessionStorage implementation
4. **Layout rusak**: Check colspan update untuk "no data" row

### **Debug Tips**
```javascript
// Debug filter state
console.log('Current filters:', {
  search: search.value,
  from: from.value,
  to: to.value
});

// Debug sessionStorage
console.log('Saved filters:', sessionStorage.getItem('foodGoodReceiveFilters'));

// Debug notes data
console.log('GR data:', goodReceives.data);
```

## Future Enhancements

### **1. Notes Management**
- **Rich Text Editor**: WYSIWYG editor untuk notes
- **Notes Categories**: Kategorisasi notes
- **Notes Templates**: Template notes yang dapat digunakan

### **2. Advanced Search**
- **Search Filters**: Filter berdasarkan field tertentu
- **Search History**: Riwayat pencarian
- **Saved Searches**: Simpan pencarian favorit

### **3. Export Features**
- **Export with Notes**: Export data termasuk notes
- **Notes Report**: Laporan khusus notes
- **Search Export**: Export hasil pencarian

### **4. UI Improvements**
- **Notes Modal**: Modal untuk melihat notes lengkap
- **Notes Edit**: Edit notes langsung dari index
- **Notes Highlight**: Highlight search terms di notes

## Related Features

- **Food Good Receive Management**: Main functionality
- **Notes Input**: Form input untuk notes
- **Search Functionality**: Enhanced search
- **Filter Management**: Filter persistence
- **Table Display**: Enhanced table layout

## Conclusion

Fitur ini meningkatkan user experience dengan menampilkan notes di halaman index dan memastikan filter tidak hilang saat navigasi. Implementasi yang robust dengan error handling dan performance optimization memberikan pengalaman yang smooth dan reliable.
