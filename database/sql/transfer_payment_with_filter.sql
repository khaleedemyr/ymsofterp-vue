-- Script untuk transfer data dari DB 1 ke DB 2
-- Transfer: orders, order_payment, dan order_items
-- Filter: tanggal >= 2026-01-01 dan grand_total <= 850000
-- ID dan paid_number akan di-generate sequential

-- ============================================
-- STEP 0: Cek struktur tabel (OPTIONAL)
-- ============================================
-- Jalankan query ini untuk melihat semua kolom di tabel orders
-- DESCRIBE ymsoft.orders;
-- atau
-- SHOW COLUMNS FROM ymsoft.orders;

-- ============================================
-- STEP 1: Cek data yang akan ditransfer (PREVIEW)
-- ============================================
-- Jalankan query ini dulu untuk melihat berapa banyak data yang akan ditransfer

-- Preview Orders
SELECT 
    o.*,
    COUNT(DISTINCT p.id) as payment_count,
    COUNT(DISTINCT oi.id) as item_count
FROM ymsoft.orders o
LEFT JOIN ymsoft.order_payment p ON p.order_id = o.id
LEFT JOIN ymsoft.order_items oi ON oi.order_id = o.id
WHERE o.created_at >= '2026-01-01 00:00:00'
    AND o.grand_total <= 850000
GROUP BY o.id
ORDER BY o.created_at ASC;

-- Preview Payments
SELECT 
    p.*,
    o.grand_total
FROM ymsoft.order_payment p
INNER JOIN ymsoft.orders o ON p.order_id = o.id
WHERE p.created_at >= '2026-01-01 00:00:00'
    AND o.grand_total <= 850000
ORDER BY p.created_at ASC;

-- Preview Order Items
SELECT 
    oi.*,
    o.grand_total
FROM ymsoft.order_items oi
INNER JOIN ymsoft.orders o ON oi.order_id = o.id
WHERE o.created_at >= '2026-01-01 00:00:00'
    AND o.grand_total <= 850000
ORDER BY oi.order_id, oi.id ASC;

-- ============================================
-- STEP 2: Cek berapa banyak data yang akan di-insert (OPTIONAL)
-- ============================================
-- Jalankan query ini untuk melihat berapa banyak orders yang akan di-insert

-- Cek total data yang sesuai filter (tanpa NOT EXISTS check)
SELECT COUNT(*) as total_orders_matching_filter
FROM ymsoft.orders o
WHERE o.created_at >= '2026-01-01 00:00:00'
    AND o.grand_total <= 850000;

-- Cek berapa banyak yang sudah ada di DB 2
SELECT COUNT(*) as orders_already_exists
FROM ymsoft.orders o
INNER JOIN db_thebarnpajak.orders o2 ON o2.id = o.id
WHERE o.created_at >= '2026-01-01 00:00:00'
    AND o.grand_total <= 850000;

-- Cek berapa banyak yang akan di-insert (belum ada di DB 2)
SELECT COUNT(*) as total_orders_to_insert
FROM ymsoft.orders o
WHERE o.created_at >= '2026-01-01 00:00:00'
    AND o.grand_total <= 850000
    AND NOT EXISTS (
        SELECT 1 FROM db_thebarnpajak.orders o2 
        WHERE o2.id = o.id
    );

-- ============================================
-- STEP 3: Transfer Orders terlebih dahulu
-- ============================================
-- Transfer orders yang sesuai filter
-- ID, nomor, dan paid_number akan di-generate baru
-- Buat temporary table untuk mapping ID lama ke ID baru

-- OPSI: Jika ada foreign key constraint error, bisa disable sementara
-- SET FOREIGN_KEY_CHECKS = 0;

-- Buat temporary table untuk mapping
CREATE TEMPORARY TABLE IF NOT EXISTS temp_order_mapping (
    old_id VARCHAR(255) COLLATE utf8mb4_general_ci,
    new_id VARCHAR(255) COLLATE utf8mb4_general_ci,
    new_nomor VARCHAR(255) COLLATE utf8mb4_general_ci,
    INDEX idx_old_id (old_id)
);

SET @order_row_number = 0;
SET @order_date_prefix = DATE_FORMAT(NOW(), '%y%m%d');

