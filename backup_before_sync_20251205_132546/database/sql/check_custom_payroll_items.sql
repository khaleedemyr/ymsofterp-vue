-- Query untuk memeriksa data custom payroll items
-- Jalankan query ini untuk melihat apakah ada data custom items

-- 1. Cek struktur tabel
DESCRIBE custom_payroll_items;

-- 2. Cek jumlah total data
SELECT COUNT(*) as total_custom_items FROM custom_payroll_items;

-- 3. Cek data per periode (ganti bulan dan tahun sesuai kebutuhan)
SELECT 
    cpi.*,
    u.nama_lengkap,
    u.nik,
    o.nama_outlet
FROM custom_payroll_items cpi
LEFT JOIN users u ON cpi.user_id = u.id
LEFT JOIN tbl_data_outlet o ON cpi.outlet_id = o.id_outlet
WHERE cpi.payroll_period_month = 8 
  AND cpi.payroll_period_year = 2025
ORDER BY cpi.user_id, cpi.item_type;

-- 4. Cek data per user tertentu (ganti user_id sesuai kebutuhan)
SELECT 
    cpi.*,
    u.nama_lengkap,
    u.nik,
    o.nama_outlet
FROM custom_payroll_items cpi
LEFT JOIN users u ON cpi.user_id = u.id
LEFT JOIN tbl_data_outlet o ON cpi.outlet_id = o.id_outlet
WHERE cpi.user_id = 2  -- Ganti dengan user_id yang ingin dicek
ORDER BY cpi.payroll_period_year DESC, cpi.payroll_period_month DESC;

-- 5. Cek summary per periode
SELECT 
    payroll_period_month,
    payroll_period_year,
    item_type,
    COUNT(*) as item_count,
    SUM(item_amount) as total_amount
FROM custom_payroll_items
GROUP BY payroll_period_month, payroll_period_year, item_type
ORDER BY payroll_period_year DESC, payroll_period_month DESC, item_type;

-- 6. Cek data yang mungkin bermasalah (NULL values)
SELECT * FROM custom_payroll_items 
WHERE user_id IS NULL 
   OR outlet_id IS NULL 
   OR payroll_period_month IS NULL 
   OR payroll_period_year IS NULL 
   OR item_type IS NULL 
   OR item_name IS NULL 
   OR item_amount IS NULL;
