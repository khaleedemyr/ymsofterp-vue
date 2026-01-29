-- Query untuk membuat tabel approval flow untuk Warehouse Stock Adjustment
-- Jalankan query ini di database

-- 1. Buat tabel approval flow (mirip dengan outlet stock adjustment)
CREATE TABLE IF NOT EXISTS `food_inventory_adjustment_approval_flows` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `adjustment_id` bigint(20) UNSIGNED NOT NULL,
  `approver_id` bigint(20) UNSIGNED NOT NULL,
  `approval_level` int(11) NOT NULL COMMENT 'Level 1 = pertama, 2 = kedua, dst',
  `status` enum('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
  `approved_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `adjustment_id` (`adjustment_id`),
  KEY `approver_id` (`approver_id`),
  KEY `status` (`status`),
  KEY `approval_level` (`approval_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Approval flow untuk warehouse stock adjustment';

-- 2. Optional: Jika ingin migrate data existing (adjustment yang masih waiting)
-- Untuk MK Warehouse yang waiting_approval -> masukkan Sous Chef MK sebagai approver
INSERT INTO `food_inventory_adjustment_approval_flows` 
    (`adjustment_id`, `approver_id`, `approval_level`, `status`, `created_at`, `updated_at`)
SELECT 
    fia.id,
    u.id as approver_id,
    1 as approval_level,
    'PENDING' as status,
    NOW() as created_at,
    NOW() as updated_at
FROM `food_inventory_adjustments` fia
INNER JOIN `warehouses` w ON fia.warehouse_id = w.id
CROSS JOIN `users` u
WHERE fia.status = 'waiting_approval'
  AND w.name IN ('MK1 Hot Kitchen', 'MK2 Cold Kitchen')
  AND u.id_jabatan = 179  -- Sous Chef MK
  AND u.status = 'A'
  AND NOT EXISTS (
    SELECT 1 FROM `food_inventory_adjustment_approval_flows` 
    WHERE adjustment_id = fia.id
  );

-- 3. Untuk Non-MK Warehouse yang waiting_approval -> masukkan Asisten SSD Manager sebagai approver
INSERT INTO `food_inventory_adjustment_approval_flows` 
    (`adjustment_id`, `approver_id`, `approval_level`, `status`, `created_at`, `updated_at`)
SELECT 
    fia.id,
    u.id as approver_id,
    1 as approval_level,
    'PENDING' as status,
    NOW() as created_at,
    NOW() as updated_at
FROM `food_inventory_adjustments` fia
INNER JOIN `warehouses` w ON fia.warehouse_id = w.id
CROSS JOIN `users` u
WHERE fia.status = 'waiting_approval'
  AND w.name NOT IN ('MK1 Hot Kitchen', 'MK2 Cold Kitchen')
  AND u.id_jabatan = 172  -- Asisten SSD Manager
  AND u.status = 'A'
  AND NOT EXISTS (
    SELECT 1 FROM `food_inventory_adjustment_approval_flows` 
    WHERE adjustment_id = fia.id
  );

-- 4. Untuk yang waiting_ssd_manager -> masukkan SSD Manager sebagai approver level 2
INSERT INTO `food_inventory_adjustment_approval_flows` 
    (`adjustment_id`, `approver_id`, `approval_level`, `status`, `created_at`, `updated_at`)
SELECT 
    fia.id,
    u.id as approver_id,
    2 as approval_level,
    'PENDING' as status,
    NOW() as created_at,
    NOW() as updated_at
FROM `food_inventory_adjustments` fia
CROSS JOIN `users` u
WHERE fia.status = 'waiting_ssd_manager'
  AND u.id_jabatan = 161  -- SSD Manager
  AND u.status = 'A'
  AND NOT EXISTS (
    SELECT 1 FROM `food_inventory_adjustment_approval_flows` 
    WHERE adjustment_id = fia.id AND approval_level = 2
  );

-- 5. Untuk yang waiting_cost_control -> masukkan Cost Control Manager sebagai approver
INSERT INTO `food_inventory_adjustment_approval_flows` 
    (`adjustment_id`, `approver_id`, `approval_level`, `status`, `created_at`, `updated_at`)
SELECT 
    fia.id,
    u.id as approver_id,
    CASE 
        WHEN EXISTS (
            SELECT 1 FROM `food_inventory_adjustment_approval_flows` fiaf2
            WHERE fiaf2.adjustment_id = fia.id
        ) THEN (
            SELECT MAX(approval_level) + 1 
            FROM `food_inventory_adjustment_approval_flows` fiaf3
            WHERE fiaf3.adjustment_id = fia.id
        )
        ELSE 1
    END as approval_level,
    'PENDING' as status,
    NOW() as created_at,
    NOW() as updated_at
FROM `food_inventory_adjustments` fia
CROSS JOIN `users` u
WHERE fia.status = 'waiting_cost_control'
  AND u.id_jabatan = 167  -- Cost Control Manager
  AND u.status = 'A'
  AND NOT EXISTS (
    SELECT 1 FROM `food_inventory_adjustment_approval_flows` fiaf
    WHERE fiaf.adjustment_id = fia.id 
      AND fiaf.approver_id = u.id
  );

-- 6. Mark sebagai APPROVED untuk yang sudah di-approve
UPDATE `food_inventory_adjustment_approval_flows` fiaf
INNER JOIN `food_inventory_adjustments` fia ON fiaf.adjustment_id = fia.id
SET fiaf.status = 'APPROVED',
    fiaf.approved_at = COALESCE(fia.approved_at_assistant_ssd_manager, fia.approved_at_ssd_manager, fia.created_at)
WHERE fiaf.approval_level = 1 
  AND fia.approved_by_assistant_ssd_manager IS NOT NULL
  AND fiaf.status = 'PENDING';

UPDATE `food_inventory_adjustment_approval_flows` fiaf
INNER JOIN `food_inventory_adjustments` fia ON fiaf.adjustment_id = fia.id
SET fiaf.status = 'APPROVED',
    fiaf.approved_at = COALESCE(fia.approved_at_ssd_manager, fia.created_at)
WHERE fiaf.approval_level = 2 
  AND fia.approved_by_ssd_manager IS NOT NULL
  AND fiaf.status = 'PENDING';

-- 7. Cek hasil
SELECT 
    fia.id,
    fia.number,
    fia.status,
    w.name as warehouse_name,
    GROUP_CONCAT(
        CONCAT(
            'Level ', fiaf.approval_level, ': ',
            u.nama_lengkap, ' (', j.nama_jabatan, ') - ',
            fiaf.status
        ) ORDER BY fiaf.approval_level SEPARATOR ' | '
    ) as approval_flow
FROM `food_inventory_adjustments` fia
LEFT JOIN `warehouses` w ON fia.warehouse_id = w.id
LEFT JOIN `food_inventory_adjustment_approval_flows` fiaf ON fia.id = fiaf.adjustment_id
LEFT JOIN `users` u ON fiaf.approver_id = u.id
LEFT JOIN `tbl_data_jabatan` j ON u.id_jabatan = j.id_jabatan
WHERE fia.status IN ('waiting_approval', 'waiting_ssd_manager', 'waiting_cost_control')
GROUP BY fia.id, fia.number, fia.status, w.name
ORDER BY fia.created_at DESC
LIMIT 20;