-- Insert orders dan simpan mapping
INSERT INTO db_thebarnpajak.orders (
    id,
    nomor,
    table_id,
    paid_number,
    user_id,
    member_id,
    member_name,
    mode,
    pax,
    total,
    discount,
    cashback,
    dpp,
    pb1,
    service,
    grand_total,
    status,
    created_at,
    updated_at,
    joined_tables,
    promo_ids,
    commfee,
    rounding,
    sales_lead,
    redeem_amount,
    issync1,
    ismobile,
    isprint,
    pc_terdekat,
    last_reprint_at,
    printed_at,
    id_investor,
    id_oc,
    id_officer_check,
    issync,
    manual_discount_amount,
    manual_discount_reason,
    `table`,
    waiters,
    kode_outlet,
    voucher_info,
    inactive_promo_items,
    promo_discount_info
)
SELECT 
    -- Generate ID baru: random string atau bisa pakai UUID
    CONCAT('mjv', SUBSTRING(MD5(CONCAT(o.id, NOW(), RAND())), 1, 12)) AS id,
    
    -- Generate nomor baru: TBTEMP + tanggal + nomor urut (4 digit)
    CONCAT('TBTEMP', @order_date_prefix, LPAD(@order_row_number := @order_row_number + 1, 4, '0')) AS nomor,
    
    o.table_id,
    
    -- Generate paid_number baru: TB + tanggal + nomor urut (4 digit)
    CONCAT('TB', @order_date_prefix, LPAD(@order_row_number, 4, '0')) AS paid_number,
    
    o.user_id,
    o.member_id,
    o.member_name,
    o.mode,
    o.pax,
    o.total,
    o.discount,
    o.cashback,
    o.dpp,
    o.pb1,
    o.service,
    o.grand_total,
    o.status,
    o.created_at,
    o.updated_at,
    o.joined_tables,
    o.promo_ids,
    o.commfee,
    o.rounding,
    o.sales_lead,
    o.redeem_amount,
    o.issync1,
    o.ismobile,
    o.isprint,
    o.pc_terdekat,
    o.last_reprint_at,
    o.printed_at,
    o.id_investor,
    o.id_oc,
    o.id_officer_check,
    o.issync,
    o.manual_discount_amount,
    o.manual_discount_reason,
    NULL AS `table`,  -- Kolom ini tidak ada di DB 1, set NULL
    NULL AS waiters,  -- Kolom ini tidak ada di DB 1, set NULL
    NULL AS kode_outlet,  -- Kolom ini tidak ada di DB 1, set NULL (atau bisa ambil dari data lain jika ada)
    o.voucher_info,
    o.inactive_promo_items,
    o.promo_discount_info
FROM ymsoft.orders o
WHERE o.created_at >= '2026-01-01 00:00:00'
    AND o.grand_total <= 850000
    -- Hapus NOT EXISTS check di bawah ini jika ingin insert ulang (force insert)
    -- Karena ID sudah di-generate baru, check berdasarkan created_at + grand_total + user_id
    AND NOT EXISTS (
        SELECT 1 FROM db_thebarnpajak.orders o2 
        WHERE o2.created_at = o.created_at
        AND o2.grand_total = o.grand_total
        AND o2.user_id = o.user_id
        AND ABS(TIMESTAMPDIFF(SECOND, o2.created_at, o.created_at)) <= 1
    )
ORDER BY o.created_at ASC;

-- Simpan mapping ID lama ke ID baru berdasarkan urutan insert
-- Matching berdasarkan created_at, grand_total, user_id, member_id, dan urutan
INSERT INTO temp_order_mapping (old_id, new_id, new_nomor)
SELECT 
    o.id AS old_id,
    o2.id AS new_id,
    o2.nomor AS new_nomor
FROM (
    SELECT 
        o.*,
        @row_num1 := @row_num1 + 1 AS rn
    FROM ymsoft.orders o
    CROSS JOIN (SELECT @row_num1 := 0) AS vars
    WHERE o.created_at >= '2026-01-01 00:00:00'
        AND o.grand_total <= 850000
    ORDER BY o.created_at ASC, o.id ASC
) o
INNER JOIN (
    SELECT 
        o2.*,
        @row_num2 := @row_num2 + 1 AS rn
    FROM db_thebarnpajak.orders o2
    CROSS JOIN (SELECT @row_num2 := 0) AS vars
    WHERE o2.created_at >= '2026-01-01 00:00:00'
        AND o2.grand_total <= 850000
        AND o2.nomor COLLATE utf8mb4_general_ci LIKE CONCAT('TBTEMP', DATE_FORMAT(NOW(), '%y%m%d'), '%') COLLATE utf8mb4_general_ci
    ORDER BY o2.created_at ASC, o2.id ASC
) o2 ON o.rn = o2.rn
    AND ABS(TIMESTAMPDIFF(SECOND, o.created_at, o2.created_at)) <= 2  -- created_at hampir sama (dalam 2 detik)
    AND o2.grand_total = o.grand_total
    AND o2.user_id = o.user_id
    AND (o2.member_id COLLATE utf8mb4_general_ci = o.member_id COLLATE utf8mb4_general_ci 
         OR (o2.member_id IS NULL AND o.member_id IS NULL));

