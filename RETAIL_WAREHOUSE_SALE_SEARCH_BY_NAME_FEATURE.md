# Retail Warehouse Sale - Search by Name Feature

Dokumentasi fitur pencarian barang berdasarkan nama untuk Retail Warehouse Sale dengan autocomplete.

## Overview

Fitur ini menambahkan kemampuan untuk user mencari barang berdasarkan nama (selain barcode scanner yang sudah ada), dengan autocomplete yang menampilkan hasil pencarian real-time.

## Fitur Utama

### 1. **Search by Name**
- Input field untuk ketik nama barang
- Minimal 2 karakter untuk memulai pencarian
- Debounce 300ms untuk performa optimal
- Hanya menampilkan item yang ada stoknya di warehouse yang dipilih

### 2. **Autocomplete Interface**
- Dropdown hasil pencarian dengan maksimal 10 item
- Menampilkan nama barang, stok, dan harga
- Highlight item yang dipilih dengan keyboard navigation
- Click atau Enter untuk memilih item

### 3. **Keyboard Navigation**
- **Arrow Up/Down**: Navigasi antar hasil pencarian
- **Enter**: Pilih item yang di-highlight
- **Escape**: Tutup dropdown hasil pencarian

### 4. **Integration dengan Cart**
- Item yang dipilih otomatis masuk ke cart
- Jika item sudah ada, qty akan bertambah
- Auto-clear search input setelah item dipilih
- Focus kembali ke search input

## Perubahan yang Dibuat

### 1. Backend Changes

#### **Controller** (`app/Http/Controllers/RetailWarehouseSaleController.php`)
- ✅ **Method Baru**: `searchItemsByName()` untuk pencarian berdasarkan nama
- ✅ **Validation**: Minimal 2 karakter untuk pencarian
- ✅ **Filter**: Hanya item yang ada stoknya di warehouse
- ✅ **Price Integration**: Ambil harga dari `item_prices` table
- ✅ **Unit Information**: Include unit names (small, medium, large)

#### **Routes** (`routes/web.php`)
- ✅ **Route Baru**: `POST /retail-warehouse-sale/search-items-by-name`

### 2. Frontend Changes

#### **Form.vue** (`resources/js/Pages/RetailWarehouseSale/Form.vue`)
- ✅ **Input Field**: Search by name dengan icon search
- ✅ **Autocomplete Dropdown**: Hasil pencarian dengan styling
- ✅ **Keyboard Navigation**: Arrow keys, Enter, Escape
- ✅ **Debounce**: 300ms delay untuk performa
- ✅ **State Management**: Search results, selected index, show/hide
- ✅ **Integration**: Terintegrasi dengan cart system yang ada

## API Endpoint

### **POST** `/retail-warehouse-sale/search-items-by-name`

#### Request Body
```json
{
  "search": "nama barang",
  "warehouse_id": 1
}
```

#### Response Success
```json
{
  "success": true,
  "items": [
    {
      "item_id": 1,
      "item_name": "Nama Barang",
      "small_unit_id": 1,
      "medium_unit_id": 2,
      "large_unit_id": 3,
      "small_conversion_qty": 1,
      "medium_conversion_qty": 10,
      "qty_small": 100,
      "qty_medium": 10,
      "qty_large": 1,
      "unit_small": "PCS",
      "unit_medium": "BOX",
      "unit_large": "CARTON",
      "price": 10000
    }
  ]
}
```

#### Response Error
```json
{
  "success": false,
  "message": "Item tidak ditemukan"
}
```

## UI/UX Features

### **Search Input**
- Placeholder: "Ketik nama barang..."
- Icon search di sebelah kanan
- Auto-focus setelah item dipilih

### **Autocomplete Dropdown**
- Position: Absolute di bawah input
- Max height: 240px dengan scroll
- Z-index: 50 untuk overlay
- Border dan shadow untuk visibility

### **Search Results Item**
- Nama barang (bold)
- Stok dan harga (secondary text)
- Hover effect (blue background)
- Selected state (darker blue)
- Click area untuk seluruh item

### **Keyboard Navigation**
- Visual feedback untuk selected item
- Smooth navigation dengan arrow keys
- Clear indication untuk Enter action

## Technical Implementation

### **Debounce Logic**
```javascript
watch(itemSearchInput, (newValue) => {
  if (searchTimeout) {
    clearTimeout(searchTimeout);
  }
  
  if (newValue && newValue.length >= 2) {
    searchTimeout = setTimeout(() => {
      searchItemsByName();
    }, 300); // 300ms debounce
  } else {
    searchResults.value = [];
    showSearchResults.value = false;
  }
});
```

