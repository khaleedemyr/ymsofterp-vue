# 🔗 Penjelasan Join dari Good Receive (GR) sampai Floor Order

## 📊 Struktur Join Lengkap

### **1. Tabel Utama: `outlet_food_good_receives` (GR)**
```sql
outlet_food_good_receives (gr)
├── id (Primary Key)
├── outlet_id (Foreign Key ke tbl_data_outlet)
├── delivery_order_id (Foreign Key ke delivery_orders)
├── receive_date
└── deleted_at
```

### **2. Alur Join Step by Step:**

#### **Step 1: GR → GR Items**
```sql
FROM outlet_food_good_receives as gr
JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
```
- **Relasi**: One-to-Many (1 GR bisa punya banyak items)
- **Join Key**: `gr.id = i.outlet_food_good_receive_id`
- **Tujuan**: Ambil detail item yang di-receive

#### **Step 2: GR Items → Items**
```sql
JOIN items as it ON i.item_id = it.id
```
- **Relasi**: Many-to-One (banyak GR items → 1 item)
- **Join Key**: `i.item_id = it.id`
- **Tujuan**: Ambil informasi item (nama, kategori, dll)

#### **Step 3: Items → Sub Categories**
```sql
JOIN sub_categories as sc ON it.sub_category_id = sc.id
```
- **Relasi**: Many-to-One (banyak items → 1 sub category)
- **Join Key**: `it.sub_category_id = sc.id`
- **Tujuan**: Ambil nama sub category untuk kategorisasi

#### **Step 4: GR Items → Units**
```sql
JOIN units as u ON i.unit_id = u.id
```
- **Relasi**: Many-to-One (banyak GR items → 1 unit)
- **Join Key**: `i.unit_id = u.id`
- **Tujuan**: Ambil nama unit (kg, pcs, dll)

#### **Step 5: GR → Delivery Orders**
```sql
JOIN delivery_orders as do ON gr.delivery_order_id = do.id
```
- **Relasi**: Many-to-One (banyak GR → 1 delivery order)
- **Join Key**: `gr.delivery_order_id = do.id`
- **Tujuan**: Ambil `floor_order_id` untuk join ke floor order

#### **Step 6: GR Items + Delivery Orders → Floor Order Items** ⭐ **KEY JOIN**
```sql
JOIN food_floor_order_items as fo ON (
    i.item_id = fo.item_id AND 
    fo.floor_order_id = do.floor_order_id
)
```
- **Relasi**: Many-to-One (banyak GR items → 1 floor order item)
- **Join Key**: 
  - `i.item_id = fo.item_id` (item yang sama)
  - `fo.floor_order_id = do.floor_order_id` (floor order yang sama)
- **Tujuan**: Ambil **HARGA** dari floor order (`fo.price`)

#### **Step 7: Items → Warehouse Division (Optional)**
```sql
LEFT JOIN warehouse_division as wd ON it.warehouse_division_id = wd.id
```
- **Relasi**: Many-to-One (banyak items → 1 warehouse division)
- **Join Key**: `it.warehouse_division_id = wd.id`
- **Tujuan**: Ambil warehouse division untuk kategorisasi

#### **Step 8: Warehouse Division → Warehouses (Optional)**
```sql
LEFT JOIN warehouses as w ON wd.warehouse_id = w.id
```
- **Relasi**: Many-to-One (banyak warehouse divisions → 1 warehouse)
- **Join Key**: `wd.warehouse_id = w.id`
- **Tujuan**: Ambil nama warehouse (MK1, MK2, MAIN STORE)

#### **Step 9: GR → Outlet**
```sql
JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet
```
- **Relasi**: Many-to-One (banyak GR → 1 outlet)
- **Join Key**: `gr.outlet_id = o.id_outlet`
- **Tujuan**: Ambil nama outlet

## 🎯 **Tujuan Utama Join: Mendapatkan Harga**

### **Mengapa Perlu Join ke Floor Order?**
- **GR Items** hanya menyimpan **quantity** yang di-receive
- **Harga** disimpan di **Floor Order Items** (`food_floor_order_items.price`)
- **Floor Order** dibuat sebelum delivery, jadi harga sudah ditetapkan

### **Alur Logika:**
1. **Floor Order** dibuat dengan item dan harga
2. **Delivery Order** dibuat berdasarkan Floor Order
3. **Good Receive** dibuat berdasarkan Delivery Order
4. **GR Items** merekam quantity yang benar-benar di-receive
5. **Harga** tetap mengacu ke Floor Order Items

## 🔍 **Join Condition yang Kritis:**

```sql
JOIN food_floor_order_items as fo ON (
    i.item_id = fo.item_id AND           -- Item yang sama
    fo.floor_order_id = do.floor_order_id -- Floor order yang sama
)
```

### **Penjelasan:**
- `i.item_id = fo.item_id`: Memastikan item yang di-receive sama dengan item di floor order
- `fo.floor_order_id = do.floor_order_id`: Memastikan floor order yang sama

## 📋 **Contoh Data Flow:**

```
Floor Order (ID: 100)
├── Floor Order Item: Item A, Price: 10000
└── Floor Order Item: Item B, Price: 15000

Delivery Order (ID: 200, floor_order_id: 100)
├── Mengacu ke Floor Order 100

Good Receive (ID: 300, delivery_order_id: 200)
├── GR Item: Item A, Qty: 5
└── GR Item: Item B, Qty: 3

Result:
├── Item A: 5 × 10000 = 50000
└── Item B: 3 × 15000 = 45000
```

## ⚠️ **Potensi Masalah:**

### **1. Missing Floor Order Items**
- Jika item di GR tidak ada di Floor Order Items
- **Solusi**: Gunakan `LEFT JOIN` dan `COALESCE(fo.price, 0)`

### **2. Multiple Floor Order Items**
- Jika ada duplikasi item di floor order
- **Solusi**: Pastikan constraint unique di database

### **3. Wrong Floor Order Reference**
- Jika `delivery_order.floor_order_id` salah
- **Solusi**: Validasi data integrity

## 🛠️ **Query Alternative dengan LEFT JOIN:**

```sql
LEFT JOIN food_floor_order_items as fo ON (
    i.item_id = fo.item_id AND 
    fo.floor_order_id = do.floor_order_id
)
-- Gunakan COALESCE(fo.price, 0) untuk handle null
```

Ini adalah penjelasan lengkap join dari Good Receive sampai Floor Order! 🚀
