# Contra Bon - Penambahan Info Outlet & Warehouse Outlet

## Permintaan User
User meminta untuk menampilkan informasi outlet dan warehouse outlet pada card info retail food di menu Contra Bon.

## Perubahan yang Diterapkan

### 1. Backend - Controller (`app/Http/Controllers/ContraBonController.php`)

#### A. Memperbaiki Query API `getRetailFoodContraBon`
**Sebelum:**
```php
$retailFoods = \DB::table('retail_food as rf')
    ->join('suppliers as s', 'rf.supplier_id', '=', 's.id')
    ->join('users as creator', 'rf.created_by', '=', 'creator.id')
    ->where('rf.payment_method', 'contra_bon')
    ->where('rf.status', 'approved')
    ->whereNotIn('rf.id', $usedRetailFoods)
    ->select(
        'rf.id as retail_food_id',
        'rf.retail_number',
        'rf.transaction_date',
        'rf.total_amount',
        'rf.notes',
        's.id as supplier_id',
        's.name as supplier_name',
        'creator.nama_lengkap as creator_name'
    )
    ->orderByDesc('rf.transaction_date')
    ->get();
```

**Sesudah:**
```php
$retailFoods = \DB::table('retail_food as rf')
    ->join('suppliers as s', 'rf.supplier_id', '=', 's.id')
    ->join('users as creator', 'rf.created_by', '=', 'creator.id')
    ->leftJoin('tbl_data_outlet as o', 'rf.outlet_id', '=', 'o.id_outlet')
    ->leftJoin('warehouse_outlets as wo', 'rf.warehouse_outlet_id', '=', 'wo.id')
    ->where('rf.payment_method', 'contra_bon')
    ->where('rf.status', 'approved')
    ->whereNotIn('rf.id', $usedRetailFoods)
    ->select(
        'rf.id as retail_food_id',
        'rf.retail_number',
        'rf.transaction_date',
        'rf.total_amount',
        'rf.notes',
        's.id as supplier_id',
        's.name as supplier_name',
        'creator.nama_lengkap as creator_name',
        'o.nama_outlet as outlet_name',
        'wo.name as warehouse_outlet_name'
    )
    ->orderByDesc('rf.transaction_date')
    ->get();
```

#### B. Menambahkan Data ke Response Array
**Sebelum:**
```php
$result[] = [
    'retail_food_id' => $row->retail_food_id,
    'retail_number' => $row->retail_number,
    'transaction_date' => $row->transaction_date,
    'total_amount' => $row->total_amount,
    'notes' => $row->notes,
    'supplier_id' => $row->supplier_id,
    'supplier_name' => $row->supplier_name,
    'creator_name' => $row->creator_name,
    'items' => $items,
];
```

**Sesudah:**
```php
$result[] = [
    'retail_food_id' => $row->retail_food_id,
    'retail_number' => $row->retail_number,
    'transaction_date' => $row->transaction_date,
    'total_amount' => $row->total_amount,
    'notes' => $row->notes,
    'supplier_id' => $row->supplier_id,
    'supplier_name' => $row->supplier_name,
    'creator_name' => $row->creator_name,
    'outlet_name' => $row->outlet_name,
    'warehouse_outlet_name' => $row->warehouse_outlet_name,
    'items' => $items,
];
```

### 2. Frontend - Vue Component (`resources/js/Pages/ContraBon/Form.vue`)

#### A. Memperbaiki Card Info Retail Food
**Sebelum:**
```vue
<!-- Card Info Retail Food -->
<div v-if="selectedRetailFood" class="bg-purple-50 rounded-lg p-4 shadow mb-4">
  <h3 class="font-bold mb-2">Info Retail Food</h3>
  <div>No. Retail Food: {{ selectedRetailFood.retail_number }}</div>
  <div>Tanggal Transaksi: {{ selectedRetailFood.transaction_date }}</div>
  <div>Supplier: <b>{{ selectedRetailFood.supplier_name }}</b></div>
  <div>Dibuat oleh: {{ selectedRetailFood.creator_name }}</div>
  <div>Total Amount: <b>{{ formatCurrency(selectedRetailFood.total_amount) }}</b></div>
  <div v-if="selectedRetailFood.notes">Notes: {{ selectedRetailFood.notes }}</div>
</div>
```

