-- Query untuk test logika discount yang baru
-- Cek data orders dengan discount dan manual_discount_amount

SELECT 
    id,
    nomor,
    total,
    discount,
    manual_discount_amount,
    -- Logika lama (salah)
    (discount + manual_discount_amount) as total_discount_old,
    -- Logika baru (benar)
    CASE 
        WHEN discount > 0 AND manual_discount_amount > 0 THEN GREATEST(discount, manual_discount_amount)
        ELSE (discount + manual_discount_amount)
    END as total_discount_new,
    grand_total,
    created_at,
    kode_outlet
FROM orders 
WHERE (discount > 0 OR manual_discount_amount > 0)
  AND DATE(created_at) = '2025-07-30'  -- Ganti dengan tanggal yang bermasalah
ORDER BY created_at DESC
LIMIT 20;

-- Cek perbedaan antara logika lama dan baru
SELECT 
    id,
    nomor,
    discount,
    manual_discount_amount,
    (discount + manual_discount_amount) as total_discount_old,
    CASE 
        WHEN discount > 0 AND manual_discount_amount > 0 THEN GREATEST(discount, manual_discount_amount)
        ELSE (discount + manual_discount_amount)
    END as total_discount_new,
    ((discount + manual_discount_amount) - 
     CASE 
         WHEN discount > 0 AND manual_discount_amount > 0 THEN GREATEST(discount, manual_discount_amount)
         ELSE (discount + manual_discount_amount)
     END) as selisih,
    created_at
FROM orders 
WHERE (discount > 0 AND manual_discount_amount > 0)  -- Hanya yang keduanya > 0
  AND DATE(created_at) = '2025-07-30'  -- Ganti dengan tanggal yang bermasalah
ORDER BY created_at DESC;
