-- Query untuk debug masalah discount 56000 menjadi 560000
-- Cek data order yang bermasalah

SELECT 
    id,
    nomor,
    total,
    discount,
    manual_discount_amount,
    -- Cek tipe data
    TYPEOF(discount) as discount_type,
    TYPEOF(manual_discount_amount) as manual_discount_type,
    -- Cek konversi
    CAST(discount AS DECIMAL(10,2)) as discount_decimal,
    CAST(manual_discount_amount AS DECIMAL(10,2)) as manual_discount_decimal,
    -- Logika perhitungan
    CASE 
        WHEN discount > 0 AND manual_discount_amount > 0 THEN GREATEST(discount, manual_discount_amount)
        ELSE (discount + manual_discount_amount)
    END as total_discount_calc,
    grand_total,
    created_at
FROM orders 
WHERE nomor = 'LBTEMP25080983'  -- Ganti dengan nomor order yang bermasalah
LIMIT 1;

-- Cek semua order dengan manual_discount_amount sekitar 56000
SELECT 
    id,
    nomor,
    total,
    discount,
    manual_discount_amount,
    (discount + manual_discount_amount) as total_discount_old,
    CASE 
        WHEN discount > 0 AND manual_discount_amount > 0 THEN GREATEST(discount, manual_discount_amount)
        ELSE (discount + manual_discount_amount)
    END as total_discount_new,
    grand_total,
    created_at
FROM orders 
WHERE manual_discount_amount BETWEEN 55000 AND 57000
  AND DATE(created_at) = '2025-07-30'  -- Ganti dengan tanggal yang bermasalah
ORDER BY created_at DESC;

-- Cek apakah ada masalah dengan format data
SELECT 
    id,
    nomor,
    discount,
    manual_discount_amount,
    LENGTH(CAST(discount AS CHAR)) as discount_length,
    LENGTH(CAST(manual_discount_amount AS CHAR)) as manual_discount_length,
    created_at
FROM orders 
WHERE (discount > 0 OR manual_discount_amount > 0)
  AND DATE(created_at) = '2025-07-30'  -- Ganti dengan tanggal yang bermasalah
ORDER BY created_at DESC
LIMIT 10;
