-- Asset ownership — fase 2: Transfer, Adjustment, Service, Disposal
-- Jalankan setelah asset_ownership.sql

START TRANSACTION;

-- Transfer
ALTER TABLE `asset_inventory_transfers`
  ADD COLUMN `owner_outlet_id` INT UNSIGNED NULL COMMENT 'FK tbl_data_outlet — pemilik stok' AFTER `transfer_date`;

UPDATE `asset_inventory_transfers` t
  INNER JOIN `warehouse_outlets` w ON t.`warehouse_outlet_from_id` = w.`id`
  SET t.`owner_outlet_id` = w.`outlet_id`
  WHERE t.`owner_outlet_id` IS NULL;

UPDATE `asset_inventory_transfers` SET `owner_outlet_id` = `outlet_id` WHERE `owner_outlet_id` IS NULL;

ALTER TABLE `asset_inventory_transfers`
  MODIFY COLUMN `owner_outlet_id` INT UNSIGNED NOT NULL COMMENT 'FK tbl_data_outlet — pemilik stok';

ALTER TABLE `asset_inventory_transfers`
  ADD KEY `idx_ait_owner` (`owner_outlet_id`);

ALTER TABLE `asset_inventory_transfers`
  MODIFY COLUMN `outlet_id` INT UNSIGNED NOT NULL COMMENT 'Outlet lokasi gudang asal (legacy/dokumen)';

-- Adjustment
ALTER TABLE `asset_inventory_adjustments`
  ADD COLUMN `owner_outlet_id` INT UNSIGNED NULL COMMENT 'FK tbl_data_outlet — pemilik stok' AFTER `date`;

UPDATE `asset_inventory_adjustments` a
  INNER JOIN `warehouse_outlets` w ON a.`warehouse_outlet_id` = w.`id`
  SET a.`owner_outlet_id` = COALESCE(a.`owner_outlet_id`, a.`outlet_id`, w.`outlet_id`)
  WHERE a.`owner_outlet_id` IS NULL;

UPDATE `asset_inventory_adjustments` SET `owner_outlet_id` = `outlet_id` WHERE `owner_outlet_id` IS NULL;

ALTER TABLE `asset_inventory_adjustments`
  MODIFY COLUMN `owner_outlet_id` INT UNSIGNED NOT NULL COMMENT 'FK tbl_data_outlet — pemilik stok';

ALTER TABLE `asset_inventory_adjustments`
  ADD KEY `idx_aia_owner` (`owner_outlet_id`);

ALTER TABLE `asset_inventory_adjustments`
  MODIFY COLUMN `outlet_id` INT UNSIGNED NOT NULL COMMENT 'FK tbl_data_outlet — lokasi fisik (outlet gudang)';

-- Service Order
ALTER TABLE `asset_service_orders`
  ADD COLUMN `owner_outlet_id` INT UNSIGNED NULL COMMENT 'FK tbl_data_outlet — pemilik stok' AFTER `date`;

UPDATE `asset_service_orders` s
  INNER JOIN `warehouse_outlets` w ON s.`warehouse_outlet_id` = w.`id`
  SET s.`owner_outlet_id` = COALESCE(s.`owner_outlet_id`, s.`outlet_id`, w.`outlet_id`)
  WHERE s.`owner_outlet_id` IS NULL;

UPDATE `asset_service_orders` SET `owner_outlet_id` = `outlet_id` WHERE `owner_outlet_id` IS NULL;

ALTER TABLE `asset_service_orders`
  MODIFY COLUMN `owner_outlet_id` INT UNSIGNED NOT NULL COMMENT 'FK tbl_data_outlet — pemilik stok';

ALTER TABLE `asset_service_orders`
  ADD KEY `idx_aso_owner` (`owner_outlet_id`);

ALTER TABLE `asset_service_orders`
  MODIFY COLUMN `outlet_id` INT UNSIGNED NOT NULL COMMENT 'FK tbl_data_outlet — lokasi fisik (outlet gudang)';

-- Disposal (kolom lokasi = id_outlet)
ALTER TABLE `asset_disposals`
  ADD COLUMN `owner_outlet_id` INT UNSIGNED NULL COMMENT 'FK tbl_data_outlet — pemilik stok' AFTER `date`;

UPDATE `asset_disposals` d
  INNER JOIN `warehouse_outlets` w ON d.`warehouse_outlet_id` = w.`id`
  SET d.`owner_outlet_id` = COALESCE(d.`owner_outlet_id`, d.`id_outlet`, w.`outlet_id`)
  WHERE d.`owner_outlet_id` IS NULL;

UPDATE `asset_disposals` SET `owner_outlet_id` = `id_outlet` WHERE `owner_outlet_id` IS NULL;

ALTER TABLE `asset_disposals`
  MODIFY COLUMN `owner_outlet_id` INT UNSIGNED NOT NULL COMMENT 'FK tbl_data_outlet — pemilik stok';

ALTER TABLE `asset_disposals`
  ADD KEY `idx_adp_owner` (`owner_outlet_id`);

ALTER TABLE `asset_disposals`
  MODIFY COLUMN `id_outlet` INT UNSIGNED NOT NULL COMMENT 'FK tbl_data_outlet — lokasi fisik (outlet gudang)';

COMMIT;
