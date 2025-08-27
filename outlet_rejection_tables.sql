-- =====================================================
-- OUTLET REJECTION TABLES
-- =====================================================

-- 1. Header table untuk outlet rejection
CREATE TABLE `outlet_rejections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `number` varchar(50) NOT NULL COMMENT 'Nomor rejection (format: ORJ-YYYYMMDD-XXXX)',
  `rejection_date` date NOT NULL COMMENT 'Tanggal rejection',
  `outlet_id` bigint(20) unsigned NOT NULL COMMENT 'ID outlet yang melakukan rejection',
  `warehouse_id` bigint(20) unsigned NOT NULL COMMENT 'ID warehouse tujuan (gudang)',
  `delivery_order_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Reference ke delivery order yang ditolak',
  `status` enum('draft','submitted','approved','completed','cancelled') NOT NULL DEFAULT 'draft',
  `notes` text DEFAULT NULL COMMENT 'Catatan umum rejection',
  `created_by` bigint(20) unsigned NOT NULL COMMENT 'User yang membuat rejection',
  `approved_by` bigint(20) unsigned DEFAULT NULL COMMENT 'User yang approve rejection',
  `approved_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu approval',
  `completed_by` bigint(20) unsigned DEFAULT NULL COMMENT 'User yang complete rejection',
  `completed_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu completion',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `outlet_rejections_number_unique` (`number`),
  KEY `outlet_rejections_outlet_id_foreign` (`outlet_id`),
  KEY `outlet_rejections_warehouse_id_foreign` (`warehouse_id`),
  KEY `outlet_rejections_delivery_order_id_foreign` (`delivery_order_id`),
  KEY `outlet_rejections_created_by_foreign` (`created_by`),
  KEY `outlet_rejections_approved_by_foreign` (`approved_by`),
  KEY `outlet_rejections_completed_by_foreign` (`completed_by`),
  KEY `outlet_rejections_status_index` (`status`),
  KEY `outlet_rejections_rejection_date_index` (`rejection_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Header table untuk outlet rejection';

-- 2. Detail table untuk items yang ditolak
CREATE TABLE `outlet_rejection_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `outlet_rejection_id` bigint(20) unsigned NOT NULL COMMENT 'Reference ke header rejection',
  `item_id` bigint(20) unsigned NOT NULL COMMENT 'ID item yang ditolak',
  `unit_id` bigint(20) unsigned NOT NULL COMMENT 'ID unit item',
  `qty_rejected` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Quantity yang ditolak',
  `qty_received` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Quantity yang diterima di gudang',
  `rejection_reason` text DEFAULT NULL COMMENT 'Alasan rejection dari outlet',
  `item_condition` enum('good','damaged','expired','other') NOT NULL DEFAULT 'good' COMMENT 'Kondisi barang saat rejection',
  `condition_notes` text DEFAULT NULL COMMENT 'Catatan kondisi barang',
  `mac_cost` decimal(15,2) DEFAULT 0.00 COMMENT 'MAC cost saat rejection (untuk inventory)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `outlet_rejection_items_outlet_rejection_id_foreign` (`outlet_rejection_id`),
  KEY `outlet_rejection_items_item_id_foreign` (`item_id`),
  KEY `outlet_rejection_items_unit_id_foreign` (`unit_id`),
  KEY `outlet_rejection_items_item_condition_index` (`item_condition`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Detail items untuk outlet rejection';

-- 3. Foreign key constraints
ALTER TABLE `outlet_rejections`
  ADD CONSTRAINT `outlet_rejections_outlet_id_foreign` FOREIGN KEY (`outlet_id`) REFERENCES `tbl_data_outlet` (`id_outlet`) ON DELETE CASCADE,
  ADD CONSTRAINT `outlet_rejections_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `outlet_rejections_delivery_order_id_foreign` FOREIGN KEY (`delivery_order_id`) REFERENCES `delivery_orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `outlet_rejections_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `outlet_rejections_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `outlet_rejections_completed_by_foreign` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `outlet_rejection_items`
  ADD CONSTRAINT `outlet_rejection_items_outlet_rejection_id_foreign` FOREIGN KEY (`outlet_rejection_id`) REFERENCES `outlet_rejections` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `outlet_rejection_items_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `outlet_rejection_items_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE;

-- =====================================================
-- INDEXES UNTUK PERFORMANCE
-- =====================================================

-- Index untuk pencarian berdasarkan outlet dan tanggal
CREATE INDEX `idx_outlet_rejections_outlet_date` ON `outlet_rejections` (`outlet_id`, `rejection_date`);

-- Index untuk pencarian berdasarkan status
CREATE INDEX `idx_outlet_rejections_status_date` ON `outlet_rejections` (`status`, `rejection_date`);

-- Index untuk pencarian berdasarkan delivery order
CREATE INDEX `idx_outlet_rejections_delivery_order` ON `outlet_rejections` (`delivery_order_id`);

-- Index untuk detail items berdasarkan rejection
CREATE INDEX `idx_outlet_rejection_items_rejection` ON `outlet_rejection_items` (`outlet_rejection_id`);

-- Index untuk detail items berdasarkan item
CREATE INDEX `idx_outlet_rejection_items_item` ON `outlet_rejection_items` (`item_id`);

-- =====================================================
-- SAMPLE DATA (OPTIONAL)
-- =====================================================

-- Insert sample outlet rejection (uncomment jika diperlukan)
/*
INSERT INTO `outlet_rejections` (
  `number`, 
  `rejection_date`, 
  `outlet_id`, 
  `warehouse_id`, 
  `status`, 
  `notes`, 
  `created_by`, 
  `created_at`, 
  `updated_at`
) VALUES (
  'ORJ-20241201-0001',
  '2024-12-01',
  1, -- outlet_id
  1, -- warehouse_id
  'draft',
  'Sample rejection untuk testing',
  1, -- created_by
  NOW(),
  NOW()
);
*/

-- =====================================================
-- NOTES
-- =====================================================

/*
NOTES TENTANG STRUKTUR TABEL:

1. OUTLET_REJECTIONS (Header):
   - number: Format ORJ-YYYYMMDD-XXXX (Outlet Rejection)
   - rejection_date: Tanggal rejection dilakukan
   - outlet_id: Outlet yang melakukan rejection
   - warehouse_id: Warehouse tujuan (gudang)
   - delivery_order_id: Optional, reference ke DO yang ditolak
   - status: Workflow status (draft -> submitted -> approved -> completed)
   - notes: Catatan umum rejection

2. OUTLET_REJECTION_ITEMS (Detail):
   - qty_rejected: Quantity yang ditolak outlet
   - qty_received: Quantity yang diterima di gudang (bisa berbeda karena kondisi)
   - rejection_reason: Alasan rejection dari outlet
   - item_condition: Kondisi barang (good/damaged/expired/other)
   - condition_notes: Catatan detail kondisi barang
   - mac_cost: MAC cost untuk inventory calculation

WORKFLOW:
1. Draft: User input rejection
2. Submitted: Kirim untuk approval
3. Approved: Manager approve rejection
4. Completed: Barang masuk ke inventory gudang

INTEGRATION:
- Delivery Order: Link ke DO yang ditolak
- Inventory: Update stock gudang saat complete
- MAC: Ambil dari food_inventory_cost_histories
- Reports: Laporan rejection per outlet/periode
*/