### **Keyboard Navigation**
```javascript
function handleSearchKeydown(event) {
  switch (event.key) {
    case 'ArrowDown':
      selectedSearchIndex.value = Math.min(selectedSearchIndex.value + 1, searchResults.value.length - 1);
      break;
    case 'ArrowUp':
      selectedSearchIndex.value = Math.max(selectedSearchIndex.value - 1, -1);
      break;
    case 'Enter':
      if (selectedSearchIndex.value >= 0) {
        selectSearchItem(searchResults.value[selectedSearchIndex.value]);
      }
      break;
    case 'Escape':
      showSearchResults.value = false;
      break;
  }
}
```

### **Item Selection**
```javascript
function selectSearchItem(item) {
  // Check if item already exists in cart
  const existingItem = form.value.items.find(i => i.item_id === item.item_id);
  if (existingItem) {
    existingItem.qty += 1;
    existingItem.subtotal = existingItem.qty * existingItem.price;
  } else {
    // Add new item to cart with medium unit as default
    form.value.items.push({
      item_id: item.item_id,
      item_name: item.item_name,
      barcode: '', // No barcode for name search
      qty: 1,
      unit: item.unit_medium, // Default to medium unit
      price: item.price || 0,
      subtotal: item.price || 0,
      // ... other properties
    });
  }
  
  // Clear search and focus back
  itemSearchInput.value = '';
  searchResults.value = [];
  showSearchResults.value = false;
}
```

## Database Query

### **Search Query**
```sql
SELECT 
  i.id as item_id,
  i.name as item_name,
  i.small_unit_id,
  i.medium_unit_id,
  i.large_unit_id,
  i.small_conversion_qty,
  i.medium_conversion_qty,
  fis.qty_small,
  fis.qty_medium,
  fis.qty_large
FROM items as i
LEFT JOIN food_inventory_items as fii ON i.id = fii.item_id
LEFT JOIN food_inventory_stocks as fis ON fii.id = fis.inventory_item_id 
  AND fis.warehouse_id = ?
WHERE i.name LIKE '%search%'
  AND fis.id IS NOT NULL
LIMIT 10
```

## Performance Considerations

### **Optimizations**
- **Debounce**: 300ms delay untuk mengurangi API calls
- **Limit Results**: Maksimal 10 hasil pencarian
- **Stock Filter**: Hanya item yang ada stoknya
- **Efficient Query**: Proper joins dan indexes

### **User Experience**
- **Real-time Search**: Hasil muncul saat user mengetik
- **Keyboard Navigation**: Full keyboard support
- **Visual Feedback**: Clear indication untuk selected item
- **Auto-clear**: Search input otomatis clear setelah pilih

## Browser Compatibility

- **Chrome/Chromium**: Full support
- **Firefox**: Full support
- **Safari**: Full support
- **Edge**: Full support

## Testing

### **Manual Testing**
1. **Basic Search**: Ketik nama barang, verify hasil muncul
2. **Keyboard Navigation**: Test arrow keys, Enter, Escape
3. **Item Selection**: Click dan Enter untuk pilih item
4. **Cart Integration**: Verify item masuk ke cart
5. **Debounce**: Test typing cepat, verify tidak spam API
6. **Empty Results**: Test dengan nama yang tidak ada
7. **Stock Filter**: Test dengan warehouse yang tidak ada stok

### **Edge Cases**
- Search dengan 1 karakter (tidak boleh search)
- Search dengan karakter khusus
- Search dengan nama yang sangat panjang
- Network error saat search
- Warehouse belum dipilih

## Future Enhancements

1. **Fuzzy Search**: Support typo tolerance
2. **Search History**: Remember recent searches
3. **Category Filter**: Filter berdasarkan kategori item
4. **Price Range**: Filter berdasarkan range harga
5. **Stock Status**: Indikator stok rendah
6. **Image Preview**: Thumbnail item di hasil pencarian
7. **Barcode Integration**: Support scan barcode di search input
8. **Voice Search**: Speech-to-text untuk pencarian

## Security

- **Input Validation**: Sanitize search input
- **SQL Injection**: Menggunakan parameterized queries
- **XSS Protection**: Escape output data
- **CSRF Protection**: Laravel CSRF token

## Maintenance

### **Regular Tasks**
1. **Monitor Performance**: Cek query execution time
2. **Update Indexes**: Optimize database indexes jika perlu
3. **Test Compatibility**: Test dengan browser baru
4. **User Feedback**: Collect feedback untuk improvement

### **Logs**
- Search activity logs
- Performance metrics
- Error logs untuk troubleshooting
