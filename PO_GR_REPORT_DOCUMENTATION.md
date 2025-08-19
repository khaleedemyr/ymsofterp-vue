# Report Item PO yang Sudah di-GR

## Deskripsi
Fitur ini menampilkan report item Purchase Order (PO) yang sudah di-Good Receive (GR) dengan informasi perubahan harga dari PO sebelumnya.

## Fitur Utama

### 1. Informasi yang Ditampilkan
- **GR Number**: Nomor Good Receive
- **Receive Date**: Tanggal penerimaan barang
- **PO Number**: Nomor Purchase Order (dengan link ke detail PO)
- **PO Date**: Tanggal Purchase Order
- **Supplier**: Nama supplier
- **Item**: Nama item yang diterima
- **Qty PO**: Jumlah yang dipesan di PO
- **Qty Received**: Jumlah yang diterima
- **Unit**: Satuan barang
- **PO Price**: Harga di PO saat ini
- **Previous Price**: Harga di PO sebelumnya (jika ada)
- **Price Change**: Perubahan harga (selisih dan persentase)
- **PO Creator**: Nama user yang membuat PO
- **Received By**: Nama user yang menerima barang

### 2. Filter yang Tersedia
- **Search**: Pencarian berdasarkan PO Number, GR Number, Supplier, atau Item
- **Date Range**: Filter berdasarkan tanggal penerimaan barang
- **Supplier**: Filter berdasarkan supplier (vue-multiselect dengan search)
- **Item**: Filter berdasarkan item (vue-multiselect dengan search)
- **Per Page**: Jumlah data per halaman (15, 25, 50, 100)

### 3. Fitur Tambahan
- **Export CSV**: Download data dalam format CSV
- **Pagination**: Navigasi halaman data
- **Full Width Table**: Tabel menggunakan lebar penuh layar
- **Vue Multiselect**: Filter supplier dan item dengan search yang mudah
- **Responsive Design**: Tampilan yang responsif untuk berbagai ukuran layar

### 4. Export Data
- Export ke format CSV
- Data yang diexport sesuai dengan filter yang diterapkan
- File nama: `po_gr_report_YYYY-MM-DD_HH-MM-SS.csv`

## Cara Mengakses

### 1. Melalui Menu
1. Login ke aplikasi
2. Buka menu **Warehouse Management**
3. Klik **Report PO GR**

### 2. Melalui URL
```
/po-report
```

## Struktur Database

### Tabel yang Digunakan
- `food_good_receives` - Data Good Receive
- `food_good_receive_items` - Item yang diterima
- `purchase_order_foods` - Data Purchase Order
- `purchase_order_food_items` - Item di Purchase Order
- `items` - Master data item
- `units` - Master data satuan
- `suppliers` - Master data supplier
- `users` - Data user yang menerima

### Query Utama
```sql
SELECT 
    gr.id as gr_id,
    gr.gr_number,
    gr.receive_date,
    po.id as po_id,
    po.number as po_number,
    po.date as po_date,
    po.supplier_id,
    s.name as supplier_name,
    i.id as item_id,
    i.name as item_name,
    gri.qty_received,
    u.name as unit_name,
    poi.price as po_price,
    received_by.nama_lengkap as received_by_name
FROM food_good_receives gr
JOIN purchase_order_foods po ON gr.po_id = po.id
JOIN food_good_receive_items gri ON gr.id = gri.good_receive_id
JOIN purchase_order_food_items poi ON gri.po_item_id = poi.id
JOIN items i ON gri.item_id = i.id
JOIN units u ON gri.unit_id = u.id
JOIN suppliers s ON po.supplier_id = s.id
LEFT JOIN users received_by ON gr.received_by = received_by.id
```

## Logika Perubahan Harga

### Cara Menghitung Previous Price
1. Mencari PO item dengan item_id yang sama
2. Mencari PO dengan supplier_id yang sama
3. Mencari PO dengan tanggal yang lebih lama dari PO saat ini
4. Mengambil harga dari PO terbaru yang memenuhi kriteria

### Rumus Perhitungan
```php
$price_change = $current_price - $previous_price;
$price_change_percentage = (($current_price - $previous_price) / $previous_price) * 100;
```

