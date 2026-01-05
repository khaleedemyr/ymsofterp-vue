-- =====================================================
-- SCRIPT UNTUK MENAMBAHKAN INDEX UNTUK OPTIMASI PERFORMANCE
-- Fitur: Category Cost Outlet, WIP Production, Stock Cut
-- =====================================================
-- 
-- CARA MENGGUNAKAN:
-- 1. Backup database dulu!
-- 2. Jalankan script ini di MySQL/MariaDB
-- 3. Monitor query performance setelah index ditambahkan
--
-- WARNING: Index akan memperlambat INSERT/UPDATE sedikit
-- Tapi sangat mempercepat SELECT (yang lebih penting untuk aplikasi ini)
-- =====================================================

-- =====================================================
-- 1. INDEX UNTUK outlet_food_inventory_cards
-- =====================================================
-- Tabel ini adalah tabel yang paling sering di-query
-- dan kemungkinan besar tidak punya index yang tepat

-- Index untuk query berdasarkan outlet + warehouse + tanggal
-- Digunakan oleh: OutletStockReportController
ALTER TABLE `outlet_food_inventory_cards` 
ADD INDEX `idx_outlet_warehouse_date` (`id_outlet`, `warehouse_outlet_id`, `date`);

-- Index untuk query berdasarkan reference_type + reference_id
-- Digunakan oleh: Semua controller yang query berdasarkan reference
ALTER TABLE `outlet_food_inventory_cards` 
ADD INDEX `idx_reference` (`reference_type`, `reference_id`);

-- Index untuk query berdasarkan outlet + warehouse + reference_type + tanggal
-- Digunakan oleh: OutletStockReportController (WIP, Internal Use Waste, dll)
ALTER TABLE `outlet_food_inventory_cards` 
ADD INDEX `idx_outlet_warehouse_ref_date` (`id_outlet`, `warehouse_outlet_id`, `reference_type`, `date`);

-- Index untuk inventory_item_id (sering di-join)
-- Digunakan oleh: Semua query yang join dengan outlet_food_inventory_items
ALTER TABLE `outlet_food_inventory_cards` 
ADD INDEX `idx_inventory_item` (`inventory_item_id`);

-- Composite index untuk query stock report (optimized)
-- Menggabungkan semua kolom yang sering digunakan bersama
ALTER TABLE `outlet_food_inventory_cards` 
ADD INDEX `idx_stock_report` (`id_outlet`, `warehouse_outlet_id`, `reference_type`, `date`, `inventory_item_id`);

-- =====================================================
-- 2. INDEX UNTUK outlet_wip_production_headers
-- =====================================================
-- Digunakan oleh: OutletWIPController, OutletStockReportController

-- Index untuk query berdasarkan status + production_date
ALTER TABLE `outlet_wip_production_headers` 
ADD INDEX `idx_status_date` (`status`, `production_date`);

-- Index untuk query berdasarkan outlet + production_date
ALTER TABLE `outlet_wip_production_headers` 
ADD INDEX `idx_outlet_date` (`outlet_id`, `production_date`);

-- Index untuk query berdasarkan outlet + status
ALTER TABLE `outlet_wip_production_headers` 
ADD INDEX `idx_outlet_status` (`outlet_id`, `status`);

-- =====================================================
-- 3. INDEX UNTUK outlet_wip_productions
-- =====================================================
-- Digunakan oleh: OutletWIPController

-- Index untuk query berdasarkan header_id
ALTER TABLE `outlet_wip_productions` 
ADD INDEX `idx_header_id` (`header_id`);

-- Index untuk query berdasarkan outlet + production_date
ALTER TABLE `outlet_wip_productions` 
ADD INDEX `idx_outlet_date` (`outlet_id`, `production_date`);

-- Index untuk query berdasarkan item_id
ALTER TABLE `outlet_wip_productions` 
ADD INDEX `idx_item_id` (`item_id`);

-- =====================================================
-- 4. INDEX UNTUK outlet_internal_use_waste_headers
-- =====================================================
-- Digunakan oleh: OutletInternalUseWasteController, OutletStockReportController

-- Index untuk query berdasarkan status + type + date
ALTER TABLE `outlet_internal_use_waste_headers` 
ADD INDEX `idx_status_type_date` (`status`, `type`, `date`);

-- Index untuk query berdasarkan outlet + date
ALTER TABLE `outlet_internal_use_waste_headers` 
ADD INDEX `idx_outlet_date` (`outlet_id`, `date`);

-- Index untuk query berdasarkan outlet + status + type
ALTER TABLE `outlet_internal_use_waste_headers` 
ADD INDEX `idx_outlet_status_type` (`outlet_id`, `status`, `type`);

-- =====================================================
-- 5. INDEX UNTUK stock_cut_logs
-- =====================================================
-- Digunakan oleh: StockCutController

-- Index untuk query berdasarkan outlet + tanggal + status
ALTER TABLE `stock_cut_logs` 
ADD INDEX `idx_outlet_tanggal_status` (`outlet_id`, `tanggal`, `status`);

-- Index untuk query berdasarkan outlet + tanggal
ALTER TABLE `stock_cut_logs` 
ADD INDEX `idx_outlet_tanggal` (`outlet_id`, `tanggal`);

-- Index untuk query berdasarkan type_filter
ALTER TABLE `stock_cut_logs` 
ADD INDEX `idx_type_filter` (`type_filter`);

