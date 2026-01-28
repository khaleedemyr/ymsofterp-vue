-- Cek warehouse stock adjustment yang menunggu approval
SELECT 
    fia.id,
    fia.number,
    fia.date,
    fia.type,
    fia.status,
    fia.warehouse_id,
    w.name as warehouse_name,
    u.nama_lengkap as creator_name,
    fia.created_at,
    (SELECT COUNT(*) FROM food_inventory_adjustment_items WHERE adjustment_id = fia.id) as items_count
FROM food_inventory_adjustments fia
LEFT JOIN warehouses w ON fia.warehouse_id = w.id
LEFT JOIN users u ON fia.created_by = u.id
WHERE fia.status IN ('waiting_approval', 'waiting_ssd_manager', 'waiting_cost_control')
ORDER BY fia.created_at DESC
LIMIT 20;

-- Cek semua status yang ada
SELECT status, COUNT(*) as total
FROM food_inventory_adjustments
GROUP BY status;

-- Cek user login (ganti dengan ID user yang login)
-- SELECT id, nama_lengkap, id_jabatan, id_role, status
-- FROM users
-- WHERE id = 'YOUR_USER_ID';
