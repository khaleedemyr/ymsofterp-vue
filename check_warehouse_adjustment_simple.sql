-- Query paling simple dulu
-- 1. Cek ada berapa data di tabel food_inventory_adjustments
SELECT COUNT(*) as total FROM food_inventory_adjustments;

-- 2. Cek struktur kolom tabel
DESCRIBE food_inventory_adjustments;

-- 3. Cek data dengan status tertentu
SELECT * FROM food_inventory_adjustments 
WHERE status IN ('waiting_approval', 'waiting_ssd_manager', 'waiting_cost_control')
LIMIT 5;

-- 4. Cek semua status yang ada
SELECT status, COUNT(*) as total
FROM food_inventory_adjustments
GROUP BY status;

-- 5. Cek 5 data terbaru
SELECT id, number, date, type, status, warehouse_id, created_at
FROM food_inventory_adjustments
ORDER BY created_at DESC
LIMIT 5;
