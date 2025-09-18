# Purchase Order Food - GR Number Display Fix

Dokumentasi perbaikan error SQL untuk fitur menampilkan nomor Good Receive Food di halaman index Purchase Order Food.

## Problem

Error SQL terjadi karena nama tabel yang salah dalam query:

```
SQLSTATE[42S02]: Base table or view not found: 1146 Table 'db_justus.purchase_requisition_foods' doesn't exist
```

## Root Cause

Query menggunakan nama tabel yang salah:
- ❌ `purchase_requisition_foods` (tidak ada)
- ❌ `purchase_requisition_food_items` (tidak ada)
- ❌ `poi.po_id` (field yang salah)

## Solution

### 1. **Correct Table Names**

#### **Before (Wrong)**
```php
$prNumbers = DB::table('purchase_requisition_foods as pr')
    ->join('purchase_requisition_food_items as pri', 'pr.id', '=', 'pri.pr_food_id')
    ->join('purchase_order_food_items as poi', 'pri.id', '=', 'poi.pr_food_item_id')
    ->where('poi.po_id', $po->id)
```

#### **After (Correct)**
```php
$prNumbers = DB::table('pr_foods as pr')
    ->join('pr_food_items as pri', 'pr.id', '=', 'pri.pr_food_id')
    ->join('purchase_order_food_items as poi', 'pri.id', '=', 'poi.pr_food_item_id')
    ->where('poi.purchase_order_food_id', $po->id)
```

### 2. **Correct Field Names**

#### **Purchase Order Food Items Table**
- ❌ `po_id` → ✅ `purchase_order_food_id`

#### **Purchase Requisition Tables**
- ❌ `purchase_requisition_foods` → ✅ `pr_foods`
- ❌ `purchase_requisition_food_items` → ✅ `pr_food_items`

### 3. **Updated Query Structure**

#### **PR Foods Query**
```php
$prNumbers = DB::table('pr_foods as pr')
    ->join('pr_food_items as pri', 'pr.id', '=', 'pri.pr_food_id')
    ->join('purchase_order_food_items as poi', 'pri.id', '=', 'poi.pr_food_item_id')
    ->where('poi.purchase_order_food_id', $po->id)
    ->distinct()
    ->pluck('pr.pr_number')
    ->toArray();
```

#### **RO Supplier Query**
```php
$roData = DB::table('food_floor_orders as fo')
    ->join('purchase_order_food_items as poi', 'fo.id', '=', 'poi.ro_id')
    ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
    ->where('poi.purchase_order_food_id', $po->id)
    ->select('fo.order_number', 'o.nama_outlet')
    ->distinct()
    ->get();
```

## Database Schema Reference

### **Correct Table Names**

#### **Purchase Requisition Tables**
- `pr_foods` (Purchase Requisition Foods)
- `pr_food_items` (Purchase Requisition Food Items)

#### **Purchase Order Tables**
- `purchase_order_foods` (Purchase Order Foods)
- `purchase_order_food_items` (Purchase Order Food Items)

#### **Good Receive Tables**
- `food_good_receives` (Food Good Receives)
- `food_good_receive_items` (Food Good Receive Items)

### **Correct Field Names**

#### **purchase_order_food_items**
- `purchase_order_food_id` (Foreign Key ke purchase_order_foods.id)
- `pr_food_item_id` (Foreign Key ke pr_food_items.id)
- `ro_id` (Foreign Key ke food_floor_orders.id)
- `ro_number` (RO Number)

#### **pr_food_items**
- `pr_food_id` (Foreign Key ke pr_foods.id)
- `item_id` (Foreign Key ke items.id)

#### **pr_foods**
- `pr_number` (PR Number)
- `tanggal` (Date)
- `status` (Status)

## Model References

### **PurchaseRequisitionFood Model**
```php
protected $table = 'pr_foods';
```

### **PurchaseRequisitionFoodItem Model**
```php
protected $table = 'pr_food_items';
```

