-- Asset Service: vendor invoice file + link to Non Food Payment (run once)
-- =============================================================

-- Invoice from vendor (PDF/JPG) stored path, e.g. asset-service-invoices/xxx.pdf
ALTER TABLE `asset_service_orders`
  ADD COLUMN `vendor_invoice_path` VARCHAR(500) NULL DEFAULT NULL COMMENT 'Storage path for vendor invoice' AFTER `actual_cost`;

-- Non Food Payment can reference an Asset Service Order (instead of PO/PR/Retail)
ALTER TABLE `non_food_payments`
  ADD COLUMN `asset_service_order_id` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'FK asset_service_orders.id' AFTER `retail_non_food_id`;

ALTER TABLE `non_food_payments`
  ADD KEY `idx_non_food_payments_asset_service_order` (`asset_service_order_id`);

ALTER TABLE `non_food_payments`
  ADD CONSTRAINT `fk_non_food_payments_asset_service_order`
    FOREIGN KEY (`asset_service_order_id`) REFERENCES `asset_service_orders` (`id`) ON DELETE SET NULL;
