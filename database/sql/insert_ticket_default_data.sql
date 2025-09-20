-- Insert default ticket statuses
INSERT INTO `ticket_statuses` (`name`, `slug`, `color`, `description`, `is_final`, `order`, `status`, `created_at`, `updated_at`) VALUES
('Open', 'open', '#3B82F6', 'Tiket baru, belum diproses', 0, 1, 'A', NOW(), NOW()),
('In Progress', 'in_progress', '#F59E0B', 'Tiket sedang dalam proses penanganan', 0, 2, 'A', NOW(), NOW()),
('Pending', 'pending', '#8B5CF6', 'Tiket menunggu informasi atau approval', 0, 3, 'A', NOW(), NOW()),
('Resolved', 'resolved', '#10B981', 'Tiket sudah diselesaikan, menunggu konfirmasi', 0, 4, 'A', NOW(), NOW()),
('Closed', 'closed', '#6B7280', 'Tiket sudah ditutup', 1, 5, 'A', NOW(), NOW()),
('Cancelled', 'cancelled', '#EF4444', 'Tiket dibatalkan', 1, 6, 'A', NOW(), NOW());

-- Insert default ticket priorities
INSERT INTO `ticket_priorities` (`name`, `level`, `max_days`, `color`, `description`, `status`, `created_at`, `updated_at`) VALUES
('Low', 1, 14, '#10B981', 'Prioritas rendah, bisa diselesaikan dalam beberapa hari', 'A', NOW(), NOW()),
('Medium', 2, 7, '#F59E0B', 'Prioritas sedang, harus diselesaikan dalam 1-2 hari', 'A', NOW(), NOW()),
('High', 3, 3, '#EF4444', 'Prioritas tinggi, harus diselesaikan dalam beberapa jam', 'A', NOW(), NOW()),
('Critical', 4, 1, '#DC2626', 'Prioritas kritis, harus diselesaikan segera', 'A', NOW(), NOW());
