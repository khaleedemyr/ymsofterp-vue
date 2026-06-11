-- =============================================================================
-- Perbaikan nominal kasbon yang salah karena bug parsing "2.000.000,00" → ×100
-- Nilai kasbon sah (maks 3 juta): 500rb, 1jt, 1,5jt, 2jt, 2,5jt, 3jt
--
-- CARA PAKAI:
--   1. Backup DB dulu
--   2. Jalankan BAGIAN 1 (preview) — pastikan corrected_amount masuk akal
--   3. Jalankan BAGIAN 2 dalam transaksi (COMMIT jika OK, ROLLBACK jika ragu)
--   4. Deploy fix kode (parseRupiahInput) + npm run build agar tidak terulang
-- =============================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Daftar nominal sah
-- 500000, 1000000, 1500000, 2000000, 2500000, 3000000

-- =============================================================================
-- BAGIAN 1: PREVIEW — PR kasbon yang kemungkinan salah (amount > 3 juta)
-- =============================================================================

SELECT
    pr.id,
    pr.pr_number,
    pr.status,
    pr.amount AS amount_salah,
    pr.kasbon_termin,
    CASE
        WHEN pr.amount > 3000000
             AND MOD(pr.amount, 100) = 0
             AND (pr.amount / 100) IN (500000, 1000000, 1500000, 2000000, 2500000, 3000000)
            THEN pr.amount / 100
        WHEN pr.amount > 3000000
             AND MOD(pr.amount, 10000) = 0
             AND (pr.amount / 10000) IN (500000, 1000000, 1500000, 2000000, 2500000, 3000000)
            THEN pr.amount / 10000
        ELSE NULL
    END AS corrected_amount,
    u.nama_lengkap AS pemohon,
    pr.created_at,
    pr.updated_at
FROM purchase_requisitions pr
LEFT JOIN users u ON u.id = pr.created_by
WHERE pr.mode = 'kasbon'
  AND pr.amount > 3000000
  AND (
        (
            MOD(pr.amount, 100) = 0
            AND (pr.amount / 100) IN (500000, 1000000, 1500000, 2000000, 2500000, 3000000)
        )
        OR (
            MOD(pr.amount, 10000) = 0
            AND (pr.amount / 10000) IN (500000, 1000000, 1500000, 2000000, 2500000, 3000000)
        )
  )
ORDER BY pr.created_at DESC;

-- Preview pr_kasbons terkait (jika PR sudah fully APPROVED)
SELECT
    pk.id AS pr_kasbon_id,
    pk.pr_number,
    pk.total_amount AS total_salah,
    pk.installment_amount AS cicilan_salah,
    pk.paid_installments,
    pk.status AS kasbon_status,
    CASE
        WHEN pk.total_amount > 3000000
             AND MOD(pk.total_amount, 100) = 0
             AND (pk.total_amount / 100) IN (500000, 1000000, 1500000, 2000000, 2500000, 3000000)
            THEN pk.total_amount / 100
        WHEN pk.total_amount > 3000000
             AND MOD(pk.total_amount, 10000) = 0
             AND (pk.total_amount / 10000) IN (500000, 1000000, 1500000, 2000000, 2500000, 3000000)
            THEN pk.total_amount / 10000
        ELSE NULL
    END AS corrected_total
FROM pr_kasbons pk
INNER JOIN purchase_requisitions pr ON pr.id = pk.purchase_requisition_id
WHERE pr.mode = 'kasbon'
  AND pk.total_amount > 3000000
  AND (
        (
            MOD(pk.total_amount, 100) = 0
            AND (pk.total_amount / 100) IN (500000, 1000000, 1500000, 2000000, 2500000, 3000000)
        )
        OR (
            MOD(pk.total_amount, 10000) = 0
            AND (pk.total_amount / 10000) IN (500000, 1000000, 1500000, 2000000, 2500000, 3000000)
        )
  );

-- PR kasbon > 3jt yang TIDAK cocok pola di atas (perlu koreksi manual)
SELECT
    pr.id,
    pr.pr_number,
    pr.status,
    pr.amount,
    pr.kasbon_termin,
    u.nama_lengkap AS pemohon
