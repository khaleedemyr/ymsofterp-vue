-- Fix Retail Non Food Total Amount untuk Data Hari Ini
-- Query ini akan menghitung ulang total_amount dari items dan update jika berbeda

-- 1. Cek data yang akan diupdate (preview dulu)
SELECT 
    rnf.id,
    rnf.retail_number,
    rnf.transaction_date,
    rnf.total_amount as current_total,
    COALESCE(SUM(rnfi.qty * rnfi.price), 0) as calculated_total,
    (rnf.total_amount - COALESCE(SUM(rnfi.qty * rnfi.price), 0)) as difference
FROM retail_non_food rnf
LEFT JOIN retail_non_food_items rnfi ON rnf.id = rnfi.retail_non_food_id
WHERE DATE(rnf.transaction_date) = CURDATE()
  AND rnf.deleted_at IS NULL
GROUP BY rnf.id, rnf.retail_number, rnf.transaction_date, rnf.total_amount
HAVING ABS(rnf.total_amount - COALESCE(SUM(rnfi.qty * rnfi.price), 0)) > 0.01
ORDER BY rnf.created_at DESC;

-- 2. Update total_amount untuk data hari ini (hanya yang berbeda)
UPDATE retail_non_food rnf
INNER JOIN (
    SELECT 
        retail_non_food_id,
        COALESCE(SUM(qty * price), 0) as calculated_total
    FROM retail_non_food_items
    GROUP BY retail_non_food_id
) items_sum ON rnf.id = items_sum.retail_non_food_id
SET rnf.total_amount = items_sum.calculated_total,
    rnf.updated_at = NOW()
WHERE DATE(rnf.transaction_date) = CURDATE()
  AND rnf.deleted_at IS NULL
  AND ABS(rnf.total_amount - items_sum.calculated_total) > 0.01;

-- 3. Verifikasi hasil update
SELECT 
    rnf.id,
    rnf.retail_number,
    rnf.transaction_date,
    rnf.total_amount as updated_total,
    COALESCE(SUM(rnfi.qty * rnfi.price), 0) as calculated_total,
    ABS(rnf.total_amount - COALESCE(SUM(rnfi.qty * rnfi.price), 0)) as difference
FROM retail_non_food rnf
LEFT JOIN retail_non_food_items rnfi ON rnf.id = rnfi.retail_non_food_id
WHERE DATE(rnf.transaction_date) = CURDATE()
  AND rnf.deleted_at IS NULL
GROUP BY rnf.id, rnf.retail_number, rnf.transaction_date, rnf.total_amount
ORDER BY rnf.created_at DESC;

