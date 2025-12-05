-- Query untuk menambahkan field discount ke tabel Contra Bon
-- Jalankan query ini di database

-- 1. Tambahkan kolom discount_percent dan discount_amount di tabel food_contra_bon_items
ALTER TABLE `food_contra_bon_items`
ADD COLUMN `discount_percent` DECIMAL(5,2) NULL DEFAULT 0 AFTER `price`,
ADD COLUMN `discount_amount` DECIMAL(15,2) NULL DEFAULT 0 AFTER `discount_percent`;

-- 2. Tambahkan kolom discount_total_percent dan discount_total_amount di tabel food_contra_bons
ALTER TABLE `food_contra_bons`
ADD COLUMN `discount_total_percent` DECIMAL(5,2) NULL DEFAULT 0 AFTER `total_amount`,
ADD COLUMN `discount_total_amount` DECIMAL(15,2) NULL DEFAULT 0 AFTER `discount_total_percent`;

-- 3. Update kolom total di food_contra_bon_items untuk menghitung dengan discount (opsional, untuk data existing)
-- UPDATE food_contra_bon_items 
-- SET total = (quantity * price) - 
--     CASE 
--         WHEN discount_percent > 0 THEN (quantity * price * discount_percent / 100)
--         WHEN discount_amount > 0 THEN discount_amount
--         ELSE 0
--     END
-- WHERE discount_percent > 0 OR discount_amount > 0;

-- 4. Update kolom total_amount di food_contra_bons untuk menghitung dengan discount total (opsional, untuk data existing)
-- UPDATE food_contra_bons cb
-- SET cb.total_amount = (
--     SELECT COALESCE(SUM(
--         (cbi.quantity * cbi.price) - 
--         CASE 
--             WHEN cbi.discount_percent > 0 THEN (cbi.quantity * cbi.price * cbi.discount_percent / 100)
--             WHEN cbi.discount_amount > 0 THEN cbi.discount_amount
--             ELSE 0
--         END
--     ), 0)
--     FROM food_contra_bon_items cbi
--     WHERE cbi.contra_bon_id = cb.id
-- ) - 
-- CASE 
--     WHEN cb.discount_total_percent > 0 THEN (
--         SELECT COALESCE(SUM(
--             (cbi.quantity * cbi.price) - 
--             CASE 
--                 WHEN cbi.discount_percent > 0 THEN (cbi.quantity * cbi.price * cbi.discount_percent / 100)
--                 WHEN cbi.discount_amount > 0 THEN cbi.discount_amount
--                 ELSE 0
--             END
--         ), 0) * cb.discount_total_percent / 100
--         FROM food_contra_bon_items cbi
--         WHERE cbi.contra_bon_id = cb.id
--     )
--     WHEN cb.discount_total_amount > 0 THEN cb.discount_total_amount
--     ELSE 0
-- END
-- WHERE cb.discount_total_percent > 0 OR cb.discount_total_amount > 0;