-- CATATAN: Jika tidak ada data yang ter-insert, kemungkinan:
-- 1. Data sudah ada di DB 2 (hapus NOT EXISTS check untuk force insert)
-- 2. Tidak ada data yang sesuai filter (cek dengan query di STEP 2)
-- 3. Pastikan tanggal filter benar (>= '2026-01-01' bukan '2025-01-01')

-- ============================================
-- STEP 4: Cek berapa banyak order_items yang akan di-insert (OPTIONAL)
-- ============================================
-- Jalankan query ini untuk melihat berapa banyak order_items yang akan di-insert

-- Cek total order_items yang sesuai filter (tanpa NOT EXISTS check)
SELECT COUNT(*) as total_order_items_matching_filter
FROM ymsoft.order_items oi
INNER JOIN ymsoft.orders o ON oi.order_id = o.id
WHERE o.created_at >= '2026-01-01 00:00:00'
    AND o.grand_total <= 850000;

-- Cek berapa banyak yang sudah ada di DB 2
SELECT COUNT(*) as order_items_already_exists
FROM ymsoft.order_items oi
INNER JOIN ymsoft.orders o ON oi.order_id = o.id
INNER JOIN db_thebarnpajak.order_items oi2 ON oi2.id = oi.id AND oi2.order_id = oi.order_id
WHERE o.created_at >= '2026-01-01 00:00:00'
    AND o.grand_total <= 850000;

-- Cek berapa banyak yang akan di-insert (belum ada di DB 2)
SELECT COUNT(*) as total_order_items_to_insert
FROM ymsoft.order_items oi
INNER JOIN ymsoft.orders o ON oi.order_id = o.id
WHERE o.created_at >= '2026-01-01 00:00:00'
    AND o.grand_total <= 850000
    AND NOT EXISTS (
        SELECT 1 FROM db_thebarnpajak.order_items oi2 
        WHERE oi2.id = oi.id 
        AND oi2.order_id = oi.order_id
    );

-- Cek apakah orders yang sesuai filter sudah ter-insert di DB 2
SELECT COUNT(*) as orders_in_db2
FROM ymsoft.orders o
INNER JOIN db_thebarnpajak.orders o2 ON o2.id = o.id
WHERE o.created_at >= '2026-01-01 00:00:00'
    AND o.grand_total <= 850000;

-- ============================================
-- STEP 5: Cek mapping table (OPTIONAL - untuk debugging)
-- ============================================
-- Jalankan query ini untuk melihat isi mapping table
-- SELECT * FROM temp_order_mapping;

-- Cek apakah semua order_id di mapping table ada di orders DB 2
-- SELECT COUNT(*) as mapping_count, 
--        (SELECT COUNT(*) FROM db_thebarnpajak.orders o WHERE o.id IN (SELECT new_id FROM temp_order_mapping)) as orders_exists_count
-- FROM temp_order_mapping;

-- ============================================
-- STEP 6: Transfer Order Items
-- ============================================
-- Transfer order_items yang terkait dengan orders yang sudah ditransfer
-- PENTING: Pastikan STEP 3 (Transfer Orders) sudah dijalankan terlebih dahulu!

