# 🚨 Fix Delivery Order Controller - Slow Query

## 📍 **LOKASI MASALAH**

**File:** `app/Http/Controllers/DeliveryOrderController.php`
**Method:** `getDeliveryOrdersOptimized()` (line 929)
**Masalah:** Query COUNT dengan subquery kompleks, scan 1.5 juta rows

---

## ⚠️ **MASALAH YANG DITEMUKAN**

### **1. Query COUNT dengan Subquery (Line 993)**
```php
$countQuery = "SELECT COUNT(*) as total FROM ($query) as count_query";
```

**Masalah:**
- Query memiliki `WHERE 1=1` (selalu true)
- Jika tidak ada filter, akan scan semua rows (1.5 juta)
- COUNT dengan subquery kompleks sangat lambat

### **2. Tidak Ada Default Filter**

Query tidak memiliki default filter, jadi:
- Jika user tidak set filter → scan semua rows
- Sangat lambat untuk table besar

---

## ✅ **SOLUSI OPTIMASI**

### **A. Optimasi COUNT Query**

**Sebelum (LAMBAT):**
```php
$countQuery = "SELECT COUNT(*) as total FROM ($query) as count_query";
$total = DB::select($countQuery, $bindings)[0]->total;
```

**Sesudah (LEBIH CEPAT):**
```php
// Langsung COUNT tanpa subquery
$countQuery = str_replace(
    "SELECT \n                do.id,\n                do.number,\n                do.created_at,\n                DATE_FORMAT(do.created_at, '%d/%m/%Y') as created_date,\n                DATE_FORMAT(do.created_at, '%H:%i:%s') as created_time,\n                do.packing_list_id,\n                do.ro_supplier_gr_id,\n                u.nama_lengkap as created_by_name,\n                COALESCE(pl.packing_number, gr.gr_number) as packing_number,\n                fo.order_number as floor_order_number,\n                o.nama_outlet,\n                wo.name as warehouse_outlet_name,\n                CONCAT(COALESCE(w.name, ''), CASE WHEN w.name IS NOT NULL AND wd.name IS NOT NULL THEN ' - ' ELSE '' END, COALESCE(wd.name, '')) as warehouse_info",
    "COUNT(*) as total",
    $query
);
$countQuery = preg_replace('/\s+ORDER BY.*$/i', '', $countQuery);
$total = DB::select($countQuery, $bindings)[0]->total;
```

**Atau lebih baik, buat query COUNT terpisah:**

```php
// Buat query COUNT yang lebih sederhana
$countQuery = "
    SELECT COUNT(*) as total
    FROM delivery_orders do
    LEFT JOIN users u ON do.created_by = u.id
    LEFT JOIN food_packing_lists pl ON do.packing_list_id = pl.id
    LEFT JOIN food_good_receives gr ON do.ro_supplier_gr_id = gr.id
    LEFT JOIN purchase_order_foods po ON gr.po_id = po.id
    LEFT JOIN food_floor_orders fo ON (
        (do.packing_list_id IS NOT NULL AND pl.food_floor_order_id = fo.id) OR
        (do.ro_supplier_gr_id IS NOT NULL AND po.source_id = fo.id)
    )
    LEFT JOIN tbl_data_outlet o ON fo.id_outlet = o.id_outlet
    LEFT JOIN warehouse_outlets wo ON fo.warehouse_outlet_id = wo.id
    LEFT JOIN warehouse_division wd ON pl.warehouse_division_id = wd.id
    LEFT JOIN warehouses w ON wd.warehouse_id = w.id
    WHERE 1=1
";

// Apply same filters
if (!empty($search)) {
    $countQuery .= " AND (
        COALESCE(pl.packing_number, gr.gr_number) LIKE ? OR
        fo.order_number LIKE ? OR
        u.nama_lengkap LIKE ? OR
        o.nama_outlet LIKE ? OR
        wo.name LIKE ? OR
        do.number LIKE ?
    )";
    $searchTerm = '%' . $search . '%';
    $countBindings = array_merge($countBindings, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($dateFrom)) {
    $countQuery .= " AND DATE(do.created_at) >= ?";
    $countBindings[] = $dateFrom;
}

if (!empty($dateTo)) {
    $countQuery .= " AND DATE(do.created_at) <= ?";
    $countBindings[] = $dateTo;
}

$total = DB::select($countQuery, $countBindings)[0]->total;
```

