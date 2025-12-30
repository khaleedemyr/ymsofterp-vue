-- =====================================================
-- AI ANALYTICS: SAMPLE QUERIES
-- =====================================================
-- Sample queries untuk memahami data structure
-- Digunakan sebagai reference untuk AI service
-- =====================================================

-- =====================================================
-- 1. SALES QUERIES
-- =====================================================

-- Sales overview per periode
SELECT 
    DATE(o.created_at) as date,
    COUNT(DISTINCT o.id) as total_orders,
    SUM(o.grand_total) as total_revenue,
    SUM(o.pax) as total_customers,
    AVG(o.grand_total) as avg_order_value,
    SUM(o.grand_total) / NULLIF(SUM(o.pax), 0) as cover
FROM orders o
WHERE DATE(o.created_at) BETWEEN '2024-01-01' AND '2024-12-31'
GROUP BY DATE(o.created_at)
ORDER BY date DESC;

-- Sales per outlet
SELECT 
    o.kode_outlet,
    outlet.nama_outlet,
    region.name as region_name,
    COUNT(DISTINCT o.id) as total_orders,
    SUM(o.grand_total) as total_revenue,
    SUM(o.pax) as total_customers,
    AVG(o.grand_total) as avg_order_value
FROM orders o
LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
LEFT JOIN regions region ON outlet.region_id = region.id
WHERE DATE(o.created_at) BETWEEN '2024-01-01' AND '2024-12-31'
GROUP BY o.kode_outlet, outlet.nama_outlet, region.name
ORDER BY total_revenue DESC;

-- Top selling items
SELECT 
    oi.item_id,
    oi.item_name,
    SUM(oi.qty) as total_qty_sold,
    SUM(oi.subtotal) as total_revenue,
    COUNT(DISTINCT oi.order_id) as order_count,
    AVG(oi.price) as avg_price
FROM order_items oi
INNER JOIN orders o ON oi.order_id = o.id
WHERE DATE(o.created_at) BETWEEN '2024-01-01' AND '2024-12-31'
GROUP BY oi.item_id, oi.item_name
ORDER BY total_revenue DESC
LIMIT 50;

-- =====================================================
-- 2. INVENTORY QUERIES
-- =====================================================

-- Current stock levels per outlet
SELECT 
    o.id_outlet,
    o.nama_outlet,
    i.id as item_id,
    i.name as item_name,
    ifi.id as inventory_item_id,
    s.warehouse_outlet_id,
    wo.name as warehouse_name,
    s.qty_small,
    s.qty_medium,
    s.qty_large,
    s.value as stock_value,
    s.last_cost_small,
    s.last_cost_medium,
    s.last_cost_large
FROM outlet_food_inventory_stocks s
INNER JOIN outlet_food_inventory_items ifi ON s.inventory_item_id = ifi.id
INNER JOIN items i ON ifi.item_id = i.id
INNER JOIN tbl_data_outlet o ON s.id_outlet = o.id_outlet
LEFT JOIN warehouse_outlets wo ON s.warehouse_outlet_id = wo.id
WHERE s.qty_small > 0 OR s.qty_medium > 0 OR s.qty_large > 0
ORDER BY o.nama_outlet, i.name;

-- Stock movements (in/out)
SELECT 
    c.id,
    c.date,
    o.nama_outlet,
    i.name as item_name,
    c.reference_type,
    c.reference_id,
    c.in_qty_small,
    c.in_qty_medium,
    c.in_qty_large,
    c.out_qty_small,
    c.out_qty_medium,
    c.out_qty_large,
    c.saldo_qty_small,
    c.saldo_qty_medium,
    c.saldo_qty_large,
    c.value_in,
    c.value_out,
    c.description
FROM outlet_food_inventory_cards c
INNER JOIN outlet_food_inventory_items ifi ON c.inventory_item_id = ifi.id
INNER JOIN items i ON ifi.item_id = i.id
INNER JOIN tbl_data_outlet o ON c.id_outlet = o.id_outlet
WHERE c.date BETWEEN '2024-01-01' AND '2024-12-31'
ORDER BY c.date DESC, c.id DESC
LIMIT 1000;

-- Stock turnover rate (per item per outlet)
SELECT 
    o.id_outlet,
    o.nama_outlet,
    i.id as item_id,
    i.name as item_name,
    SUM(CASE WHEN c.out_qty_small > 0 THEN c.out_qty_small ELSE 0 END) as total_out_small,
    SUM(CASE WHEN c.out_qty_medium > 0 THEN c.out_qty_medium ELSE 0 END) as total_out_medium,
    SUM(CASE WHEN c.out_qty_large > 0 THEN c.out_qty_large ELSE 0 END) as total_out_large,
    AVG(s.qty_small) as avg_stock_small,
    AVG(s.qty_medium) as avg_stock_medium,
    AVG(s.qty_large) as avg_stock_large,
    CASE 
        WHEN AVG(s.qty_small) > 0 THEN SUM(c.out_qty_small) / AVG(s.qty_small)
        ELSE 0
    END as turnover_rate_small
