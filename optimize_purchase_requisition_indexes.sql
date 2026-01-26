-- =====================================================
-- QUERY ALTER INDEX untuk Optimasi Purchase Requisition
-- =====================================================
-- Jalankan query ini secara manual di database
-- Pastikan backup database terlebih dahulu!
-- =====================================================

-- 1. Index untuk tabel purchase_requisitions
-- Index untuk filter berdasarkan created_by (sangat sering digunakan)
ALTER TABLE `purchase_requisitions` 
ADD INDEX `idx_pr_created_by` (`created_by`);

-- Index untuk filter berdasarkan status
ALTER TABLE `purchase_requisitions` 
ADD INDEX `idx_pr_status` (`status`);

-- Index untuk filter berdasarkan created_at (date range)
ALTER TABLE `purchase_requisitions` 
ADD INDEX `idx_pr_created_at` (`created_at` DESC);

-- Index untuk filter berdasarkan mode
ALTER TABLE `purchase_requisitions` 
ADD INDEX `idx_pr_mode` (`mode`);

-- Composite index untuk query dengan created_by + status + created_at
ALTER TABLE `purchase_requisitions` 
ADD INDEX `idx_pr_user_status_date` (`created_by`, `status`, `created_at` DESC);

-- Index untuk search (pr_number, title)
ALTER TABLE `purchase_requisitions` 
ADD INDEX `idx_pr_number` (`pr_number`);

-- Index untuk division_id dan outlet_id
ALTER TABLE `purchase_requisitions` 
ADD INDEX `idx_pr_division_id` (`division_id`);

ALTER TABLE `purchase_requisitions` 
ADD INDEX `idx_pr_outlet_id` (`outlet_id`);

-- 2. Index untuk tabel purchase_order_ops_items
-- Index untuk source lookup (sangat penting untuk N+1 query fix!)
ALTER TABLE `purchase_order_ops_items` 
ADD INDEX `idx_poi_source` (`source_type`, `source_id`);

-- Index untuk purchase_order_ops_id
ALTER TABLE `purchase_order_ops_items` 
ADD INDEX `idx_poi_po_id` (`purchase_order_ops_id`);

-- 3. Index untuk tabel non_food_payments
-- Index untuk purchase_requisition_id (sangat penting!)
ALTER TABLE `non_food_payments` 
ADD INDEX `idx_nfp_pr_id` (`purchase_requisition_id`);

-- Index untuk purchase_order_ops_id
ALTER TABLE `non_food_payments` 
ADD INDEX `idx_nfp_po_id` (`purchase_order_ops_id`);

-- Index untuk status (untuk filter paid/approved)
ALTER TABLE `non_food_payments` 
ADD INDEX `idx_nfp_status` (`status`);

-- Composite index untuk query payment lookup
ALTER TABLE `non_food_payments` 
ADD INDEX `idx_nfp_pr_status` (`purchase_requisition_id`, `status`);

ALTER TABLE `non_food_payments` 
ADD INDEX `idx_nfp_po_status` (`purchase_order_ops_id`, `status`);

-- 4. Index untuk tabel purchase_requisition_comments
-- Index untuk purchase_requisition_id
ALTER TABLE `purchase_requisition_comments` 
ADD INDEX `idx_prc_pr_id` (`purchase_requisition_id`);

-- Index untuk user_id
ALTER TABLE `purchase_requisition_comments` 
ADD INDEX `idx_prc_user_id` (`user_id`);

-- Index untuk created_at (untuk unread count)
ALTER TABLE `purchase_requisition_comments` 
ADD INDEX `idx_prc_created_at` (`created_at` DESC);

-- Composite index untuk unread comments query
ALTER TABLE `purchase_requisition_comments` 
ADD INDEX `idx_prc_pr_user_created` (`purchase_requisition_id`, `user_id`, `created_at` DESC);

-- 5. Index untuk tabel purchase_requisition_history
-- Index untuk purchase_requisition_id dan user_id (untuk last view)
ALTER TABLE `purchase_requisition_history` 
ADD INDEX `idx_prh_pr_user` (`purchase_requisition_id`, `user_id`);

-- Index untuk action dan created_at
ALTER TABLE `purchase_requisition_history` 
ADD INDEX `idx_prh_action_created` (`action`, `created_at` DESC);

-- 6. Index untuk tabel purchase_order_ops (untuk PurchaseOrderOpsController)
-- Index untuk supplier_id
ALTER TABLE `purchase_order_ops` 
ADD INDEX `idx_po_supplier_id` (`supplier_id`);

-- Index untuk created_by
ALTER TABLE `purchase_order_ops` 
ADD INDEX `idx_po_created_by` (`created_by`);

-- Index untuk source_id
ALTER TABLE `purchase_order_ops` 
ADD INDEX `idx_po_source_id` (`source_id`);

-- Index untuk date (untuk date range filter)
ALTER TABLE `purchase_order_ops` 
ADD INDEX `idx_po_date` (`date`);

-- Index untuk status
ALTER TABLE `purchase_order_ops` 
ADD INDEX `idx_po_status` (`status`);

-- Composite index untuk query dengan status + date
ALTER TABLE `purchase_order_ops` 
ADD INDEX `idx_po_status_date` (`status`, `date` DESC);

-- Index untuk created_at (untuk sorting)
ALTER TABLE `purchase_order_ops` 
ADD INDEX `idx_po_created_at` (`created_at` DESC);

-- =====================================================
-- CATATAN PENTING:
-- =====================================================
-- 1. Pastikan backup database sebelum menjalankan query ini
-- 2. Query ini akan memakan waktu jika data sudah banyak
-- 3. Index akan mempercepat SELECT query tapi sedikit memperlambat INSERT/UPDATE
-- 4. Monitor performa setelah index ditambahkan
-- 5. Jika ada error "Duplicate key name", berarti index sudah ada, skip query tersebut
-- =====================================================

-- Untuk cek index yang sudah ada:
-- SHOW INDEX FROM `purchase_requisitions`;
-- SHOW INDEX FROM `purchase_order_ops_items`;
-- SHOW INDEX FROM `non_food_payments`;
-- SHOW INDEX FROM `purchase_requisition_comments`;
-- SHOW INDEX FROM `purchase_order_ops`;

-- Untuk drop index jika diperlukan (HATI-HATI!):
-- ALTER TABLE `purchase_requisitions` DROP INDEX `idx_pr_created_by`;
