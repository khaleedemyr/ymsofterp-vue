# 📅 Set Default Date Filter ke Hari Ini

## ✅ **BISA! Filter Tanggal Bisa Di-Set ke Hari Ini**

Filter "Dari Tanggal" dan "Sampai Tanggal" bisa di-set ke hari ini. Ada 2 cara:

---

## 🔧 **SOLUSI 1: Set Default di Frontend (Vue Component)**

### **Edit File: `resources/js/Pages/DeliveryOrder/Index.vue`**

**Cari baris sekitar line 287-288:**

```javascript
const dateFrom = ref(props.filters?.dateFrom || '');
const dateTo = ref(props.filters?.dateTo || '');
```

**Ubah menjadi:**

```javascript
// Set default ke hari ini jika tidak ada filter
const today = new Date().toISOString().split('T')[0]; // Format: YYYY-MM-DD
const dateFrom = ref(props.filters?.dateFrom || today);
const dateTo = ref(props.filters?.dateTo || today);
```

**Atau jika ingin range (misalnya: last 7 days atau last 30 days):**

```javascript
// Set default ke hari ini untuk kedua filter
const today = new Date().toISOString().split('T')[0];
const dateFrom = ref(props.filters?.dateFrom || today);
const dateTo = ref(props.filters?.dateTo || today);

// Atau set default ke last 7 days
// const today = new Date();
// const last7Days = new Date(today);
// last7Days.setDate(today.getDate() - 7);
// const dateFrom = ref(props.filters?.dateFrom || last7Days.toISOString().split('T')[0]);
// const dateTo = ref(props.filters?.dateTo || today.toISOString().split('T')[0]);
```

---

## 🔧 **SOLUSI 2: Set Default di Backend (Controller)**

### **Edit File: `app/Http/Controllers/DeliveryOrderController.php`**

**Cari method `index()` sekitar line 46-48:**

```php
$dateFrom = $request->dateFrom ?? $filters['dateFrom'] ?? '';
$dateTo = $request->dateTo ?? $filters['dateTo'] ?? '';
```

**Ubah menjadi:**

```php
// Set default ke hari ini jika tidak ada filter
$today = date('Y-m-d');
$dateFrom = $request->dateFrom ?? $filters['dateFrom'] ?? $today;
$dateTo = $request->dateTo ?? $filters['dateTo'] ?? $today;
```

**Atau jika ingin range (misalnya: last 7 days):**

```php
// Set default ke last 7 days jika tidak ada filter
$today = date('Y-m-d');
$last7Days = date('Y-m-d', strtotime('-7 days'));
$dateFrom = $request->dateFrom ?? $filters['dateFrom'] ?? $last7Days;
$dateTo = $request->dateTo ?? $filters['dateTo'] ?? $today;
```

---

## 🚀 **SOLUSI 3: Set Default di Method `getDeliveryOrdersOptimized()` (RECOMMENDED)**

### **Edit File: `app/Http/Controllers/DeliveryOrderController.php`**

**Cari method `getDeliveryOrdersOptimized()` sekitar line 979-988:**

```php
// Apply date filters
if (!empty($dateFrom)) {
    $query .= " AND DATE(do.created_at) >= ?";
    $bindings[] = $dateFrom;
}

if (!empty($dateTo)) {
    $query .= " AND DATE(do.created_at) <= ?";
    $bindings[] = $dateTo;
}
```

**Ubah menjadi:**

```php
// Apply date filters
if (!empty($dateFrom)) {
    $query .= " AND DATE(do.created_at) >= ?";
    $bindings[] = $dateFrom;
}

if (!empty($dateTo)) {
    $query .= " AND DATE(do.created_at) <= ?";
    $bindings[] = $dateTo;
}

// Default filter: hari ini jika tidak ada filter
if (empty($dateFrom) && empty($dateTo)) {
    $today = date('Y-m-d');
    $query .= " AND DATE(do.created_at) >= ?";
    $bindings[] = $today;
    $query .= " AND DATE(do.created_at) <= ?";
    $bindings[] = $today;
}
```

**Atau jika ingin range (misalnya: last 30 days):**

```php
// Default filter: last 30 days jika tidak ada filter
if (empty($dateFrom) && empty($dateTo)) {
    $today = date('Y-m-d');
    $last30Days = date('Y-m-d', strtotime('-30 days'));
    $query .= " AND DATE(do.created_at) >= ?";
    $bindings[] = $last30Days;
    $query .= " AND DATE(do.created_at) <= ?";
    $bindings[] = $today;
}
```

---

## 📋 **REKOMENDASI**

**Gunakan Solusi 3** karena:
1. ✅ Memastikan query selalu ada filter (tidak scan semua rows)
2. ✅ Lebih aman di backend
3. ✅ User tetap bisa set filter manual
4. ✅ Mencegah query lambat karena tidak ada filter

---

## 🔧 **IMPLEMENTASI LENGKAP (Solusi 3)**

### **Edit Method `getDeliveryOrdersOptimized()`**

```php
private function getDeliveryOrdersOptimized($search, $dateFrom, $dateTo, $perPage)
{
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
    
    // Default filter: hari ini jika tidak ada filter
    if (empty($dateFrom) && empty($dateTo)) {
        $today = date('Y-m-d');
        $query .= " AND DATE(do.created_at) >= ?";
        $bindings[] = $today;
        $query .= " AND DATE(do.created_at) <= ?";
        $bindings[] = $today;
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
        $today = date('Y-m-d');
        $countQuery .= " AND DATE(do.created_at) >= ?";
        $countBindings[] = $today;
        $countQuery .= " AND DATE(do.created_at) <= ?";
        $countBindings[] = $today;
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

Setelah set default ke hari ini:

| Scenario | Sebelum | Sesudah |
|----------|---------|---------|
| **Tanpa filter** | Scan 1.5 juta rows | Scan hanya rows hari ini |
| **Query time** | 1.18 detik | < 0.1 detik |
| **Rows examined** | 1,469,699 | < 1000 |

---

## ⚠️ **CATATAN**

1. **Default ke hari ini** - User tetap bisa set filter manual
2. **Lebih aman** - Query selalu ada filter, tidak scan semua rows
3. **Lebih cepat** - Query hanya scan rows hari ini
4. **User experience** - Data langsung muncul tanpa perlu set filter

---

**Rekomendasi: Gunakan Solusi 3 (set default di method `getDeliveryOrdersOptimized()`)** ✅
