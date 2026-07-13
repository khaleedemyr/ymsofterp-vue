-- RO Supplier: supplier_id & item_supplier_id boleh NULL jika item belum punya mapping Item Supplier
ALTER TABLE `food_floor_order_supplier_items`
  MODIFY COLUMN `supplier_id` bigint unsigned NULL,
  MODIFY COLUMN `item_supplier_id` bigint unsigned NULL;
