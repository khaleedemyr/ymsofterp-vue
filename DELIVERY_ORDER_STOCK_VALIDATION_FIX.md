# Delivery Order Stock Validation Fix

## Masalah yang Ditemukan

### 1. **Inkonsistensi Unit dalam Validasi Stock**

**Gejala:**
- User melihat stock: "15 Pcs" (Medium/Large unit)
- Error message: "Stok tersedia: 3735.0000 Gram" (Small unit)
- User input: 15 Pcs
- Validasi gagal meskipun stock mencukupi

**Penyebab:**
- Backend hanya memvalidasi `qty_small` vs `stockInfo['qty_small']`
- Error message menampilkan stock dalam unit small (Gram)
- Tidak mempertimbangkan unit yang dipilih user

### 2. **Konversi Unit yang Tidak Konsisten**

**Frontend:**
```javascript
// Konversi semua stock ke small unit, lalu ke unit yang diminta
const totalSmall = smallStock + (mediumStock * conversion) + (largeStock * conversion);
if (item.unit === item.units.medium_unit) {
  stock = totalSmall / conversion;
}
```

**Backend:**
```php
// Hanya validasi qty_small
if ($quantities['qty_small'] > $stockInfo['qty_small']) {
    throw new \Exception("Qty melebihi stok yang tersedia. Stok tersedia: {$stockInfo['qty_small']}");
}
```

## Solusi yang Diterapkan

### 1. **Debug Logging**
Menambahkan logging untuk melihat nilai stock yang sebenarnya:

```php
Log::info('Stock validation debug', [
    'item_id' => $realItemId,
    'qty_small_needed' => $quantities['qty_small'],
    'qty_medium_needed' => $quantities['qty_medium'],
    'qty_large_needed' => $quantities['qty_large'],
    'stock_small_available' => $stockInfo['qty_small'],
    'stock_medium_available' => $stockInfo['qty_medium'],
    'stock_large_available' => $stockInfo['qty_large'],
    'input_qty' => $item['qty_scan'],
    'input_unit' => $item['unit'] ?? 'null'
]);
```

### 2. **Error Message yang Lebih Informatif**
Memperbaiki error message untuk menampilkan stock dalam unit yang dipilih user:

```php
// Show stock in the unit that user is trying to use
$inputUnit = $item['unit'] ?? null;
$availableStock = 0;
$unitName = '';

if ($inputUnit === $unitSmall) {
    $availableStock = $stockInfo['qty_small'];
    $unitName = $unitSmall;
} elseif ($inputUnit === $unitMedium) {
    $availableStock = $stockInfo['qty_medium'];
    $unitName = $unitMedium;
} elseif ($inputUnit === $unitLarge) {
    $availableStock = $stockInfo['qty_large'];
    $unitName = $unitLarge;
} else {
    $availableStock = $stockInfo['qty_small'];
    $unitName = $unitSmall;
}

throw new \Exception("Qty melebihi stok yang tersedia. Stok tersedia: {$availableStock} {$unitName}");
```

## Testing yang Diperlukan

### 1. **Test Case 1: Stock dalam Unit Medium (Pcs)**
- Stock tersedia: 15 Pcs
- User input: 15 Pcs
- Expected: Berhasil (tidak ada error)

### 2. **Test Case 2: Stock dalam Unit Small (Gram)**
- Stock tersedia: 3735 Gram
- User input: 15 Pcs (jika 1 Pcs = 249 Gram)
- Expected: Berhasil (tidak ada error)

### 3. **Test Case 3: Stock Melebihi Ketersediaan**
- Stock tersedia: 10 Pcs
- User input: 15 Pcs
- Expected: Error dengan message "Stok tersedia: 10 Pcs"

## File yang Dimodifikasi

1. **app/Http/Controllers/DeliveryOrderController.php**
   - Line 418-429: Menambahkan debug logging
   - Line 432-459: Memperbaiki error message dengan unit yang benar

## Monitoring

Setelah deploy, monitor log untuk melihat:
1. Nilai stock yang sebenarnya di database
2. Konversi unit yang dilakukan
3. Validasi yang berhasil/gagal

## Catatan Penting

- Masalah ini terjadi karena perbedaan cara frontend dan backend menangani konversi unit
- Frontend mengkonversi stock ke unit yang dipilih user
- Backend hanya memvalidasi dalam unit small
- Error message tidak informatif karena tidak menunjukkan unit yang benar
