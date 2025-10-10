-- Debug Query untuk September 2025 - Outlet ID 23

-- 1. Cek apakah outlet ID 23 ada
SELECT 'Outlet Check' as check_type, id_outlet, nama_outlet, status 
FROM tbl_data_outlet 
WHERE id_outlet = 23;

-- 2. Cek data outlet_food_good_receives untuk outlet 23 di September 2025
SELECT 'Outlet Food GR Sept 2025' as check_type, 
       DATE(receive_date) as tanggal,
       COUNT(*) as records,
       COUNT(DISTINCT gr.id) as unique_gr
FROM outlet_food_good_receives as gr
JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet
WHERE o.id_outlet = 23
  AND YEAR(gr.receive_date) = 2025
  AND MONTH(gr.receive_date) = 9
GROUP BY DATE(receive_date)
ORDER BY tanggal;

-- 3. Cek data good_receive_outlet_suppliers untuk outlet 23 di September 2025
SELECT 'Good Receive Supplier Sept 2025' as check_type, 
       DATE(receive_date) as tanggal,
       COUNT(*) as records,
       COUNT(DISTINCT gr.id) as unique_gr
FROM good_receive_outlet_suppliers as gr
JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet
WHERE o.id_outlet = 23
  AND YEAR(gr.receive_date) = 2025
  AND MONTH(gr.receive_date) = 9
GROUP BY DATE(receive_date)
ORDER BY tanggal;

-- 4. Cek outlet lain yang punya data September 2025
SELECT 'Other Outlets Sept 2025' as check_type, 
       o.id_outlet,
       o.nama_outlet,
       COUNT(*) as records
FROM outlet_food_good_receives as gr
JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet
WHERE YEAR(gr.receive_date) = 2025
  AND MONTH(gr.receive_date) = 9
GROUP BY o.id_outlet, o.nama_outlet
ORDER BY records DESC
LIMIT 10;

-- 5. Cek data outlet 23 di bulan lain tahun 2025
SELECT 'Outlet 23 Other Months 2025' as check_type, 
       MONTH(gr.receive_date) as bulan,
       COUNT(*) as records
FROM outlet_food_good_receives as gr
JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet
WHERE o.id_outlet = 23
  AND YEAR(gr.receive_date) = 2025
GROUP BY MONTH(gr.receive_date)
ORDER BY bulan;

-- 6. Cek data outlet 23 di tahun lain
SELECT 'Outlet 23 Other Years' as check_type, 
       YEAR(gr.receive_date) as tahun,
       COUNT(*) as records
FROM outlet_food_good_receives as gr
JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet
WHERE o.id_outlet = 23
GROUP BY YEAR(gr.receive_date)
ORDER BY tahun DESC;

-- 7. Cek apakah ada data di good_receive_outlet_suppliers untuk outlet 23
SELECT 'Good Receive Supplier All Data' as check_type, 
       YEAR(gr.receive_date) as tahun,
       MONTH(gr.receive_date) as bulan,
       COUNT(*) as records
FROM good_receive_outlet_suppliers as gr
JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet
WHERE o.id_outlet = 23
GROUP BY YEAR(gr.receive_date), MONTH(gr.receive_date)
ORDER BY tahun DESC, bulan DESC;