### **B. Tambah Default Filter (PENTING!)**

**Masalah:** Query tidak memiliki default filter, jadi scan semua rows.

**Solusi:** Tambah default filter (misalnya: last 30 days atau last 1000 records):

```php
// Tambah default date filter jika tidak ada filter
if (empty($dateFrom) && empty($dateTo)) {
    // Default: last 30 days
    $dateFrom = date('Y-m-d', strtotime('-30 days'));
    $query .= " AND DATE(do.created_at) >= ?";
    $bindings[] = $dateFrom;
}
```

**Atau lebih baik, wajibkan filter:**

```php
// Wajibkan filter minimal dateFrom atau dateTo
if (empty($dateFrom) && empty($dateTo) && empty($search)) {
    // Jika tidak ada filter sama sekali, return empty
    return $this->getEmptyPagination();
}
```

### **C. Optimasi Query Structure**

**Hapus subquery yang tidak perlu untuk COUNT:**

```php
// Sebelum (LAMBAT)
$countQuery = "SELECT COUNT(*) as total FROM ($query) as count_query";

// Sesudah (LEBIH CEPAT)
// Langsung COUNT tanpa subquery
$countQuery = "SELECT COUNT(*) as total FROM delivery_orders do ... WHERE ...";
```

---

## 🔧 **IMPLEMENTASI LENGKAP**

### **Edit Method `getDeliveryOrdersOptimized()`**

