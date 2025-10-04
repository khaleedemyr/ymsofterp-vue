# Debug Purchase Order Ops Generate

## Masalah yang Ditemukan:
1. **Error**: `The selected items_by_supplier.[object Object].0.supplier_id is invalid`
2. **Penyebab**: Validasi Laravel tidak sesuai dengan struktur data yang dikirim

## Perbaikan yang Dilakukan:

### 1. ✅ Menambahkan Debug Logging
```php
\Log::info('=== START GENERATE PO OPS ===');
\Log::info('Generate PO Request Data:', [
    'items_by_supplier' => $request->items_by_supplier,
    'ppn_enabled' => $request->ppn_enabled,
    'notes' => $request->notes
]);
```

### 2. ✅ Memperbaiki Struktur Data
- **Sebelum**: `$itemData['pr_item_id']` 
- **Sesudah**: `$itemData['id']`

### 3. ✅ Memperbaiki Source Type
- **Sebelum**: `'source_type' => 'purchase_requisition_ops'`
- **Sesudah**: `'source_type' => 'pr_ops'`

### 4. ✅ Memperbaiki Status PR
- **Sebelum**: `'status' => 'IN_PO'`
- **Sesudah**: `'status' => 'PROCESSED'`

## Struktur Data yang Diharapkan:

### Frontend (Vue.js):
```javascript
items_by_supplier: {
  "1": [ // supplier_id = 1
    {
      id: 123, // item ID
      supplier_id: 1,
      qty: 10,
      price: 50000,
      pr_id: 456,
      item_name: "PSU",
      unit: "Unit",
      arrival_date: "2025-01-15"
    }
  ]
}
```

### Backend (Laravel):
```php
// Validasi yang benar:
'items_by_supplier.*.*.supplier_id' => 'required|exists:suppliers,id'
'items_by_supplier.*.*.qty' => 'required|numeric|min:0'
'items_by_supplier.*.*.price' => 'required|numeric|min:0'
```

## Testing Steps:
1. ✅ Cek log Laravel untuk debug info
2. ✅ Pastikan supplier_id valid di database
3. ✅ Pastikan struktur data sesuai
4. ✅ Test generate PO lagi

## File yang Diperbaiki:
- `app/Http/Controllers/PurchaseOrderOpsController.php`
