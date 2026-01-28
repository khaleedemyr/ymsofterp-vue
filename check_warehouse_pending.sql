-- Cek semua adjustment yang pending
SELECT 
    adj.id,
    adj.number,
    adj.date,
    adj.status,
    adj.type,
    w.name as warehouse_name,
    u.nama_lengkap as creator_name
FROM food_inventory_adjustments adj
LEFT JOIN warehouses w ON adj.warehouse_id = w.id
LEFT JOIN users u ON adj.created_by = u.id
WHERE adj.status IN ('waiting_approval', 'waiting_ssd_manager', 'waiting_cost_control')
ORDER BY adj.created_at DESC;

-- Count per status
SELECT 
    status, 
    COUNT(*) as count,
    GROUP_CONCAT(number) as numbers
FROM food_inventory_adjustments
WHERE status IN ('waiting_approval', 'waiting_ssd_manager', 'waiting_cost_control')
GROUP BY status;
