-- ============================================
-- FIX SLOW QUERIES - Tambahkan Index yang Diperlukan
-- ============================================
-- Jalankan script ini untuk menambahkan index pada table yang lambat
-- ============================================

-- ============================================
-- STEP 1: CEK INDEX YANG SUDAH ADA
-- ============================================
-- Cek index untuk table yang lambat
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
SHOW INDEX FROM order_payment;
SHOW INDEX FROM outlet_food_good_receive_items;
SHOW INDEX FROM food_floor_order_items;
SHOW INDEX FROM food_packing_list_items;

-- ============================================
-- STEP 2: TAMBAHKAN INDEX UNTUK activity_logs
-- ============================================
-- Table ini paling lambat (166 detik!)
-- Pastikan ada index pada kolom yang sering di-query

-- Index untuk created_at (sering digunakan untuk filter date)
CREATE INDEX IF NOT EXISTS idx_activity_logs_created_at ON activity_logs(created_at);

-- Index untuk user_id (jika sering filter by user)
CREATE INDEX IF NOT EXISTS idx_activity_logs_user_id ON activity_logs(user_id);

-- Index untuk log_name (jika sering filter by log type)
CREATE INDEX IF NOT EXISTS idx_activity_logs_log_name ON activity_logs(log_name);

-- Composite index untuk query yang filter by user dan date
CREATE INDEX IF NOT EXISTS idx_activity_logs_user_created ON activity_logs(user_id, created_at);

-- ============================================
-- STEP 3: TAMBAHKAN INDEX UNTUK order_items
-- ============================================
-- Table ini sangat lambat (44 detik)

-- Index untuk order_id (sering digunakan untuk join/filter)
CREATE INDEX IF NOT EXISTS idx_order_items_order_id ON order_items(order_id);

-- Index untuk created_at (untuk filter date range)
CREATE INDEX IF NOT EXISTS idx_order_items_created_at ON order_items(created_at);

-- Index untuk item_id (jika sering filter by item)
CREATE INDEX IF NOT EXISTS idx_order_items_item_id ON order_items(item_id);

-- Composite index untuk query yang filter by order dan date
CREATE INDEX IF NOT EXISTS idx_order_items_order_created ON order_items(order_id, created_at);

-- ============================================
-- STEP 4: TAMBAHKAN INDEX UNTUK outlet_food_inventory_cards
-- ============================================
-- Table ini sangat lambat (39 detik)

-- Index untuk outlet_id (sering digunakan untuk filter)
CREATE INDEX IF NOT EXISTS idx_outlet_food_inventory_cards_outlet_id ON outlet_food_inventory_cards(outlet_id);

-- Index untuk date (untuk filter date range)
CREATE INDEX IF NOT EXISTS idx_outlet_food_inventory_cards_date ON outlet_food_inventory_cards(date);

-- Index untuk item_id (jika sering filter by item)
CREATE INDEX IF NOT EXISTS idx_outlet_food_inventory_cards_item_id ON outlet_food_inventory_cards(item_id);

-- Composite index untuk query yang filter by outlet dan date (sangat umum)
CREATE INDEX IF NOT EXISTS idx_outlet_food_inventory_cards_outlet_date ON outlet_food_inventory_cards(outlet_id, date);

-- Composite index untuk query yang filter by outlet, item, dan date
CREATE INDEX IF NOT EXISTS idx_outlet_food_inventory_cards_outlet_item_date ON outlet_food_inventory_cards(outlet_id, item_id, date);

-- ============================================
-- STEP 5: TAMBAHKAN INDEX UNTUK member_apps_notifications
-- ============================================
-- Table ini sangat lambat (33 detik)

-- Index untuk member_id (sering digunakan untuk filter)
CREATE INDEX IF NOT EXISTS idx_member_apps_notifications_member_id ON member_apps_notifications(member_id);

-- Index untuk read_at (untuk filter unread notifications)
CREATE INDEX IF NOT EXISTS idx_member_apps_notifications_read_at ON member_apps_notifications(read_at);

-- Index untuk created_at (untuk sorting)
CREATE INDEX IF NOT EXISTS idx_member_apps_notifications_created_at ON member_apps_notifications(created_at);

-- Composite index untuk query unread notifications (sangat umum)
CREATE INDEX IF NOT EXISTS idx_member_apps_notifications_member_read ON member_apps_notifications(member_id, read_at);

-- Composite index untuk query notifications dengan sorting
CREATE INDEX IF NOT EXISTS idx_member_apps_notifications_member_created ON member_apps_notifications(member_id, created_at);

-- ============================================
-- STEP 6: TAMBAHKAN INDEX UNTUK TABLE LAINNYA
-- ============================================

