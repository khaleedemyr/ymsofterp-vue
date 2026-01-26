-- =====================================================
-- QUERY ALTER INDEX untuk Optimasi Outlet WIP Production
-- =====================================================
-- Jalankan query ini secara manual di database
-- Pastikan backup database terlebih dahulu!
-- =====================================================

-- 1. Index untuk tabel outlet_wip_production_headers
-- Index untuk filter berdasarkan outlet_id (sering digunakan)
ALTER TABLE `outlet_wip_production_headers` 
ADD INDEX `idx_outlet_wip_headers_outlet_id` (`outlet_id`);

-- Index untuk filter berdasarkan production_date (untuk date range filter)
-- CATATAN: Jika kolom production_date adalah DATETIME, index ini akan otomatis include jam, menit, detik
-- Index ini akan optimal untuk: whereDate(), whereBetween(), dan ORDER BY production_date
ALTER TABLE `outlet_wip_production_headers` 
ADD INDEX `idx_outlet_wip_headers_production_date` (`production_date`);

-- Index untuk filter berdasarkan status (untuk filter DRAFT)
ALTER TABLE `outlet_wip_production_headers` 
ADD INDEX `idx_outlet_wip_headers_status` (`status`);

-- Composite index untuk query draft (outlet_id + warehouse_outlet_id + created_by + status)
-- Ini sangat penting untuk query di method store() yang mencari draft existing
ALTER TABLE `outlet_wip_production_headers` 
ADD INDEX `idx_outlet_wip_headers_draft_lookup` (`outlet_id`, `warehouse_outlet_id`, `created_by`, `status`);

-- Index untuk warehouse_outlet_id (untuk join dan filter)
ALTER TABLE `outlet_wip_production_headers` 
ADD INDEX `idx_outlet_wip_headers_warehouse_outlet` (`warehouse_outlet_id`);

-- Composite index untuk sorting dan filtering (production_date + id untuk ORDER BY)
-- Index ini akan optimal untuk: ORDER BY production_date DESC, id DESC
-- Jika production_date adalah DATETIME, index ini akan include jam, menit, detik untuk sorting yang lebih akurat
-- Data akan diurutkan berdasarkan: tanggal -> jam -> menit -> detik -> id
ALTER TABLE `outlet_wip_production_headers` 
ADD INDEX `idx_outlet_wip_headers_date_id` (`production_date` DESC, `id` DESC);

-- Index untuk created_at (untuk sorting berdasarkan waktu pembuatan)
-- Index ini akan optimal untuk: ORDER BY created_at DESC/ASC
-- created_at adalah TIMESTAMP/DATETIME, jadi index akan include jam, menit, detik
ALTER TABLE `outlet_wip_production_headers` 
ADD INDEX `idx_outlet_wip_headers_created_at` (`created_at` DESC);


-- 2. Index untuk tabel outlet_wip_productions
-- Index untuk header_id (sangat sering digunakan untuk join dan whereIn)
ALTER TABLE `outlet_wip_productions` 
ADD INDEX `idx_outlet_wip_productions_header_id` (`header_id`);

-- Index untuk outlet_id (untuk filter)
ALTER TABLE `outlet_wip_productions` 
ADD INDEX `idx_outlet_wip_productions_outlet_id` (`outlet_id`);

-- Index untuk production_date (untuk filter date range)
-- CATATAN: Jika kolom production_date adalah DATETIME, index ini akan otomatis include jam, menit, detik
-- Index ini akan optimal untuk: whereDate(), whereBetween(), dan ORDER BY production_date
ALTER TABLE `outlet_wip_productions` 
ADD INDEX `idx_outlet_wip_productions_production_date` (`production_date`);

-- Index untuk item_id (untuk join dengan items)
ALTER TABLE `outlet_wip_productions` 
ADD INDEX `idx_outlet_wip_productions_item_id` (`item_id`);

-- Composite index untuk menghindari duplicate (header_id + item_id)
-- Ini penting untuk query yang menggunakan GROUP BY header_id, item_id
ALTER TABLE `outlet_wip_productions` 
ADD INDEX `idx_outlet_wip_productions_header_item` (`header_id`, `item_id`);

-- Index untuk header_id NULL (untuk query old data yang tidak punya header_id)
ALTER TABLE `outlet_wip_productions` 
ADD INDEX `idx_outlet_wip_productions_header_null` (`header_id`);

-- Index untuk created_at (untuk sorting berdasarkan waktu pembuatan)
-- Index ini akan optimal untuk: ORDER BY created_at DESC/ASC
-- created_at adalah TIMESTAMP/DATETIME, jadi index akan include jam, menit, detik
ALTER TABLE `outlet_wip_productions` 
ADD INDEX `idx_outlet_wip_productions_created_at` (`created_at` DESC);


-- 3. Index untuk tabel outlet_food_inventory_items
-- Index untuk item_id (sangat sering digunakan untuk lookup)
ALTER TABLE `outlet_food_inventory_items` 
ADD INDEX `idx_outlet_food_inv_items_item_id` (`item_id`);


