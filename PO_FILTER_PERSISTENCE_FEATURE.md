# Purchase Order Food - Filter Persistence Feature

Dokumentasi fitur filter persistence untuk halaman Purchase Order Food agar filter tidak hilang saat pindah page atau navigasi lainnya.

## Overview

Fitur ini memastikan bahwa semua filter (search, status, date range, per page) tetap tersimpan dan tidak hilang saat user melakukan navigasi seperti pindah page, masuk ke detail, edit, atau refresh halaman.

## Fitur Utama

### 1. **Filter Persistence**
- Filter state tersimpan di `sessionStorage`
- Filter tidak hilang saat pindah page
- Filter tidak hilang saat navigasi ke detail/edit
- Filter tidak hilang saat refresh halaman
- Filter dipulihkan otomatis saat kembali ke halaman

### 2. **Auto-Save Filter State**
- Filter state otomatis tersimpan saat ada perubahan
- Real-time saving menggunakan Vue watchers
- Tidak perlu manual save

### 3. **Clear Filter Functionality**
- Tombol "Clear Filter" untuk reset semua filter
- Reset ke nilai default
- Trigger search otomatis setelah clear

### 4. **Navigation State Preservation**
- Filter state tersimpan sebelum navigasi
- State dipulihkan saat kembali
- Preserve state menggunakan Inertia.js

## Perubahan yang Dibuat

### 1. Frontend Changes

#### **Enhanced Filter State Management**
```javascript
// Watch untuk auto-save filter state saat ada perubahan
watch([search, selectedStatus, from, to, perPage], () => {
  const filterState = {
    search: search.value,
    status: selectedStatus.value,
    from: from.value,
    to: to.value,
    perPage: perPage.value
  };
  sessionStorage.setItem('po-foods-filters', JSON.stringify(filterState));
}, { deep: true });
```

#### **Improved Filter Restoration**
```javascript
// Fungsi untuk memulihkan filter state dari sessionStorage
function restoreFilterState() {
  try {
    const savedFilters = sessionStorage.getItem('po-foods-filters');
    if (savedFilters) {
      const filters = JSON.parse(savedFilters);
      search.value = filters.search || '';
      selectedStatus.value = filters.status || '';
      from.value = filters.from || '';
      to.value = filters.to || '';
      perPage.value = filters.perPage || 10;
      
      // Trigger search dengan filter yang dipulihkan
      debouncedSearch();
    }
  } catch (error) {
    console.error('Error restoring filter state:', error);
  }
}
```

#### **Enhanced Navigation Functions**
```javascript
function goToPage(url) {
  if (url) {
    // Simpan filter state sebelum navigasi
    const filterState = {
      search: search.value,
      status: selectedStatus.value,
      from: from.value,
      to: to.value,
      perPage: perPage.value
    };
    sessionStorage.setItem('po-foods-filters', JSON.stringify(filterState));
    
    router.visit(url, { preserveState: true, replace: true });
  }
}
```

#### **Clear Filter Function**
```javascript
function clearFilters() {
  search.value = '';
  selectedStatus.value = '';
  from.value = '';
  to.value = '';
  perPage.value = 10;
  debouncedSearch();
}
```

#### **Clear Filter Button**
```html
<button @click="clearFilters" class="px-4 py-2 rounded-xl bg-gray-500 text-white hover:bg-gray-600 transition font-semibold">
    <i class="fas fa-undo mr-2"></i>
    Clear Filter
</button>
```

### 2. State Management Flow

#### **Filter State Lifecycle**
```
1. User Input → 2. Auto-Save to sessionStorage → 3. Debounced Search → 4. Update URL
     ↓
5. Navigation → 6. Save State → 7. Navigate → 8. Return → 9. Restore State
```

#### **State Storage Structure**
```javascript
{
  "search": "PO-001",
  "status": "approved",
  "from": "2025-09-01",
  "to": "2025-09-30",
  "perPage": "25"
}
```

## Technical Implementation

### **1. SessionStorage Management**

#### **Save Filter State**
```javascript
const filterState = {
  search: search.value,
  status: selectedStatus.value,
  from: from.value,
  to: to.value,
  perPage: perPage.value
};
sessionStorage.setItem('po-foods-filters', JSON.stringify(filterState));
```

#### **Restore Filter State**
```javascript
const savedFilters = sessionStorage.getItem('po-foods-filters');
if (savedFilters) {
  const filters = JSON.parse(savedFilters);
  // Apply filters to reactive variables
}
```

### **2. Vue Watchers**

#### **Props Watcher**
```javascript
watch(() => props.filters, (newFilters) => {
  if (newFilters) {
    // Apply filters from props
    search.value = newFilters.search || '';
    selectedStatus.value = newFilters.status || '';
    from.value = newFilters.from || '';
    to.value = newFilters.to || '';
    perPage.value = newFilters.perPage || 10;
  } else {
    // Restore from sessionStorage
    restoreFilterState();
  }
}, { immediate: true });
```

