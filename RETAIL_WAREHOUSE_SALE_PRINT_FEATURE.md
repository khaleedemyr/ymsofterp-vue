# Retail Warehouse Sale Print Feature

Dokumentasi fitur print untuk Retail Warehouse Sale yang menggunakan layout roll paper seperti Delivery Orders.

## Overview

Fitur print ini memungkinkan pengguna untuk mencetak struk penjualan retail warehouse dengan format roll paper (80mm) yang sama dengan Delivery Orders, termasuk:

- Layout roll paper 80mm
- Logo Justus Group
- Informasi penjualan lengkap
- Daftar item dengan harga
- Auto print dan download PDF

## File yang Dibuat/Dimodifikasi

### 1. Komponen Print
- **`resources/js/Pages/RetailWarehouseSale/PrintStruk.vue`** - Komponen utama untuk print layout
- **`resources/js/Pages/RetailWarehouseSale/generateStrukPDF.js`** - Script untuk generate PDF

### 2. Controller
- **`app/Http/Controllers/RetailWarehouseSaleController.php`** - Menambahkan method `print()`

### 3. Routes
- **`routes/web.php`** - Menambahkan route print

### 4. View
- **`resources/js/Pages/RetailWarehouseSale/Show.vue`** - Update tombol print

## Fitur Print

### Layout Struk
```
RETAIL WAREHOUSE SALE
JUSTUS GROUP
[Warehouse/Division Name]
--------------------------------
No: RWS2501090001
Tanggal: 09/01/2025
Customer: Nama Customer
Kode: CUST001
Telp: 08123456789
--------------------------------
2   PCS   Nama Item Panjang
    @Rp 10.000 = Rp 20.000
1   KG    Item Lain
    @Rp 5.000 = Rp 5.000
--------------------------------
TOTAL: Rp 25.000
--------------------------------
Catatan: Catatan penjualan
--------------------------------
        Terima kasih
```

### Fitur Utama

#### 1. **Auto Print**
- Struk otomatis terbuka untuk print saat halaman dimuat
- Delay 500ms untuk memastikan konten ter-render

#### 2. **Manual Print**
- Tombol "Print" untuk print manual
- Tombol "Download PDF" untuk download PDF

#### 3. **Layout Responsif**
- Width: 80mm (roll paper)
- Font: Courier New (monospace)
- Auto wrap untuk item name panjang

#### 4. **Informasi Lengkap**
- Header dengan logo dan judul
- Informasi penjualan (no, tanggal, customer)
- Daftar item dengan qty, unit, harga
- Total amount
- Catatan (jika ada)

## Cara Penggunaan

### 1. Dari Halaman Detail
1. Buka detail penjualan retail warehouse
2. Klik tombol "Cetak"
3. Halaman print akan terbuka di tab baru
4. Struk otomatis siap untuk print

### 2. Print Manual
1. Di halaman print, klik tombol "Print"
2. Pilih printer roll paper (80mm)
3. Print struk

### 3. Download PDF
1. Di halaman print, klik tombol "Download PDF"
2. PDF akan ter-download dengan nama file: `Retail_Sale_[NUMBER].pdf`

## Technical Details

### Print Layout CSS
```css
@media print {
  html, body { 
    width: 80mm !important; 
    margin: 0 !important; 
    padding: 0 !important; 
    background: #fff !important; 
  }
  #struk {
    width: 80mm !important;
    padding: 5mm 0 10mm 4mm !important;
    background: #fff !important;
  }
}
```

### PDF Generation
- Menggunakan jsPDF library
- Format: 80mm width, dynamic height
- Font: Arial (default jsPDF)
- Auto calculate height berdasarkan jumlah item


## Route Structure

```php
// Print route
Route::get('/retail-warehouse-sale/{id}/print', [RetailWarehouseSaleController::class, 'print'])
    ->name('retail-warehouse-sale.print');
```

## Controller Method

```php
public function print($id)
{
    // Get sale data with joins
    $sale = DB::table('retail_warehouse_sales as rws')
        ->leftJoin('customers as c', 'rws.customer_id', '=', 'c.id')
        ->leftJoin('warehouses as w', 'rws.warehouse_id', '=', 'w.id')
        ->leftJoin('warehouse_division as wd', 'rws.warehouse_division_id', '=', 'wd.id')
        ->leftJoin('users as u', 'rws.created_by', '=', 'u.id')
        // ... select fields
        ->where('rws.id', $id)
        ->first();

    // Get items
    $items = DB::table('retail_warehouse_sale_items as rwsi')
        ->leftJoin('items as i', 'rwsi.item_id', '=', 'i.id')
        // ... select fields
        ->where('rwsi.retail_warehouse_sale_id', $id)
        ->get();

    return Inertia::render('RetailWarehouseSale/PrintStruk', [
        'sale' => $sale,
        'items' => $items,
        'customer' => [...],
        'warehouse' => [...],
        'division' => [...]
    ]);
}
```

## Dependencies

### Frontend
- **Vue 3** - Framework utama
- **Inertia.js** - SPA routing
- **jsPDF** - PDF generation
- **SweetAlert2** - Notifications

### Backend
- **Laravel** - Framework backend
- **MySQL** - Database

## Browser Compatibility

- Chrome/Chromium (Recommended)
- Firefox
- Safari
- Edge

## Printer Requirements

- **Paper Size**: 80mm roll paper
- **Print Quality**: 300 DPI minimum
- **Connection**: USB/Network
- **Driver**: Thermal printer driver

## Troubleshooting

### Print Tidak Muncul
1. Pastikan printer roll paper terhubung
2. Cek driver printer
3. Set paper size ke 80mm
4. Cek browser print settings

### PDF Error
1. Pastikan jsPDF library ter-load
2. Cek console untuk error
3. Pastikan data sale dan items valid


## Future Enhancements

1. **Print Preview** - Preview sebelum print
2. **Multiple Print** - Print beberapa struk sekaligus
3. **Print History** - Log print activity
4. **Custom Layout** - Template layout yang bisa disesuaikan
5. **Barcode Support** - Tambah barcode item
6. **Multi Language** - Support bahasa lain

## Testing

### Manual Testing
1. Buat penjualan retail warehouse baru
2. Buka detail penjualan
3. Klik tombol "Cetak"
4. Verifikasi layout dan data
5. Test print dan download PDF

### Automated Testing
```javascript
// Test print functionality
describe('Retail Warehouse Sale Print', () => {
  it('should open print page', () => {
    // Test implementation
  });
  
  it('should generate PDF', () => {
    // Test implementation
  });
});
```

## Performance Considerations

- **Lazy Loading**: PDF generation hanya saat dibutuhkan
- **Caching**: Cache sale data untuk performa
- **Memory**: Monitor memory usage untuk PDF generation
- **Network**: Optimize data transfer

## Security

- **Authorization**: Hanya user yang authorized bisa print
- **Data Validation**: Validasi input data
- **XSS Protection**: Sanitize output data
- **CSRF Protection**: Laravel CSRF token

## Maintenance

### Regular Tasks
1. **Update Dependencies**: Update library secara berkala
2. **Test Printers**: Test dengan printer baru
3. **Monitor Performance**: Monitor loading time
4. **Backup Data**: Backup sale data

### Logs
- Print activity logs
- Error logs untuk troubleshooting
- Performance metrics

## Support

Untuk support dan troubleshooting:
1. Cek dokumentasi ini
2. Cek console browser untuk error
3. Cek Laravel logs
4. Hubungi tim development
