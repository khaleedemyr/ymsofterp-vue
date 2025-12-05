# Optimasi Performa Create Packing List

## Masalah
Menu create packing list sangat lambat, loading bisa sampai 10 menit.

## Penyebab
1. **Query mengambil semua floor orders** tanpa filter di database
2. **Filter di PHP** dengan nested loops - sangat lambat untuk data besar
3. **N+1 Query Problem** - cek packed items untuk setiap FO
4. **Eager loading berlebihan** - load semua relasi meski tidak diperlukan

## Optimasi yang Dilakukan

### 1. Pre-filter di Database (Raw Query)
**Sebelum:**
- Ambil semua FO yang approved/packing
- Filter di PHP untuk cek item yang belum di-packing

**Sesudah:**
- Query langsung di database untuk mendapatkan hanya FO yang masih punya item belum di-packing
- Menggunakan JOIN dan LEFT JOIN untuk efisiensi maksimal

```sql
SELECT DISTINCT fo.id
FROM food_floor_orders fo
JOIN food_floor_order_items foi ON fo.id = foi.floor_order_id
JOIN items i ON foi.item_id = i.id
LEFT JOIN food_packing_list_items pli ON foi.id = pli.food_floor_order_item_id
LEFT JOIN food_packing_lists pl ON pli.packing_list_id = pl.id 
    AND pl.food_floor_order_id = fo.id 
    AND pl.status = 'packing'
WHERE fo.status IN ('approved', 'packing')
AND fo.fo_mode != 'RO Supplier'
AND pli.id IS NULL
GROUP BY fo.id
```

### 2. Optimasi Batch Query untuk Packed Items
**Sebelum:**
```php
FoodPackingListItem::whereHas('packingList', ...) // Lambat karena subquery
```

**Sesudah:**
```php
// Raw query dengan JOIN langsung
SELECT pl.food_floor_order_id, pl.warehouse_division_id, pli.food_floor_order_item_id
FROM food_packing_list_items pli
JOIN food_packing_lists pl ON pli.packing_list_id = pl.id
WHERE pl.food_floor_order_id IN (...)
AND pl.status = 'packing'
```

### 3. Reduce Eager Loading
**Sebelum:**
```php
->with([
    'items.item.smallUnit', 
    'items.item.mediumUnit', 
    'items.item.largeUnit',  // Tidak diperlukan di create form
    ...
])
```

**Sesudah:**
```php
->with([
    'items' => function($q) {
        $q->select('id', 'floor_order_id', 'item_id', 'qty', 'unit')
          ->with(['item:id,name,warehouse_division_id']); // Hanya field yang diperlukan
    },
    ...
])
```

### 4. Early Return
Jika tidak ada FO yang perlu di-packing, langsung return tanpa query lebih lanjut.

## Perkiraan Peningkatan Performa

- **Sebelum:** 10 menit (600 detik)
- **Sesudah:** ~10-30 detik (tergantung jumlah data)
- **Peningkatan:** 20-60x lebih cepat

## Testing

1. Buka menu "Create Packing List"
2. Monitor waktu loading
3. Pastikan data yang ditampilkan benar
4. Test dengan filter arrival_date

## Catatan

- Optimasi ini menggunakan raw query untuk performa maksimal
- Filter di PHP masih diperlukan untuk logic kompleks (cek per division)
- Jika masih lambat, pertimbangkan:
  - Tambahkan index di database
  - Implementasi pagination atau lazy loading
  - Cache untuk data yang jarang berubah

## Index yang Disarankan

Jika masih lambat, tambahkan index di database:

```sql
-- Index untuk mempercepat query
CREATE INDEX idx_floor_orders_status_mode ON food_floor_orders(status, fo_mode);
CREATE INDEX idx_packing_lists_fo_status ON food_packing_lists(food_floor_order_id, status);
CREATE INDEX idx_packing_list_items_fo_item ON food_packing_list_items(food_floor_order_item_id);
CREATE INDEX idx_floor_order_items_fo_item ON food_floor_order_items(floor_order_id, item_id);
```