#### **Auto-Save Watcher**
```javascript
watch([search, selectedStatus, from, to, perPage], () => {
  // Auto-save filter state
  const filterState = { /* ... */ };
  sessionStorage.setItem('po-foods-filters', JSON.stringify(filterState));
}, { deep: true });
```

### **3. Navigation Integration**

#### **Pagination**
```javascript
function goToPage(url) {
  // Save state before navigation
  saveFilterState();
  router.visit(url, { preserveState: true, replace: true });
}
```

#### **Detail/Edit Navigation**
```javascript
function openDetail(id) {
  // Save state before navigation
  saveFilterState();
  router.visit(`/po-foods/${id}`);
}
```

## UI/UX Features

### **Filter Section Layout**
```
┌─────────────────────────────────────────────────────────────────────────┐
│ [Search] [Status] [From Date] [To Date] [Per Page] [Clear Filter] [GM] │
└─────────────────────────────────────────────────────────────────────────┘
```

### **Clear Filter Button**
- **Icon**: Undo icon (`fas fa-undo`)
- **Color**: Gray background (`bg-gray-500`)
- **Hover**: Darker gray (`hover:bg-gray-600`)
- **Position**: After per page selector

### **Filter Persistence Indicators**
- **Visual**: Filter values tetap terlihat
- **Behavior**: Filter tidak hilang saat navigasi
- **Feedback**: Smooth transitions

## Browser Compatibility

### **SessionStorage Support**
- **Chrome/Chromium**: Full support
- **Firefox**: Full support
- **Safari**: Full support
- **Edge**: Full support

### **Fallback Behavior**
- Jika sessionStorage tidak tersedia, filter akan hilang
- Error handling untuk JSON parsing
- Graceful degradation

## Performance Considerations

### **Optimizations**
- **Debounced Search**: Mengurangi API calls
- **Deep Watching**: Efficient change detection
- **SessionStorage**: Fast local storage
- **Preserve State**: Mengurangi re-renders

### **Memory Management**
- **Cleanup**: SessionStorage dibersihkan saat session berakhir
- **Size Limit**: SessionStorage memiliki limit ~5-10MB
- **JSON Serialization**: Efficient serialization

## Testing

### **Manual Testing**
1. **Filter Input**: Test input berbagai filter
2. **Navigation**: Test pindah page, detail, edit
3. **Refresh**: Test refresh halaman
4. **Clear Filter**: Test tombol clear filter
5. **Browser Back/Forward**: Test browser navigation
6. **Session Storage**: Test dengan browser dev tools

### **Edge Cases**
- SessionStorage disabled
- JSON parsing errors
- Invalid filter values
- Network errors
- Browser compatibility

## Security

### **Data Protection**
- **Local Storage**: Data tersimpan di browser user
- **No Sensitive Data**: Hanya filter values
- **JSON Validation**: Validate JSON structure
- **Error Handling**: Proper error handling

## Maintenance

### **Regular Tasks**
1. **Monitor Performance**: Cek sessionStorage usage
2. **Test Compatibility**: Test dengan browser baru
3. **User Feedback**: Collect feedback untuk improvement
4. **Error Monitoring**: Monitor error logs

### **Debugging**
```javascript
// Debug filter state
console.log('Current filters:', {
  search: search.value,
  status: selectedStatus.value,
  from: from.value,
  to: to.value,
  perPage: perPage.value
});

// Debug sessionStorage
console.log('Saved filters:', sessionStorage.getItem('po-foods-filters'));
```

## Troubleshooting

### **Common Issues**
1. **Filter Tidak Tersimpan**: Cek sessionStorage support
2. **Filter Tidak Dipulihkan**: Cek JSON parsing
3. **Performance Issues**: Cek watcher efficiency
4. **Navigation Issues**: Cek Inertia.js configuration

### **Debug Tips**
- Check browser dev tools → Application → Session Storage
- Verify Vue watchers are working
- Check console for errors
- Test with different browsers

## Future Enhancements

1. **Filter Presets**: Simpan filter preset favorit
2. **Export Filters**: Export/import filter configurations
3. **Filter History**: Riwayat filter yang digunakan
4. **Advanced Persistence**: Persist across browser sessions
5. **Filter Analytics**: Track filter usage patterns
6. **Smart Defaults**: Default filter berdasarkan user behavior

## Related Features

- **Search Functionality**: Real-time search dengan debounce
- **Pagination**: Pagination dengan filter persistence
- **GR Number Display**: Filter berdasarkan GR Number
- **Status Management**: Filter berdasarkan status PO
- **Date Range**: Filter berdasarkan tanggal

## Conclusion

Fitur filter persistence memastikan user experience yang smooth dengan filter yang tidak hilang saat navigasi. Implementasi menggunakan sessionStorage dan Vue watchers memberikan performa yang optimal dengan fallback yang baik.
