# Prioritas Fix Slow Queries - Action Plan

## 🔴 MASALAH KRITIS YANG DITEMUKAN

Berdasarkan hasil slow query analysis, ditemukan query-query yang **SANGAT LAMBAT**:

### Top 5 Query Paling Lambat:

1. **`SELECT * FROM 'activity_logs'`** - **166.25 detik** ⚠️⚠️⚠️ **KRITIS!**
2. **`SELECT * FROM 'order_items'`** - **44.67 detik** ⚠️⚠️ **SANGAT PENTING!**
3. **`SELECT * FROM 'outlet_food_inventory_cards'`** - **39.85 detik** ⚠️⚠️ **SANGAT PENTING!**
4. **`SELECT * FROM 'member_apps_notifications'`** - **33.12 detik** ⚠️⚠️ **SANGAT PENTING!**
5. **`SELECT * FROM 'tbl_checklist_wt_detail'`** - **24.77 detik** ⚠️ **PENTING!**

## 🎯 Root Cause Analysis

### Masalah Utama:
- **Semua query adalah `SELECT *` tanpa WHERE clause**
- **Full table scan** - scan semua rows di table
- **Tidak ada LIMIT** - load semua data ke memory
- **Tidak ada index** yang tepat untuk query ini
- **Execution count = 1** - mungkin query yang dijalankan sekali tapi sangat lambat

### Dampak ke Server:
- **CPU 100%**: Query scan semua rows consume banyak CPU
- **Memory tinggi**: Load semua data ke memory
- **I/O tinggi**: Read banyak data dari disk
- **Blocking**: Query yang lama block query lain
- **Response time sangat lambat**: User experience buruk

## 🚀 ACTION PLAN - Prioritas

### PRIORITAS 1: Fix activity_logs (166 detik!) - SEGERA!

#### Step 1: Cek Table Structure
```sql
-- Cek jumlah rows
SELECT COUNT(*) as total_rows FROM activity_logs;

-- Cek ukuran table
SELECT 
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
FROM information_schema.TABLES
WHERE table_schema = DATABASE()
  AND table_name = 'activity_logs';

-- Cek index yang ada
SHOW INDEX FROM activity_logs;
```

#### Step 2: Tambahkan Index
```sql
-- Index untuk created_at (sering digunakan untuk filter date)
CREATE INDEX idx_activity_logs_created_at ON activity_logs(created_at);

-- Index untuk user_id (jika sering filter by user)
CREATE INDEX idx_activity_logs_user_id ON activity_logs(user_id);

-- Composite index untuk query yang filter by user dan date
CREATE INDEX idx_activity_logs_user_created ON activity_logs(user_id, created_at);
```

#### Step 3: Cari Source Code yang Menggunakan Query Ini
```bash
# Cari di Laravel code
grep -r "activity_logs" app/ --include="*.php"
grep -r "ActivityLog" app/ --include="*.php"
```

#### Step 4: Fix Source Code
```php
// BAD (Full table scan):
ActivityLog::all();
DB::table('activity_logs')->get();

// GOOD (Dengan WHERE + LIMIT):
ActivityLog::where('created_at', '>=', now()->subDays(30))
    ->orderBy('created_at', 'desc')
    ->limit(100)
    ->get();

// Atau dengan pagination:
ActivityLog::where('created_at', '>=', now()->subDays(30))
    ->orderBy('created_at', 'desc')
    ->paginate(50);
```

### PRIORITAS 2: Fix order_items (44 detik)

#### Step 1: Tambahkan Index
```sql
CREATE INDEX idx_order_items_order_id ON order_items(order_id);
CREATE INDEX idx_order_items_created_at ON order_items(created_at);
CREATE INDEX idx_order_items_order_created ON order_items(order_id, created_at);
```

#### Step 2: Fix Source Code
```php
// BAD:
OrderItem::all();

// GOOD:
OrderItem::where('order_id', $orderId)->get();

// Atau dengan date range:
OrderItem::whereBetween('created_at', [$dateFrom, $dateTo])
    ->orderBy('created_at', 'desc')
    ->limit(1000)
    ->get();
```

### PRIORITAS 3: Fix outlet_food_inventory_cards (39 detik)

#### Step 1: Tambahkan Index
```sql
CREATE INDEX idx_outlet_food_inventory_cards_outlet_id ON outlet_food_inventory_cards(outlet_id);
CREATE INDEX idx_outlet_food_inventory_cards_date ON outlet_food_inventory_cards(date);
CREATE INDEX idx_outlet_food_inventory_cards_outlet_date ON outlet_food_inventory_cards(outlet_id, date);
```

