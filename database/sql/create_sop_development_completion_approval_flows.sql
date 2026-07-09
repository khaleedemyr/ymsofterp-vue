/*
  Production fix: buat tabel sop_development_completion_approval_flows
  Error: Table 'db_justus.sop_development_completion_approval_flows' doesn't exist

  Jalankan di Navicat / phpMyAdmin (database: db_justus)
  Aman diulang: CREATE TABLE IF NOT EXISTS
*/

CREATE TABLE IF NOT EXISTS `sop_development_completion_approval_flows` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sop_development_completion_id` bigint(20) unsigned NOT NULL,
  `approver_id` int(10) unsigned NOT NULL,
  `approval_level` smallint(5) unsigned NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'PENDING',
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sdc_af_completion_id_idx` (`sop_development_completion_id`),
  KEY `sop_development_completion_approval_flows_approver_id_index` (`approver_id`),
  KEY `sdc_af_completion_status_idx` (`sop_development_completion_id`, `status`),
  KEY `sdc_af_completion_level_idx` (`sop_development_completion_id`, `approval_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*
  Migrasi data lama dari kolom approver_id (jika masih ada)
  Hanya insert jika belum ada flow untuk SOP tersebut
*/
INSERT INTO `sop_development_completion_approval_flows` (
  `sop_development_completion_id`,
  `approver_id`,
  `approval_level`,
  `status`,
  `approved_at`,
  `rejected_at`,
  `comments`,
  `created_at`,
  `updated_at`
)
SELECT
  s.`id`,
  s.`approver_id`,
  1,
  CASE s.`status`
    WHEN 'approved' THEN 'APPROVED'
    WHEN 'rejected' THEN 'REJECTED'
    ELSE 'PENDING'
  END,
  s.`approved_at`,
  s.`rejected_at`,
  s.`approval_notes`,
  NOW(),
  NOW()
FROM `sop_development_completions` s
WHERE s.`approver_id` IS NOT NULL
  AND s.`status` IN ('pending', 'approved', 'rejected')
  AND NOT EXISTS (
    SELECT 1 FROM `sop_development_completion_approval_flows` f
    WHERE f.`sop_development_completion_id` = s.`id`
  );

/*
  Opsional: hapus kolom approver_id lama setelah migrasi
  Uncomment baris ALTER di bawah jika kolom approver_id masih ada di tabel utama
*/
-- ALTER TABLE `sop_development_completions` DROP COLUMN `approver_id`;

/*
  Catat migration Laravel (opsional, agar artisan migrate tidak jalan ulang)
  Ganti batch number sesuai migrations terakhir di server
*/
-- INSERT INTO `migrations` (`migration`, `batch`)
-- SELECT '2026_07_09_110000_create_sop_development_completion_approval_flows_table', (SELECT IFNULL(MAX(`batch`), 0) + 1 FROM `migrations` m)
-- WHERE NOT EXISTS (
--   SELECT 1 FROM `migrations` WHERE `migration` = '2026_07_09_110000_create_sop_development_completion_approval_flows_table'
-- );
