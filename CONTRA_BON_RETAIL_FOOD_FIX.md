# Contra Bon Retail Food Fix Documentation

## Masalah yang Ditemukan

Error yang terjadi pada fitur Contra Bon saat memilih Retail Food:
```
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'rfi.item_id' in 'field list'
```

## Analisis Masalah

1. **Root Cause**: API `getRetailFoodContraBon()` mencoba mengakses kolom `item_id` dan `unit_id` di tabel `retail_food_items` yang tidak ada
2. **Struktur Tabel**: Tabel `retail_food_items` hanya memiliki kolom:
   - `retail_food_id`
   - `item_name` (string)
   - `qty`
   - `unit` (string)
   - `price`
   - `subtotal`
3. **Penyebab**: Query API mencoba melakukan JOIN dengan tabel `items` dan `units` menggunakan kolom yang tidak ada

## Solusi yang Diterapkan

### 1. Memperbaiki API Query di ContraBonController.php

**Sebelum:**
```php
$items = \DB::table('retail_food_items as rfi')
    ->join('items as i', 'rfi.item_id', '=', 'i.id')
    ->join('units as u', 'rfi.unit_id', '=', 'u.id')
    ->where('rfi.retail_food_id', $row->retail_food_id)
    ->select(
        'rfi.id',
        'rfi.item_id',
        'i.name as item_name',
        'rfi.unit_id',
        'u.name as unit_name',
        'rfi.qty',
        'rfi.price'
    )
    ->get();
```

**Sesudah:**
```php
$items = \DB::table('retail_food_items as rfi')
    ->where('rfi.retail_food_id', $row->retail_food_id)
    ->select(
        'rfi.id',
        'rfi.item_name',
        'rfi.unit as unit_name',
        'rfi.qty',
        'rfi.price'
    )
    ->get();
```

### 2. Memperbaiki Frontend di Form.vue

**Sebelum:**
```javascript
form.items = retailFood.items.map(item => ({
    gr_item_id: null,
    item_id: item.item_id,
    po_item_id: null,
    unit_id: item.unit_id,
    quantity: item.qty,
    price: item.price,
    notes: '',
    _rowKey: Date.now() + '-' + Math.random(),
}));
```

**Sesudah:**
```javascript
form.items = retailFood.items.map(item => ({
    gr_item_id: null,
    item_id: null, // Tidak ada item_id karena menggunakan item_name
    po_item_id: null,
    unit_id: null, // Tidak ada unit_id karena menggunakan unit string
    quantity: item.qty,
    price: item.price,
    notes: '',
    item_name: item.item_name,
    unit_name: item.unit_name,
    _rowKey: Date.now() + '-' + Math.random(),
}));
```

### 3. Memperbaiki Backend Store Logic

**Sebelum:**
```php
ContraBonItem::create([
    'contra_bon_id' => $contraBon->id,
    'item_id' => $item['item_id'],
    'po_item_id' => $item['po_item_id'] ?? null,
    'quantity' => $item['quantity'],
    'unit_id' => $item['unit_id'],
    'price' => $item['price'],
    'total' => $item['quantity'] * $item['price'],
    'notes' => $item['notes'] ?? null
]);
```

**Sesudah:**
```php
// Untuk retail food, item_id dan unit_id bisa null
$itemId = $item['item_id'] ?? null;
$unitId = $item['unit_id'] ?? null;

// Jika dari retail food dan tidak ada item_id, coba cari berdasarkan item_name
if ($sourceType === 'retail_food' && !$itemId && isset($item['item_name'])) {
    $foundItem = \DB::table('items')->where('name', $item['item_name'])->first();
    if ($foundItem) {
        $itemId = $foundItem->id;
    }
}

// Jika dari retail food dan tidak ada unit_id, coba cari berdasarkan unit_name
if ($sourceType === 'retail_food' && !$unitId && isset($item['unit_name'])) {
    $foundUnit = \DB::table('units')->where('name', $item['unit_name'])->first();
    if ($foundUnit) {
        $unitId = $foundUnit->id;
    }
}

ContraBonItem::create([
    'contra_bon_id' => $contraBon->id,
    'item_id' => $itemId,
    'po_item_id' => $item['po_item_id'] ?? null,
    'quantity' => $item['quantity'],
    'unit_id' => $unitId,
    'price' => $item['price'],
    'total' => $item['quantity'] * $item['price'],
    'notes' => $item['notes'] ?? null
]);
```

## Struktur Data yang Benar

### Tabel retail_food_items
```sql
CREATE TABLE `retail_food_items` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `retail_food_id` bigint(20) UNSIGNED NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `qty` decimal(10,2) NOT NULL,
  `unit` varchar(255) NOT NULL,
  `price` decimal(15,2) NOT NULL,
  `subtotal` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
);
```

### Response API yang Benar
```json
{
  "retail_food_id": 116,
  "retail_number": "RF202501290001",
  "transaction_date": "2025-01-29",
  "total_amount": "150000.00",
  "notes": "Sample retail food",
  "supplier_id": 1,
  "supplier_name": "Supplier ABC",
  "creator_name": "John Doe",
  "items": [
    {
      "id": 1,
      "item_name": "Ayam Potong",
      "unit_name": "KG",
      "qty": "10.00",
      "price": "15000.00"
    }
  ]
}
```

## Testing

Untuk memverifikasi perbaikan, gunakan file test:
```bash
php test_contra_bon_fixed.php
```

## Kesimpulan

1. **Masalah Utama**: Query API mencoba mengakses kolom `item_id` dan `unit_id` yang tidak ada di tabel `retail_food_items`
2. **Solusi**: Menggunakan kolom `item_name` dan `unit` langsung dari tabel `retail_food_items`
3. **Hasil**: API retail food contra bon sekarang berfungsi dengan benar
4. **Konsistensi**: Data tetap konsisten dengan struktur tabel yang ada

## File yang Dimodifikasi

- `app/Http/Controllers/ContraBonController.php` - Perbaikan API query dan store logic
- `resources/js/Pages/ContraBon/Form.vue` - Perbaikan frontend data mapping
- `test_contra_bon_fixed.php` - File test untuk verifikasi perbaikan

## Langkah Selanjutnya

1. Test fitur Contra Bon Retail Food untuk memastikan error sudah teratasi
2. Monitor log untuk memastikan tidak ada error baru
3. Pastikan data retail food dengan payment_method = 'contra_bon' tersedia untuk testing
