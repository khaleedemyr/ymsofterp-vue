# Laporan DO (Delivery Order) yang Belum di GR (Good Receive)

## Deskripsi
Fitur ini menampilkan laporan Delivery Order (DO) yang sudah dibuat tetapi belum diterima (Good Receive) oleh outlet. Membantu memantau DO yang pending dan belum diproses outlet.

## Struktur Implementasi

### 1. Database Tables
- **delivery_orders**: Menyimpan data DO yang dibuat dari packing list
  - Relationship dengan outlet melalui food_floor_orders
  - Memiliki delivery_order_items untuk detail item
  
- **outlet_food_good_receives**: Menyimpan data GR dari outlet
  - Linked ke delivery_orders via delivery_order_id
  - Jika delivery_order_id tidak ada di tabel ini = DO belum di GR
  - Soft delete via deleted_at timestamp

- **food_floor_orders**: Menyimpan floor order yang terhubung ke outlet
- **food_packing_lists**: Menyimpan packing list yang terhubung ke warehouse division
- **tbl_data_outlet**: Master outlet data
- **warehouse_outlets**: Warehouse outlet yang terhubung ke floor order

### 2. Controller Method
File: `app/Http/Controllers/FoodGoodReceiveReportController.php`

#### Method: `deliveryOrdersNotReceived(Request $request)`
- Query DO yang belum di GR menggunakan LEFT JOIN
- Filter:
  - Date range (from_date, to_date)
  - Outlet filter (outlet_id)
  - Warehouse outlet filter (warehouse_outlet_id)
  - Minimum hari belum GR (min_days)
  - Search (do.number, outlet, warehouse_outlet)
- Pagination: default 20 items per page

#### Method: `exportDeliveryOrdersNotReceived(Request $request)`
- Export ke Excel dengan filter yang sama
- Menggunakan `DeliveryOrdersNotReceivedExport` class

### 3. Export Class
File: `app/Exports/DeliveryOrdersNotReceivedExport.php`
- Implements: FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, Responsable
- Column:
  1. DO Number
  2. DO Date
  3. Outlet Name
  4. Warehouse Outlet
  5. Division
  6. Days Not Received
  7. Source Type
  8. Created By

### 4. Routes
File: `routes/web.php`

```
Route::get('/delivery-orders-not-received', [FoodGoodReceiveReportController::class, 'deliveryOrdersNotReceived'])
  ->name('delivery-orders-not-received.report');
  
Route::get('/delivery-orders-not-received/export', [FoodGoodReceiveReportController::class, 'exportDeliveryOrdersNotReceived'])
  ->name('delivery-orders-not-received.export');
```

### 5. Vue Component
File: `resources/js/Pages/FoodGoodReceive/DeliveryOrdersNotReceived.vue`

**Features:**
- Filter panel dengan:
  - Date range picker
  - Outlet selector (dropdown)
  - Warehouse outlet selector (dropdown)
  - Minimum days not received (input number)
  - Search input
  - Per page selector
  
- Summary cards menampilkan:
  - Total DO Not Received
  - Min Days Pending
  - Max Days Pending
  - Average Days Pending
  
- Results table dengan columns:
  - DO Number (blue text, clickable)
  - DO Date (formatted)
  - Outlet Name
  - Division Name
  - Days Not Received (color-coded badge: red ≥14 days, orange 7-13 days, yellow <7 days)
  - Warehouse Outlet
  - Source Type
  - Created By
  
- Pagination links

## Query Logic

### Main Query (deliveryOrdersNotReceived)
```sql
SELECT 
  do.id,
  do.number as do_number,
  do.created_at as do_date,
  o.id_outlet as outlet_id,
  o.nama_outlet as outlet_name,
  wo.name as warehouse_outlet_name,
  COALESCE(wd.name, 'Perishable') as division_name,
  DATEDIFF(NOW(), DATE(do.created_at)) as days_not_received,
  do.source_type
FROM delivery_orders as do
LEFT JOIN outlet_food_good_receives as gr 
  ON gr.delivery_order_id = do.id AND gr.deleted_at IS NULL
LEFT JOIN food_floor_orders as fo 
  ON do.floor_order_id = fo.id
LEFT JOIN food_packing_lists as pl 
  ON do.packing_list_id = pl.id
LEFT JOIN tbl_data_outlet as o 
  ON fo.id_outlet = o.id_outlet
LEFT JOIN warehouse_outlets as wo 
  ON fo.warehouse_outlet_id = wo.id
LEFT JOIN warehouse_division as wd 
  ON pl.warehouse_division_id = wd.id
WHERE gr.id IS NULL  -- DO yang belum di GR
ORDER BY do.created_at ASC
```

