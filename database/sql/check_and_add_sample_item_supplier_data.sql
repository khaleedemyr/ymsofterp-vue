-- Check existing data in item_supplier and item_supplier_outlet tables
SELECT 'item_supplier count' as table_name, COUNT(*) as count FROM item_supplier
UNION ALL
SELECT 'item_supplier_outlet count' as table_name, COUNT(*) as count FROM item_supplier_outlet;

-- Check suppliers that have items
SELECT 
    s.id as supplier_id,
    s.name as supplier_name,
    COUNT(is.id) as item_count
FROM suppliers s
LEFT JOIN item_supplier is ON s.id = is.supplier_id
WHERE s.status = 'active'
GROUP BY s.id, s.name
ORDER BY item_count DESC;

-- Check outlets that have supplier items
SELECT 
    o.id_outlet,
    o.nama_outlet,
    COUNT(iso.id) as supplier_item_count
FROM tbl_data_outlet o
LEFT JOIN item_supplier_outlet iso ON o.id_outlet = iso.outlet_id
WHERE o.status = 'A'
GROUP BY o.id_outlet, o.nama_outlet
ORDER BY supplier_item_count DESC;

-- Add sample data if no data exists
-- First, check if we have any active suppliers and items
SET @supplier_id = (SELECT id FROM suppliers WHERE status = 'active' LIMIT 1);
SET @outlet_id = (SELECT id_outlet FROM tbl_data_outlet WHERE status = 'A' LIMIT 1);
SET @item_id = (SELECT id FROM items WHERE status = 'active' LIMIT 1);
SET @unit_id = (SELECT id FROM units LIMIT 1);

-- Insert sample item_supplier if no data exists
INSERT INTO item_supplier (supplier_id, item_id, price, unit_id, created_at, updated_at)
SELECT @supplier_id, @item_id, 10000, @unit_id, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM item_supplier WHERE supplier_id = @supplier_id AND item_id = @item_id);

-- Insert sample item_supplier_outlet if no data exists
INSERT INTO item_supplier_outlet (item_supplier_id, outlet_id, created_at, updated_at)
SELECT is.id, @outlet_id, NOW(), NOW()
FROM item_supplier is
WHERE is.supplier_id = @supplier_id 
AND is.item_id = @item_id
AND NOT EXISTS (
    SELECT 1 FROM item_supplier_outlet iso 
    WHERE iso.item_supplier_id = is.id 
    AND iso.outlet_id = @outlet_id
);

-- Show final data
SELECT 'Final check - item_supplier' as table_name, COUNT(*) as count FROM item_supplier
UNION ALL
SELECT 'Final check - item_supplier_outlet' as table_name, COUNT(*) as count FROM item_supplier_outlet;