### **PurchaseOrderFoodItem Model**
```php
protected $table = 'purchase_order_food_items';

protected $fillable = [
    'purchase_order_food_id',
    'pr_food_item_id',
    'ro_id',
    'ro_number',
    // ... other fields
];
```

## Testing

### **Manual Testing**
1. **PR Foods Source**: Test PO yang dibuat dari PR Foods
2. **RO Supplier Source**: Test PO yang dibuat dari RO Supplier
3. **Legacy PO**: Test PO tanpa source_type
4. **GR Number Display**: Test tampilan GR Number
5. **Search Functionality**: Test search berdasarkan GR Number

### **Query Testing**
```sql
-- Test PR Foods query
SELECT DISTINCT pr.pr_number
FROM pr_foods pr
JOIN pr_food_items pri ON pr.id = pri.pr_food_id
JOIN purchase_order_food_items poi ON pri.id = poi.pr_food_item_id
WHERE poi.purchase_order_food_id = 1695;

-- Test RO Supplier query
SELECT fo.order_number, o.nama_outlet
FROM food_floor_orders fo
JOIN purchase_order_food_items poi ON fo.id = poi.ro_id
LEFT JOIN tbl_data_outlet o ON fo.id_outlet = o.id_outlet
WHERE poi.purchase_order_food_id = 1695;
```

## Performance Considerations

### **Indexes Needed**
```sql
-- Purchase Order Food Items
ALTER TABLE purchase_order_food_items ADD INDEX idx_purchase_order_food_id (purchase_order_food_id);
ALTER TABLE purchase_order_food_items ADD INDEX idx_pr_food_item_id (pr_food_item_id);
ALTER TABLE purchase_order_food_items ADD INDEX idx_ro_id (ro_id);

-- PR Food Items
ALTER TABLE pr_food_items ADD INDEX idx_pr_food_id (pr_food_id);

-- PR Foods
ALTER TABLE pr_foods ADD INDEX idx_pr_number (pr_number);
```

### **Query Optimization**
- **DISTINCT**: Menggunakan DISTINCT untuk menghindari duplikasi
- **JOIN**: Menggunakan INNER JOIN untuk efisiensi
- **WHERE**: Filter yang tepat untuk mengurangi data yang diproses

## Error Prevention

### **Best Practices**
1. **Always Check Model**: Gunakan model untuk mendapatkan nama tabel yang benar
2. **Verify Field Names**: Pastikan field name sesuai dengan database schema
3. **Test Queries**: Test query sebelum implementasi
4. **Use Eloquent**: Pertimbangkan menggunakan Eloquent untuk relasi yang kompleks

### **Debugging Tips**
1. **Check Laravel Logs**: Lihat error di `storage/logs/laravel.log`
2. **Verify Database**: Cek struktur tabel di database
3. **Test Raw Queries**: Test query langsung di database
4. **Check Model Relations**: Pastikan relasi model sudah benar

## Related Files

### **Models**
- `app/Models/PurchaseRequisitionFood.php`
- `app/Models/PurchaseRequisitionFoodItem.php`
- `app/Models/PurchaseOrderFood.php`
- `app/Models/PurchaseOrderFoodItem.php`

### **Controllers**
- `app/Http/Controllers/PurchaseOrderFoodsController.php`

### **Database Schema**
- `database/sql/add_source_fields_to_po_foods.sql`
- `database/sql/add_ppn_to_purchase_order_foods.sql`

## Future Improvements

1. **Use Eloquent Relations**: Ganti query builder dengan Eloquent relations
2. **Caching**: Implement caching untuk query yang sering digunakan
3. **Error Handling**: Tambahkan error handling yang lebih baik
4. **Logging**: Tambahkan logging untuk debugging
5. **Unit Tests**: Buat unit tests untuk query yang kompleks

## Conclusion

Perbaikan ini menyelesaikan error SQL dengan menggunakan nama tabel dan field yang benar sesuai dengan database schema yang ada. Fitur GR Number Display sekarang dapat berfungsi dengan baik tanpa error.
