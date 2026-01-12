-- ============================================
-- QUICK FIX: Tambahkan Index untuk Slow Queries
-- ============================================
-- Jalankan script ini untuk menambahkan index yang diperlukan
-- ============================================

-- ============================================
-- PRIORITAS 1: activity_logs (166 detik!)
-- ============================================
-- Cek index yang ada
SHOW INDEX FROM activity_logs;

-- Tambahkan index (jalankan satu per satu, monitor progress)
CREATE INDEX IF NOT EXISTS idx_activity_logs_created_at ON activity_logs(created_at);
CREATE INDEX IF NOT EXISTS idx_activity_logs_user_id ON activity_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_activity_logs_log_name ON activity_logs(log_name);
CREATE INDEX IF NOT EXISTS idx_activity_logs_user_created ON activity_logs(user_id, created_at);

-- ============================================
-- PRIORITAS 2: order_items (44 detik)
-- ============================================
SHOW INDEX FROM order_items;

CREATE INDEX IF NOT EXISTS idx_order_items_order_id ON order_items(order_id);
CREATE INDEX IF NOT EXISTS idx_order_items_created_at ON order_items(created_at);
CREATE INDEX IF NOT EXISTS idx_order_items_item_id ON order_items(item_id);
CREATE INDEX IF NOT EXISTS idx_order_items_order_created ON order_items(order_id, created_at);

-- ============================================
-- PRIORITAS 3: outlet_food_inventory_cards (39 detik)
-- ============================================
SHOW INDEX FROM outlet_food_inventory_cards;

CREATE INDEX IF NOT EXISTS idx_outlet_food_inventory_cards_outlet_id ON outlet_food_inventory_cards(outlet_id);
CREATE INDEX IF NOT EXISTS idx_outlet_food_inventory_cards_date ON outlet_food_inventory_cards(date);
CREATE INDEX IF NOT EXISTS idx_outlet_food_inventory_cards_item_id ON outlet_food_inventory_cards(item_id);
CREATE INDEX IF NOT EXISTS idx_outlet_food_inventory_cards_outlet_date ON outlet_food_inventory_cards(outlet_id, date);

-- ============================================
-- PRIORITAS 4: member_apps_notifications (33 detik)
-- ============================================
SHOW INDEX FROM member_apps_notifications;

CREATE INDEX IF NOT EXISTS idx_member_apps_notifications_member_id ON member_apps_notifications(member_id);
CREATE INDEX IF NOT EXISTS idx_member_apps_notifications_read_at ON member_apps_notifications(read_at);
CREATE INDEX IF NOT EXISTS idx_member_apps_notifications_created_at ON member_apps_notifications(created_at);
CREATE INDEX IF NOT EXISTS idx_member_apps_notifications_member_read ON member_apps_notifications(member_id, read_at);
CREATE INDEX IF NOT EXISTS idx_member_apps_notifications_member_created ON member_apps_notifications(member_id, created_at);

-- ============================================
-- PRIORITAS 5: Table lainnya yang lambat
-- ============================================

-- tbl_checklist_wt_detail (24 detik)
CREATE INDEX IF NOT EXISTS idx_tbl_checklist_wt_detail_created_at ON tbl_checklist_wt_detail(created_at);

-- tbl_jadwal_kerja_detail (21 detik)
CREATE INDEX IF NOT EXISTS idx_tbl_jadwal_kerja_detail_created_at ON tbl_jadwal_kerja_detail(created_at);

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

-- ============================================
-- CATATAN PENTING:
-- ============================================
-- 1. CREATE INDEX bisa memakan waktu untuk table besar
-- 2. Monitor progress dengan: SHOW PROCESSLIST;
-- 3. Jika stuck, gunakan: CREATE INDEX ... ALGORITHM=INPLACE, LOCK=NONE;
-- 4. Jangan buat semua index sekaligus untuk table besar
-- 5. Buat index satu per satu dan monitor hasilnya
