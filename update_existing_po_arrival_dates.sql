-- Update existing PO Foods with arrival_date from PR items
-- This script will update PO Foods that don't have arrival_date but have PR items with arrival_date

UPDATE purchase_order_foods po
SET arrival_date = (
    SELECT MIN(pr_item.arrival_date)
    FROM purchase_order_food_items po_item
    JOIN purchase_requisition_food_items pr_item ON po_item.pr_food_item_id = pr_item.id
    WHERE po_item.purchase_order_food_id = po.id
    AND pr_item.arrival_date IS NOT NULL
)
WHERE po.arrival_date IS NULL
AND EXISTS (
    SELECT 1
    FROM purchase_order_food_items po_item
    JOIN purchase_requisition_food_items pr_item ON po_item.pr_food_item_id = pr_item.id
    WHERE po_item.purchase_order_food_id = po.id
    AND pr_item.arrival_date IS NOT NULL
);

-- Show updated records
SELECT 
    po.id,
    po.number,
    po.arrival_date,
    COUNT(po_item.id) as item_count,
    COUNT(pr_item.arrival_date) as items_with_arrival_date
FROM purchase_order_foods po
LEFT JOIN purchase_order_food_items po_item ON po.id = po_item.purchase_order_food_id
LEFT JOIN purchase_requisition_food_items pr_item ON po_item.pr_food_item_id = pr_item.id
WHERE po.arrival_date IS NOT NULL
GROUP BY po.id, po.number, po.arrival_date
ORDER BY po.created_at DESC
LIMIT 10;
