# Outlet Internal Use Waste Stock Fix

## Problem Description
Setelah melakukan internal use/waste di outlet, inventory card menampilkan qty keluar yang tidak lengkap dan saldo yang tidak akurat. Dari gambar yang ditunjukkan, terlihat bahwa:

1. **Qty Keluar**: Hanya menampilkan qty small (1500 Mili liter), padahal seharusnya menampilkan semua unit (small, medium, large)
2. **Saldo**: Tidak ter-update dengan benar karena stock table tidak ter-update dengan proper

## Root Cause Analysis

### **Masalah di OutletInternalUseWasteController**

#### **1. Store Method - Stock Update Issue**
**Before (SALAH):**
```php
// Update stok di outlet (kurangi)
DB::table('outlet_food_inventory_stocks')
    ->where('inventory_item_id', $inventoryItem->id)
    ->where('id_outlet', $request->outlet_id)
    // ❌ MISSING: warehouse_outlet_id
    ->update([
        'qty_small' => $stock->qty_small - $qty_small,
        'qty_medium' => $stock->qty_medium - $qty_medium,
        'qty_large' => $stock->qty_large - $qty_large,
        'updated_at' => now(),
    ]);
```

**After (BENAR):**
```php
// Update stok di outlet (kurangi)
DB::table('outlet_food_inventory_stocks')
    ->where('inventory_item_id', $inventoryItem->id)
    ->where('id_outlet', $request->outlet_id)
    ->where('warehouse_outlet_id', $request->warehouse_outlet_id)  // ✅ ADDED
    ->update([
        'qty_small' => $stock->qty_small - $qty_small,
        'qty_medium' => $stock->qty_medium - $qty_medium,
        'qty_large' => $stock->qty_large - $qty_large,
        'updated_at' => now(),
    ]);
```

#### **2. Destroy Method - Stock Rollback Issue**
**Before (SALAH):**
```php
// Rollback stok di outlet_food_inventory_stocks
$stock = DB::table('outlet_food_inventory_stocks')
    ->where('inventory_item_id', $inventory_item_id)
    ->where('id_outlet', $data->outlet_id)
    // ❌ MISSING: warehouse_outlet_id
    ->first();
if ($stock) {
    DB::table('outlet_food_inventory_stocks')
        ->where('inventory_item_id', $inventory_item_id)
        ->where('id_outlet', $data->outlet_id)
        // ❌ MISSING: warehouse_outlet_id
        ->update([...]);
}
```

**After (BENAR):**
```php
// Rollback stok di outlet_food_inventory_stocks
$stock = DB::table('outlet_food_inventory_stocks')
    ->where('inventory_item_id', $inventory_item_id)
    ->where('id_outlet', $data->outlet_id)
    ->where('warehouse_outlet_id', $data->warehouse_outlet_id)  // ✅ ADDED
    ->first();
if ($stock) {
    DB::table('outlet_food_inventory_stocks')
        ->where('inventory_item_id', $inventory_item_id)
        ->where('id_outlet', $data->outlet_id)
        ->where('warehouse_outlet_id', $data->warehouse_outlet_id)  // ✅ ADDED
        ->update([...]);
}
```

## Technical Analysis

### **Database Schema Understanding**
Table `outlet_food_inventory_stocks` memiliki composite key:
- `inventory_item_id` (FK ke outlet_food_inventory_items)
- `id_outlet` (FK ke tbl_data_outlet)
- `warehouse_outlet_id` (FK ke warehouse_outlets)

### **Why warehouse_outlet_id is Critical**
1. **Multi-warehouse Support**: Satu outlet bisa memiliki multiple warehouse
2. **Stock Isolation**: Stock di warehouse A berbeda dengan warehouse B
3. **Data Integrity**: Tanpa warehouse_outlet_id, query bisa update stock yang salah

### **Comparison with Working Controllers**

#### **OutletFoodReturnController (BENAR):**
```php
DB::table('outlet_food_inventory_stocks')
    ->where('inventory_item_id', $item->inventory_item_id)
    ->where('id_outlet', $return->outlet_id)
    ->where('warehouse_outlet_id', $return->warehouse_outlet_id)  // ✅ CORRECT
    ->update([
        'qty_small' => $currentStock->qty_small - $qty_small,
        'updated_at' => now()
    ]);
```