### Key Points:
1. **gr.id IS NULL** - Memastikan DO belum memiliki Good Receive
2. **gr.deleted_at IS NULL** - Exclude soft deleted GR (jika ada)
3. **DATEDIFF(NOW(), DATE(do.created_at))** - Hitung berapa hari sejak DO dibuat
4. **COALESCE(wd.name, 'Perishable')** - Default division jika tidak ada

## Fitur Filter

### 1. Date Range Filter
- Nama parameter: `from_date`, `to_date`
- Format: YYYY-MM-DD
- Memfilter berdasarkan tanggal pembuatan DO (do.created_at)

### 2. Outlet Filter
- Nama parameter: `outlet_id`
- Menampilkan dropdown semua outlet aktif
- Filter berdasarkan outlet yang menerima DO

### 3. Warehouse Outlet Filter
- Nama parameter: `warehouse_outlet_id`
- Menampilkan dropdown semua warehouse outlet
- Filter berdasarkan warehouse outlet dari floor order

### 4. Minimum Days Not Received
- Nama parameter: `min_days`
- Input number
- Hanya menampilkan DO yang belum GR selama >= X hari
- Contoh: min_days=7 akan menampilkan DO yg belum GR minimal 7 hari (urgent)

### 5. Search
- Nama parameter: `search`
- Search di: do.number, o.nama_outlet, wo.name
- Partial match (like %search%)

## Usage

### Akses Laporan
```
http://localhost:8000/delivery-orders-not-received
```

### Export Excel
```
http://localhost:8000/delivery-orders-not-received/export?from_date=2024-02-01&to_date=2024-02-19&outlet_id=1
```

### Filter URL Example
```
/delivery-orders-not-received?from_date=2024-02-01&to_date=2024-02-19&outlet_id=1&min_days=7&per_page=50
```

## Color Coding (Days Not Received Badge)
- **Red** (bg-red-100, text-red-800): >= 14 hari - URGENT
- **Orange** (bg-orange-100, text-orange-800): 7-13 hari - WARNING  
- **Yellow** (bg-yellow-100, text-yellow-800): < 7 hari - INFO

## Performance Considerations

1. **Index Recommendations**:
   ```sql
   -- Create indexes untuk performa query
   ALTER TABLE delivery_orders ADD INDEX idx_created_at (created_at);
   ALTER TABLE outlet_food_good_receives ADD INDEX idx_delivery_order_id (delivery_order_id);
   ALTER TABLE outlet_food_good_receives ADD INDEX idx_deleted_at (deleted_at);
   ALTER TABLE food_floor_orders ADD INDEX idx_id_outlet (id_outlet);
   ```

2. **Pagination**: Default 20 items per page, bisa diubah sampai 200
3. **Query Optimization**: Menggunakan single query dengan LEFT JOIN, tidak ada N+1 problem

## Testing Checklist

- [ ] Laporan menampilkan DO yang belum GR
- [ ] Filter by date range bekerja
- [ ] Filter by outlet bekerja
- [ ] Filter by warehouse outlet bekerja
- [ ] Filter by min_days bekerja
- [ ] Search by DO number bekerja
- [ ] Search by outlet name bekerja
- [ ] Summary cards menampilkan data benar
- [ ] Pagination bekerja
- [ ] Export Excel terunduh dengan format benar
- [ ] Days not received dihitung dengan benar
- [ ] Badge colors sesuai dengan hari pending
- [ ] Responsive design di mobile/tablet

## Future Enhancements

1. Add quick action buttons (e.g., "Create GR" button)
2. Add DO details view modal
3. Add bulk action (e.g., mark as received)
4. Add notification alert untuk DO > X hari
5. Add chart/graph untuk DO pending trend
6. Add email notification ke outlet untuk pending DO
7. Add DO status tracking (pending, received, cancelled)

## Troubleshooting

### Laporan tidak menampilkan data
1. Pastikan ada delivery orders di database
2. Pastikan DO tersebut belum memiliki good receive di outlet_food_good_receives
3. Check apakah deleted_at bernilai NULL (tidak soft deleted)

### Filter tidak bekerja
1. Clear browser cache
2. Pastikan parameter query dikirim dengan benar
3. Check Laravel logs di storage/logs/laravel.log

### Export Excel error
1. Pastikan maatwebsite/excel package ter-install
2. Pastikan folder storage/app/public writable
3. Check apakah DeliveryOrdersNotReceivedExport.php file exists
