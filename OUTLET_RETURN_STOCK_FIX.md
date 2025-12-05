# Outlet Return Stock Fix

## Problem Description
Error "Stok tidak mencukupi untuk item: Syrup Almond. Stok tersedia: 0" terjadi saat approve return outlet, padahal ada stock di outlet yang melakukan return.

## Root Cause Analysis
Masalah terjadi karena **mismatch mapping antara `item_id` dan `inventory_item_id`** di sistem return approval.

### Database Structure
- **`items`** table: Master data item dengan `id` sebagai primary key
- **`outlet_food_inventory_items`** table: Mapping item ke inventory dengan `id` sebagai `inventory_item_id`
- **`outlet_food_inventory_stocks`** table: Stock data menggunakan `inventory_item_id` (bukan `item_id`)

### The Bug
Di method `approve()` pada kedua controller:
1. **OutletFoodReturnController.php** (line 381, 392)
2. **HeadOfficeReturnController.php** (line 215, 226)

Sistem menggunakan `$item->item_id` sebagai `inventory_item_id` saat query stock:

```php
// WRONG - menggunakan item_id sebagai inventory_item_id
$currentStock = DB::table('outlet_food_inventory_stocks')
    ->where('inventory_item_id', $item->item_id)  // ❌ SALAH!
    ->where('id_outlet', $return->outlet_id)
    ->where('warehouse_outlet_id', $return->warehouse_outlet_id)
    ->first();
```

Padahal seharusnya menggunakan `inventory_item_id` yang sebenarnya dari tabel `outlet_food_inventory_items`.

## Solution

### 1. Update Query untuk Get Return Items
Tambahkan `inventory_item_id` ke SELECT statement:

```php
// BEFORE
$returnItems = DB::table('outlet_food_return_items as ofri')
    ->leftJoin('items as i', 'ofri.item_id', '=', 'i.id')
    ->leftJoin('outlet_food_inventory_items as ofii', 'ofri.item_id', '=', 'ofii.item_id')
    ->select(
        'ofri.*',
        'i.small_unit_id',
        'i.medium_unit_id',
        'i.large_unit_id',
        'i.small_conversion_qty',
        'i.medium_conversion_qty',
        'i.name as item_name'
    )
    ->where('ofri.outlet_food_return_id', $id)
    ->get();

// AFTER
$returnItems = DB::table('outlet_food_return_items as ofri')
    ->leftJoin('items as i', 'ofri.item_id', '=', 'i.id')
    ->leftJoin('outlet_food_inventory_items as ofii', 'ofri.item_id', '=', 'ofii.item_id')
    ->select(
        'ofri.*',
        'ofii.id as inventory_item_id',  // ✅ TAMBAHAN
        'i.small_unit_id',
        'i.medium_unit_id',
        'i.large_unit_id',
        'i.small_conversion_qty',
        'i.medium_conversion_qty',
        'i.name as item_name'
    )
    ->where('ofri.outlet_food_return_id', $id)
    ->get();
```

### 2. Update Stock Check Query
Gunakan `inventory_item_id` yang benar:

```php
// BEFORE
$currentStock = DB::table('outlet_food_inventory_stocks')
    ->where('inventory_item_id', $item->item_id)  // ❌ SALAH
    ->where('id_outlet', $return->outlet_id)
    ->where('warehouse_outlet_id', $return->warehouse_outlet_id)
    ->first();

// AFTER
$currentStock = DB::table('outlet_food_inventory_stocks')
    ->where('inventory_item_id', $item->inventory_item_id)  // ✅ BENAR
    ->where('id_outlet', $return->outlet_id)
    ->where('warehouse_outlet_id', $return->warehouse_outlet_id)
    ->first();
```

### 3. Update Stock Reduction Query
```php
// BEFORE
DB::table('outlet_food_inventory_stocks')
    ->where('inventory_item_id', $item->item_id)  // ❌ SALAH
    ->where('id_outlet', $return->outlet_id)
    ->where('warehouse_outlet_id', $return->warehouse_outlet_id)
    ->update([
        'qty_small' => $currentStock->qty_small - $qty_small,
        'updated_at' => now()
    ]);

// AFTER
DB::table('outlet_food_inventory_stocks')
    ->where('inventory_item_id', $item->inventory_item_id)  // ✅ BENAR
    ->where('id_outlet', $return->outlet_id)
    ->where('warehouse_outlet_id', $return->warehouse_outlet_id)
    ->update([
        'qty_small' => $currentStock->qty_small - $qty_small,
        'updated_at' => now()
    ]);
```

### 4. Update Inventory Card Insert
```php
// BEFORE
DB::table('outlet_food_inventory_cards')->insert([
    'inventory_item_id' => $item->item_id,  // ❌ SALAH
    // ... other fields
]);

// AFTER
DB::table('outlet_food_inventory_cards')->insert([
    'inventory_item_id' => $item->inventory_item_id,  // ✅ BENAR
    // ... other fields
]);
```

## Files Modified

### 1. OutletFoodReturnController.php
- **Line 352**: Tambah `'ofii.id as inventory_item_id'` ke SELECT
- **Line 382**: Ganti `$item->item_id` dengan `$item->inventory_item_id` di stock check
- **Line 393**: Ganti `$item->item_id` dengan `$item->inventory_item_id` di stock update

### 2. HeadOfficeReturnController.php
- **Line 186**: Tambah `'ofii.id as inventory_item_id'` ke SELECT
- **Line 216**: Ganti `$item->item_id` dengan `$item->inventory_item_id` di stock check
- **Line 227**: Ganti `$item->item_id` dengan `$item->inventory_item_id` di stock update
- **Line 249**: Ganti `$item->item_id` dengan `$item->inventory_item_id` di inventory card

## Testing

### Before Fix
1. Create return dengan item yang ada stock di outlet
2. Approve return
3. **Result**: Error "Stok tidak mencukupi untuk item: [Item Name]. Stok tersedia: 0"

### After Fix
1. Create return dengan item yang ada stock di outlet
2. Approve return
3. **Result**: Return berhasil di-approve dan stock berhasil dikurangi

## Impact

### Positive Impact
- ✅ Return approval berfungsi normal
- ✅ Stock validation bekerja dengan benar
- ✅ Stock reduction berjalan sesuai expected
- ✅ Inventory cards ter-update dengan benar

### No Negative Impact
- ✅ Tidak ada breaking changes
- ✅ Backward compatibility terjaga
- ✅ Data integrity terjaga

## Prevention

### Code Review Checklist
- [ ] Pastikan mapping `inventory_item_id` vs `item_id` konsisten
- [ ] Verify JOIN dengan `outlet_food_inventory_items` table
- [ ] Test stock operations dengan data real
- [ ] Check inventory card operations

### Database Design Notes
- `outlet_food_inventory_stocks` menggunakan `inventory_item_id` (bukan `item_id`)
- `outlet_food_inventory_items` adalah bridge table antara `items` dan inventory system
- Selalu JOIN dengan `outlet_food_inventory_items` untuk mendapatkan `inventory_item_id` yang benar

## Related Systems
- Outlet Food Good Receive (menggunakan mapping yang benar)
- Outlet Stock Balance (menggunakan mapping yang benar)
- Outlet Inventory Adjustment (perlu dicek apakah ada masalah serupa)