INSERT INTO db_thebarnpajak.order_items (
    id,
    order_id,
    item_id,
    item_name,
    qty,
    price,
    tally,
    modifiers,
    notes,
    subtotal,
    created_at,
    isprint,
    last_reprint_at,
    printed_at,
    updated_at,
    kode_outlet,
    b1g1_promo_id,
    b1g1_status
)
SELECT 
    -- Generate ID baru untuk order_items
    CONCAT('oitem', SUBSTRING(MD5(CONCAT(oi.id, NOW(), RAND())), 1, 12)) AS id,
    
    -- Update order_id ke ID baru dari orders
    m.new_id AS order_id,
    
    oi.item_id,
    oi.item_name,
    oi.qty,
    oi.price,
    oi.tally,
    oi.modifiers,
    oi.notes,
    oi.subtotal,
    oi.created_at,
    oi.isprint,
    oi.last_reprint_at,
    oi.printed_at,
    oi.updated_at,
    NULL AS kode_outlet,  -- Kolom ini tidak ada di DB 1, set NULL (atau bisa ambil dari orders jika ada)
    oi.b1g1_promo_id,
    oi.b1g1_status
FROM ymsoft.order_items oi
INNER JOIN ymsoft.orders o ON oi.order_id = o.id
INNER JOIN temp_order_mapping m ON m.old_id COLLATE utf8mb4_general_ci = o.id COLLATE utf8mb4_general_ci
WHERE o.created_at >= '2026-01-01 00:00:00'
    AND o.grand_total <= 850000
    -- Pastikan order_id baru ada di orders DB 2 (untuk avoid foreign key error)
    AND EXISTS (
        SELECT 1 FROM db_thebarnpajak.orders o2 
        WHERE o2.id COLLATE utf8mb4_general_ci = m.new_id COLLATE utf8mb4_general_ci
    )
    -- Hapus NOT EXISTS check di bawah ini jika ingin insert ulang (force insert)
    AND NOT EXISTS (
        SELECT 1 FROM db_thebarnpajak.order_items oi2 
        WHERE oi2.order_id COLLATE utf8mb4_general_ci = m.new_id COLLATE utf8mb4_general_ci
    )
ORDER BY oi.order_id, oi.id ASC;

-- CATATAN: Jika tidak ada data yang ter-insert, kemungkinan:
-- 1. Orders belum di-insert di DB 2 (jalankan STEP 3 dulu!)
-- 2. Order_items sudah ada di DB 2 (hapus NOT EXISTS check untuk force insert)
-- 3. Tidak ada order_items yang sesuai filter (cek dengan query di STEP 4)

-- ============================================
-- STEP 7: Cek berapa banyak order_payment yang akan di-insert (OPTIONAL)
-- ============================================
-- Jalankan query ini untuk melihat berapa banyak order_payment yang akan di-insert
SELECT COUNT(*) as total_payments_to_insert
FROM ymsoft.order_payment p
INNER JOIN ymsoft.orders o ON p.order_id = o.id
WHERE p.created_at >= '2026-01-01 00:00:00'
    AND o.grand_total <= 850000
    AND NOT EXISTS (
        SELECT 1 FROM db_thebarnpajak.order_payment p2 
        WHERE p2.order_id COLLATE utf8mb4_general_ci = p.order_id COLLATE utf8mb4_general_ci 
        AND p2.created_at = p.created_at
    );

-- ============================================
-- STEP 8: Transfer Payments dengan ID dan paid_number sequential
-- ============================================
-- Transfer order_payment dengan ID dan paid_number sequential

SET @row_number = 0;
SET @date_prefix = DATE_FORMAT(NOW(), '%y%m%d');

INSERT INTO db_thebarnpajak.order_payment (
    id,
    order_id,
    paid_number,
    payment_type,
    payment_code,
    amount,
    card_first4,
    card_last4,
    approval_code,
    created_at,
    user_id,
    note,
    `change`,
    kode_outlet,
    kasir
)
SELECT 
    -- Generate ID sequential: TBTEMP + tanggal + nomor urut (4 digit)
    CONCAT('TBTEMP', @date_prefix, LPAD(@row_number := @row_number + 1, 4, '0')) AS id,
    
    -- Update order_id ke ID baru dari orders (pakai mapping)
    m.new_id AS order_id,
    
    -- Generate paid_number sequential: TB + tanggal + nomor urut (4 digit)
    CONCAT('TB', @date_prefix, LPAD(@row_number, 4, '0')) AS paid_number,
    
    p.payment_type,
    p.payment_code,
    p.amount,
    p.card_first4,
    p.card_last4,
    p.approval_code,
    p.created_at,
    p.user_id,
    p.note,
    p.`change`,
    NULL AS kode_outlet,  -- Kolom ini tidak ada di DB 1, set NULL
    NULL AS kasir  -- Kolom ini tidak ada di DB 1, set NULL