-- =====================================================
-- 6. INDEX UNTUK stock_cut_details
-- =====================================================
-- Digunakan oleh: StockCutController, OutletStockReportController

-- Index untuk query berdasarkan stock_cut_log_id + item_id + warehouse
ALTER TABLE `stock_cut_details` 
ADD INDEX `idx_log_item_warehouse` (`stock_cut_log_id`, `item_id`, `warehouse_outlet_id`);

-- Index untuk query berdasarkan item_id
ALTER TABLE `stock_cut_details` 
ADD INDEX `idx_item_id` (`item_id`);

-- Index untuk query berdasarkan stock_cut_log_id
ALTER TABLE `stock_cut_details` 
ADD INDEX `idx_stock_cut_log_id` (`stock_cut_log_id`);

-- =====================================================
-- 7. INDEX UNTUK order_items
-- =====================================================
-- Digunakan oleh: StockCutController

-- Index untuk query berdasarkan kode_outlet + created_at + stock_cut
-- Sangat penting untuk query stock cut
ALTER TABLE `order_items` 
ADD INDEX `idx_outlet_date_stockcut` (`kode_outlet`, `created_at`, `stock_cut`);

-- Index untuk query berdasarkan item_id + created_at
ALTER TABLE `order_items` 
ADD INDEX `idx_item_date` (`item_id`, `created_at`);

-- Index untuk query berdasarkan kode_outlet + stock_cut
ALTER TABLE `order_items` 
ADD INDEX `idx_outlet_stockcut` (`kode_outlet`, `stock_cut`);

-- =====================================================
-- 8. INDEX UNTUK outlet_food_inventory_stocks
-- =====================================================
-- Digunakan oleh: Semua controller yang check/update stock

-- Index untuk query berdasarkan outlet + warehouse + inventory_item
-- Sangat penting untuk query stock
ALTER TABLE `outlet_food_inventory_stocks` 
ADD INDEX `idx_outlet_warehouse_item` (`id_outlet`, `warehouse_outlet_id`, `inventory_item_id`);

-- Index untuk query berdasarkan inventory_item_id
ALTER TABLE `outlet_food_inventory_stocks` 
ADD INDEX `idx_inventory_item` (`inventory_item_id`);

-- Index untuk query berdasarkan outlet + warehouse
ALTER TABLE `outlet_food_inventory_stocks` 
ADD INDEX `idx_outlet_warehouse` (`id_outlet`, `warehouse_outlet_id`);

-- =====================================================
-- 9. INDEX UNTUK outlet_food_inventory_items
-- =====================================================
-- Digunakan oleh: Semua controller (sering di-join)

-- Index untuk query berdasarkan item_id
ALTER TABLE `outlet_food_inventory_items` 
ADD INDEX `idx_item_id` (`item_id`);

-- =====================================================
-- 10. INDEX UNTUK item_boms
-- =====================================================
-- Digunakan oleh: StockCutController (untuk kalkulasi BOM)

-- Index untuk query berdasarkan item_id
ALTER TABLE `item_boms` 
ADD INDEX `idx_item_id` (`item_id`);

-- Index untuk query berdasarkan material_item_id
ALTER TABLE `item_boms` 
ADD INDEX `idx_material_item_id` (`material_item_id`);

-- =====================================================
-- 11. INDEX UNTUK outlet_stock_opnames
-- =====================================================
-- Digunakan oleh: OutletStockReportController

-- Index untuk query berdasarkan outlet + warehouse + opname_date
ALTER TABLE `outlet_stock_opnames` 
ADD INDEX `idx_outlet_warehouse_date` (`outlet_id`, `warehouse_outlet_id`, `opname_date`);

-- Index untuk query berdasarkan status
ALTER TABLE `outlet_stock_opnames` 
ADD INDEX `idx_status` (`status`);

-- =====================================================
-- 12. INDEX UNTUK outlet_stock_opname_items
-- =====================================================
-- Digunakan oleh: OutletStockReportController

-- Index untuk query berdasarkan stock_opname_id
ALTER TABLE `outlet_stock_opname_items` 
ADD INDEX `idx_stock_opname_id` (`stock_opname_id`);

-- Index untuk query berdasarkan inventory_item_id
ALTER TABLE `outlet_stock_opname_items` 
ADD INDEX `idx_inventory_item_id` (`inventory_item_id`);

-- =====================================================
-- VERIFIKASI INDEX
-- =====================================================
-- Jalankan query berikut untuk cek apakah index sudah ditambahkan:

-- SHOW INDEX FROM `outlet_food_inventory_cards`;
-- SHOW INDEX FROM `outlet_wip_production_headers`;
-- SHOW INDEX FROM `outlet_internal_use_waste_headers`;
-- SHOW INDEX FROM `stock_cut_logs`;
-- SHOW INDEX FROM `stock_cut_details`;
-- SHOW INDEX FROM `order_items`;
-- SHOW INDEX FROM `outlet_food_inventory_stocks`;

-- =====================================================
-- CATATAN PENTING
-- =====================================================
-- 1. Backup database dulu sebelum menjalankan script ini!
-- 2. Index akan memperlambat INSERT/UPDATE sedikit (trade-off)
-- 3. Tapi sangat mempercepat SELECT (yang lebih penting)
-- 4. Monitor query performance setelah index ditambahkan
-- 5. Jika ada error "Duplicate key name", berarti index sudah ada (skip saja)
-- 6. Waktu eksekusi: ~5-10 menit (tergantung ukuran tabel)

