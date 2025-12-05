# Debug Guide: PO Items Tidak Tersimpan

## Masalah
PO berhasil dibuat di table `purchase_order_foods` tapi items tidak tersimpan di table `purchase_order_food_items`.

## Langkah Debugging

### 1. Jalankan Script Debug
```bash
php debug_po_generation.php
```

### 2. Jalankan Script Cek Table
```bash
php check_po_items_table.php
```

### 3. Cek Log Laravel
```bash
tail -f storage/logs/laravel.log
```

### 4. Cek Data yang Dikirim dari Frontend

#### Di Browser Developer Tools:
1. Buka Network tab
2. Buat PO baru
3. Cari request ke `/api/po-foods/generate`
4. Cek Request Payload

#### Data yang seharusnya dikirim:
```json
{
  "items_by_supplier": {
    "1": [  // supplier_id
      {
        "id": 123,  // untuk PR Foods
        "supplier_id": {"id": 1, "name": "Supplier Name"},
        "qty": 10,
        "price": 50000,
        "source": "pr_foods"
      }
    ],
    "2": [
      {
        "item_id": 53063,  // untuk RO Supplier
        "item_name": "Item Name",
        "supplier_id": {"id": 2, "name": "Supplier Name"},
        "qty": 5,
        "price": 25000,
        "source": "ro_supplier",
        "ro_id": 1154,
        "ro_number": "RO-20250828-1234"
      }
    ]
  },
  "ppn_enabled": false,
  "notes": "Test notes"
}
```

### 5. Cek Kemungkinan Penyebab

#### A. Data Frontend Salah
- Pastikan `items_by_supplier` tidak kosong
- Pastikan setiap item memiliki `supplier_id`, `qty`, dan `price`
- Pastikan `source` field ada untuk RO Supplier

#### B. Validation Error
- Cek apakah ada validation error di backend
- Pastikan semua required fields terisi

#### C. Database Constraint
- Cek foreign key constraints
- Pastikan `item_id` ada di table `items`
- Pastikan `unit_id` ada di table `units`

#### D. Exception Handling
- Cek apakah ada exception yang tidak tertangkap
- Pastikan transaction tidak rollback

### 6. Cek Log yang Ditambahkan

Setelah menambahkan logging yang lebih detail, cek log untuk:

```
=== START GENERATE PO ===
Generate PO Request Data: {...}
Database transaction started
PR IDs collected: [...]
Items grouped by supplier: {...}
Processing supplier: {...}
Generated PO number: {...}
Creating PO with data: {...}
PO created successfully: {...}
Processing items for PO: {...}
Processing item: {...}
Creating PO item: {...}
Attempting to create PO item with data: {...}
PO item created successfully: {...}
=== PO GENERATION COMPLETED SUCCESSFULLY ===
```

### 7. Jika Masih Bermasalah

#### Cek Database Langsung:
```sql
-- Cek PO terbaru
SELECT * FROM purchase_order_foods ORDER BY created_at DESC LIMIT 5;

-- Cek items di PO terbaru
SELECT poi.*, po.number as po_number 
FROM purchase_order_food_items poi
JOIN purchase_order_foods po ON poi.purchase_order_food_id = po.id
ORDER BY poi.created_at DESC LIMIT 10;

-- Cek apakah ada PO tanpa items
SELECT po.id, po.number, po.created_at
FROM purchase_order_foods po
LEFT JOIN purchase_order_food_items poi ON po.id = poi.purchase_order_food_id
WHERE poi.id IS NULL
ORDER BY po.created_at DESC;
```

#### Cek Struktur Table:
```sql
DESCRIBE purchase_order_food_items;
SHOW CREATE TABLE purchase_order_food_items;
```

### 8. Solusi Umum

#### A. Jika Data Frontend Salah:
- Perbaiki data yang dikirim dari frontend
- Pastikan semua required fields terisi

#### B. Jika Database Constraint Error:
- Cek apakah `item_id` dan `unit_id` valid
- Pastikan foreign key constraints terpenuhi

#### C. Jika Exception Tidak Tertangkap:
- Tambahkan try-catch yang lebih spesifik
- Log semua exception yang terjadi

#### D. Jika Transaction Rollback:
- Cek apakah ada error yang menyebabkan rollback
- Pastikan semua data valid sebelum commit

### 9. Test Manual

Buat script test untuk insert manual:

```php
<?php
// Test manual insert
$poItem = \App\Models\PurchaseOrderFoodItem::create([
    'purchase_order_food_id' => 1, // PO ID yang valid
    'item_id' => 1, // Item ID yang valid
    'quantity' => 10,
    'unit_id' => 1, // Unit ID yang valid
    'price' => 50000,
    'total' => 500000,
    'created_by' => 1,
    'source_type' => 'pr_foods'
]);
echo "Test insert berhasil: " . $poItem->id;
?>
```

## Kesimpulan

Jika semua langkah di atas sudah dilakukan dan masih bermasalah, kemungkinan penyebabnya adalah:

1. **Data Frontend**: Data yang dikirim tidak sesuai format yang diharapkan
2. **Validation**: Ada field yang tidak valid
3. **Database**: Ada constraint yang tidak terpenuhi
4. **Exception**: Ada error yang tidak tertangkap dengan baik

Gunakan logging yang sudah ditambahkan untuk melacak di mana masalah terjadi.