FROM ymsoft.order_payment p
INNER JOIN ymsoft.orders o ON p.order_id = o.id
INNER JOIN temp_order_mapping m ON m.old_id COLLATE utf8mb4_general_ci = o.id COLLATE utf8mb4_general_ci
WHERE p.created_at >= '2026-01-01 00:00:00'
    AND o.grand_total <= 850000
    -- Pastikan data belum ada di DB 2 (optional, untuk avoid duplicate)
    AND NOT EXISTS (
        SELECT 1 FROM db_thebarnpajak.order_payment p2 
        WHERE p2.order_id COLLATE utf8mb4_general_ci = m.new_id COLLATE utf8mb4_general_ci 
        AND p2.created_at = p.created_at
    )
ORDER BY p.created_at ASC;

-- ============================================
-- STEP 9: ALTERNATIVE - Jika ingin menggunakan tanggal dari created_at (bukan tanggal sekarang)
-- ============================================
-- Script ini akan menggunakan tanggal dari created_at untuk prefix ID dan paid_number

INSERT INTO db_thebarnpajak.order_payment (
    id,
    order_id,
    paid_number,
    payment_type,
    payment_code,
    amount,
    card_first4,
    card_last4,
    approval_code,
    created_at,
    user_id,
    note,
    `change`,
    kode_outlet,
    kasir
)
SELECT 
    -- Generate ID: TBTEMP + tanggal dari created_at + nomor urut per tanggal
    CONCAT('TBTEMP', DATE_FORMAT(p.created_at, '%y%m%d'), 
           LPAD((@row_num := IF(@prev_date = DATE(p.created_at), @row_num + 1, IF(@prev_date := DATE(p.created_at), 1, 1))), 4, '0')) AS id,
    
    -- Update order_id ke ID baru dari orders (pakai mapping)
    m.new_id AS order_id,
    
    -- Generate paid_number: TB + tanggal dari created_at + nomor urut per tanggal
    CONCAT('TB', DATE_FORMAT(p.created_at, '%y%m%d'), 
           LPAD(@row_num, 4, '0')) AS paid_number,
    
    p.payment_type,
    p.payment_code,
    p.amount,
    p.card_first4,
    p.card_last4,
    p.approval_code,
    p.created_at,
    p.user_id,
    p.note,
    p.`change`,
    NULL AS kode_outlet,  -- Kolom ini tidak ada di DB 1, set NULL
    NULL AS kasir  -- Kolom ini tidak ada di DB 1, set NULL
FROM ymsoft.order_payment p
INNER JOIN ymsoft.orders o ON p.order_id = o.id
INNER JOIN temp_order_mapping m ON m.old_id COLLATE utf8mb4_general_ci = o.id COLLATE utf8mb4_general_ci
CROSS JOIN (SELECT @row_num := 0, @prev_date := '') AS vars
WHERE p.created_at >= '2026-01-01 00:00:00'
    AND o.grand_total <= 850000
    AND NOT EXISTS (
        SELECT 1 FROM db_thebarnpajak.order_payment p2 
        WHERE p2.order_id COLLATE utf8mb4_general_ci = m.new_id COLLATE utf8mb4_general_ci 
        AND p2.created_at = p.created_at
    )
ORDER BY p.created_at ASC, p.id ASC;

-- ============================================
-- NOTES:
-- ============================================
-- 1. Database: ymsoft (source) → db_thebarnpajak (target) - SUDAH DI-SET
-- 2. Pastikan nama tabel 'orders', 'order_payment', dan 'order_items' sesuai dengan struktur database
-- 3. Pastikan kolom-kolom di INSERT sesuai dengan struktur tabel di DB 2
-- 4. Pastikan kolom 'order_id' di order_payment dan order_items menghubungkan ke 'id' di orders
-- 5. Urutan transfer: Orders → Order Items → Order Payment (karena ada foreign key dependency)
-- 6. Test dulu dengan SELECT (STEP 1) sebelum INSERT
-- 7. Backup database sebelum eksekusi!
-- 8. Jika ada foreign key constraint, mungkin perlu disable dulu:
--    SET FOREIGN_KEY_CHECKS = 0;
--    -- ... insert statements ...
--    SET FOREIGN_KEY_CHECKS = 1;
-- 9. Temporary table temp_order_mapping akan otomatis terhapus setelah session berakhir
--    Atau bisa hapus manual dengan: DROP TEMPORARY TABLE IF EXISTS temp_order_mapping;

