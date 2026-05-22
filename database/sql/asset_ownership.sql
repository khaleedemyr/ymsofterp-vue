-- Asset inventory ownership (pemilik vs lokasi fisik)
-- Jalankan manual di MySQL. Bukan Laravel migration.
--
-- Konvensi:
--   owner_outlet_id  = outlet pemilik aset (dari PO / PR)
--   outlet_id        = outlet lokasi fisik (outlet induk gudang warehouse_outlets)

START TRANSACTION;

-- ─── Stok & ledger ───────────────────────────────────────────────────────────

ALTER TABLE `asset_inventory_stocks`
  ADD COLUMN `owner_outlet_id` INT UNSIGNED NULL COMMENT 'FK tbl_data_outlet — pemilik' AFTER `inventory_item_id`;

UPDATE `asset_inventory_stocks` SET `owner_outlet_id` = `outlet_id` WHERE `owner_outlet_id` IS NULL;

ALTER TABLE `asset_inventory_stocks`
  MODIFY COLUMN `owner_outlet_id` INT UNSIGNED NOT NULL COMMENT 'FK tbl_data_outlet — pemilik';

ALTER TABLE `asset_inventory_stocks`
  DROP INDEX `uk_asset_stock_loc`;

ALTER TABLE `asset_inventory_stocks`
  ADD UNIQUE KEY `uk_asset_stock_owner_loc` (`inventory_item_id`, `owner_outlet_id`, `warehouse_outlet_id`);

ALTER TABLE `asset_inventory_stocks`
  ADD KEY `idx_asset_stock_owner` (`owner_outlet_id`);

ALTER TABLE `asset_inventory_stocks`
  MODIFY COLUMN `outlet_id` INT UNSIGNED NOT NULL COMMENT 'FK tbl_data_outlet — lokasi fisik (outlet gudang)';

-- ─── Kartu stok & riwayat biaya ──────────────────────────────────────────────

ALTER TABLE `asset_inventory_cards`
  ADD COLUMN `owner_outlet_id` INT UNSIGNED NULL COMMENT 'FK tbl_data_outlet — pemilik' AFTER `inventory_item_id`;

UPDATE `asset_inventory_cards` SET `owner_outlet_id` = `outlet_id` WHERE `owner_outlet_id` IS NULL;

ALTER TABLE `asset_inventory_cards`
  MODIFY COLUMN `owner_outlet_id` INT UNSIGNED NOT NULL COMMENT 'FK tbl_data_outlet — pemilik';

ALTER TABLE `asset_inventory_cards`
  ADD KEY `idx_asset_card_owner` (`owner_outlet_id`);

ALTER TABLE `asset_inventory_cost_histories`
  ADD COLUMN `owner_outlet_id` INT UNSIGNED NULL COMMENT 'FK tbl_data_outlet — pemilik' AFTER `inventory_item_id`;

UPDATE `asset_inventory_cost_histories` SET `owner_outlet_id` = `outlet_id` WHERE `owner_outlet_id` IS NULL;

ALTER TABLE `asset_inventory_cost_histories`
  MODIFY COLUMN `owner_outlet_id` INT UNSIGNED NOT NULL COMMENT 'FK tbl_data_outlet — pemilik';

ALTER TABLE `asset_inventory_cost_histories`
  ADD KEY `idx_asset_cost_owner` (`owner_outlet_id`);

-- ─── Asset Good Receive ──────────────────────────────────────────────────────

ALTER TABLE `asset_good_receives`
  ADD COLUMN `owner_outlet_id` INT UNSIGNED NULL COMMENT 'FK tbl_data_outlet — pemilik (dari PO)' AFTER `po_id`;

UPDATE `asset_good_receives` SET `owner_outlet_id` = `outlet_id` WHERE `owner_outlet_id` IS NULL;

ALTER TABLE `asset_good_receives`
  MODIFY COLUMN `owner_outlet_id` INT UNSIGNED NOT NULL COMMENT 'FK tbl_data_outlet — pemilik (dari PO)';

ALTER TABLE `asset_good_receives`
  ADD KEY `idx_asset_gr_owner` (`owner_outlet_id`);

ALTER TABLE `asset_good_receives`
  MODIFY COLUMN `outlet_id` INT UNSIGNED NOT NULL COMMENT 'FK tbl_data_outlet — lokasi simpan (outlet gudang)';

COMMIT;