FROM outlet_food_inventory_cards c
INNER JOIN outlet_food_inventory_items ifi ON c.inventory_item_id = ifi.id
INNER JOIN items i ON ifi.item_id = i.id
INNER JOIN tbl_data_outlet o ON c.id_outlet = o.id_outlet
LEFT JOIN outlet_food_inventory_stocks s ON s.inventory_item_id = ifi.id AND s.id_outlet = o.id_outlet
WHERE c.date BETWEEN DATE_SUB(CURDATE(), INTERVAL 90 DAY) AND CURDATE()
GROUP BY o.id_outlet, o.nama_outlet, i.id, i.name
HAVING total_out_small > 0 OR total_out_medium > 0 OR total_out_large > 0
ORDER BY turnover_rate_small DESC;

-- =====================================================
-- 3. BOM QUERIES
-- =====================================================

-- Items dengan BOM
SELECT 
    i.id as item_id,
    i.name as item_name,
    i.composition_type,
    COUNT(b.id) as bom_material_count,
    GROUP_CONCAT(mi.name SEPARATOR ', ') as materials
FROM items i
INNER JOIN item_bom b ON i.id = b.item_id
INNER JOIN items mi ON b.material_item_id = mi.id
WHERE i.composition_type = 'composed'
    AND i.status = 'active'
GROUP BY i.id, i.name, i.composition_type
ORDER BY bom_material_count DESC;

-- BOM detail untuk item tertentu
SELECT 
    i.id as item_id,
    i.name as item_name,
    mi.id as material_item_id,
    mi.name as material_name,
    b.qty as required_qty,
    u.name as unit_name
FROM items i
INNER JOIN item_bom b ON i.id = b.item_id
INNER JOIN items mi ON b.material_item_id = mi.id
LEFT JOIN units u ON b.unit_id = u.id
WHERE i.id = 123  -- Replace with actual item_id
ORDER BY mi.name;

-- Kalkulasi kebutuhan bahan baku dari sales
SELECT 
    oi.item_id,
    i.name as item_name,
    SUM(oi.qty) as total_qty_sold,
    b.material_item_id,
    mi.name as material_name,
    SUM(oi.qty * b.qty) as total_material_needed,
    u.name as unit_name
FROM order_items oi
INNER JOIN orders o ON oi.order_id = o.id
INNER JOIN items i ON oi.item_id = i.id
INNER JOIN item_bom b ON i.id = b.item_id
INNER JOIN items mi ON b.material_item_id = mi.id
LEFT JOIN units u ON b.unit_id = u.id
WHERE DATE(o.created_at) BETWEEN '2024-01-01' AND '2024-12-31'
    AND i.composition_type = 'composed'
GROUP BY oi.item_id, i.name, b.material_item_id, mi.name, u.name
ORDER BY total_material_needed DESC;

-- =====================================================
-- 4. CROSS-MODULE QUERIES (Sales + Inventory)
-- =====================================================

-- Sales vs Stock correlation
SELECT 
    o.id_outlet,
    o.nama_outlet,
    i.id as item_id,
    i.name as item_name,
    -- Sales data
    SUM(oi.qty) as total_qty_sold,
    SUM(oi.subtotal) as total_revenue,
    COUNT(DISTINCT oi.order_id) as order_count,
    -- Stock data
    COALESCE(SUM(s.qty_small), 0) as current_stock_small,
    COALESCE(SUM(s.qty_medium), 0) as current_stock_medium,
    COALESCE(SUM(s.qty_large), 0) as current_stock_large,
    -- Stock movements
    SUM(CASE WHEN c.out_qty_small > 0 THEN c.out_qty_small ELSE 0 END) as total_stock_out_small,
    -- Correlation
    CASE 
        WHEN SUM(oi.qty) > 0 AND COALESCE(SUM(s.qty_small), 0) > 0 
        THEN COALESCE(SUM(s.qty_small), 0) / SUM(oi.qty)
        ELSE 0
    END as stock_to_sales_ratio
