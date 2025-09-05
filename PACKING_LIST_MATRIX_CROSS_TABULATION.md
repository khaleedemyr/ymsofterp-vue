# Packing List Matrix Cross-tabulation Implementation

## Overview
Implementasi fitur Matrix Cross-tabulation untuk packing list. Fitur ini menampilkan data dalam format tabel matrix dengan header row (outlet) dan header column (warehouse division + item + unit), memudahkan user untuk melihat quantity setiap item per outlet dalam satu tampilan.

**Penting**: Matrix ini menggunakan **tanggal kedatangan (arrival_date)** dari Request Orders yang belum di-packing, bukan tanggal pembuatan RO. User juga bisa memfilter berdasarkan **warehouse division** tertentu.

**Logic Matrix**: Menampilkan **SEMUA outlet** yang aktif di database, meskipun tidak ada order pada tanggal tersebut. Cell yang kosong akan ditampilkan sebagai blank/empty.

## Features

### 1. Matrix Table Layout
- **Header Row (Baris atas)**: Nama-nama outlet
- **Header Column (Kolom kiri)**: Warehouse Division + Nama item + unit
- **Cell Content**: Quantity untuk setiap kombinasi item + outlet
- **Filter Options**: Tanggal kedatangan + Warehouse Division
- **Export Excel**: Langsung download dengan format yang rapi

### 2. Format Tabel yang Detail
```
Warehouse Division | Nama Items | Unit | Outlet A | Outlet B | Outlet C
Fresh Food        | Item 1     | PCS  |    10    |    15    |    20
Fresh Food        | Item 2     | KG   |     5    |    12    |     8
Dry Food          | Item 3     | BOX  |    18    |     7    |    14
```

## Changes Made

### 1. PackingListController.php - Method `exportMatrix()`
- **File**: `app/Http/Controllers/PackingListController.php`
- **Method**: `exportMatrix()`
- **Purpose**: API endpoint untuk export Excel matrix cross-tabulation
- **Features**:
  - Filter berdasarkan **tanggal kedatangan (arrival_date)**
  - Exclude RO Supplier
  - Generate pivot data untuk export
  - Format Excel dengan styling yang baik

### 2. PackingListMatrixExport.php - Export Class
- **File**: `app/Exports/PackingListMatrixExport.php`
- **Purpose**: Class untuk export Excel dengan styling
- **Features**:
  - Header dengan background kuning
  - Border untuk semua cell
  - Freeze pane pada kolom C (setelah nama item dan unit)
  - Column width yang optimal
  - Alignment yang tepat (left untuk item, right untuk quantity)

### 3. Index.vue - Frontend Matrix Export
- **File**: `resources/js/Pages/PackingList/Index.vue`
- **Changes**:
  - Menambahkan tombol "Matrix Cross-tabulation" (gradient purple)
  - Menambahkan date picker untuk **tanggal kedatangan**
  - Langsung export ke Excel tanpa modal
  - Loading state dan error handling

### 4. Routes - API Endpoint
- **File**: `routes/web.php`
- **Route**: `GET /api/packing-list/export-matrix`
- **Controller**: `PackingListController@exportMatrix`

## Technical Details

### Backend Export Process
```php
// Filter berdasarkan arrival_date bukan tanggal biasa
$floorOrders = FoodFloorOrder::whereIn('status', ['approved', 'packing'])
    ->where('fo_mode', '!=', 'RO Supplier')
    ->whereDate('arrival_date', $request->tanggal) // Gunakan arrival_date
    ->with(['outlet', 'items.item', 'items.item.warehouseDivision'])
    ->get();

// Get ALL outlets from database (not just from floor orders)
$allOutlets = \App\Models\Outlet::where('status', 'A') // Active outlets only
    ->orderBy('nama_outlet')
    ->get();

// Build pivot data for export - include ALL outlets
$pivot = [];
foreach ($itemsArray as $item) {
    $pivot[$item['id']] = [
        'item_name' => $item['name'],
        'unit' => $item['unit'],
        'warehouse_division' => $item['warehouse_division']
    ];
    
    // Initialize all outlets with empty values
    foreach ($outletsArray as $outlet) {
        $pivot[$item['id']][$outlet['nama_outlet']] = '';
    }
}

// Fill in actual quantities where they exist
foreach ($matrixData as $row) {
    $itemId = $row['item_id'];
    $outletName = $allOutlets->where('id', $row['outlet_id'])->first()['nama_outlet'] ?? '';
    
    if (isset($pivot[$itemId]) && $outletName) {
        $pivot[$itemId][$outletName] = $row['qty'];
    }
}
```

