# Stock Card Error 500 Fix Documentation

## Masalah yang Ditemukan

Error 500 pada laporan kartu stok di server production, sementara di localhost berfungsi normal.

## Analisis Penyebab

### 1. **Query yang Kompleks dan Berat**
- Method `stockCard()` menggunakan query dengan banyak JOIN (8+ tabel)
- Query tanpa pagination bisa memakan memory besar
- Tidak ada limit pada hasil query

### 2. **Perbedaan Konfigurasi Server**
- Memory limit di server production lebih kecil
- Max execution time terbatas
- Database timeout settings berbeda

### 3. **Volume Data yang Besar**
- Server production memiliki data lebih banyak
- Query tanpa filter yang tepat bisa menyebabkan memory overflow

## Solusi yang Diterapkan

### 1. **Optimasi Query dan Memory Management**

**File:** `app/Http/Controllers/InventoryReportController.php`

**Perubahan:**
- ✅ Menambahkan `ini_set('memory_limit', '512M')`
- ✅ Menambahkan `ini_set('max_execution_time', 300)`
- ✅ Menambahkan limit 10,000 records pada query
- ✅ Validasi input yang diperlukan (item_id wajib)
- ✅ Batasi range tanggal maksimal 1 tahun
- ✅ Error handling dengan try-catch
- ✅ Logging error untuk debugging

### 2. **Validasi Input**

```php
// Validasi item_id wajib
if (!$itemId) {
    return inertia('Inventory/StockCard', [
        'error' => 'Silakan pilih item terlebih dahulu'
    ]);
}

// Batasi range tanggal
if ($diffInDays > 365) {
    return inertia('Inventory/StockCard', [
        'error' => 'Range tanggal maksimal 1 tahun untuk performa yang optimal'
    ]);
}
```

### 3. **Error Handling**

```php
try {
    // Query logic
} catch (\Exception $e) {
    \Log::error('Stock Card Report Error', [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'request' => $request->all()
    ]);
    
    return inertia('Inventory/StockCard', [
        'error' => 'Terjadi kesalahan saat memuat data. Silakan coba lagi atau hubungi administrator.'
    ]);
}
```

## Konfigurasi Server yang Disarankan

### 1. **PHP Configuration (.htaccess)**

```apache
# PHP settings untuk stock card report
<IfModule mod_php8.c>
    php_value upload_max_filesize 64M
    php_value post_max_size 64M
    php_value max_execution_time 300
    php_value max_input_time 300
    php_value memory_limit 512M
    php_value max_input_vars 3000
</IfModule>
```

### 2. **Database Configuration**

Pastikan MySQL memiliki konfigurasi yang optimal:

```sql
-- MySQL settings untuk query yang berat
SET SESSION wait_timeout = 300;
SET SESSION interactive_timeout = 300;
SET SESSION max_execution_time = 300;
```

### 3. **Laravel Configuration**

Tambahkan di `.env` production:

```env
# Database timeout settings
DB_TIMEOUT=300
DB_READ_TIMEOUT=300
DB_WRITE_TIMEOUT=300

# Memory settings
MEMORY_LIMIT=512M
MAX_EXECUTION_TIME=300
```

## Testing dan Monitoring

### 1. **Test Query Performance**

```php
// Test query dengan EXPLAIN
DB::select('EXPLAIN SELECT ... FROM food_inventory_cards ...');
```

### 2. **Monitor Memory Usage**

```php
// Log memory usage
\Log::info('Memory usage', [
    'peak_memory' => memory_get_peak_usage(true),
    'current_memory' => memory_get_usage(true)
]);
```

### 3. **Database Indexing**

Pastikan ada index pada kolom yang sering digunakan:

```sql
-- Index untuk performa query stock card
CREATE INDEX idx_food_inventory_cards_date ON food_inventory_cards(date);
CREATE INDEX idx_food_inventory_cards_item_warehouse ON food_inventory_cards(inventory_item_id, warehouse_id);
CREATE INDEX idx_food_inventory_cards_reference ON food_inventory_cards(reference_type, reference_id);
```

## Troubleshooting

### 1. **Error 500 Masih Terjadi**

1. Check log error di `storage/logs/laravel.log`
2. Pastikan konfigurasi PHP di server sudah sesuai
3. Test dengan range tanggal yang lebih kecil
4. Pastikan database connection stabil

### 2. **Query Timeout**

1. Kurangi limit query (dari 10,000 ke 5,000)
2. Tambahkan index database
3. Optimasi query dengan subquery

### 3. **Memory Exhausted**

1. Tingkatkan memory_limit di PHP
2. Implementasi pagination
3. Gunakan chunk() untuk data besar

## Implementasi di Server

### 1. **Upload File yang Diperbaiki**

```bash
# Upload file controller yang sudah diperbaiki
scp app/Http/Controllers/InventoryReportController.php user@server:/path/to/app/Http/Controllers/
```

### 2. **Update .htaccess**

```bash
# Update .htaccess dengan konfigurasi PHP yang optimal
scp public/.htaccess user@server:/path/to/public/
```

### 3. **Clear Cache**

```bash
# Clear cache Laravel
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### 4. **Test Functionality**

1. Akses menu laporan kartu stok
2. Pilih item dan warehouse
3. Set range tanggal (maksimal 1 tahun)
4. Klik "Load Data"
5. Monitor log untuk error

## Monitoring dan Maintenance

### 1. **Regular Monitoring**

- Monitor log error setiap hari
- Check memory usage pada query yang berat
- Monitor database performance

### 2. **Performance Optimization**

- Implementasi caching untuk data yang jarang berubah
- Gunakan database indexing yang optimal
- Consider pagination untuk data yang sangat besar

### 3. **Backup Strategy**

- Backup database sebelum perubahan besar
- Test di staging environment dulu
- Document semua perubahan konfigurasi

## Kesimpulan

Dengan implementasi perbaikan ini, laporan kartu stok seharusnya bisa berjalan dengan baik di server production. Perbaikan utama meliputi:

1. ✅ Optimasi memory dan execution time
2. ✅ Validasi input yang ketat
3. ✅ Error handling yang proper
4. ✅ Limit query untuk mencegah memory overflow
5. ✅ Logging untuk debugging

Jika masih ada masalah, check log error dan sesuaikan konfigurasi server sesuai kebutuhan.