FROM items i
LEFT JOIN order_items oi ON i.id = oi.item_id
LEFT JOIN orders o2 ON oi.order_id = o2.id
LEFT JOIN tbl_data_outlet o ON o2.kode_outlet = o.qr_code
LEFT JOIN outlet_food_inventory_items ifi ON i.id = ifi.item_id
LEFT JOIN outlet_food_inventory_stocks s ON ifi.id = s.inventory_item_id AND s.id_outlet = o.id_outlet
LEFT JOIN outlet_food_inventory_cards c ON ifi.id = c.inventory_item_id AND c.id_outlet = o.id_outlet
    AND c.date BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND CURDATE()
WHERE DATE(o2.created_at) BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND CURDATE()
GROUP BY o.id_outlet, o.nama_outlet, i.id, i.name
HAVING total_qty_sold > 0
ORDER BY total_revenue DESC
LIMIT 100;

-- Stock-out impact analysis (items yang stock habis vs sales)
SELECT 
    i.id as item_id,
    i.name as item_name,
    o.id_outlet,
    o.nama_outlet,
    -- Sales sebelum stock habis
    SUM(CASE WHEN DATE(o2.created_at) < stock_out_date THEN oi.qty ELSE 0 END) as qty_sold_before,
    SUM(CASE WHEN DATE(o2.created_at) < stock_out_date THEN oi.subtotal ELSE 0 END) as revenue_before,
    -- Sales setelah stock habis
    SUM(CASE WHEN DATE(o2.created_at) >= stock_out_date THEN oi.qty ELSE 0 END) as qty_sold_after,
    SUM(CASE WHEN DATE(o2.created_at) >= stock_out_date THEN oi.subtotal ELSE 0 END) as revenue_after,
    -- Stock out date
    MIN(stock_out_date) as first_stock_out_date
FROM items i
INNER JOIN order_items oi ON i.id = oi.item_id
INNER JOIN orders o2 ON oi.order_id = o2.id
INNER JOIN tbl_data_outlet o ON o2.kode_outlet = o.qr_code
INNER JOIN (
    -- Find stock out dates
    SELECT 
        ifi.item_id,
        c.id_outlet,
        MIN(c.date) as stock_out_date
    FROM outlet_food_inventory_cards c
    INNER JOIN outlet_food_inventory_items ifi ON c.inventory_item_id = ifi.id
    WHERE c.saldo_qty_small <= 0 AND c.saldo_qty_medium <= 0 AND c.saldo_qty_large <= 0
    GROUP BY ifi.item_id, c.id_outlet
) so ON i.id = so.item_id AND o.id_outlet = so.id_outlet
WHERE DATE(o2.created_at) BETWEEN DATE_SUB(CURDATE(), INTERVAL 90 DAY) AND CURDATE()
GROUP BY i.id, i.name, o.id_outlet, o.nama_outlet, stock_out_date
HAVING qty_sold_before > 0
ORDER BY revenue_before DESC;

-- =====================================================
-- 5. DEMAND FORECASTING QUERIES
-- =====================================================

-- Average daily sales per item (untuk forecasting)
SELECT 
    i.id as item_id,
    i.name as item_name,
    o.id_outlet,
    o.nama_outlet,
    COUNT(DISTINCT DATE(o2.created_at)) as days_with_sales,
    SUM(oi.qty) as total_qty_sold,
    AVG(oi.qty) as avg_qty_per_order,
    SUM(oi.qty) / NULLIF(COUNT(DISTINCT DATE(o2.created_at)), 0) as avg_daily_qty,
    STDDEV(oi.qty) as stddev_qty
FROM items i
INNER JOIN order_items oi ON i.id = oi.item_id
INNER JOIN orders o2 ON oi.order_id = o2.id
INNER JOIN tbl_data_outlet o ON o2.kode_outlet = o.qr_code
WHERE DATE(o2.created_at) BETWEEN DATE_SUB(CURDATE(), INTERVAL 90 DAY) AND CURDATE()
GROUP BY i.id, i.name, o.id_outlet, o.nama_outlet
HAVING days_with_sales > 0
ORDER BY avg_daily_qty DESC;

-- Seasonal pattern analysis (per hari dalam minggu)
SELECT 
    DAYNAME(o.created_at) as day_name,
    DAYOFWEEK(o.created_at) as day_of_week,
    COUNT(DISTINCT o.id) as total_orders,
    SUM(o.grand_total) as total_revenue,
    AVG(o.grand_total) as avg_order_value
FROM orders o
WHERE DATE(o.created_at) BETWEEN DATE_SUB(CURDATE(), INTERVAL 90 DAY) AND CURDATE()
GROUP BY DAYNAME(o.created_at), DAYOFWEEK(o.created_at)
ORDER BY day_of_week;

-- =====================================================
-- END OF SAMPLE QUERIES
-- =====================================================