### Frontend Matrix Table
```html
<table class="w-full text-sm border border-gray-200">
  <thead>
    <tr class="bg-purple-50">
      <th>Item (Unit)</th>
      <th v-for="outlet in matrixOutlets">{{ outlet.nama_outlet }}</th>
      <th>Total</th>
    </tr>
  </thead>
  <tbody>
    <tr v-for="item in matrixItems">
      <td>{{ item.name }} ({{ item.unit }})</td>
      <td v-for="outlet in matrixOutlets">{{ getMatrixValue(item.id, outlet.id) || '-' }}</td>
      <td>{{ getItemTotal(item.id) }}</td>
    </tr>
    <!-- Total Row -->
    <tr class="bg-purple-100">
      <td>Total</td>
      <td v-for="outlet in matrixOutlets">{{ getOutletTotal(outlet.id) }}</td>
      <td>{{ getGrandTotal() }}</td>
    </tr>
  </tbody>
</table>
```

### Helper Methods
```javascript
// Get quantity for specific item + outlet combination
function getMatrixValue(itemId, outletId) {
  const matrixItem = matrixTable.value.find(item => 
    item.item_id === itemId && item.outlet_id === outletId
  );
  return matrixItem ? matrixItem.qty : null;
}

// Get total quantity for specific item (all outlets)
function getItemTotal(itemId) {
  return matrixTable.value
    .filter(item => item.item_id === itemId)
    .reduce((sum, item) => sum + (item.qty || 0), 0);
}

// Get total quantity for specific outlet (all items)
function getOutletTotal(outletId) {
  return matrixTable.value
    .filter(item => item.outlet_id === outletId)
    .reduce((sum, item) => sum + (item.qty || 0), 0);
}

// Get grand total (all items + all outlets)
function getGrandTotal() {
  return matrixTable.value.reduce((sum, item) => sum + (item.qty || 0), 0);
}
```

## User Experience

### Before
- User harus melihat data per outlet atau per item secara terpisah
- Sulit untuk membandingkan quantity antar outlet
- Tidak ada overview quantity per item secara keseluruhan

### After
- User bisa melihat semua data dalam satu tabel matrix
- Mudah membandingkan quantity item antar outlet
- Total quantity per item dan per outlet tersedia
- Format yang jelas dengan unit yang ditampilkan

## Usage

### 1. Buka Modal Matrix Cross-tabulation
1. Buka halaman Index Packing List
2. Klik tombol "Matrix Cross-tabulation" (ungu)
3. Modal akan terbuka dengan filter options

### 2. Set Filter
1. **Pilih Tanggal Kedatangan** (wajib diisi)
2. **Pilih Warehouse Division** (opsional - kosongkan untuk semua)
3. Klik tombol "Export Excel"

### 3. Format Excel yang Dihasilkan
- **Kolom A**: Warehouse Division (frozen)
- **Kolom B**: Nama Items (frozen)
- **Kolom C**: Unit (frozen)
- **Kolom D+**: Quantity per outlet
- **Header**: Background kuning dengan border
- **Data**: Border tipis dengan alignment yang tepat

### 4. Interpretasi Data
- **Baris**: Warehouse Division + Item dengan unit yang jelas
- **Kolom**: Outlet yang memesan
- **Cell**: Quantity yang dipesan outlet tersebut untuk item tersebut
- **Filter**: Berdasarkan **tanggal kedatangan (arrival_date)** dan **warehouse division**

## Benefits

### 1. **Data Visualization**
- Tampilan matrix yang mudah dibaca
- Perbandingan quantity antar outlet
- Overview quantity per item

### 2. **Business Intelligence**
- Identifikasi item yang paling banyak dipesan
- Analisis outlet dengan order terbanyak
- Planning inventory berdasarkan demand

### 3. **Operational Efficiency**
- Quick reference untuk quantity per outlet
- Memudahkan proses packing dan delivery
- Data yang terstruktur untuk reporting

## Testing

### Test Cases
1. **Matrix Display**: Pastikan tabel matrix ditampilkan dengan benar
2. **Data Accuracy**: Quantity di cell sesuai dengan data asli
3. **Total Calculation**: Total row dan column dihitung dengan benar
4. **Date Filter**: Filter tanggal berfungsi dengan baik
5. **Empty Data**: Handle kasus tidak ada data

### Expected Results
- Matrix table ditampilkan dengan format yang benar
- Quantity per cell akurat
- Total calculation benar
- Responsive design untuk berbagai ukuran layar
- Performance baik untuk data besar

## Notes

- Fitur ini backward compatible
- Tidak mempengaruhi fitur existing
- Performance optimized dengan query yang efisien
- UI responsive dan user-friendly
- Data real-time berdasarkan tanggal yang dipilih

## Files Modified

- `app/Http/Controllers/PackingListController.php` - Method `matrix()`
- `resources/js/Pages/PackingList/Index.vue` - Frontend matrix modal
- `routes/web.php` - API route untuk matrix

## Date Implemented
[Current Date]

## Developer
[Your Name]
