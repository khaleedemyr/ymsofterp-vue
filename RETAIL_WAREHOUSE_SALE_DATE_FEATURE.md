# Retail Warehouse Sale Date Feature

Dokumentasi fitur tanggal penjualan untuk Retail Warehouse Sale yang memungkinkan user memilih tanggal penjualan.

## Overview

Fitur ini menambahkan kemampuan untuk user memilih tanggal penjualan saat membuat retail warehouse sale, bukan hanya menggunakan tanggal saat ini (created_at). Ini berguna untuk:

- Backdate penjualan
- Mencatat penjualan yang dilakukan di hari sebelumnya
- Laporan berdasarkan tanggal penjualan yang sebenarnya

## Perubahan yang Dibuat

### 1. Database Changes
- **Tabel**: `retail_warehouse_sales`
- **Kolom Baru**: `sale_date` (DATE, NOT NULL, DEFAULT CURDATE())
- **Index**: 
  - `idx_retail_warehouse_sales_sale_date` pada kolom `sale_date`
  - `idx_retail_warehouse_sales_date_customer` pada `(sale_date, customer_id)`

### 2. Frontend Changes

#### **Form.vue**
- ✅ **Field Tanggal**: Input date picker untuk memilih tanggal penjualan
- ✅ **Default Value**: Otomatis set ke tanggal hari ini
- ✅ **Validation**: Required field dengan validasi date

#### **Show.vue**
- ✅ **Display**: Menampilkan "Tanggal Penjualan" dengan fallback ke created_at
- ✅ **Format**: Format tanggal Indonesia (DD/MM/YYYY)

#### **PrintStruk.vue & generateStrukPDF.js**
- ✅ **Print**: Menggunakan sale_date untuk struk print
- ✅ **Fallback**: Jika sale_date tidak ada, gunakan created_at

### 3. Backend Changes

#### **Controller**
- ✅ **Validation**: Validasi sale_date sebagai required date
- ✅ **Store**: Menyimpan sale_date ke database
- ✅ **Fallback**: Default ke tanggal hari ini jika tidak ada

## File yang Dimodifikasi

### Frontend
1. `resources/js/Pages/RetailWarehouseSale/Form.vue`
2. `resources/js/Pages/RetailWarehouseSale/Show.vue`
3. `resources/js/Pages/RetailWarehouseSale/PrintStruk.vue`
4. `resources/js/Pages/RetailWarehouseSale/generateStrukPDF.js`

### Backend
1. `app/Http/Controllers/RetailWarehouseSaleController.php`

### Database
1. `add_sale_date_to_retail_warehouse_sales.sql`
2. `run_sale_date_migration.php`

## Cara Penggunaan

### 1. Membuat Penjualan Baru
1. Buka form "Buat Penjualan Warehouse Retail"
2. Pilih customer
3. **Pilih tanggal penjualan** (default: hari ini)
4. Pilih warehouse dan division
5. Scan barcode items
6. Simpan penjualan

### 2. Melihat Detail Penjualan
- Di halaman detail, akan menampilkan "Tanggal Penjualan" yang dipilih user
- Jika sale_date tidak ada (data lama), akan fallback ke created_at

### 3. Print Struk
- Struk akan menampilkan tanggal penjualan yang dipilih user
- Format: DD/MM/YYYY

## Database Migration

### Manual Migration
```sql
-- Add sale_date column
ALTER TABLE retail_warehouse_sales 
ADD COLUMN sale_date DATE NOT NULL DEFAULT (CURDATE()) 
AFTER customer_id;

-- Update existing records
UPDATE retail_warehouse_sales 
SET sale_date = DATE(created_at) 
WHERE sale_date IS NULL OR sale_date = '0000-00-00';

-- Add indexes
CREATE INDEX idx_retail_warehouse_sales_sale_date ON retail_warehouse_sales(sale_date);
CREATE INDEX idx_retail_warehouse_sales_date_customer ON retail_warehouse_sales(sale_date, customer_id);
```

### Automated Migration
```bash
# Run the migration script
php run_sale_date_migration.php
```

## Validation Rules

### Frontend
- **Required**: Field tanggal wajib diisi
- **Type**: Date input (HTML5 date picker)
- **Default**: Tanggal hari ini

### Backend
```php
$request->validate([
    'sale_date' => 'required|date',
    // ... other validations
]);
```

## Data Structure