FROM purchase_requisitions pr
LEFT JOIN users u ON u.id = pr.created_by
WHERE pr.mode = 'kasbon'
  AND pr.amount > 3000000
  AND NOT (
        (
            MOD(pr.amount, 100) = 0
            AND (pr.amount / 100) IN (500000, 1000000, 1500000, 2000000, 2500000, 3000000)
        )
        OR (
            MOD(pr.amount, 10000) = 0
            AND (pr.amount / 10000) IN (500000, 1000000, 1500000, 2000000, 2500000, 3000000)
        )
  )
ORDER BY pr.created_at DESC;

-- =============================================================================
-- BAGIAN 2: PERBAIKAN (jalankan setelah preview OK)
-- =============================================================================

START TRANSACTION;

-- 2a. Tabel sementara: id PR + nominal benar
DROP TEMPORARY TABLE IF EXISTS tmp_kasbon_fix;
CREATE TEMPORARY TABLE tmp_kasbon_fix AS
SELECT
    pr.id AS purchase_requisition_id,
    CASE
        WHEN pr.amount > 3000000
             AND MOD(pr.amount, 100) = 0
             AND (pr.amount / 100) IN (500000, 1000000, 1500000, 2000000, 2500000, 3000000)
            THEN pr.amount / 100
        WHEN pr.amount > 3000000
             AND MOD(pr.amount, 10000) = 0
             AND (pr.amount / 10000) IN (500000, 1000000, 1500000, 2000000, 2500000, 3000000)
            THEN pr.amount / 10000
        ELSE pr.amount
    END AS corrected_amount,
    pr.kasbon_termin
FROM purchase_requisitions pr
WHERE pr.mode = 'kasbon'
  AND pr.amount > 3000000
  AND (
        (
            MOD(pr.amount, 100) = 0
            AND (pr.amount / 100) IN (500000, 1000000, 1500000, 2000000, 2500000, 3000000)
        )
        OR (
            MOD(pr.amount, 10000) = 0
            AND (pr.amount / 10000) IN (500000, 1000000, 1500000, 2000000, 2500000, 3000000)
        )
  );

-- 2b. Update header PR
UPDATE purchase_requisitions pr
INNER JOIN tmp_kasbon_fix f ON f.purchase_requisition_id = pr.id
SET
    pr.amount = f.corrected_amount,
    pr.updated_at = NOW();

-- 2c. Update item kasbon
UPDATE purchase_requisition_items pri
INNER JOIN tmp_kasbon_fix f ON f.purchase_requisition_id = pri.purchase_requisition_id
SET
    pri.unit_price = f.corrected_amount,
    pri.subtotal = f.corrected_amount,
    pri.updated_at = NOW();

-- 2d. Update pr_kasbons (jika tabel ada & PR sudah fully approved)
UPDATE pr_kasbons pk
INNER JOIN tmp_kasbon_fix f ON f.purchase_requisition_id = pk.purchase_requisition_id
SET
    pk.total_amount = f.corrected_amount,
    pk.installment_amount = ROUND(f.corrected_amount / GREATEST(1, COALESCE(f.kasbon_termin, pk.termin_total, 1)), 2),
    pk.updated_at = NOW();

-- Verifikasi setelah update
SELECT
    pr.pr_number,
    pr.amount,
    pri.subtotal AS item_subtotal,
    pk.total_amount AS kasbon_total,
    pk.installment_amount AS kasbon_cicilan,
    pk.paid_installments
FROM purchase_requisitions pr
INNER JOIN tmp_kasbon_fix f ON f.purchase_requisition_id = pr.id
LEFT JOIN purchase_requisition_items pri ON pri.purchase_requisition_id = pr.id
LEFT JOIN pr_kasbons pk ON pk.purchase_requisition_id = pr.id;

-- Jika hasil verifikasi sudah benar:
COMMIT;
-- Jika ada yang salah:
-- ROLLBACK;

DROP TEMPORARY TABLE IF EXISTS tmp_kasbon_fix;

-- =============================================================================
-- CATATAN PENTING
-- =============================================================================
-- • Jika pr_kasbons.paid_installments > 0, potongan gaji mungkin sudah pakai nominal
--   salah — cek payroll_generated_details (potongan_kasbon, pr_kasbon_id) secara manual.
-- • Contoh koreksi satu PR saja (ganti id):
--     UPDATE purchase_requisitions SET amount = 2000000 WHERE id = 3588;
--     UPDATE purchase_requisition_items SET unit_price = 2000000, subtotal = 2000000
--       WHERE purchase_requisition_id = 3588;
