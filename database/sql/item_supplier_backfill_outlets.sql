-- Backfill item_supplier_outlet: salin outlet yang sudah dipakai supplier yang sama
-- ke semua baris item_supplier supplier tersebut (hindari duplikat).
-- Jalankan di production setelah backup.

INSERT IGNORE INTO item_supplier_outlet (item_supplier_id, outlet_id, created_at, updated_at)
SELECT is1.id, iso2.outlet_id, NOW(), NOW()
FROM item_supplier is1
INNER JOIN item_supplier is2 ON is1.supplier_id = is2.supplier_id
INNER JOIN item_supplier_outlet iso2 ON iso2.item_supplier_id = is2.id
LEFT JOIN item_supplier_outlet iso1
    ON iso1.item_supplier_id = is1.id AND iso1.outlet_id = iso2.outlet_id
WHERE iso1.item_supplier_id IS NULL;