-- 4. Index untuk tabel outlet_food_inventory_stocks
-- Index untuk inventory_item_id (sering digunakan)
ALTER TABLE `outlet_food_inventory_stocks` 
ADD INDEX `idx_outlet_food_inv_stocks_inv_item_id` (`inventory_item_id`);

-- Index untuk id_outlet (sering digunakan)
ALTER TABLE `outlet_food_inventory_stocks` 
ADD INDEX `idx_outlet_food_inv_stocks_outlet_id` (`id_outlet`);

-- Index untuk warehouse_outlet_id (sering digunakan)
ALTER TABLE `outlet_food_inventory_stocks` 
ADD INDEX `idx_outlet_food_inv_stocks_warehouse_outlet` (`warehouse_outlet_id`);

-- Composite index untuk query stock lookup (sangat penting!)
-- Query sering menggunakan: WHERE inventory_item_id = X AND id_outlet = Y AND warehouse_outlet_id = Z
ALTER TABLE `outlet_food_inventory_stocks` 
ADD INDEX `idx_outlet_food_inv_stocks_lookup` (`inventory_item_id`, `id_outlet`, `warehouse_outlet_id`);


-- 5. Index untuk tabel item_bom
-- Index untuk item_id (sering digunakan untuk get BOM)
ALTER TABLE `item_bom` 
ADD INDEX `idx_item_bom_item_id` (`item_id`);

-- Index untuk material_item_id (untuk join dengan items)
ALTER TABLE `item_bom` 
ADD INDEX `idx_item_bom_material_item_id` (`material_item_id`);


-- 6. Index untuk tabel outlet_food_inventory_cards (opsional, untuk report)
-- Index untuk reference_type dan reference_id (untuk query stock cards)
ALTER TABLE `outlet_food_inventory_cards` 
ADD INDEX `idx_outlet_food_inv_cards_reference` (`reference_type`, `reference_id`);

-- Index untuk date (untuk sorting)
-- CATATAN: Jika kolom date adalah DATETIME, index ini akan otomatis include jam, menit, detik
ALTER TABLE `outlet_food_inventory_cards` 
ADD INDEX `idx_outlet_food_inv_cards_date` (`date`);

-- Index untuk created_at (untuk sorting berdasarkan waktu pembuatan)
-- Index ini akan optimal untuk: ORDER BY created_at DESC/ASC
-- created_at adalah TIMESTAMP/DATETIME, jadi index akan include jam, menit, detik
ALTER TABLE `outlet_food_inventory_cards` 
ADD INDEX `idx_outlet_food_inv_cards_created_at` (`created_at` DESC);


-- =====================================================
-- CATATAN PENTING:
-- =====================================================
-- 1. Pastikan backup database sebelum menjalankan query ini
-- 2. Query ini akan memakan waktu jika data sudah banyak
-- 3. Index akan mempercepat SELECT query tapi sedikit memperlambat INSERT/UPDATE
-- 4. Monitor performa setelah index ditambahkan
-- 5. Jika ada error "Duplicate key name", berarti index sudah ada, skip query tersebut
-- 
-- TENTANG INDEX DENGAN WAKTU (JAM, MENIT, DETIK):
-- =====================================================
-- - Jika kolom production_date/created_at adalah tipe DATETIME atau TIMESTAMP, 
--   index akan OTOMATIS include jam, menit, detik
-- - Index pada DATETIME/TIMESTAMP akan optimal untuk:
--   * Sorting berdasarkan waktu (ORDER BY created_at DESC, ORDER BY production_date DESC)
--   * Filter range dengan waktu (WHERE created_at BETWEEN ...)
--   * Filter exact datetime (WHERE created_at = '2025-01-26 14:30:00')
-- - Index pada DATE hanya akan index bagian tanggal saja (tidak include waktu)
-- - created_at biasanya adalah TIMESTAMP, jadi index akan include jam, menit, detik
-- - Untuk cek tipe kolom: DESCRIBE `outlet_wip_production_headers`;
-- =====================================================

-- Untuk cek tipe kolom (apakah DATETIME atau DATE):
-- DESCRIBE `outlet_wip_production_headers`;
-- DESCRIBE `outlet_wip_productions`;
-- Jika kolom production_date adalah DATETIME/TIMESTAMP, index akan include jam, menit, detik
-- Jika kolom production_date adalah DATE, index hanya akan include tanggal saja

-- Untuk cek index yang sudah ada:
-- SHOW INDEX FROM `outlet_wip_production_headers`;
-- SHOW INDEX FROM `outlet_wip_productions`;
-- SHOW INDEX FROM `outlet_food_inventory_items`;
-- SHOW INDEX FROM `outlet_food_inventory_stocks`;
-- SHOW INDEX FROM `item_bom`;

-- Untuk drop index jika diperlukan (HATI-HATI!):
-- ALTER TABLE `outlet_wip_production_headers` DROP INDEX `idx_outlet_wip_headers_outlet_id`;
