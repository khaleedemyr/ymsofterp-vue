-- Asset Service: internal vs external (run once)
-- internal = perbaikan/maintenance tanpa vendor luar (tanpa NFP)
-- external = vendor luar → boleh Non Food Payment + invoice vendor

ALTER TABLE `asset_service_orders`
  ADD COLUMN `service_type` ENUM('internal','external') NOT NULL DEFAULT 'external'
  COMMENT 'internal=tim sendiri/divisi; external=vendor (NFP)'
  AFTER `description`;

ALTER TABLE `asset_service_orders`
  MODIFY COLUMN `supplier_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'FK suppliers — wajib untuk external';
