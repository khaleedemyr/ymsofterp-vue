-- ============================================
-- Update orders set issync=0 untuk nomor-nomor berikut
-- Semua nomor menggunakan prefix DGTEMP
-- ============================================

UPDATE orders SET issync=0 WHERE nomor='DGTEMP26010614';
UPDATE orders SET issync=0 WHERE nomor='DGTEMP26010612';
UPDATE orders SET issync=0 WHERE nomor='DGTEMP26010611';
UPDATE orders SET issync=0 WHERE nomor='DGTEMP26010610';
UPDATE orders SET issync=0 WHERE nomor='DGTEMP26010609';
UPDATE orders SET issync=0 WHERE nomor='DGTEMP26010608';
UPDATE orders SET issync=0 WHERE nomor='DGTEMP26010607';
UPDATE orders SET issync=0 WHERE nomor='DGTEMP26010606';
UPDATE orders SET issync=0 WHERE nomor='DGTEMP26010605';
UPDATE orders SET issync=0 WHERE nomor='DGTEMP26010604';
UPDATE orders SET issync=0 WHERE nomor='DGTEMP26010603';
UPDATE orders SET issync=0 WHERE nomor='DGTEMP26010602';
UPDATE orders SET issync=0 WHERE nomor='DGTEMP26010601';
UPDATE orders SET issync=0 WHERE nomor='DGTEMP26010600';
UPDATE orders SET issync=0 WHERE nomor='DGTEMP26010599';
UPDATE orders SET issync=0 WHERE nomor='DGTEMP26010598';
UPDATE orders SET issync=0 WHERE nomor='DGTEMP26010593';
UPDATE orders SET issync=0 WHERE nomor='DGTEMP26010591';
UPDATE orders SET issync=0 WHERE nomor='DGTEMP26010589';
UPDATE orders SET issync=0 WHERE nomor='DGTEMP26010590';

-- ============================================
-- Update orders set issync=0 untuk nomor-nomor CMNTEMP
-- ============================================

UPDATE orders SET issync=0 WHERE nomor='CMNTEMP26010276';
UPDATE orders SET issync=0 WHERE nomor='CMNTEMP26010279';
UPDATE orders SET issync=0 WHERE nomor='CMNTEMP26010280';
UPDATE orders SET issync=0 WHERE nomor='CMNTEMP26010281';
UPDATE orders SET issync=0 WHERE nomor='CMNTEMP26010282';
UPDATE orders SET issync=0 WHERE nomor='CMNTEMP26010283';
UPDATE orders SET issync=0 WHERE nomor='CMNTEMP26010284';
UPDATE orders SET issync=0 WHERE nomor='CMNTEMP26010285';

-- ============================================
-- Update orders set issync=0 untuk nomor-nomor MIMTEMP
-- ============================================

UPDATE orders SET issync=0 WHERE nomor='MIMTEMP26010358';
UPDATE orders SET issync=0 WHERE nomor='MIMTEMP26010359';
UPDATE orders SET issync=0 WHERE nomor='MIMTEMP26010360';
UPDATE orders SET issync=0 WHERE nomor='MIMTEMP26010361';
UPDATE orders SET issync=0 WHERE nomor='MIMTEMP26010362';
UPDATE orders SET issync=0 WHERE nomor='MIMTEMP26010363';
UPDATE orders SET issync=0 WHERE nomor='MIMTEMP26010364';

-- ============================================
-- ALTERNATIF: Update dalam satu query (lebih efisien)
-- ============================================
-- Uncomment jika ingin menggunakan cara ini:

-- UPDATE orders SET issync=0 WHERE nomor IN (
--     'DGTEMP26010614',
--     'DGTEMP26010612',
--     'DGTEMP26010611',
--     'DGTEMP26010610',
--     'DGTEMP26010609',
--     'DGTEMP26010608',
--     'DGTEMP26010607',
--     'DGTEMP26010606',
--     'DGTEMP26010605',
--     'DGTEMP26010604',
--     'DGTEMP26010603',
--     'DGTEMP26010602',
--     'DGTEMP26010601',
--     'DGTEMP26010600',
--     'DGTEMP26010599',
--     'DGTEMP26010598',
--     'DGTEMP26010593',
--     'DGTEMP26010591',
--     'DGTEMP26010589',
--     'DGTEMP26010590'
-- );
--
-- UPDATE orders SET issync=0 WHERE nomor IN (
--     'CMNTEMP26010276',
--     'CMNTEMP26010279',
--     'CMNTEMP26010280',
--     'CMNTEMP26010281',
--     'CMNTEMP26010282',
--     'CMNTEMP26010283',
--     'CMNTEMP26010284',
--     'CMNTEMP26010285'
-- );
--
-- UPDATE orders SET issync=0 WHERE nomor IN (
--     'MIMTEMP26010358',
--     'MIMTEMP26010359',
--     'MIMTEMP26010360',
--     'MIMTEMP26010361',
--     'MIMTEMP26010362',
--     'MIMTEMP26010363',
--     'MIMTEMP26010364'
-- );

-- ============================================
-- VERIFIKASI: Cek hasil update
-- ============================================
-- SELECT nomor, issync FROM orders WHERE nomor IN (
--     'DGTEMP26010614',
--     'DGTEMP26010612',
--     'DGTEMP26010611',
--     'DGTEMP26010610',
--     'DGTEMP26010609',
--     'DGTEMP26010608',
--     'DGTEMP26010607',
--     'DGTEMP26010606',
--     'DGTEMP26010605',
--     'DGTEMP26010604',
--     'DGTEMP26010603',
--     'DGTEMP26010602',
--     'DGTEMP26010601',
--     'DGTEMP26010600',
--     'DGTEMP26010599',
--     'DGTEMP26010598',
--     'DGTEMP26010593',
--     'DGTEMP26010591',
--     'DGTEMP26010589',
--     'DGTEMP26010590'
-- ) ORDER BY nomor DESC;
--
-- SELECT nomor, issync FROM orders WHERE nomor IN (
--     'CMNTEMP26010276',
--     'CMNTEMP26010279',
--     'CMNTEMP26010280',
--     'CMNTEMP26010281',
--     'CMNTEMP26010282',
--     'CMNTEMP26010283',
--     'CMNTEMP26010284',
--     'CMNTEMP26010285'
-- ) ORDER BY nomor DESC;
--
-- SELECT nomor, issync FROM orders WHERE nomor IN (
--     'MIMTEMP26010358',
--     'MIMTEMP26010359',
--     'MIMTEMP26010360',
--     'MIMTEMP26010361',
--     'MIMTEMP26010362',
--     'MIMTEMP26010363',
--     'MIMTEMP26010364'
-- ) ORDER BY nomor DESC;