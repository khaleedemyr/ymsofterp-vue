-- Insert Retail Non Food permissions
INSERT IGNORE INTO `permissions` (`code`, `name`, `description`, `created_at`, `updated_at`) VALUES
('view-retail-non-food', 'View Retail Non Food', 'Can view retail non food transactions', NOW(), NOW()),
('create-retail-non-food', 'Create Retail Non Food', 'Can create retail non food transactions', NOW(), NOW()),
('edit-retail-non-food', 'Edit Retail Non Food', 'Can edit retail non food transactions', NOW(), NOW()),
('delete-retail-non-food', 'Delete Retail Non Food', 'Can delete retail non food transactions', NOW(), NOW()),
('approve-retail-non-food', 'Approve Retail Non Food', 'Can approve retail non food transactions', NOW(), NOW()); 