**Sesudah:**
```vue
<!-- Card Info Retail Food -->
<div v-if="selectedRetailFood" class="bg-purple-50 rounded-lg p-4 shadow mb-4">
  <h3 class="font-bold mb-2">Info Retail Food</h3>
  <div>No. Retail Food: {{ selectedRetailFood.retail_number }}</div>
  <div>Tanggal Transaksi: {{ selectedRetailFood.transaction_date }}</div>
  <div>Outlet: <b>{{ selectedRetailFood.outlet_name || '-' }}</b></div>
  <div>Warehouse Outlet: <b>{{ selectedRetailFood.warehouse_outlet_name || '-' }}</b></div>
  <div>Supplier: <b>{{ selectedRetailFood.supplier_name }}</b></div>
  <div>Dibuat oleh: {{ selectedRetailFood.creator_name }}</div>
  <div>Total Amount: <b>{{ formatCurrency(selectedRetailFood.total_amount) }}</b></div>
  <div v-if="selectedRetailFood.notes">Notes: {{ selectedRetailFood.notes }}</div>
</div>
```

#### B. Membersihkan Debug Code
- Menghapus semua `console.log` debugging yang tidak diperlukan
- Menyederhanakan logic pencarian retail food

### 3. Testing - Script Debug (`test_contra_bon_debug.php`)

#### A. Memperbarui Query Test
Menambahkan join ke tabel outlet dan warehouse outlet untuk testing:
```php
$retailFoods = DB::table('retail_food as rf')
    ->join('suppliers as s', 'rf.supplier_id', '=', 's.id')
    ->join('users as creator', 'rf.created_by', '=', 'creator.id')
    ->leftJoin('tbl_data_outlet as o', 'rf.outlet_id', '=', 'o.id_outlet')
    ->leftJoin('warehouse_outlets as wo', 'rf.warehouse_outlet_id', '=', 'wo.id')
    // ... rest of query
```

#### B. Menampilkan Data Outlet & Warehouse Outlet
```php
echo "Sample retail food:\n";
echo "- ID: {$sampleRF->retail_food_id}\n";
echo "- Number: {$sampleRF->retail_number}\n";
echo "- Date: {$sampleRF->transaction_date}\n";
echo "- Outlet: " . ($sampleRF->outlet_name ?? 'null') . "\n";
echo "- Warehouse Outlet: " . ($sampleRF->warehouse_outlet_name ?? 'null') . "\n";
echo "- Supplier: {$sampleRF->supplier_name}\n";
echo "- Creator: {$sampleRF->creator_name}\n";
```

## Hasil Testing

### API Response Structure
```json
{
    "retail_food_id": 116,
    "retail_number": "RF202508290002",
    "transaction_date": "2025-08-29",
    "total_amount": "855680.00",
    "notes": null,
    "supplier_id": 189,
    "supplier_name": "PT.PANFILA INDOSARI",
    "creator_name": "Agung hidayat",
    "outlet_name": "Justus Steak House Lebak Bulus",
    "warehouse_outlet_name": "Bar",
    "items": [
        {
            "id": 111,
            "item_name": "Mineral Water SH",
            "unit_name": "Karton",
            "qty": "8.00",
            "price": "106960.00"
        }
    ]
}
```

### Sample Data
- **Outlet**: "Justus Steak House Lebak Bulus"
- **Warehouse Outlet**: "Bar"
- **Supplier**: "PT.PANFILA INDOSARI"

## Fitur yang Ditambahkan

### 1. Informasi Outlet
- Menampilkan nama outlet dari retail food
- Menggunakan `leftJoin` untuk menangani kasus outlet yang null
- Menampilkan "-" jika outlet tidak ada

### 2. Informasi Warehouse Outlet
- Menampilkan nama warehouse outlet dari retail food
- Menggunakan `leftJoin` untuk menangani kasus warehouse outlet yang null
- Menampilkan "-" jika warehouse outlet tidak ada

### 3. UI/UX Improvements
- Informasi outlet dan warehouse outlet ditampilkan dengan format yang konsisten
- Menggunakan bold text untuk highlight informasi penting
- Fallback ke "-" jika data tidak tersedia

## File yang Dimodifikasi

1. **`app/Http/Controllers/ContraBonController.php`**
   - Method `getRetailFoodContraBon()`: Menambahkan join dan select untuk outlet dan warehouse outlet

2. **`resources/js/Pages/ContraBon/Form.vue`**
   - Template: Menambahkan display outlet dan warehouse outlet di card info
   - Script: Membersihkan debug code

3. **`test_contra_bon_debug.php`**
   - Query: Menambahkan join untuk testing outlet dan warehouse outlet
   - Output: Menampilkan data outlet dan warehouse outlet

## Status

**✅ COMPLETED** - Informasi outlet dan warehouse outlet sudah ditambahkan:
- Backend API mengirimkan data outlet dan warehouse outlet
- Frontend menampilkan informasi outlet dan warehouse outlet di card info
- Testing script sudah diperbarui untuk memverifikasi data
- Debug code sudah dibersihkan

## Langkah Selanjutnya

1. Test fitur dengan memilih retail food yang berbeda
2. Verifikasi informasi outlet dan warehouse outlet muncul dengan benar
3. Pastikan fallback "-" berfungsi untuk data yang null