### Database Schema
```sql
CREATE TABLE retail_warehouse_sales (
    id BIGINT PRIMARY KEY,
    number VARCHAR(255),
    customer_id BIGINT,
    sale_date DATE NOT NULL DEFAULT (CURDATE()), -- NEW COLUMN
    warehouse_id BIGINT,
    warehouse_division_id BIGINT,
    total_amount DECIMAL(15,2),
    notes TEXT,
    status VARCHAR(50),
    created_by BIGINT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### API Request/Response
```json
// Request
{
    "customer_id": 1,
    "sale_date": "2025-01-09",
    "warehouse_id": 1,
    "warehouse_division_id": 1,
    "items": [...],
    "total_amount": 100000,
    "notes": "Catatan penjualan"
}

// Response
{
    "success": true,
    "message": "Retail Warehouse Sale berhasil disimpan!",
    "sale_id": 123,
    "number": "RWS2501090001"
}
```

## Backward Compatibility

### Data Lama
- **Existing Records**: Otomatis di-update dengan `sale_date = DATE(created_at)`
- **Fallback Logic**: Jika `sale_date` tidak ada, gunakan `created_at`
- **No Breaking Changes**: Semua fitur existing tetap berfungsi

### Code Compatibility
```javascript
// Frontend - Safe access dengan fallback
const displayDate = sale.sale_date || sale.created_at;

// Backend - Safe access dengan fallback
$saleDate = $request->sale_date ?? now()->toDateString();
```

## Performance Considerations

### Indexes
- **Primary Index**: `idx_retail_warehouse_sales_sale_date` untuk query berdasarkan tanggal
- **Composite Index**: `idx_retail_warehouse_sales_date_customer` untuk query gabungan

### Query Optimization
```sql
-- Efficient date range queries
SELECT * FROM retail_warehouse_sales 
WHERE sale_date BETWEEN '2025-01-01' AND '2025-01-31';

-- Efficient customer + date queries
SELECT * FROM retail_warehouse_sales 
WHERE customer_id = 1 AND sale_date = '2025-01-09';
```

## Testing

### Manual Testing
1. **Create Sale**: Test dengan tanggal berbeda
2. **View Detail**: Verify tanggal yang ditampilkan
3. **Print**: Verify tanggal di struk
4. **Backward Compatibility**: Test dengan data lama

### Test Cases
```javascript
// Test cases
describe('Retail Warehouse Sale Date Feature', () => {
  it('should save sale with custom date', () => {
    // Test implementation
  });
  
  it('should display custom date in detail view', () => {
    // Test implementation
  });
  
  it('should print with custom date', () => {
    // Test implementation
  });
  
  it('should fallback to created_at for old data', () => {
    // Test implementation
  });
});
```

## Troubleshooting

### Common Issues

#### 1. Migration Failed
```bash
# Check if column exists
SHOW COLUMNS FROM retail_warehouse_sales LIKE 'sale_date';

# Manual fix
ALTER TABLE retail_warehouse_sales ADD COLUMN sale_date DATE DEFAULT (CURDATE());
```

#### 2. Date Not Saving
- Check validation rules
- Verify frontend form data
- Check database constraints

#### 3. Print Shows Wrong Date
- Verify sale_date field in database
- Check fallback logic in print components

### Debug Commands
```php
// Check sale_date data
DB::table('retail_warehouse_sales')
  ->select('id', 'number', 'sale_date', 'created_at')
  ->orderBy('id', 'desc')
  ->limit(5)
  ->get();

// Check migration status
DB::select("SHOW COLUMNS FROM retail_warehouse_sales LIKE 'sale_date'");
```

## Future Enhancements

1. **Date Range Validation**: Prevent future dates
2. **Business Day Validation**: Only allow business days
3. **Date Picker Enhancement**: Better UI/UX
4. **Bulk Date Update**: Update multiple sales dates
5. **Date-based Reports**: Enhanced reporting by sale_date

## Security Considerations

1. **Input Validation**: Strict date validation
2. **SQL Injection**: Use parameterized queries
3. **Date Manipulation**: Prevent date tampering
4. **Access Control**: Ensure user can only set valid dates

## Monitoring

### Logs
- Migration execution logs
- Date validation errors
- Performance metrics

### Metrics
- Sales by date distribution
- Date picker usage
- Migration success rate

## Support

Untuk support dan troubleshooting:
1. Cek dokumentasi ini
2. Run migration script
3. Verify database schema
4. Check application logs
5. Hubungi tim development
