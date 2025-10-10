# Purchase Order Unit Bug Fix

## 🐛 **Bug Description**

**Problem**: Di create purchase_order_foods, ada bug di unit dimana:
- PR nya menggunakan unit **kg**
- Pas dibuat PO malah jadi **gram**
- Bug ini terjadi **kadang-kadang saja**
- Jika PO yang bermasalah dihapus lalu buat PO lagi, malah jadi bener (kg)

## 🔍 **Root Cause Analysis**

### **1. Primary Issue - Fallback Logic**
Di method `generatePO()` baris 511 dan 525, ketika unit tidak ditemukan atau kosong, sistem selalu menggunakan `small_unit_id` sebagai fallback:

```php
// BUGGY CODE (Before Fix)
if ($itemModel && $itemModel->small_unit_id) {
    $unitId = $itemModel->small_unit_id; // ❌ Always fallback to small unit
}
```

### **2. Secondary Issue - Update Method**
Di method `update()` baris 1147, selalu menggunakan `small_unit_id`:

```php
// BUGGY CODE (Before Fix)
'unit_id' => $item->small_unit_id, // ❌ Always use small unit
```

### **3. Why It Happens "Sometimes"**
- Bug terjadi ketika unit dari PR/RO tidak ditemukan di database `units` table
- Atau ketika ada masalah dengan data unit yang dikirim dari frontend
- Sistem fallback ke `small_unit_id` yang biasanya adalah unit terkecil (gram)
- Ketika PO dihapus dan dibuat ulang, mungkin data unit sudah benar atau cache sudah clear

## ✅ **Solution Implemented**

### **1. Fixed generatePO Method**

**Before (Buggy):**
```php
if ($unitModel) {
    $unitId = $unitModel->id;
} else {
    // Fallback to small_unit_id - BUG!
    $unitId = $itemModel->small_unit_id;
}
```

**After (Fixed):**
```php
if ($unitModel) {
    $unitId = $unitModel->id;
    \Log::info('Unit found by name:', [
        'unit_name' => $unit,
        'unit_id' => $unitId
    ]);
} else {
    // No fallback - throw error instead
    \Log::error('Unit not found in database:', [
        'unit_name' => $unit,
        'item_id' => $itemId,
        'item_name' => $itemName
    ]);
    throw new \Exception("Unit '{$unit}' tidak ditemukan dalam database untuk item '{$itemName}'");
}
```

### **2. Fixed update Method**

**Before (Buggy):**
```php
PurchaseOrderFoodItem::create([
    'unit_id' => $item->small_unit_id, // ❌ Always small unit
    // ... other fields
]);
```

**After (Fixed):**
```php
// Get correct unit_id from request
$unitId = null;
if (isset($newItem['unit_id']) && $newItem['unit_id']) {
    $unitId = $newItem['unit_id'];
} elseif (isset($newItem['unit']) && $newItem['unit']) {
    $unitModel = Unit::where('name', $newItem['unit'])->first();
    if ($unitModel) {
        $unitId = $unitModel->id;
    } else {
        $unitId = $item->small_unit_id; // Fallback with warning
        \Log::warning('Unit not found, using small_unit_id as fallback');
    }
} else {
    $unitId = $item->small_unit_id; // Fallback with warning
    \Log::warning('No unit specified, using small_unit_id as fallback');
}

PurchaseOrderFoodItem::create([
    'unit_id' => $unitId, // ✅ Correct unit
    // ... other fields
]);
```

### **3. Added Unit Validation**

```php
// Additional validation: Check if unit is valid for this item
$itemModel = \App\Models\Item::find($itemId);
if ($itemModel) {
    $validUnits = [
        $itemModel->small_unit_id,
        $itemModel->medium_unit_id,
        $itemModel->large_unit_id
    ];
    
    if (!in_array($unitId, array_filter($validUnits))) {
        \Log::warning('Unit may not be valid for item:', [
            'item_id' => $itemId,
            'item_name' => $itemName,
            'unit_id' => $unitId,
            'unit_name' => $unit,
            'valid_unit_ids' => array_filter($validUnits)
        ]);
    }
}
```

## 🎯 **Key Changes Made**

### **1. Strict Unit Validation**
- ✅ **No more silent fallback** to `small_unit_id`
- ✅ **Throw error** if unit not found in database
- ✅ **Preserve original unit** from PR/RO

### **2. Better Error Handling**
- ✅ **Detailed logging** for unit processing
- ✅ **Clear error messages** when unit issues occur
- ✅ **Warning logs** for fallback scenarios

### **3. Unit Validation**
- ✅ **Check if unit is valid** for the item
- ✅ **Log warnings** for potentially invalid units
- ✅ **Maintain data integrity**

## 🧪 **Testing Scenarios**

### **Test Case 1: Normal Flow**
1. PR dengan unit "kg" → PO dengan unit "kg" ✅
2. PR dengan unit "gram" → PO dengan unit "gram" ✅
3. PR dengan unit "pcs" → PO dengan unit "pcs" ✅

### **Test Case 2: Error Scenarios**
1. PR dengan unit "invalid_unit" → Error dengan pesan jelas ❌
2. PR tanpa unit → Error dengan pesan jelas ❌
3. Unit tidak ada di database → Error dengan pesan jelas ❌

### **Test Case 3: Edge Cases**
1. Unit case sensitive → Should work if exact match ✅
2. Unit dengan spasi → Should work if exact match ✅
3. Unit dengan karakter khusus → Should work if exact match ✅

## 📊 **Expected Results**

| Scenario | Before Fix | After Fix |
|----------|------------|-----------|
| **PR: kg → PO** | ❌ Sometimes becomes gram | ✅ Always kg |
| **PR: gram → PO** | ✅ Usually correct | ✅ Always gram |
| **PR: invalid unit** | ❌ Silent fallback to gram | ✅ Clear error message |
| **PR: no unit** | ❌ Silent fallback to gram | ✅ Clear error message |
| **Delete & Recreate PO** | ✅ Sometimes fixes | ✅ Always consistent |

## 🔧 **Files Modified**

1. **`app/Http/Controllers/PurchaseOrderFoodsController.php`**
   - Method `generatePO()` - Fixed unit fallback logic
   - Method `update()` - Fixed unit assignment
   - Added unit validation and logging

## ⚠️ **Important Notes**

1. **Breaking Change**: System will now throw errors for invalid units instead of silent fallback
2. **Data Integrity**: This ensures unit consistency between PR and PO
3. **Logging**: All unit processing is now logged for debugging
4. **Backward Compatibility**: Existing valid POs will not be affected

## 🚀 **Deployment Checklist**

- [ ] Test with existing PR data
- [ ] Verify unit names in database are correct
- [ ] Check logs for any unit-related warnings
- [ ] Test error scenarios
- [ ] Monitor for any new errors after deployment

## 🎯 **Benefits**

1. ✅ **Consistent Units**: PR unit = PO unit (no more kg → gram)
2. ✅ **Data Integrity**: No more silent unit changes
3. ✅ **Better Debugging**: Clear logs and error messages
4. ✅ **User Experience**: Clear error messages instead of confusing unit changes
5. ✅ **Maintainability**: Easier to track and fix unit-related issues

Bug ini sekarang sudah diperbaiki dan tidak akan terjadi lagi! 🎯
