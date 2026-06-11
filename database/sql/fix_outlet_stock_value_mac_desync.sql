-- =============================================================================
-- Perbaikan desync outlet_food_inventory_stocks.value vs last_cost_small
-- Akar masalah anomali MAC: value/qty != last_cost_small setelah IWT/transfer
-- yang hanya update qty + last_cost tanpa sinkronisasi field value.
--
-- Sampling: Justus Steak House Alam Sutera (id_outlet = 22)
-- Jalankan PREVIEW dulu, lalu FIX setelah dicek.
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 1) PREVIEW: baris stok dengan value tidak selaras (>5% dari qty * last_cost)
-- -----------------------------------------------------------------------------
SELECT
    s.id,
    s.id_outlet,
    o.name AS outlet_name,
    s.warehouse_outlet_id,
    s.inventory_item_id,
    i.name AS item_name,
    s.qty_small,
    s.value AS value_current,
    s.last_cost_small,
    CASE WHEN s.qty_small > 0 THEN s.value / s.qty_small ELSE 0 END AS implied_mac,
    s.qty_small * s.last_cost_small AS value_expected,
    ABS(s.value - (s.qty_small * s.last_cost_small)) AS value_diff
FROM outlet_food_inventory_stocks s
JOIN outlets o ON o.id = s.id_outlet
JOIN outlet_food_inventory_items oi ON oi.id = s.inventory_item_id
JOIN items i ON i.id = oi.item_id
WHERE s.id_outlet = 22
  AND s.qty_small > 0
  AND s.last_cost_small > 0
  AND ABS(s.value / s.qty_small - s.last_cost_small) / s.last_cost_small > 0.05
ORDER BY value_diff DESC
LIMIT 100;

-- Hitung total baris bermasalah (outlet 22)
SELECT COUNT(*) AS desync_rows_outlet_22
FROM outlet_food_inventory_stocks s
WHERE s.id_outlet = 22
  AND s.qty_small > 0
  AND s.last_cost_small > 0
  AND ABS(s.value / s.qty_small - s.last_cost_small) / s.last_cost_small > 0.05;

-- Hitung total baris bermasalah (semua outlet)
SELECT COUNT(*) AS desync_rows_all_outlets
FROM outlet_food_inventory_stocks s
WHERE s.qty_small > 0
  AND s.last_cost_small > 0
  AND ABS(s.value / s.qty_small - s.last_cost_small) / s.last_cost_small > 0.05;

-- -----------------------------------------------------------------------------
-- 2) FIX: sinkronkan value = qty_small * last_cost_small
--    (hanya baris dengan qty > 0; qty=0 direset value ke 0)
-- -----------------------------------------------------------------------------
-- Outlet 22 saja (disarankan pilot dulu):
/*
UPDATE outlet_food_inventory_stocks s
SET s.value = s.qty_small * s.last_cost_small,
    s.updated_at = NOW()
WHERE s.id_outlet = 22
  AND s.qty_small > 0
  AND (
    s.value IS NULL
    OR ABS(s.value - (s.qty_small * s.last_cost_small)) > 0.01
  );

UPDATE outlet_food_inventory_stocks
SET value = 0,
    updated_at = NOW()
WHERE id_outlet = 22
  AND qty_small <= 0
  AND COALESCE(value, 0) != 0;
*/

-- Semua outlet (jalankan setelah pilot outlet 22 OK):
/*
UPDATE outlet_food_inventory_stocks s
SET s.value = s.qty_small * s.last_cost_small,
    s.updated_at = NOW()
WHERE s.qty_small > 0
  AND (
    s.value IS NULL
    OR ABS(s.value - (s.qty_small * s.last_cost_small)) > 0.01
  );

UPDATE outlet_food_inventory_stocks
SET value = 0,
    updated_at = NOW()
WHERE qty_small <= 0
  AND COALESCE(value, 0) != 0;
*/

-- -----------------------------------------------------------------------------
-- 3) Catatan: cost_histories yang sudah tercatat salah TIDAK otomatis terperbaiki
--    oleh script ini. Setelah fix stok, anomali baru tidak muncul; untuk riwayat
--    MAC historis yang sudah spike perlu investigasi manual per reference_id.
-- -----------------------------------------------------------------------------