#### **OutletInternalUseWasteController (FIXED):**
```php
DB::table('outlet_food_inventory_stocks')
    ->where('inventory_item_id', $inventoryItem->id)
    ->where('id_outlet', $request->outlet_id)
    ->where('warehouse_outlet_id', $request->warehouse_outlet_id)  // ✅ NOW CORRECT
    ->update([
        'qty_small' => $stock->qty_small - $qty_small,
        'qty_medium' => $stock->qty_medium - $qty_medium,
        'qty_large' => $stock->qty_large - $qty_large,
        'updated_at' => now(),
    ]);
```

## Impact of the Fix

### **Before Fix:**
1. **Stock Update**: Query tidak menemukan record yang tepat karena missing warehouse_outlet_id
2. **Inventory Card**: Menampilkan qty keluar yang tidak lengkap
3. **Saldo Calculation**: Tidak akurat karena stock table tidak ter-update
4. **Data Inconsistency**: Inventory card vs stock table tidak sinkron

### **After Fix:**
1. **Stock Update**: Query menemukan record yang tepat dengan composite key lengkap
2. **Inventory Card**: Menampilkan semua qty keluar (small, medium, large)
3. **Saldo Calculation**: Akurat karena stock table ter-update dengan benar
4. **Data Consistency**: Inventory card dan stock table sinkron

## Files Modified

### **`app/Http/Controllers/OutletInternalUseWasteController.php`**

#### **1. Store Method (Line 147-157)**
- **Added**: `->where('warehouse_outlet_id', $request->warehouse_outlet_id)` to stock update query

#### **2. Destroy Method (Line 260-276)**
- **Added**: `->where('warehouse_outlet_id', $data->warehouse_outlet_id)` to stock rollback queries

## Testing Scenarios

### **Test Case 1: Internal Use Creation**
1. **Input**: Create internal use with 1500ml item
2. **Expected**: 
   - Stock table: qty_small, qty_medium, qty_large all updated
   - Inventory card: Shows all qty out (small, medium, large)
   - Saldo: Correctly calculated

### **Test Case 2: Internal Use Deletion**
1. **Input**: Delete existing internal use record
2. **Expected**:
   - Stock table: qty_small, qty_medium, qty_large all rolled back
   - Inventory card: Record deleted
   - Saldo: Restored to original value

### **Test Case 3: Multi-warehouse Scenario**
1. **Input**: Same item in different warehouses
2. **Expected**: Only correct warehouse stock is updated

## Related Systems

This fix pattern should be applied to other controllers that handle stock operations:
- `OutletFoodGoodReceiveController` (if applicable)
- `OutletFoodAdjustmentController` (if applicable)
- Any other inventory management controllers

## Best Practices

### **1. Always Include Composite Keys**
When updating stock tables, always include all parts of the composite key:
- `inventory_item_id`
- `id_outlet` 
- `warehouse_outlet_id`

### **2. Consistent Query Pattern**
```php
// ✅ CORRECT Pattern
DB::table('outlet_food_inventory_stocks')
    ->where('inventory_item_id', $inventoryItemId)
    ->where('id_outlet', $outletId)
    ->where('warehouse_outlet_id', $warehouseOutletId)
    ->update([...]);
```

### **3. Validation Before Update**
```php
// ✅ Check stock exists before update
$stock = DB::table('outlet_food_inventory_stocks')
    ->where('inventory_item_id', $inventoryItemId)
    ->where('id_outlet', $outletId)
    ->where('warehouse_outlet_id', $warehouseOutletId)
    ->first();

if (!$stock) {
    throw new \Exception("Stock not found");
}
```

## Future Considerations

1. **Add Database Constraints**: Ensure composite key uniqueness
2. **Add Logging**: Log all stock operations for audit trail
3. **Add Validation**: Validate stock availability before operations
4. **Add Rollback Mechanism**: Automatic rollback on errors
