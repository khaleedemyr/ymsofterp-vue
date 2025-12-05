-- Migration untuk mengisi data lama contra bon ke tabel food_contra_bon_sources
-- Script ini akan mengambil source_type dan source_id dari food_contra_bons
-- dan memasukkannya ke tabel food_contra_bon_sources
-- 
-- PENTING: Jalankan script ini SETELAH membuat tabel food_contra_bon_sources
-- Script ini aman untuk dijalankan berkali-kali (idempotent)

-- Insert data dari contra bon yang sudah ada (hanya yang belum ada di food_contra_bon_sources)
INSERT INTO `food_contra_bon_sources` (`contra_bon_id`, `source_type`, `source_id`, `po_id`, `gr_id`, `created_at`, `updated_at`)
SELECT 
    cb.id AS contra_bon_id,
    cb.source_type,
    CASE 
        -- Untuk purchase_order, gunakan po_id sebagai source_id
        WHEN cb.source_type = 'purchase_order' AND cb.po_id IS NOT NULL THEN cb.po_id
        -- Untuk retail_food dan warehouse_retail_food, gunakan source_id langsung
        WHEN cb.source_type IN ('retail_food', 'warehouse_retail_food') AND cb.source_id IS NOT NULL THEN 
            -- Convert source_id to integer jika numeric
            CASE 
                WHEN CAST(cb.source_id AS CHAR) REGEXP '^[0-9]+$' THEN CAST(cb.source_id AS UNSIGNED)
                ELSE cb.source_id
            END
        ELSE NULL
    END AS source_id,
    cb.po_id,
    NULL AS gr_id, -- gr_id tidak tersimpan di food_contra_bons, jadi NULL
    cb.created_at,
    cb.updated_at
FROM `food_contra_bons` cb
LEFT JOIN `food_contra_bon_sources` cbs ON cb.id = cbs.contra_bon_id
WHERE 
    cbs.id IS NULL -- Hanya ambil yang belum ada di food_contra_bon_sources
    AND cb.source_type IS NOT NULL
    AND (
        (cb.source_type = 'purchase_order' AND cb.po_id IS NOT NULL)
        OR (cb.source_type = 'retail_food' AND cb.source_id IS NOT NULL)
        OR (cb.source_type = 'warehouse_retail_food' AND cb.source_id IS NOT NULL)
    );

-- Catatan:
-- 1. Script ini hanya akan insert data yang belum ada di food_contra_bon_sources (idempotent)
-- 2. Untuk purchase_order, po_id akan diambil dari kolom po_id di food_contra_bons
-- 3. Untuk retail_food dan warehouse_retail_food, source_id akan diambil dari kolom source_id
-- 4. gr_id akan NULL karena tidak tersimpan di food_contra_bons (hanya di source_id sebagai string "po_id-gr_id")
-- 5. Jika ada contra bon yang source_type atau source_id NULL, akan di-skip
-- 6. Data lama tetap bisa diakses melalui kolom source_type dan source_id di food_contra_bons (backward compatibility)

