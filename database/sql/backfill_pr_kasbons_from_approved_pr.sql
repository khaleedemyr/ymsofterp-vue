-- Backfill: PR mode kasbon status APPROVED → baris di pr_kasbons
-- Hanya PR yang tanggal acuannya jatuh di bulan kalender ini (waktu query dijalankan).
-- Tanggal acuan: approved_ssd_at, jika NULL pakai updated_at, jika NULL pakai created_at.
-- Jalankan SETELAH create_pr_kasbons.sql. Aman dijalankan ulang (skip PR yang sudah punya baris).
-- MySQL / MariaDB

INSERT INTO `pr_kasbons` (
  `purchase_requisition_id`,
  `pr_number`,
  `outlet_id`,
  `division_id`,
  `employee_user_id`,
  `total_amount`,
  `termin_total`,
  `installment_amount`,
  `paid_installments`,
  `status`,
  `approved_at`,
  `last_installment_at`,
  `notes`,
  `created_at`,
  `updated_at`
)
SELECT
  pr.`id` AS `purchase_requisition_id`,
  pr.`pr_number`,
  pr.`outlet_id`,
  pr.`division_id`,
  pr.`created_by` AS `employee_user_id`,
  COALESCE(
    NULLIF(CAST(pr.`amount` AS DECIMAL(15, 2)), 0),
    (SELECT CAST(pri.`subtotal` AS DECIMAL(15, 2))
     FROM `purchase_requisition_items` pri
     WHERE pri.`purchase_requisition_id` = pr.`id`
     ORDER BY pri.`id` ASC
     LIMIT 1),
    0
  ) AS `total_amount`,
  GREATEST(1, LEAST(3, COALESCE(pr.`kasbon_termin`, 1))) AS `termin_total`,
  ROUND(
    COALESCE(
      NULLIF(CAST(pr.`amount` AS DECIMAL(15, 2)), 0),
      (SELECT CAST(pri2.`subtotal` AS DECIMAL(15, 2))
       FROM `purchase_requisition_items` pri2
       WHERE pri2.`purchase_requisition_id` = pr.`id`
       ORDER BY pri2.`id` ASC
       LIMIT 1),
      0
    ) / GREATEST(1, LEAST(3, COALESCE(pr.`kasbon_termin`, 1))),
    2
  ) AS `installment_amount`,
  0 AS `paid_installments`,
  'active' AS `status`,
  COALESCE(pr.`approved_ssd_at`, pr.`updated_at`, pr.`created_at`) AS `approved_at`,
  NULL AS `last_installment_at`,
  NULL AS `notes`,
  NOW() AS `created_at`,
  NOW() AS `updated_at`
FROM `purchase_requisitions` pr
WHERE pr.`mode` = 'kasbon'
  AND UPPER(TRIM(pr.`status`)) = 'APPROVED'
  AND DATE(COALESCE(pr.`approved_ssd_at`, pr.`updated_at`, pr.`created_at`)) >= DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')
  AND DATE(COALESCE(pr.`approved_ssd_at`, pr.`updated_at`, pr.`created_at`)) < DATE_FORMAT(DATE_ADD(CURRENT_DATE(), INTERVAL 1 MONTH), '%Y-%m-01')
  AND NOT EXISTS (
    SELECT 1 FROM `pr_kasbons` k WHERE k.`purchase_requisition_id` = pr.`id`
  );

-- Cek jumlah baris yang terisi:
-- SELECT COUNT(*) FROM pr_kasbons;
