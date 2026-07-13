ALTER TABLE purchase_requisition_categories
ADD COLUMN show_on_retail TINYINT(1) NOT NULL DEFAULT 1 AFTER active;
