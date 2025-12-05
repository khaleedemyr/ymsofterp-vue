-- Query untuk debug masalah discount
-- Cek data orders dengan discount yang mencurigakan

SELECT 
    id,
    nomor,
    total,
    discount,
    manual_discount_amount,
    (discount + manual_discount_amount) as total_discount_calc,
    grand_total,
    created_at,
    kode_outlet
FROM orders 
WHERE (discount > 0 OR manual_discount_amount > 0)
  AND DATE(created_at) = '2025-07-30'  -- Ganti dengan tanggal yang bermasalah
ORDER BY created_at DESC
LIMIT 20;

-- Cek tipe data field discount
DESCRIBE orders;

-- Cek sample data dengan discount yang besar
SELECT 
    id,
    nomor,
    total,
    discount,
    manual_discount_amount,
    CAST(discount AS DECIMAL(10,2)) as discount_decimal,
    CAST(manual_discount_amount AS DECIMAL(10,2)) as manual_discount_decimal,
    (CAST(discount AS DECIMAL(10,2)) + CAST(manual_discount_amount AS DECIMAL(10,2))) as total_discount_safe,
    grand_total,
    created_at
FROM orders 
WHERE discount > 10000 OR manual_discount_amount > 10000
ORDER BY created_at DESC
LIMIT 10;

-- Cek apakah ada data dengan discount yang aneh (berkali lipat)
SELECT 
    id,
    nomor,
    total,
    discount,
    manual_discount_amount,
    CASE 
        WHEN discount > total * 0.5 THEN 'DISCOUNT TERLALU BESAR'
        WHEN manual_discount_amount > total * 0.5 THEN 'MANUAL DISCOUNT TERLALU BESAR'
        ELSE 'NORMAL'
    END as status_discount,
    grand_total,
    created_at
FROM orders 
WHERE (discount > 0 OR manual_discount_amount > 0)
  AND DATE(created_at) = '2025-07-30'  -- Ganti dengan tanggal yang bermasalah
ORDER BY created_at DESC;