#### Step 2: Fix Source Code
```php
// BAD:
OutletFoodInventoryCard::all();

// GOOD:
OutletFoodInventoryCard::where('outlet_id', $outletId)
    ->whereBetween('date', [$dateFrom, $dateTo])
    ->orderBy('date', 'desc')
    ->get();
```

### PRIORITAS 4: Fix member_apps_notifications (33 detik)

#### Step 1: Tambahkan Index
```sql
CREATE INDEX idx_member_apps_notifications_member_id ON member_apps_notifications(member_id);
CREATE INDEX idx_member_apps_notifications_read_at ON member_apps_notifications(read_at);
CREATE INDEX idx_member_apps_notifications_member_read ON member_apps_notifications(member_id, read_at);
```

#### Step 2: Fix Source Code
```php
// BAD:
MemberAppsNotification::all();

// GOOD:
MemberAppsNotification::where('member_id', $memberId)
    ->whereNull('read_at')
    ->orderBy('created_at', 'desc')
    ->limit(50)
    ->get();
```

## 📋 Checklist Implementasi

### Immediate Actions (Hari Ini):
- [ ] **SEGERA**: Tambahkan index untuk `activity_logs` (166 detik!)
- [ ] **SEGERA**: Cari dan fix query `SELECT * FROM activity_logs` di source code
- [ ] **PRIORITAS TINGGI**: Tambahkan index untuk `order_items`
- [ ] **PRIORITAS TINGGI**: Tambahkan index untuk `outlet_food_inventory_cards`
- [ ] **PRIORITAS TINGGI**: Tambahkan index untuk `member_apps_notifications`

### Short Term (Minggu Ini):
- [ ] Fix semua query `SELECT *` tanpa WHERE clause
- [ ] Tambahkan LIMIT pada semua query yang tidak perlu semua data
- [ ] Tambahkan pagination untuk list queries
- [ ] Monitor slow query log setelah fix

### Long Term (Bulan Ini):
- [ ] Setup log rotation untuk slow query log
- [ ] Archive old data untuk table yang besar (activity_logs, dll)
- [ ] Setup monitoring untuk slow queries
- [ ] Review dan optimasi semua query yang > 1 detik

## 🔍 Cara Cari Source Code yang Menggunakan Query Lambat

### 1. Cari dengan grep
```bash
# Cari activity_logs
grep -r "activity_logs" app/ --include="*.php"
grep -r "ActivityLog" app/ --include="*.php"

# Cari order_items
grep -r "order_items" app/ --include="*.php"
grep -r "OrderItem" app/ --include="*.php"

# Cari member_apps_notifications
grep -r "member_apps_notifications" app/ --include="*.php"
grep -r "MemberAppsNotification" app/ --include="*.php"
```

### 2. Cari Pattern Query yang Tidak Efisien
```bash
# Cari ->all() tanpa where
grep -r "->all()" app/ --include="*.php"

# Cari ->get() tanpa where
grep -r "->get()" app/ --include="*.php" | grep -v "where"

# Cari DB::table()->get() tanpa where
grep -r "DB::table.*->get()" app/ --include="*.php"
```

## 📊 Expected Results Setelah Fix

Setelah optimasi:
- ✅ `activity_logs`: 166 detik → **< 1 detik** (dengan WHERE + LIMIT + index)
- ✅ `order_items`: 44 detik → **< 0.5 detik** (dengan WHERE + index)
- ✅ `outlet_food_inventory_cards`: 39 detik → **< 0.5 detik** (dengan WHERE + index)
- ✅ `member_apps_notifications`: 33 detik → **< 0.5 detik** (dengan WHERE + index)
- ✅ **CPU usage**: 100% → **60-80%**
- ✅ **Response time**: Sangat lambat → **Normal (< 500ms)**
- ✅ **User experience**: Sangat lambat → **Cepat dan responsif**

## ⚡ Quick Fix Script

Jalankan script `FIX_SLOW_QUERIES.sql` untuk menambahkan semua index yang diperlukan:

```sql
-- Jalankan file FIX_SLOW_QUERIES.sql
source FIX_SLOW_QUERIES.sql;
```

Atau copy-paste query dari file tersebut dan jalankan satu per satu.

## 🚨 Warning

**JANGAN** langsung buat semua index sekaligus untuk table besar! 
- Buat index satu per satu
- Monitor progress dengan `SHOW PROCESSLIST`
- Jika index creation stuck, gunakan `ALGORITHM=INPLACE, LOCK=NONE`
