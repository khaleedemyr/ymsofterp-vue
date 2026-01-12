# Analisa Hasil Slow Query - Masalah yang Ditemukan

## 🔴 Masalah Utama yang Teridentifikasi

### Query Paling Lambat:

1. **`SELECT * FROM 'activity_logs'`** - **166.25 detik** ⚠️⚠️⚠️
   - **Masalah**: Full table scan tanpa WHERE clause
   - **Dampak**: Sangat lambat, consume banyak CPU dan memory
   - **Solusi**: 
     - Tambahkan WHERE clause dengan date range
     - Tambahkan LIMIT
     - Tambahkan index pada kolom yang sering di-query
     - Pertimbangkan archive old logs

2. **`SELECT * FROM 'order_items'`** - **44.67 detik** ⚠️⚠️
   - **Masalah**: Full table scan
   - **Dampak**: Sangat lambat
   - **Solusi**: 
     - Tambahkan WHERE clause (order_id, created_at, dll)
     - Tambahkan LIMIT
     - Tambahkan index pada order_id, item_id

3. **`SELECT * FROM 'outlet_food_inventory_cards'`** - **39.85 detik** ⚠️⚠️
   - **Masalah**: Full table scan
   - **Dampak**: Sangat lambat
   - **Solusi**: 
     - Tambahkan WHERE clause (outlet_id, date, item_id)
     - Tambahkan index pada outlet_id, date, item_id

4. **`SELECT * FROM 'member_apps_notifications'`** - **33.12 detik** ⚠️⚠️
   - **Masalah**: Full table scan
   - **Dampak**: Sangat lambat
   - **Solusi**: 
     - Tambahkan WHERE clause (member_id, read_at, created_at)
     - Tambahkan LIMIT
     - Tambahkan index pada member_id, read_at

5. **`SELECT * FROM 'tbl_checklist_wt_detail'`** - **24.77 detik** ⚠️
   - **Masalah**: Full table scan
   - **Solusi**: Tambahkan WHERE clause dan index

## 📊 Analisa Detail

### Pattern yang Ditemukan:
- **Semua query adalah `SELECT *` tanpa WHERE clause**
- **Semua query melakukan full table scan**
- **Tidak ada LIMIT clause**
- **Execution count = 1** (mungkin query yang dijalankan sekali tapi sangat lambat)

### Dampak ke Server:
- **CPU 100%**: Query-query ini consume banyak CPU untuk scan semua rows
- **Memory tinggi**: Load semua data ke memory
- **I/O tinggi**: Read banyak data dari disk
- **Blocking**: Query yang lama bisa block query lain

## 🛠️ Solusi Prioritas

### PRIORITAS 1: Fix Query yang Paling Lambat

#### 1. activity_logs (166 detik!)
```sql
-- Cek struktur table
SHOW CREATE TABLE activity_logs;

-- Cek jumlah rows
SELECT COUNT(*) FROM activity_logs;

-- Cek index yang ada
SHOW INDEX FROM activity_logs;

-- Tambahkan index pada kolom yang sering di-query
CREATE INDEX idx_activity_logs_created_at ON activity_logs(created_at);
CREATE INDEX idx_activity_logs_user_id ON activity_logs(user_id);
CREATE INDEX idx_activity_logs_log_name ON activity_logs(log_name);

-- Optimasi query: JANGAN SELECT * tanpa WHERE
-- BAD:
SELECT * FROM activity_logs;

-- GOOD:
SELECT * FROM activity_logs 
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
ORDER BY created_at DESC 
LIMIT 100;
```

#### 2. order_items (44 detik)
```sql
-- Cek index
SHOW INDEX FROM order_items;

-- Tambahkan index
CREATE INDEX idx_order_items_order_id ON order_items(order_id);
CREATE INDEX idx_order_items_created_at ON order_items(created_at);
CREATE INDEX idx_order_items_item_id ON order_items(item_id);

-- Optimasi query
-- BAD:
SELECT * FROM order_items;

-- GOOD:
SELECT * FROM order_items 
WHERE order_id = ? 
ORDER BY created_at DESC;

-- Atau dengan date range:
SELECT * FROM order_items 
WHERE created_at >= ? AND created_at <= ?
ORDER BY created_at DESC 
LIMIT 1000;
```

#### 3. outlet_food_inventory_cards (39 detik)
```sql
-- Tambahkan index
CREATE INDEX idx_outlet_food_inventory_cards_outlet_id ON outlet_food_inventory_cards(outlet_id);
CREATE INDEX idx_outlet_food_inventory_cards_date ON outlet_food_inventory_cards(date);
CREATE INDEX idx_outlet_food_inventory_cards_item_id ON outlet_food_inventory_cards(item_id);
CREATE INDEX idx_outlet_food_inventory_cards_outlet_date ON outlet_food_inventory_cards(outlet_id, date);

-- Optimasi query
-- BAD:
SELECT * FROM outlet_food_inventory_cards;

-- GOOD:
SELECT * FROM outlet_food_inventory_cards 
WHERE outlet_id = ? AND date >= ? AND date <= ?
ORDER BY date DESC;
```

#### 4. member_apps_notifications (33 detik)
```sql
-- Tambahkan index
CREATE INDEX idx_member_apps_notifications_member_id ON member_apps_notifications(member_id);
CREATE INDEX idx_member_apps_notifications_read_at ON member_apps_notifications(read_at);
CREATE INDEX idx_member_apps_notifications_created_at ON member_apps_notifications(created_at);
CREATE INDEX idx_member_apps_notifications_member_read ON member_apps_notifications(member_id, read_at);

-- Optimasi query
-- BAD:
SELECT * FROM member_apps_notifications;

-- GOOD:
SELECT * FROM member_apps_notifications 
WHERE member_id = ? AND read_at IS NULL
ORDER BY created_at DESC 
LIMIT 50;
```