-- tbl_checklist_wt_detail (24 detik)
CREATE INDEX IF NOT EXISTS idx_tbl_checklist_wt_detail_created_at ON tbl_checklist_wt_detail(created_at);
-- Tambahkan index lain sesuai kebutuhan

-- tbl_jadwal_kerja_detail (21 detik)
CREATE INDEX IF NOT EXISTS idx_tbl_jadwal_kerja_detail_created_at ON tbl_jadwal_kerja_detail(created_at);
-- Tambahkan index lain sesuai kebutuhan

-- tbl_attendance (18 detik)
CREATE INDEX IF NOT EXISTS idx_tbl_attendance_created_at ON tbl_attendance(created_at);
CREATE INDEX IF NOT EXISTS idx_tbl_attendance_user_id ON tbl_attendance(user_id);
CREATE INDEX IF NOT EXISTS idx_tbl_attendance_date ON tbl_attendance(date);
CREATE INDEX IF NOT EXISTS idx_tbl_attendance_user_date ON tbl_attendance(user_id, date);

-- food_inventory_cards (16 detik)
CREATE INDEX IF NOT EXISTS idx_food_inventory_cards_date ON food_inventory_cards(date);
CREATE INDEX IF NOT EXISTS idx_food_inventory_cards_item_id ON food_inventory_cards(item_id);
CREATE INDEX IF NOT EXISTS idx_food_inventory_cards_item_date ON food_inventory_cards(item_id, date);

-- orders (8.78 detik)
CREATE INDEX IF NOT EXISTS idx_orders_created_at ON orders(created_at);
CREATE INDEX IF NOT EXISTS idx_orders_member_id ON orders(member_id);
CREATE INDEX IF NOT EXISTS idx_orders_kode_outlet ON orders(kode_outlet);
CREATE INDEX IF NOT EXISTS idx_orders_outlet_date ON orders(kode_outlet, created_at);

-- notifications (8.32 detik)
CREATE INDEX IF NOT EXISTS idx_notifications_created_at ON notifications(created_at);
CREATE INDEX IF NOT EXISTS idx_notifications_user_id ON notifications(user_id);
CREATE INDEX IF NOT EXISTS idx_notifications_read_at ON notifications(read_at);
CREATE INDEX IF NOT EXISTS idx_notifications_user_read ON notifications(user_id, read_at);

-- order_payment (4.87 detik)
CREATE INDEX IF NOT EXISTS idx_order_payment_created_at ON order_payment(created_at);
CREATE INDEX IF NOT EXISTS idx_order_payment_order_id ON order_payment(order_id);

-- outlet_food_good_receive_items (4.66 detik)
CREATE INDEX IF NOT EXISTS idx_outlet_food_good_receive_items_created_at ON outlet_food_good_receive_items(created_at);
CREATE INDEX IF NOT EXISTS idx_outlet_food_good_receive_items_gr_id ON outlet_food_good_receive_items(good_receive_id);

-- food_floor_order_items (4.38 detik)
CREATE INDEX IF NOT EXISTS idx_food_floor_order_items_created_at ON food_floor_order_items(created_at);
CREATE INDEX IF NOT EXISTS idx_food_floor_order_items_floor_order_id ON food_floor_order_items(floor_order_id);

-- food_packing_list_items (4.10 detik)
CREATE INDEX IF NOT EXISTS idx_food_packing_list_items_created_at ON food_packing_list_items(created_at);
CREATE INDEX IF NOT EXISTS idx_food_packing_list_items_packing_list_id ON food_packing_list_items(packing_list_id);

-- ============================================
-- STEP 7: VERIFIKASI INDEX YANG SUDAH DIBUAT
-- ============================================
-- Cek lagi index yang sudah dibuat
SHOW INDEX FROM activity_logs;
SHOW INDEX FROM order_items;
SHOW INDEX FROM outlet_food_inventory_cards;
SHOW INDEX FROM member_apps_notifications;

-- ============================================
-- CATATAN PENTING:
-- ============================================
-- 1. CREATE INDEX bisa memakan waktu untuk table besar
-- 2. Gunakan ALGORITHM=INPLACE, LOCK=NONE jika MySQL 5.6+
-- 3. Monitor progress dengan SHOW PROCESSLIST
-- 4. Jangan buat index terlalu banyak sekaligus
-- 5. Buat index satu per satu dan monitor hasilnya
-- 6. Setelah index dibuat, test query lagi untuk lihat improvement

-- ============================================
-- CONTOH CREATE INDEX DENGAN ALGORITHM=INPLACE
-- ============================================
-- Untuk table besar, gunakan:
-- CREATE INDEX idx_name ON table_name(column_name) 
-- ALGORITHM=INPLACE, LOCK=NONE;
