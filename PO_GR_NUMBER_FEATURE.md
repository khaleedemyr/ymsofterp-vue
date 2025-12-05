# Purchase Order Food - GR Number Display Feature

Dokumentasi fitur menampilkan nomor Good Receive Food di halaman index Purchase Order Food.

## Overview

Fitur ini menambahkan kolom "GR Number" di halaman index Purchase Order Food yang menampilkan nomor Good Receive Food ketika status PO berubah menjadi "receive". Ini memungkinkan user untuk melihat dengan mudah apakah PO sudah diterima dan nomor GR-nya.

## Fitur Utama

### 1. **GR Number Display**
- Menampilkan nomor GR (Good Receive) di kolom baru
- Hanya muncul ketika PO sudah diterima (status = "receive")
- Format: Badge hijau dengan icon check circle
- Jika belum ada GR, menampilkan "-"

### 2. **Visual Indicator**
- Badge hijau dengan text putih untuk GR yang ada
- Icon check circle untuk indikasi visual
- Text abu-abu untuk PO yang belum diterima

### 3. **Search Integration**
- GR Number dapat dicari melalui search box
- Search berdasarkan nomor PO, nama supplier, atau nomor GR

## Perubahan yang Dibuat

### 1. Backend Changes

#### **Controller** (`app/Http/Controllers/PurchaseOrderFoodsController.php`)
- ✅ **Query Builder**: Menggunakan query builder dengan left join
- ✅ **GR Join**: Join dengan tabel `food_good_receives` untuk mengambil `gr_number`
- ✅ **Search Enhancement**: Menambahkan search berdasarkan `gr_number`
- ✅ **Data Formatting**: Format data untuk kompatibilitas dengan frontend

```php
$query = DB::table('purchase_order_foods as po')
    ->leftJoin('food_good_receives as gr', 'po.id', '=', 'gr.po_id')
    ->leftJoin('suppliers as s', 'po.supplier_id', '=', 's.id')
    ->leftJoin('users as creator', 'po.created_by', '=', 'creator.id')
    ->leftJoin('users as pm', 'po.purchasing_manager_approved_by', '=', 'pm.id')
    ->leftJoin('users as gm', 'po.gm_finance_approved_by', '=', 'gm.id')
    ->select(
        'po.*',
        'gr.gr_number',
        's.name as supplier_name',
        'creator.nama_lengkap as creator_name',
        'pm.nama_lengkap as purchasing_manager_name',
        'gm.nama_lengkap as gm_finance_name'
    )
    ->orderBy('po.created_at', 'desc');
```

#### **Search Enhancement**
```php
if (request('search')) {
    $search = request('search');
    $query->where(function($q) use ($search) {
        $q->where('po.number', 'like', '%' . $search . '%')
          ->orWhere('s.name', 'like', '%' . $search . '%')
          ->orWhere('gr.gr_number', 'like', '%' . $search . '%');
    });
}
```

### 2. Frontend Changes

#### **Index.vue** (`resources/js/Pages/PurchaseOrder/PurchaseOrderFoods.vue`)
- ✅ **New Column**: Menambahkan kolom "GR Number" di tabel
- ✅ **Visual Design**: Badge hijau dengan icon untuk GR yang ada
- ✅ **Responsive Layout**: Kolom baru terintegrasi dengan layout yang ada
- ✅ **Empty State**: Menampilkan "-" untuk PO yang belum diterima

#### **Table Header Update**
```html
<th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">GR Number</th>
```

#### **Table Body Update**
```html
<td class="px-6 py-3">
    <span v-if="po.gr_number" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
        <i class="fas fa-check-circle mr-1"></i>
        {{ po.gr_number }}
    </span>
    <span v-else class="text-gray-400 text-sm">-</span>
</td>
```

## Database Schema

### **Tabel yang Terlibat**

#### **purchase_order_foods**
- `id` (Primary Key)
- `number` (PO Number)
- `status` (draft, approved, rejected, receive, payment)
- `supplier_id`
- `created_by`
- `purchasing_manager_approved_by`
- `gm_finance_approved_by`
- `date`
- `arrival_date`
- `printed_at`

#### **food_good_receives**
- `id` (Primary Key)
- `po_id` (Foreign Key ke purchase_order_foods.id)
- `gr_number` (Generated GR Number)
- `receive_date`
- `received_by`
- `supplier_id`

### **Relasi**
```
purchase_order_foods (1) ←→ (0..1) food_good_receives
```

## UI/UX Features

### **GR Number Column Layout**
```
┌─────────────────────────────────────────────────────────────────────────┐
│ No. PO │ Tanggal │ Source │ Supplier │ GR Number │ Tgl Kedatangan │ ... │
├─────────────────────────────────────────────────────────────────────────┤
│ PO-001 │ 18/9/25 │ PR-001 │ Supplier │ ✓ GR-001  │ 19/9/25        │ ... │
│ PO-002 │ 18/9/25 │ PR-002 │ Supplier │ -         │ -              │ ... │
└─────────────────────────────────────────────────────────────────────────┘
```

### **Visual States**

#### **GR Available (Status: receive)**
- **Badge**: Hijau dengan background `bg-green-100`
- **Text**: Hijau gelap dengan `text-green-800`
- **Icon**: Check circle (`fas fa-check-circle`)
- **Content**: Nomor GR (contoh: "GR-20250918-0001")

#### **GR Not Available (Status: draft/approved/rejected)**
- **Text**: Abu-abu dengan `text-gray-400`
- **Content**: "-"
- **No Icon**: Tidak ada icon