## 🔍 Cek Index yang Sudah Ada

Jalankan script ini untuk cek index yang sudah ada:

```sql
-- Cek index untuk semua table yang lambat
SHOW INDEX FROM activity_logs;
SHOW INDEX FROM order_items;
SHOW INDEX FROM outlet_food_inventory_cards;
SHOW INDEX FROM member_apps_notifications;
SHOW INDEX FROM tbl_checklist_wt_detail;
SHOW INDEX FROM tbl_jadwal_kerja_detail;
SHOW INDEX FROM tbl_attendance;
SHOW INDEX FROM food_inventory_cards;
SHOW INDEX FROM orders;
SHOW INDEX FROM notifications;
```

## 📝 Rekomendasi Optimasi

### 1. Laravel Code Optimization

#### activity_logs
```php
// BAD:
ActivityLog::all();

// GOOD:
ActivityLog::where('created_at', '>=', now()->subDays(30))
    ->orderBy('created_at', 'desc')
    ->limit(100)
    ->get();

// Atau dengan pagination:
ActivityLog::where('created_at', '>=', now()->subDays(30))
    ->orderBy('created_at', 'desc')
    ->paginate(50);
```

#### order_items
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

#### member_apps_notifications
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

### 2. Tambahkan Index yang Diperlukan

Buat file SQL untuk menambahkan semua index yang diperlukan:

```sql
-- Index untuk activity_logs
CREATE INDEX IF NOT EXISTS idx_activity_logs_created_at ON activity_logs(created_at);
CREATE INDEX IF NOT EXISTS idx_activity_logs_user_id ON activity_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_activity_logs_log_name ON activity_logs(log_name);

-- Index untuk order_items
CREATE INDEX IF NOT EXISTS idx_order_items_order_id ON order_items(order_id);
CREATE INDEX IF NOT EXISTS idx_order_items_created_at ON order_items(created_at);
CREATE INDEX IF NOT EXISTS idx_order_items_item_id ON order_items(item_id);

-- Index untuk outlet_food_inventory_cards
CREATE INDEX IF NOT EXISTS idx_outlet_food_inventory_cards_outlet_id ON outlet_food_inventory_cards(outlet_id);
CREATE INDEX IF NOT EXISTS idx_outlet_food_inventory_cards_date ON outlet_food_inventory_cards(date);
CREATE INDEX IF NOT EXISTS idx_outlet_food_inventory_cards_outlet_date ON outlet_food_inventory_cards(outlet_id, date);

-- Index untuk member_apps_notifications
CREATE INDEX IF NOT EXISTS idx_member_apps_notifications_member_id ON member_apps_notifications(member_id);
CREATE INDEX IF NOT EXISTS idx_member_apps_notifications_read_at ON member_apps_notifications(read_at);
CREATE INDEX IF NOT EXISTS idx_member_apps_notifications_created_at ON member_apps_notifications(created_at);
CREATE INDEX IF NOT EXISTS idx_member_apps_notifications_member_read ON member_apps_notifications(member_id, read_at);
```

### 3. Archive Old Data

Untuk tabel seperti `activity_logs` yang terus bertambah:
- Archive data yang lebih dari 6 bulan
- Hanya query data recent (30-90 hari terakhir)
- Pertimbangkan partitioning berdasarkan date

## ⚡ Quick Fix (Langkah Cepat)

### Step 1: Cek Index yang Ada
```sql
-- Jalankan untuk semua table yang lambat
SHOW INDEX FROM activity_logs;
SHOW INDEX FROM order_items;
SHOW INDEX FROM outlet_food_inventory_cards;
SHOW INDEX FROM member_apps_notifications;
```

### Step 2: Tambahkan Index yang Diperlukan
```sql
-- Copy dari rekomendasi di atas
-- Jalankan satu per satu untuk monitor progress
```

### Step 3: Cari Source Code yang Menggunakan Query Lambat
```bash
# Cari di Laravel code
grep -r "ActivityLog::all()" app/
grep -r "OrderItem::all()" app/
grep -r "MemberAppsNotification::all()" app/
grep -r "OutletFoodInventoryCard::all()" app/
```

### Step 4: Fix Source Code
- Tambahkan WHERE clause
- Tambahkan LIMIT
- Gunakan pagination
- Gunakan eager loading untuk relationships

## 📊 Expected Results Setelah Optimasi

Setelah optimasi:
- ✅ `activity_logs`: 166 detik → < 1 detik (dengan WHERE + LIMIT)
- ✅ `order_items`: 44 detik → < 0.5 detik (dengan WHERE + index)
- ✅ `outlet_food_inventory_cards`: 39 detik → < 0.5 detik (dengan WHERE + index)
- ✅ `member_apps_notifications`: 33 detik → < 0.5 detik (dengan WHERE + index)
- ✅ CPU usage: 100% → 60-80%
- ✅ Response time: Sangat lambat → Normal (< 500ms)

## 🚨 Action Items

1. **SEGERA**: Cari dan fix query `SELECT * FROM activity_logs` (166 detik!)
2. **PRIORITAS TINGGI**: Fix query `SELECT * FROM order_items` (44 detik)
3. **PRIORITAS TINGGI**: Fix query `SELECT * FROM outlet_food_inventory_cards` (39 detik)
4. **PRIORITAS TINGGI**: Fix query `SELECT * FROM member_apps_notifications` (33 detik)
5. **PRIORITAS SEDANG**: Fix query lainnya yang > 10 detik
6. **PRIORITAS SEDANG**: Tambahkan index yang diperlukan
7. **PRIORITAS RENDAH**: Archive old data untuk table yang besar