```php
private function getDeliveryOrdersOptimized($search, $dateFrom, $dateTo, $perPage)
{
    // Wajibkan filter minimal
    if (empty($dateFrom) && empty($dateTo) && empty($search)) {
        // Jika tidak ada filter sama sekali, return empty dengan warning
        Log::warning('Delivery Order query called without any filter');
        return $this->getEmptyPagination();
    }
    
    // Query untuk data
    $query = "
        SELECT 
            do.id,
            do.number,
            do.created_at,
            DATE_FORMAT(do.created_at, '%d/%m/%Y') as created_date,
            DATE_FORMAT(do.created_at, '%H:%i:%s') as created_time,
            do.packing_list_id,
            do.ro_supplier_gr_id,
            u.nama_lengkap as created_by_name,
            COALESCE(pl.packing_number, gr.gr_number) as packing_number,
            fo.order_number as floor_order_number,
            o.nama_outlet,
            wo.name as warehouse_outlet_name,
            CONCAT(COALESCE(w.name, ''), CASE WHEN w.name IS NOT NULL AND wd.name IS NOT NULL THEN ' - ' ELSE '' END, COALESCE(wd.name, '')) as warehouse_info
        FROM delivery_orders do
        LEFT JOIN users u ON do.created_by = u.id
        LEFT JOIN food_packing_lists pl ON do.packing_list_id = pl.id
        LEFT JOIN food_good_receives gr ON do.ro_supplier_gr_id = gr.id
        LEFT JOIN purchase_order_foods po ON gr.po_id = po.id
        LEFT JOIN food_floor_orders fo ON (
            (do.packing_list_id IS NOT NULL AND pl.food_floor_order_id = fo.id) OR
            (do.ro_supplier_gr_id IS NOT NULL AND po.source_id = fo.id)
        )
        LEFT JOIN tbl_data_outlet o ON fo.id_outlet = o.id_outlet
        LEFT JOIN warehouse_outlets wo ON fo.warehouse_outlet_id = wo.id
        LEFT JOIN warehouse_division wd ON pl.warehouse_division_id = wd.id
        LEFT JOIN warehouses w ON wd.warehouse_id = w.id
        WHERE 1=1
    ";
    
    $bindings = [];
    
    // Apply search filter
    if (!empty($search)) {
        $query .= " AND (
            COALESCE(pl.packing_number, gr.gr_number) LIKE ? OR
            fo.order_number LIKE ? OR
            u.nama_lengkap LIKE ? OR
            o.nama_outlet LIKE ? OR
            wo.name LIKE ? OR
            do.number LIKE ?
        )";
        $searchTerm = '%' . $search . '%';
        $bindings = array_merge($bindings, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    // Apply date filters
    if (!empty($dateFrom)) {
        $query .= " AND DATE(do.created_at) >= ?";
        $bindings[] = $dateFrom;
    }
    
    if (!empty($dateTo)) {
        $query .= " AND DATE(do.created_at) <= ?";
        $bindings[] = $dateTo;
    }
    
    // Default filter: last 30 days jika tidak ada filter
    if (empty($dateFrom) && empty($dateTo)) {
        $query .= " AND DATE(do.created_at) >= ?";
        $bindings[] = date('Y-m-d', strtotime('-30 days'));
    }
    
    // OPTIMIZED: Query COUNT terpisah (lebih cepat)
    $countQuery = "
        SELECT COUNT(*) as total
        FROM delivery_orders do
        LEFT JOIN users u ON do.created_by = u.id
        LEFT JOIN food_packing_lists pl ON do.packing_list_id = pl.id
        LEFT JOIN food_good_receives gr ON do.ro_supplier_gr_id = gr.id
        LEFT JOIN purchase_order_foods po ON gr.po_id = po.id
        LEFT JOIN food_floor_orders fo ON (
            (do.packing_list_id IS NOT NULL AND pl.food_floor_order_id = fo.id) OR
            (do.ro_supplier_gr_id IS NOT NULL AND po.source_id = fo.id)
        )
        LEFT JOIN tbl_data_outlet o ON fo.id_outlet = o.id_outlet
        LEFT JOIN warehouse_outlets wo ON fo.warehouse_outlet_id = wo.id
        LEFT JOIN warehouse_division wd ON pl.warehouse_division_id = wd.id
        LEFT JOIN warehouses w ON wd.warehouse_id = w.id
        WHERE 1=1
    ";
    
    $countBindings = [];
    
    // Apply same filters untuk COUNT
    if (!empty($search)) {
        $countQuery .= " AND (
            COALESCE(pl.packing_number, gr.gr_number) LIKE ? OR
            fo.order_number LIKE ? OR
            u.nama_lengkap LIKE ? OR
            o.nama_outlet LIKE ? OR
            wo.name LIKE ? OR
            do.number LIKE ?
        )";
        $searchTerm = '%' . $search . '%';
        $countBindings = array_merge($countBindings, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    if (!empty($dateFrom)) {
        $countQuery .= " AND DATE(do.created_at) >= ?";
        $countBindings[] = $dateFrom;
    }
    
    if (!empty($dateTo)) {
        $countQuery .= " AND DATE(do.created_at) <= ?";
        $countBindings[] = $dateTo;
    }
    
    // Default filter untuk COUNT juga
    if (empty($dateFrom) && empty($dateTo)) {
        $countQuery .= " AND DATE(do.created_at) >= ?";
        $countBindings[] = date('Y-m-d', strtotime('-30 days'));
    }
    
    // Get total count
    $total = DB::select($countQuery, $countBindings)[0]->total;
    
    // Apply pagination untuk data query
    $query .= " ORDER BY do.created_at DESC";
    $offset = (request('page', 1) - 1) * $perPage;
    $query .= " LIMIT $perPage OFFSET $offset";
    
    $results = DB::select($query, $bindings);
    
    // Convert to pagination format
    $currentPage = request('page', 1);
    $lastPage = ceil($total / $perPage);
    
    return [
        'data' => $results,
        'current_page' => (int)$currentPage,
        'last_page' => $lastPage,
        'per_page' => $perPage,
        'total' => $total,
        'from' => $total > 0 ? (($currentPage - 1) * $perPage) + 1 : null,
        'to' => min($currentPage * $perPage, $total),
        'links' => []
    ];
}
```

---

## 📊 **EXPECTED RESULTS**

Setelah optimasi:

| Metric | Sebelum | Sesudah |
|--------|---------|---------|
| **Rows_examined** | 1,469,699 | < 10,000 (dengan filter) |
| **Query_time** | 1.18 detik | < 0.1 detik |
| **COUNT query** | Subquery kompleks | Query sederhana |

---

## ⚠️ **CATATAN PENTING**

1. **Wajibkan filter** - Jangan biarkan query tanpa filter
2. **Default filter** - Tambah default (misalnya: last 30 days)
3. **COUNT query terpisah** - Lebih cepat daripada COUNT dengan subquery
4. **Test setelah optimasi** - Pastikan query lebih cepat
5. **Monitor slow query log** - Verifikasi tidak ada lagi di slow log

---

**Masalah utama: Query tidak ada default filter, jadi scan semua rows!** ⚠️

**Solusi: Tambah default filter (last 30 days) atau wajibkan filter!** ✅