## File yang Dibuat/Dimodifikasi

### 1. Controller
- `app/Http/Controllers/PurchaseOrderReportController.php` (Baru)

### 2. View
- `resources/js/Pages/PurchaseOrder/Report.vue` (Baru)

### 3. Routes
- `routes/web.php` - Menambahkan route untuk report

### 4. Menu
- `resources/js/Layouts/AppLayout.vue` - Menambahkan menu Report PO GR

### 5. Database
- `database/sql/insert_po_report_menu.sql` - SQL untuk menu dan permission

## Permission yang Dibutuhkan

### Menu Permission
- `po_report` - Akses ke menu Report PO GR

### Action Permission
- `po_report_view` - Melihat report
- `po_report_export` - Export data

## Cara Setup

### 1. Jalankan SQL untuk Menu dan Permission
```bash
# Pilih salah satu file SQL berikut:

# File sederhana
mysql -u username -p database_name < database/sql/insert_po_report_menu_simple.sql

# File lengkap dengan verifikasi
mysql -u username -p database_name < database/sql/insert_po_report_menu_complete.sql
```

### 2. Pastikan Route Terdaftar
Route sudah ditambahkan di `routes/web.php`:
```php
Route::get('/po-report', [\App\Http\Controllers\PurchaseOrderReportController::class, 'index'])->name('po-report.index');
Route::get('/po-report/export', [\App\Http\Controllers\PurchaseOrderReportController::class, 'export'])->name('po-report.export');
```

### 3. Verifikasi Menu
Menu "Report PO GR" akan muncul di sidebar di bawah menu "Purchase Order Foods" dalam grup "Warehouse Management" (parent_id = 6).

## Troubleshooting

### 1. Menu tidak muncul
- Pastikan SQL menu sudah dijalankan
- Pastikan user memiliki permission `po_report`
- Cek apakah ada error di console browser

### 2. Data tidak muncul
- Pastikan ada data Good Receive yang sudah dibuat
- Cek apakah relasi antara PO dan GR sudah benar
- Cek log error di storage/logs

### 3. Export tidak berfungsi
- Pastikan folder storage memiliki permission write
- Cek apakah ada error di log Laravel
- Pastikan memory limit cukup untuk export data besar

### 4. Perubahan harga tidak akurat
- Pastikan data PO sebelumnya sudah benar
- Cek apakah supplier_id dan item_id sudah sesuai
- Verifikasi tanggal PO untuk urutan yang benar

## Contoh Output

### Tabel Report
| GR Number | Receive Date | PO Number | Supplier | Item | Qty | Unit | PO Price | Previous Price | Price Change | Received By |
|-----------|--------------|-----------|----------|------|-----|------|----------|----------------|--------------|-------------|
| GR-20241201-0001 | 01/12/2024 | PO-2024-001 | Supplier A | Ayam | 100 | kg | Rp 25,000 | Rp 23,000 | +Rp 2,000 (+8.7%) | John Doe |
| GR-20241201-0002 | 01/12/2024 | PO-2024-002 | Supplier B | Beras | 50 | kg | Rp 12,000 | - | - | Jane Smith |

### File CSV Export
```csv
GR Number,Receive Date,PO Number,PO Date,Supplier,Item,Qty Received,Unit,PO Price,Previous Price,Price Change,Price Change %,Received By
GR-20241201-0001,2024-12-01,PO-2024-001,2024-11-30,Supplier A,Ayam,100,kg,25000.00,23000.00,2000.00,8.70%,John Doe
GR-20241201-0002,2024-12-01,PO-2024-002,2024-11-30,Supplier B,Beras,50,kg,12000.00,-,-,-
```

## Pengembangan Selanjutnya

### Fitur yang Bisa Ditambahkan
1. **Chart/Graph**: Visualisasi perubahan harga
2. **Email Report**: Kirim report otomatis via email
3. **Scheduled Export**: Export otomatis berdasarkan jadwal
4. **Comparison Report**: Bandingkan harga antar periode
5. **Supplier Analysis**: Analisis performa supplier berdasarkan perubahan harga
6. **Item Trend**: Trend harga per item dalam periode tertentu
