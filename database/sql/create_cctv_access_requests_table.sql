-- Query SQL untuk membuat tabel cctv_access_requests
-- Jalankan query ini di database Anda

CREATE TABLE IF NOT EXISTS `cctv_access_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL COMMENT 'User yang mengajukan permintaan',
  `access_type` enum('live_view','playback') NOT NULL COMMENT 'Jenis akses: live_view atau playback',
  `reason` text NOT NULL COMMENT 'Alasan permintaan akses',
  `outlet_ids` text NULL COMMENT 'Daftar outlet yang diminta (JSON array)',
  `email` varchar(255) NULL COMMENT 'Email untuk live view access',
  `area` varchar(255) NULL COMMENT 'Area untuk playback request',
  `time_from` time NULL COMMENT 'Waktu mulai untuk playback',
  `time_to` time NULL COMMENT 'Waktu selesai untuk playback',
  `incident_description` text NULL COMMENT 'Deskripsi kejadian untuk playback',
  `status` enum('pending','approved','rejected','revoked') NOT NULL DEFAULT 'pending',
  `it_manager_id` bigint(20) unsigned NULL COMMENT 'IT Manager yang approve/reject',
  `approval_notes` text NULL COMMENT 'Catatan dari IT Manager',
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu akses dicabut',
  `revoked_by` bigint(20) unsigned NULL COMMENT 'User yang mencabut akses',
  `revocation_reason` text NULL COMMENT 'Alasan pencabutan akses',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_it_manager_id` (`it_manager_id`),
  CONSTRAINT `fk_cctv_access_requests_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cctv_access_requests_it_manager` FOREIGN KEY (`it_manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cctv_access_requests_revoked_by` FOREIGN KEY (`revoked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

