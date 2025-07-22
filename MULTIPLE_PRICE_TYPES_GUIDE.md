# Multiple Price Types Guide

## Overview
Sistem mendukung **multiple price types** untuk satu item dalam satu file upload. Satu item bisa memiliki:
- Harga umum (all regions)
- Harga spesifik region
- Harga spesifik outlet
- Kombinasi dari ketiganya

## Format Data

### Template Export
Template akan menghasilkan multiple rows untuk satu item:

| Item ID | SKU | Item Name | Current Price | New Price | Region/Outlet |
|---------|-----|-----------|---------------|-----------|---------------|
| 54157 | SR-20250620-4744 | Sambal Buntut | 14433 | 14433 all | All |
| 54157 | SR-20250620-4744 | Sambal Buntut | 14433 | 14433 region | Jakarta-Tangerang |
| 54157 | SR-20250620-4744 | Sambal Buntut | 14433 | 14433 region | Bandung Prime |
| 54157 | SR-20250620-4744 | Sambal Buntut | 14433 | 14433 region | Bandung Reguler |

### Format New Price
- `14152.94 all` = Harga untuk semua region
- `14152.94 region` = Harga untuk region tertentu
- `15000.00 outlet` = Harga untuk outlet tertentu

## Cara Kerja Import

### 1. Parsing Data
Setiap row diproses secara terpisah:
```php
// Row 1: "14152.94 all" + "All"
// Result: price=14152.94, type=all, region=All

// Row 2: "14152.94 region" + "Jakarta-Tangerang"  
// Result: price=14152.94, type=region, region=Jakarta-Tangerang

// Row 3: "14152.94 region" + "Bandung Prime"
// Result: price=14152.94, type=region, region=Bandung Prime
```

### 2. Database Update
Setiap row akan mengupdate/create record terpisah:

```sql
-- Row 1: Update general price
UPDATE item_prices SET price = 14152.94 
WHERE item_id = 54157 AND region_id IS NULL AND outlet_id IS NULL

-- Row 2: Update region Jakarta-Tangerang price  
UPDATE item_prices SET price = 14152.94 
WHERE item_id = 54157 AND region_id = (SELECT id FROM regions WHERE name = 'Jakarta-Tangerang')

-- Row 3: Update region Bandung Prime price
UPDATE item_prices SET price = 14152.94 
WHERE item_id = 54157 AND region_id = (SELECT id FROM regions WHERE name = 'Bandung Prime')
```

## Contoh Penggunaan

### Scenario 1: Update Harga Umum + Region Tertentu
```
Row 1: 14152.94 all + All
Row 2: 14152.94 region + Jakarta-Tangerang
Row 3: 14152.94 region + Bandung Prime
```
**Result:** Item akan memiliki 3 price configurations

### Scenario 2: Update Harga Umum + Outlet Tertentu
```
Row 1: 14152.94 all + All  
Row 2: 15000.00 outlet + Outlet Jakarta Pusat
```
**Result:** Item akan memiliki 2 price configurations

### Scenario 3: Update Semua Price Types
```
Row 1: 14152.94 all + All
Row 2: 14152.94 region + Jakarta-Tangerang
Row 3: 14152.94 region + Bandung Prime
Row 4: 15000.00 outlet + Outlet Jakarta Pusat
```
**Result:** Item akan memiliki 4 price configurations

## Validasi dan Error Handling

### 1. Validasi Format
- Format "14152.94 region" harus valid
- Angka harus positif
- Price type harus: all, region, atau outlet

### 2. Validasi Region/Outlet
- Region harus ada di tabel `regions`
- Outlet harus ada di tabel `tbl_data_outlet`
- Jika tidak ditemukan, akan error

### 3. Duplicate Handling
- Jika price untuk region/outlet yang sama sudah ada, akan diupdate
- Jika belum ada, akan dibuat baru

## Logging dan Monitoring

### Import Log
```
[INFO] Row 2: Processing Sambal Buntut - region Jakarta-Tangerang
[INFO] Row 3: Processing Sambal Buntut - region Bandung Prime  
[INFO] Row 4: Processing Sambal Buntut - region Bandung Reguler
[INFO] Import completed: 4 price updates, 0 errors
```

### Activity Log
Setiap update akan dicatat di `activity_logs`:
```
"Update harga item: Sambal Buntut dari Rp 14.433 ke Rp 14.152 (region: Jakarta-Tangerang)"
"Update harga item: Sambal Buntut dari Rp 14.433 ke Rp 14.152 (region: Bandung Prime)"
```

## Best Practices

### 1. Template Usage
- Download template untuk melihat semua price configurations yang ada
- Edit harga di kolom "New Price" dengan format yang benar
- Jangan hapus row yang tidak ingin diupdate (biarkan kosong)

### 2. Data Validation
- Pastikan region/outlet name sesuai dengan database
- Gunakan format angka yang benar (14152.94, bukan 14.152,94)
- Pastikan price type sesuai (all, region, outlet)

### 3. Testing
- Test dengan data kecil dulu
- Cek log untuk memastikan semua row terproses
- Verifikasi hasil di halaman detail item

## Troubleshooting

### Problem: Hanya satu price yang terupdate
**Solution:** Cek format "New Price" dan "Region/Outlet" di setiap row

### Problem: Error "Region tidak ditemukan"
**Solution:** Pastikan nama region sesuai dengan yang ada di database

### Problem: Error "Invalid price format"
**Solution:** Pastikan format "14152.94 region" (angka + spasi + type)

## Summary

✅ **Multiple price types didukung penuh**
✅ **Setiap row diproses secara terpisah**
✅ **Tidak ada konflik antara price types**
✅ **Semua configurations tersimpan di database**
✅ **Logging lengkap untuk monitoring**
✅ **Error handling yang robust** 