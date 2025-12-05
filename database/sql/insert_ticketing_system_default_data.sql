-- Insert default data for ticketing system

-- 1. Insert Ticket Categories
INSERT IGNORE INTO `ticket_categories` (`id`, `name`, `description`, `color`, `icon`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Equipment Issue', 'Masalah dengan peralatan atau equipment', '#EF4444', 'fa-tools', 'A', NOW(), NOW()),
(2, 'Maintenance', 'Permintaan maintenance atau perbaikan', '#F59E0B', 'fa-wrench', 'A', NOW(), NOW()),
(3, 'Cleaning', 'Permintaan cleaning atau kebersihan', '#10B981', 'fa-broom', 'A', NOW(), NOW()),
(4, 'Safety', 'Masalah keselamatan atau safety', '#DC2626', 'fa-shield-alt', 'A', NOW(), NOW()),
(5, 'Food Quality', 'Masalah kualitas makanan', '#8B5CF6', 'fa-utensils', 'A', NOW(), NOW()),
(6, 'Service', 'Masalah layanan atau service', '#06B6D4', 'fa-concierge-bell', 'A', NOW(), NOW()),
(7, 'IT Support', 'Masalah IT atau sistem', '#6366F1', 'fa-laptop', 'A', NOW(), NOW()),
(8, 'General', 'Masalah umum atau lainnya', '#6B7280', 'fa-question-circle', 'A', NOW(), NOW());

-- 2. Insert Ticket Priorities
INSERT IGNORE INTO `ticket_priorities` (`id`, `name`, `level`, `max_days`, `color`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Low', 1, 14, '#10B981', 'Prioritas rendah, bisa diselesaikan dalam beberapa hari', 'A', NOW(), NOW()),
(2, 'Medium', 2, 7, '#F59E0B', 'Prioritas sedang, harus diselesaikan dalam 1-2 hari', 'A', NOW(), NOW()),
(3, 'High', 3, 3, '#EF4444', 'Prioritas tinggi, harus diselesaikan dalam beberapa jam', 'A', NOW(), NOW()),
(4, 'Critical', 4, 1, '#DC2626', 'Prioritas kritis, harus diselesaikan segera', 'A', NOW(), NOW());

-- 3. Insert Ticket Statuses
INSERT IGNORE INTO `ticket_statuses` (`id`, `name`, `slug`, `color`, `description`, `is_final`, `order`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Open', 'open', '#3B82F6', 'Tiket baru, belum diproses', 0, 1, 'A', NOW(), NOW()),
(2, 'In Progress', 'in_progress', '#F59E0B', 'Tiket sedang dalam proses penanganan', 0, 2, 'A', NOW(), NOW()),
(3, 'Pending', 'pending', '#8B5CF6', 'Tiket menunggu informasi atau approval', 0, 3, 'A', NOW(), NOW()),
(4, 'Resolved', 'resolved', '#10B981', 'Tiket sudah diselesaikan, menunggu konfirmasi', 0, 4, 'A', NOW(), NOW()),
(5, 'Closed', 'closed', '#6B7280', 'Tiket sudah ditutup', 1, 5, 'A', NOW(), NOW()),
(6, 'Cancelled', 'cancelled', '#EF4444', 'Tiket dibatalkan', 1, 6, 'A', NOW(), NOW());

-- 4. Update tickets table to set default status_id to 1 (Open)
-- This will be handled in the application logic
