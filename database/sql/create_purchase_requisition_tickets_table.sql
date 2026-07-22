-- Pivot: 1 Purchase Requisition → banyak Ticket
-- Jalankan jika migration Laravel belum dijalankan.

CREATE TABLE IF NOT EXISTS `purchase_requisition_tickets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `purchase_requisition_id` bigint unsigned NOT NULL,
  `ticket_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pr_tickets_pr_id_ticket_id_unique` (`purchase_requisition_id`, `ticket_id`),
  KEY `pr_tickets_ticket_id_index` (`ticket_id`),
  CONSTRAINT `pr_tickets_pr_id_fk`
    FOREIGN KEY (`purchase_requisition_id`) REFERENCES `purchase_requisitions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pr_tickets_ticket_id_fk`
    FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill dari ticket_id existing
INSERT IGNORE INTO `purchase_requisition_tickets`
  (`purchase_requisition_id`, `ticket_id`, `created_at`, `updated_at`)
SELECT
  `id`,
  `ticket_id`,
  COALESCE(`created_at`, NOW()),
  COALESCE(`updated_at`, NOW())
FROM `purchase_requisitions`
WHERE `ticket_id` IS NOT NULL;
