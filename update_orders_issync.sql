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
-- Update orders set issync=0 untuk nomor-nomor JWTEMP
-- ============================================

UPDATE orders SET issync=0 WHERE nomor='JWTEMP26010372';
UPDATE orders SET issync=0 WHERE nomor='JWTEMP26010371';
UPDATE orders SET issync=0 WHERE nomor='JWTEMP26010370';
UPDATE orders SET issync=0 WHERE nomor='JWTEMP26010369';
UPDATE orders SET issync=0 WHERE nomor='JWTEMP26010368';
UPDATE orders SET issync=0 WHERE nomor='JWTEMP26010367';
UPDATE orders SET issync=0 WHERE nomor='JWTEMP26010366';
UPDATE orders SET issync=0 WHERE nomor='JWTEMP26010365';
UPDATE orders SET issync=0 WHERE nomor='JWTEMP26010364';
UPDATE orders SET issync=0 WHERE nomor='JWTEMP26010363';
UPDATE orders SET issync=0 WHERE nomor='JWTEMP26010362';
UPDATE orders SET issync=0 WHERE nomor='JWTEMP26010361';
UPDATE orders SET issync=0 WHERE nomor='JWTEMP26010360';
UPDATE orders SET issync=0 WHERE nomor='JWTEMP26010359';
UPDATE orders SET issync=0 WHERE nomor='JWTEMP26010358';
UPDATE orders SET issync=0 WHERE nomor='JWTEMP26010357';
UPDATE orders SET issync=0 WHERE nomor='JWTEMP26010356';
UPDATE orders SET issync=0 WHERE nomor='JWTEMP26010355';
UPDATE orders SET issync=0 WHERE nomor='JWTEMP26010354';
UPDATE orders SET issync=0 WHERE nomor='JWTEMP26010353';
UPDATE orders SET issync=0 WHERE nomor='JWTEMP26010352';
UPDATE orders SET issync=0 WHERE nomor='JWTEMP26010351';
UPDATE orders SET issync=0 WHERE nomor='JWTEMP26010350';
UPDATE orders SET issync=0 WHERE nomor='JWTEMP26010349';

-- ============================================
-- Update orders set issync=0 untuk nomor-nomor PSKLTEMP
-- ============================================

UPDATE orders SET issync=0 WHERE nomor='PSKLTEMP26010651';
UPDATE orders SET issync=0 WHERE nomor='PSKLTEMP26010632';
UPDATE orders SET issync=0 WHERE nomor='PSKLTEMP26010645';
UPDATE orders SET issync=0 WHERE nomor='PSKLTEMP26010652';

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
--
-- UPDATE orders SET issync=0 WHERE nomor IN (
--     'JWTEMP26010372',
--     'JWTEMP26010371',
--     'JWTEMP26010370',
--     'JWTEMP26010369',
--     'JWTEMP26010368',
--     'JWTEMP26010367',
--     'JWTEMP26010366',
--     'JWTEMP26010365',
--     'JWTEMP26010364',
--     'JWTEMP26010363',
--     'JWTEMP26010362',
--     'JWTEMP26010361',
--     'JWTEMP26010360',
--     'JWTEMP26010359',
--     'JWTEMP26010358',
--     'JWTEMP26010357',
--     'JWTEMP26010356',
--     'JWTEMP26010355',
--     'JWTEMP26010354',
--     'JWTEMP26010353',
--     'JWTEMP26010352',
--     'JWTEMP26010351',
--     'JWTEMP26010350',
--     'JWTEMP26010349'
-- );
--
-- UPDATE orders SET issync=0 WHERE nomor IN (
--     'PSKLTEMP26010651',
--     'PSKLTEMP26010632',
--     'PSKLTEMP26010645',
--     'PSKLTEMP26010652'
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
--
-- SELECT nomor, issync FROM orders WHERE nomor IN (
--     'JWTEMP26010372',
--     'JWTEMP26010371',
--     'JWTEMP26010370',
--     'JWTEMP26010369',
--     'JWTEMP26010368',
--     'JWTEMP26010367',
--     'JWTEMP26010366',
--     'JWTEMP26010365',
--     'JWTEMP26010364',
--     'JWTEMP26010363',
--     'JWTEMP26010362',
--     'JWTEMP26010361',
--     'JWTEMP26010360',
--     'JWTEMP26010359',
--     'JWTEMP26010358',
--     'JWTEMP26010357',
--     'JWTEMP26010356',
--     'JWTEMP26010355',
--     'JWTEMP26010354',
--     'JWTEMP26010353',
--     'JWTEMP26010352',
--     'JWTEMP26010351',
--     'JWTEMP26010350',
--     'JWTEMP26010349'
-- ) ORDER BY nomor DESC;
--
-- SELECT nomor, issync FROM orders WHERE nomor IN (
--     'PSKLTEMP26010651',
--     'PSKLTEMP26010632',
--     'PSKLTEMP26010645',
--     'PSKLTEMP26010652'
-- ) ORDER BY nomor DESC;