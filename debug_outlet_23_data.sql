-- Debug Query untuk Outlet ID 23 - Cek data yang tersedia

-- 1. Cek apakah outlet ID 23 ada
SELECT 'Outlet Check' as check_type, id_outlet, nama_outlet, status 
FROM tbl_data_outlet 
WHERE id_outlet = 23;

-- 2. Cek data outlet_food_good_receives untuk outlet 23
SELECT 'Outlet Food GR Count' as check_type, COUNT(*) as total_records
FROM outlet_food_good_receives as gr
JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet
WHERE o.id_outlet = 23;

-- 3. Cek data good_receive_outlet_suppliers untuk outlet 23
SELECT 'Good Receive Supplier Count' as check_type, COUNT(*) as total_records
FROM good_receive_outlet_suppliers as gr
JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet
WHERE o.id_outlet = 23;

-- 4. Cek tanggal data yang tersedia untuk outlet 23 (outlet_food_good_receives)
SELECT 'Outlet Food GR Dates' as check_type, 
       MIN(DATE(receive_date)) as min_date, 
       MAX(DATE(receive_date)) as max_date,
       COUNT(DISTINCT DATE(receive_date)) as unique_dates
FROM outlet_food_good_receives as gr
JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet
WHERE o.id_outlet = 23;

-- 5. Cek tanggal data yang tersedia untuk outlet 23 (good_receive_outlet_suppliers)
SELECT 'Good Receive Supplier Dates' as check_type, 
       MIN(DATE(receive_date)) as min_date, 
       MAX(DATE(receive_date)) as max_date,
       COUNT(DISTINCT DATE(receive_date)) as unique_dates
FROM good_receive_outlet_suppliers as gr
JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet
WHERE o.id_outlet = 23;

-- 6. Cek data September 2024 untuk outlet 23 (outlet_food_good_receives)
SELECT 'Outlet Food GR Sept 2024' as check_type, 
       DATE(receive_date) as tanggal,
       COUNT(*) as records
FROM outlet_food_good_receives as gr
JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet
WHERE o.id_outlet = 23
  AND YEAR(gr.receive_date) = 2025
  AND MONTH(gr.receive_date) = 9
GROUP BY DATE(receive_date)
ORDER BY tanggal;

-- 7. Cek data September 2024 untuk outlet 23 (good_receive_outlet_suppliers)
SELECT 'Good Receive Supplier Sept 2024' as check_type, 
       DATE(receive_date) as tanggal,
       COUNT(*) as records
FROM good_receive_outlet_suppliers as gr
JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet
WHERE o.id_outlet = 23
  AND YEAR(gr.receive_date) = 2025
  AND MONTH(gr.receive_date) = 9
GROUP BY DATE(receive_date)
ORDER BY tanggal;

-- 8. Cek outlet lain yang punya data September 2024
SELECT 'Other Outlets Sept 2024' as check_type, 
       o.id_outlet,
       o.nama_outlet,
       COUNT(*) as records
FROM outlet_food_good_receives as gr
JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet
WHERE YEAR(gr.receive_date) = 2024
  AND MONTH(gr.receive_date) = 9
GROUP BY o.id_outlet, o.nama_outlet
ORDER BY records DESC
LIMIT 10;