### **Search Functionality**
- **Search Fields**: 
  - PO Number
  - Supplier Name
  - GR Number
- **Real-time**: Search saat user mengetik
- **Case-insensitive**: Search tidak case sensitive

## Technical Implementation

### **Query Logic**
```sql
SELECT 
  po.*,
  gr.gr_number,
  s.name as supplier_name,
  creator.nama_lengkap as creator_name,
  pm.nama_lengkap as purchasing_manager_name,
  gm.nama_lengkap as gm_finance_name
FROM purchase_order_foods po
LEFT JOIN food_good_receives gr ON po.id = gr.po_id
LEFT JOIN suppliers s ON po.supplier_id = s.id
LEFT JOIN users creator ON po.created_by = creator.id
LEFT JOIN users pm ON po.purchasing_manager_approved_by = pm.id
LEFT JOIN users gm ON po.gm_finance_approved_by = gm.id
ORDER BY po.created_at DESC
```

### **Data Transformation**
```php
$purchaseOrders->getCollection()->transform(function ($po) {
    // Convert to object if it's an array
    if (is_array($po)) {
        $po = (object) $po;
    }
    
    // Format supplier data
    $po->supplier = (object) [
        'name' => $po->supplier_name
    ];
    
    // Format creator data
    $po->creator = (object) [
        'nama_lengkap' => $po->creator_name
    ];
    
    // Format purchasing manager data
    $po->purchasing_manager = $po->purchasing_manager_name ? (object) [
        'nama_lengkap' => $po->purchasing_manager_name
    ] : null;
    
    // Format GM finance data
    $po->gm_finance = $po->gm_finance_name ? (object) [
        'nama_lengkap' => $po->gm_finance_name
    ] : null;
    
    return $po;
});
```

## Business Logic

### **GR Number Generation**
- **Format**: `GR-YYYYMMDD-XXXX`
- **Example**: `GR-20250918-0001`
- **Generation**: Otomatis saat Good Receive dibuat
- **Uniqueness**: Unique per tanggal

### **Status Flow**
```
Draft → Approved → Receive → Payment
                ↓
            GR Number Generated
```

### **Display Rules**
1. **GR Number Visible**: Hanya ketika status = "receive"
2. **Search Available**: GR Number dapat dicari kapan saja
3. **Visual Indicator**: Badge hijau untuk GR yang ada
4. **Empty State**: "-" untuk PO yang belum diterima

## Performance Considerations

### **Optimizations**
- **Left Join**: Menggunakan LEFT JOIN untuk tidak mempengaruhi PO tanpa GR
- **Indexed Queries**: Database indexes untuk efficient querying
- **Selective Fields**: Hanya select field yang diperlukan
- **Pagination**: Support pagination untuk data yang besar

### **Query Performance**
- **Indexes Needed**:
  - `purchase_order_foods.id` (Primary Key)
  - `food_good_receives.po_id` (Foreign Key)
  - `purchase_order_foods.status`
  - `purchase_order_foods.created_at`

## Browser Compatibility

- **Chrome/Chromium**: Full support
- **Firefox**: Full support
- **Safari**: Full support
- **Edge**: Full support

## Testing

### **Manual Testing**
1. **GR Display**: Test tampilan GR Number untuk PO dengan status "receive"
2. **Empty State**: Test tampilan "-" untuk PO tanpa GR
3. **Search**: Test search berdasarkan GR Number
4. **Visual Design**: Test badge hijau dan icon
5. **Responsive**: Test di berbagai ukuran layar
6. **Pagination**: Test dengan data yang banyak

### **Edge Cases**
- PO dengan multiple GR (jika ada)
- GR Number dengan format yang berbeda
- Search dengan GR Number yang tidak ada
- PO dengan status yang tidak valid

## Future Enhancements

1. **Clickable GR**: Link ke detail Good Receive
2. **GR Status**: Tampilkan status GR (completed, partial, etc.)
3. **GR Date**: Tampilkan tanggal GR
4. **Multiple GR**: Support untuk multiple GR per PO
5. **GR History**: Riwayat GR untuk PO yang sama
6. **Export**: Include GR Number dalam export
7. **Filter**: Filter berdasarkan status GR

## Security

- **Input Validation**: Validate semua search parameters
- **SQL Injection**: Parameterized queries
- **XSS Protection**: Escape output data
- **CSRF Protection**: Laravel CSRF token

## Maintenance

### **Regular Tasks**
1. **Monitor Performance**: Cek query execution time
2. **Update Indexes**: Optimize database indexes jika perlu
3. **Test Compatibility**: Test dengan browser baru
4. **User Feedback**: Collect feedback untuk improvement

### **Logs**
- GR Number display activity
- Search performance metrics
- Error logs untuk troubleshooting
- User interaction logs

## Troubleshooting

### **Common Issues**
1. **GR Number Tidak Muncul**: Cek relasi PO dengan GR
2. **Search Tidak Berfungsi**: Cek parameter search
3. **Performance Lambat**: Cek database indexes
4. **Visual Issues**: Cek CSS classes

### **Debug Tips**
- Check browser network tab untuk API calls
- Verify GR data di database
- Test dengan data sample yang berbeda
- Check console untuk JavaScript errors
- Verify query execution time

## Related Features

- **Good Receive Management**: Fitur untuk mengelola GR
- **Purchase Order Workflow**: Workflow PO dari draft ke payment
- **Inventory Management**: Update stok saat GR dibuat
- **Reporting**: Laporan PO dan GR
- **Notification**: Notifikasi saat PO diterima
